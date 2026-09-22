<?php

namespace App\Http\Controllers\Shops;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Services\AdminMasterService;
use App\Services\BillingManagementService;
use App\Services\DocumentReviewService;
use App\Support\RecruitCatchOverlay;
use App\Support\ShopJobApplicationView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RecruitmentController extends Controller
{
    use ResolvesActor;

    private const MSG_LICENSE_REQUIRED_FOR_PUBLISH = '求人を公開するには、営業許可証と風営許可証の両方を提出し、運営の承認が必要です。';

    public function __construct(
        private readonly AdminMasterService $adminMasterService,
        private readonly DocumentReviewService $documentReviewService,
        private readonly BillingManagementService $billingManagementService,
        private readonly \App\Services\RecruitPublicationService $recruitPublicationService,
    ) {
    }

    /** 採用ステータスラベル（shop_job_applications.status） */
    private const APPLICATION_STATUS_LABELS = [
        1 => 'やり取り中',
        2 => '面談日調整中',
        3 => '面談日決定',
        4 => '採用',
        5 => '不採用',
        6 => '本採用',
        7 => '体験後不採用',
    ];

    /**
     * 採用・入金管理 統合画面
     *
     * 採用 → ボーナス申請 → 店舗承認 → 請求書発行 → 店舗入金 → 振込実行 → 受領完了
     * を 1 件のケースとして組み立て、店舗の操作待ち状態がぱっと見で分かるように表示する。
     */
    public function management(Request $request)
    {
        $shopId = $this->currentShopId();
        $applications = $this->getApplicationsForShop($shopId);
        $paymentData = $this->billingManagementService->getShopPaymentPageData($shopId);

        // 店舗側に紐づく deposit を application_id でキー化
        $shopDeposits = collect($this->billingManagementService->getAllDeposits())
            ->where('shop_id', $shopId)
            ->keyBy('shop_job_application_id');

        $hiredCases = [];
        $ongoingApplications = [];
        $rejectedApplications = [];
        foreach ($applications as $app) {
            $code = (int) ($app['status'] ?? 0);
            // 1=やり取り中 / 2=面談調整 / 3=面談確定 / 4=採用 / 5=不採用 / 6=本採用 / 7=本入店不採用
            if (in_array($code, [4, 6], true)) {
                $deposit = $shopDeposits->get((int) $app['id']);
                $hiredCases[] = $this->buildShopEmploymentCase($app, $deposit ? (array) $deposit : null);
            } elseif (in_array($code, [5, 7], true)) {
                $rejectedApplications[] = $app;
            } else {
                $ongoingApplications[] = $app;
            }
        }

        // 進行中（未完了）→ 完了済の順に並べ替え
        usort($hiredCases, function (array $a, array $b) {
            $aDone = (int) ($a['is_completed'] ?? 0);
            $bDone = (int) ($b['is_completed'] ?? 0);
            if ($aDone !== $bDone) {
                return $aDone <=> $bDone;
            }
            return ($b['progress_index'] ?? 0) <=> ($a['progress_index'] ?? 0);
        });

        return view('shops.mypage.management', [
            'pageId' => 'management',
            'applications' => $applications,
            'hiredCases' => $hiredCases,
            'ongoingApplications' => $ongoingApplications,
            'rejectedApplications' => $rejectedApplications,
            'shopBank' => $paymentData['bank'],
            'invoices' => $paymentData['invoices'],
            'summary' => $paymentData['summary'],
        ]);
    }

    /**
     * 採用済み案件 1 件分の「採用→入金」一気通貫ケース（店舗視点）を組み立てる。
     *
     * @param  array        $app     getApplicationsForShop 由来の application 配列
     * @param  array|null   $deposit getAllDeposits の deposit 配列（null = 入金フロー未開始）
     */
    private function buildShopEmploymentCase(array $app, ?array $deposit): array
    {
        // パイプライン定義（cast 側と同一の 7 段階）
        //   0: 採用確定（deposit 未作成）
        //   1: ボーナス申請受信（deposit status=1, 店舗の承認待ち）★ 店舗操作
        //   2: 店舗承認済み（deposit status=2）
        //   3: 請求書発行済み（deposit status=3）★ 店舗操作（入金）
        //   4: 店舗入金（deposit status=4 or 5）
        //   5: 振込実行（deposit status=6）
        //   6: 受領完了（deposit status=7）
        $depositStatus = $deposit['status_code'] ?? null;
        $progressIndex = match ((int) ($depositStatus ?? -1)) {
            -1 => 0,
            0, 1 => 1,
            2 => 2,
            3 => 3,
            4, 5 => 4,
            6 => 5,
            default => 6,
        };
        $isCompleted = $progressIndex >= 6;

        $stages = [
            ['key' => 'hired',         'label' => '採用確定',     'desc' => '店舗が採用を決定'],
            ['key' => 'cast_request',  'label' => 'ボーナス申請', 'desc' => 'キャストから申請'],
            ['key' => 'shop_approve',  'label' => '店舗承認',     'desc' => '店舗が承認'],
            ['key' => 'invoice_issue', 'label' => '請求書発行',   'desc' => '運営が発行'],
            ['key' => 'shop_pay',      'label' => '店舗入金',     'desc' => '店舗が支払い'],
            ['key' => 'cast_transfer', 'label' => '振込実行',     'desc' => '運営から振込'],
            ['key' => 'received',      'label' => '受領完了',     'desc' => '受領確認済み'],
        ];

        // 「店舗の次のアクション」算出
        // - 1: ボーナス申請受信 → 「承認する」
        // - 3: 請求書発行済み → 「入金処理する」
        $actionableState = null;
        $actionableLabel = null;
        if ($progressIndex === 1) {
            $actionableState = 'approve';
            $actionableLabel = 'ボーナス申請を承認する';
        } elseif ($progressIndex === 3) {
            $actionableState = 'pay';
            $actionableLabel = '入金処理を行う';
        }

        $waitingOnLabel = null;
        if ($actionableState === null) {
            $waitingOnLabel = match ($progressIndex) {
                0 => 'キャストの申請待ち',
                2 => '運営の請求書発行待ち',
                4 => '運営の振込実行待ち',
                5 => 'キャストの受領確認待ち',
                default => null,
            };
        }

        $primaryStatus = match (true) {
            $isCompleted              => ['label' => '振込完了',         'tone' => 'done'],
            $progressIndex === 1      => ['label' => '承認待ち',         'tone' => 'action'],
            $progressIndex === 3      => ['label' => '入金待ち',         'tone' => 'action'],
            $progressIndex >= 1       => ['label' => '入金処理中',       'tone' => 'progress'],
            default                   => ['label' => '採用済（申請前）', 'tone' => 'progress'],
        };

        return [
            'application_id' => (int) ($app['id'] ?? 0),
            'cast_id'        => (string) ($app['cast_id'] ?? ''),
            'cast_name'      => $app['cast_name'] ?? '',
            'cast_avatar_url' => $app['cast_avatar_url'] ?? null,
            'job_kind_label' => $app['job_kind_label'] ?? '',
            'pattern_label'  => $app['pattern_label'] ?? '',
            'hired_at'       => $app['result_date'] ?? null,
            'real_start_date' => $app['real_start_date'] ?? null,
            'hired_hourly_wage_display' => $app['hired_regular_hourly_wage'] ?? null,
            'talk_link'      => !empty($app['cast_id']) ? route('shop.talk.room', ['id' => $app['cast_id'], 'talk_topic' => 'other', 'initiate' => 1]) : null,

            // パイプライン
            'stages'         => $stages,
            'progress_index' => $progressIndex,
            'is_completed'   => $isCompleted,

            // 状態表示
            'status_label'   => $primaryStatus['label'],
            'status_tone'    => $primaryStatus['tone'],
            'waiting_on'     => $waitingOnLabel,
            'actionable'     => $actionableState,
            'actionable_label' => $actionableLabel,

            // deposit のスナップショット
            'deposit'        => $deposit ? [
                'id'                  => $deposit['id'] ?? null,
                'status_label'        => $deposit['status_label'] ?? '',
                'invoice_number'      => $deposit['invoice_number'] ?? null,
                'invoice_issued_at'   => $deposit['invoice_issued_at'] ?? null,
                'invoice_due_date'    => $deposit['invoice_due_date'] ?? null,
                'invoice_amount'      => $deposit['invoice_amount'] ?? null,
                'invoice_pdf_url'     => !empty($deposit['id']) ? $this->billingManagementService->getSignedInvoicePdfUrl((int) $deposit['id']) : null,
                'invoice_url'         => !empty($deposit['id']) ? $this->billingManagementService->getSignedInvoiceUrl((int) $deposit['id']) : null,
                'shop_payment_confirmed_at' => $deposit['shop_payment_confirmed_at'] ?? null,
                'cast_transferred_at' => $deposit['cast_transferred_at'] ?? null,
                'cast_transfer_amount' => $deposit['cast_transfer_amount'] ?? null,
                'bonus_amount'        => $deposit['bonus_amount'] ?? null,
                'updated_at_label'    => $deposit['updated_at_label'] ?? null,
            ] : null,
        ];
    }

    /**
     * 自店舗の求人への応募一覧（マッチしているキャスト）
     */
    private function getApplicationsForShop(string $shopId): array
    {
        $jobIds = DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->pluck('id');
        if ($jobIds->isEmpty()) {
            return [];
        }

        $query = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('cast_profiles', 'shop_job_applications.cast_id', '=', 'cast_profiles.cast_id')
            ->whereIn('shop_job_applications.shop_job_id', $jobIds)
            ->select(
                'shop_job_applications.id',
                'shop_job_applications.cast_id',
                'shop_job_applications.status',
                'shop_job_applications.result_date',
                'shop_job_applications.real_start_date',
                'shop_job_applications.created_at',
                'shop_job_applications.updated_at',
                'cast_profiles.nickname',
                'cast_profiles.name'
            )
            ->orderBy('shop_job_applications.status')
            ->orderByDesc('shop_job_applications.updated_at');

        if (Schema::hasColumn('shop_jobs', 'job_type')) {
            $query->addSelect('shop_jobs.job_type');
        }
        $query->addSelect(DB::raw("(SELECT ci.image_path FROM cast_images ci WHERE ci.cast_id = cast_profiles.cast_id ORDER BY ci.is_main DESC, ci.main_order IS NULL, ci.main_order, ci.id LIMIT 1) as main_image_path"));
        if (Schema::hasColumn('shop_job_applications', 'reason_rejection')) {
            $query->addSelect('shop_job_applications.reason_rejection');
        }
        if (Schema::hasColumn('shop_job_applications', 'rejection_reason')) {
            $query->addSelect('shop_job_applications.rejection_reason');
        }
        if (Schema::hasColumn('shop_job_applications', 'hired_regular_hourly_wage')) {
            $query->addSelect('shop_job_applications.hired_regular_hourly_wage');
        }
        foreach ([
            'talk_job_kind',
            'hired_bonus_amount',
            'hired_bonus_condition',
            'applied_regular_hourly_wage',
            'applied_norma_day',
            'applied_norma_hours',
            'applied_bonus_reward',
            'applied_bonus_remarks',
            'applied_bonus_condition',
            'applied_trial_hourly_wage',
            'applied_help_hourly_wage',
            'applied_working_day',
            'applied_working_hours',
            'applied_regular_holiday',
            'applied_qualification',
        ] as $col) {
            if (Schema::hasColumn('shop_job_applications', $col)) {
                $query->addSelect('shop_job_applications.' . $col);
            }
        }
        $fulltimeRequestCastIds = DB::table('messages')
            ->where('shop_id', $shopId)
            ->where('sender_type', 1)
            ->where('type', 1)
            ->where('is_read', false)
            ->where('content', '本入店を希望します。ご確認をお願いします。')
            ->pluck('cast_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $query
            ->get()
            ->map(function ($row) use ($fulltimeRequestCastIds) {
                $status = (int) $row->status;
                $resolvedTalkJobKind = in_array((string) ($row->talk_job_kind ?? ''), ['fulltime', 'trial', 'help'], true)
                    ? (string) $row->talk_job_kind
                    : null;
                $jobType = match ($resolvedTalkJobKind) {
                    'trial' => 2,
                    'help' => 3,
                    default => (isset($row->job_type) ? (int) $row->job_type : 1),
                };
                $pattern = $jobType === 3 ? 'P2' : 'P1';
                $patternLabel = match ($jobType) {
                    3 => 'ヘルプ',
                    2 => '新規採用（体験入店）',
                    default => '新規採用（本入店）',
                };
                $jobKindLabel = match ($jobType) {
                    3 => 'ヘルプ',
                    2 => '体験入店',
                    default => '本入店',
                };
                $statusLabel = match ($status) {
                    4 => match ($jobType) {
                        3 => 'ヘルプ採用',
                        1 => '本入採用',
                        default => '体験採用',
                    },
                    5 => match ($jobType) {
                        3 => 'ヘルプ不採用',
                        1 => '本入不採用',
                        default => '不採用',
                    },
                    6 => '本入採用',
                    7 => '体験後不採用',
                    default => self::APPLICATION_STATUS_LABELS[$status] ?? '未設定',
                };
                $statusDisplayLabel = match ($status) {
                    1 => 'やり取り中',
                    2 => '面談日調整中',
                    3 => '面談日決定',
                    4 => match ($jobType) {
                        3 => 'ヘルプ採用',
                        1 => '本入採用',
                        default => '体験採用',
                    },
                    5 => match ($jobType) {
                        3 => 'ヘルプ不採用',
                        1 => '本入不採用',
                        default => '不採用',
                    },
                    6 => '本入採用',
                    7 => '体験後不採用',
                    default => '未設定',
                };
                $hasFulltimeRequest = $status === 4
                    && $jobType === 2
                    && in_array((string) $row->cast_id, $fulltimeRequestCastIds, true);
                if ($hasFulltimeRequest) {
                    $statusLabel = '本入店希望（体験採用）';
                    $statusDisplayLabel = '本入店希望（体験採用）';
                }
                $mainImagePath = $row->main_image_path ?? null;
                $castAvatarUrl = $mainImagePath ? $this->assetPathForStored((string) $mainImagePath) : null;
                $rejectionReason = '';
                if (Schema::hasColumn('shop_job_applications', 'reason_rejection')) {
                    $rejectionReason = trim((string) ($row->reason_rejection ?? ''));
                }
                if ($rejectionReason === '' && Schema::hasColumn('shop_job_applications', 'rejection_reason')) {
                    $rejectionReason = trim((string) ($row->rejection_reason ?? ''));
                }
                $appliedSummaryLines = ShopJobApplicationView::appliedJobSummaryLines($row);
                $hiredWage = ShopJobApplicationView::wageAtHire($row);
                $hiredWageInput = $hiredWage ?? '';
                $confirmedSummaryLines = [];
                if (in_array($status, [4, 6], true)) {
                    $confirmedSummaryLines[] = '確定種別: ' . $jobKindLabel;
                    $confirmedWage = $hiredWage;
                    if ($confirmedWage === null || $confirmedWage === '') {
                        $confirmedWage = match ($jobType) {
                            2 => (property_exists($row, 'applied_trial_hourly_wage') && $row->applied_trial_hourly_wage !== null
                                ? trim((string) $row->applied_trial_hourly_wage)
                                : ''),
                            3 => (property_exists($row, 'applied_help_hourly_wage') && $row->applied_help_hourly_wage !== null
                                ? trim((string) $row->applied_help_hourly_wage)
                                : ''),
                            default => (property_exists($row, 'applied_regular_hourly_wage') && $row->applied_regular_hourly_wage !== null
                                ? trim((string) $row->applied_regular_hourly_wage)
                                : ''),
                        };
                    }
                    $confirmedSummaryLines[] = '時給（確定）: ' . (($confirmedWage !== null && $confirmedWage !== '') ? ($confirmedWage . '円') : '未設定');
                    $confirmedBonusAmount = property_exists($row, 'hired_bonus_amount') && $row->hired_bonus_amount !== null
                        ? (int) $row->hired_bonus_amount
                        : null;
                    if ($confirmedBonusAmount !== null) {
                        $confirmedSummaryLines[] = 'ボーナス金額（確定）: ¥' . number_format($confirmedBonusAmount);
                    }
                    $confirmedBonusCondition = property_exists($row, 'hired_bonus_condition') && $row->hired_bonus_condition !== null
                        ? trim((string) $row->hired_bonus_condition)
                        : '';
                    if ($confirmedBonusCondition !== '') {
                        $confirmedSummaryLines[] = '達成条件（確定）: ' . $confirmedBonusCondition;
                    }
                }

                // 面談日超過 × 採用/不採用通知未送信 (status: 1=やり取り中, 2=面談日調整中, 3=面談日決定)
                $isDecisionOverdue = false;
                if (in_array($status, [1, 2, 3], true) && !empty($row->result_date)) {
                    $resultTs = strtotime((string) $row->result_date);
                    $todayTs  = strtotime(date('Y-m-d'));
                    $isDecisionOverdue = $resultTs !== false && $resultTs < $todayTs;
                }

                return [
                    'id' => $row->id,
                    'cast_id' => $row->cast_id,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'pattern' => $pattern,
                    'pattern_label' => $patternLabel,
                    'job_kind_label' => $jobKindLabel,
                    'status_display_label' => $statusDisplayLabel,
                    'has_fulltime_request' => $hasFulltimeRequest,
                    'result_date' => $row->result_date ? date('Y/m/d', strtotime($row->result_date)) : null,
                    'real_start_date' => $row->real_start_date ? date('Y/m/d', strtotime($row->real_start_date)) : null,
                    'created_at' => $row->created_at ? date('Y/m/d', strtotime($row->created_at)) : null,
                    'cast_name' => $row->nickname ?: $row->name ?: 'キャスト',
                    'cast_avatar_url' => $castAvatarUrl,
                    'rejection_reason' => $rejectionReason !== '' ? $rejectionReason : null,
                    'is_delayed' => false,
                    'delay_message' => '',
                    'applied_summary_lines' => $appliedSummaryLines,
                    'confirmed_summary_lines' => $confirmedSummaryLines,
                    'hired_regular_hourly_wage' => $hiredWage,
                    'hired_regular_hourly_wage_input' => $hiredWageInput,
                    'can_edit_hired_wage' => in_array($status, [4, 6], true)
                        && (Schema::hasColumn('shop_job_applications', 'hired_regular_hourly_wage')
                            || Schema::hasColumn('shop_job_applications', 'hourly_wage_regular')),
                    'is_decision_overdue' => $isDecisionOverdue,
                ];
            })
            ->all();
    }

    /**
     * 採用一覧・採用済み応募の「採用時給」を更新
     */
    public function updateApplicationHiredWage(Request $request)
    {
        $request->validate([
            'application_id' => ['required', 'integer', 'min:1'],
            'hired_regular_hourly_wage' => ['nullable', 'string', 'max:32'],
        ]);

        $wageColumn = Schema::hasColumn('shop_job_applications', 'hired_regular_hourly_wage')
            ? 'hired_regular_hourly_wage'
            : (Schema::hasColumn('shop_job_applications', 'hourly_wage_regular') ? 'hourly_wage_regular' : null);
        if ($wageColumn === null) {
            abort(404);
        }

        $shopId = $this->currentShopId();
        $applicationId = (int) $request->input('application_id');
        $wage = ShopJobApplicationView::normalizeWageDigits(
            $request->input('hired_regular_hourly_wage') !== null
                ? (string) $request->input('hired_regular_hourly_wage')
                : null
        );

        $target = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->where('shop_job_applications.id', $applicationId)
            ->where('shop_jobs.shop_id', $shopId)
            ->whereIn('shop_job_applications.status', [4, 6])
            ->select('shop_job_applications.id')
            ->first();

        if (!$target) {
            return redirect()
                ->route('shop.mypage.management', ['tab' => 'recruit'])
                ->with('message', '対象の応募が見つからないか、採用ステータスではありません。');
        }

        DB::table('shop_job_applications')
            ->where('id', $target->id)
            ->update([
                $wageColumn => $wage,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('shop.mypage.management', ['tab' => 'recruit'])
            ->with('message', '採用時給を保存しました。');
    }

    /**
     * Random auto-fill suggestion for the recruit editor.
     * Returns a single random enabled template body for the given category.
     * Category is constrained to catch_copy / manager_message via the route pattern.
     */
    public function randomSuggestion(string $category)
    {
        if (!Schema::hasTable('ai_suggestion_templates')) {
            return response()->json(['ok' => false, 'body' => ''], 404);
        }
        $row = DB::table('ai_suggestion_templates')
            ->where('category', $category)
            ->where('is_enabled', 1)
            ->inRandomOrder()
            ->select('id', 'body')
            ->first();
        if (!$row) {
            return response()->json(['ok' => false, 'body' => '']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'body' => (string) $row->body,
        ]);
    }

    /**
     * キャストに表示される求人画面を、非公開中でも店舗本人が確認する。
     */
    public function preview(Request $request)
    {
        $shopId = $this->currentShopId();
        $data = $this->getRecruitData($shopId);
        abort_if(empty($data['recruit']), 404);

        $initialJobPanel = (string) $request->query('job', '');
        $initialJobPanel = in_array($initialJobPanel, ['fulltime', 'help'], true) ? $initialJobPanel : '';
        $numericId = (int) ltrim($shopId, 's0');
        $shopName = $data['shop']['name'] ?? $data['recruit']['store_name'] ?? '店舗';
        $shareText = trim((string) ($data['recruit']['catch_copy'] ?? $data['recruit']['message'] ?? ''));

        return view('shops.recruit.show', [
            'pageId' => 'job_preview',
            'recruit' => $data['recruit'],
            'recruit_trial' => $data['recruit_trial'] ?? $data['recruit'],
            'recruit_help' => $data['recruit_help'] ?? $data['recruit'],
            'usesJobTypes' => $this->shopJobsUseMultipleTypes(),
            'horizontalShopJobs' => $this->shopJobsHorizontalSchema(),
            'initial_job_panel' => $initialJobPanel,
            'shop' => $data['shop'],
            'forCast' => false,
            'isPublicShare' => false,
            'shareUrl' => route('share.recruit.show', ['id' => max(1, $numericId)]),
            'shareTitle' => $shopName . 'の求人情報',
            'shareText' => mb_strimwidth($shareText !== '' ? $shareText : 'ミセチョクの求人情報です。', 0, 80, '…'),
            'distanceKm' => null,
            'distanceLabel' => null,
        ]);
    }

    /**
     * 求人情報編集
     */
    public function edit()
    {
        $shopId = $this->currentShopId();
        $recruitData = $this->getRecruitData($shopId);
        $usesJobTypes = $this->shopJobsUseMultipleTypes();
        $horizontal = $this->shopJobsHorizontalSchema();

        $recruit = $recruitData['recruit'];

        $licenseData = $this->documentReviewService->getShopLicensePageData($shopId);

        return view('shops.recruit.edit', [
            'pageId' => 'job_edit',
            'recruit' => $recruit,
            'recruitTrial' => $usesJobTypes ? $recruitData['recruit_trial'] : null,
            'recruitHelp'  => $usesJobTypes ? $recruitData['recruit_help']  : null,
            'usesJobTypes' => $usesJobTypes,
            'horizontalShopJobs' => $horizontal,
            'masters' => $this->adminMasterService->getRecruitmentMasters(),
            'canPublishJobs' => $licenseData['all_approved'],
        ]);
    }

    /**
     * 求人情報更新 (Ajax想定)
     */
    public function update(Request $request)
    {
        if ($this->shopJobsHorizontalSchema()) {
            return $this->updateHorizontal($request);
        }

        if ($this->shopJobsUseMultipleTypes()) {
            return $this->updateVerticalMultiTypes($request);
        }

        $jobKind = (string) $request->input('recruit_job_kind', 'fulltime');
        if (!in_array($jobKind, ['fulltime', 'trial', 'help'], true)) {
            $jobKind = 'fulltime';
        }

        $wageRules = [
            'hourly_wage_regular' => 'required|integer|min:0',
            'trial_hourly_wage' => 'nullable|integer|min:0',
            'help_hourly_wage' => 'nullable|integer|min:0',
        ];
        if ($jobKind === 'trial') {
            $wageRules = [
                'hourly_wage_regular' => 'nullable|integer|min:0',
                'trial_hourly_wage' => 'required|integer|min:0',
                'help_hourly_wage' => 'nullable|integer|min:0',
            ];
        } elseif ($jobKind === 'help') {
            $wageRules = [
                'hourly_wage_regular' => 'nullable|integer|min:0',
                'trial_hourly_wage' => 'nullable|integer|min:0',
                'help_hourly_wage' => 'required|integer|min:0',
            ];
        }

        $data = $request->validate(array_merge([
            'recruit_job_kind' => 'nullable|string|in:fulltime,trial,help',
            'catch_copy' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'job_content' => 'nullable|string|max:2000',
            'noruma_reward' => 'nullable|integer|min:0',
            'bonus_condition' => 'nullable|string|max:1000',
            'bonus_total_working_days' => 'nullable|integer|min:0',
            'bonus_total_working_hours' => 'nullable|integer|min:0',
            'bonus_other_conditions' => 'nullable|string|max:1000',
            'salary_text' => 'nullable|string|max:1000',
            'shift_time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'shift_time_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'shift_end_is_last' => 'nullable|boolean',
            'regular_hourly_wage_max' => 'nullable|integer|min:0',
            'trial_hourly_wage_max' => 'nullable|integer|min:0',
            'help_hourly_wage_max' => 'nullable|integer|min:0',
            'working_days' => 'required|string|max:255',
            'regular_holiday' => 'nullable|string|max:255',
            'qualification' => 'required|string|max:255',
            'work_style_tag_ids' => 'nullable|array',
            'work_style_tag_ids.*' => 'integer|exists:shop_tags,id',
            'welcome_tag_ids' => 'nullable|array',
            'welcome_tag_ids.*' => 'integer|exists:shop_tags,id',
            'benefit_tag_ids' => 'nullable|array',
            'benefit_tag_ids.*' => 'integer|exists:shop_tags,id',
        ], $wageRules));

        $this->assertShiftEndValid($request);
        $data['working_hours'] = $this->composeWorkingHoursFromShiftRequest($request);

        $shopId = $this->currentShopId();
        $metaJobType = match ($jobKind) {
            'trial' => 2,
            'help' => 3,
            default => 1,
        };
        $meta = $metaJobType !== 1
            ? $this->getRecruitMetaForJobType($shopId, $metaJobType)
            : $this->getRecruitMeta($shopId);
        $bonusOther = trim((string) ($request->input('bonus_other_conditions', $data['bonus_condition'] ?? '')));
        $payload = array_merge($meta, [
            'catch_copy' => $data['catch_copy'],
            'job_content' => trim((string) ($data['job_content'] ?? '')),
            'bonus_condition' => $bonusOther,
            'bonus_total_working_days' => $request->filled('bonus_total_working_days') ? (int) $request->input('bonus_total_working_days') : null,
            'bonus_total_working_hours' => $request->filled('bonus_total_working_hours') ? (int) $request->input('bonus_total_working_hours') : null,
            'bonus_other_conditions' => $bonusOther,
            'working_hours' => $data['working_hours'],
            'working_days' => $data['working_days'],
            'regular_holiday' => $data['regular_holiday'] ?? '',
            'qualification' => $data['qualification'],
        ]);
        // 旧スキーマ由来のフィールドはメタから除去（job_content は JSON メタに残す）
        unset($payload['message'], $payload['tag_ids']);

        $jobPayload = $this->buildJobPayloadFromValidated($request, $shopId, $data, $payload);
        $publishSquashed = false;
        if (!$this->documentReviewService->shopLicenseFullyApproved($shopId) && $request->boolean('published')) {
            $jobPayload['status'] = 0;
            $publishSquashed = true;
        }
        $this->applyShiftColumnsToPatch($jobPayload, $request);
        $this->applyHourlyWageMaxToPatch($jobPayload, $data);
        $jobTagsPayload = [
            'work_style' => $request->input('work_style_tag_ids', []),
            'welcome'    => $request->input('welcome_tag_ids', []),
            'benefit'    => $request->input('benefit_tag_ids', []),
        ];

        $existingQ = DB::table('shop_jobs')->where('shop_id', $shopId);
        $this->scopeShopJobPrimaryRow($existingQ);
        $existingId = (int) $existingQ->value('id');

        if ($existingId > 0) {
            $upd = DB::table('shop_jobs')->where('shop_id', $shopId);
            $this->scopeShopJobPrimaryRow($upd);
            $upd->update($jobPayload);
            $jobId = $existingId;
        } else {
            $jobId = (int) DB::table('shop_jobs')->insertGetId(array_merge(
                $jobPayload,
                $this->shopJobPrimaryTypeInsertAttributes(),
                ['created_at' => now()]
            ));
        }

        $this->syncShopJobTags($jobId, $jobTagsPayload);

        $msg = '求人情報を保存しました';
        if ($publishSquashed) {
            $msg .= ' ' . self::MSG_LICENSE_REQUIRED_FOR_PUBLISH . 'そのため公開設定はオフのままにしました。';
        }

        return redirect()
            ->to(route('shop.recruits.edit'))
            ->with('message', $msg);
    }

    public function toggleStatus(Request $request)
    {
        $shopId = $this->currentShopId();

        // Horizontal schema path: delegate to RecruitPublicationService.
        if ($this->shopJobsHorizontalSchema()) {
            $jt = (int) $request->input('job_type', 1);
            if (!in_array($jt, [1, 2, 3], true)) {
                $jt = 1;
            }
            $result = $this->recruitPublicationService->toggleHorizontal($shopId, $jt);
            return redirect()->back()->with('message', $result['message']);
        }

        $currentStatus = $this->getCurrentRecruitStatus($shopId);
        $nextStatus = $currentStatus === 1 ? 0 : 1;
        if ($nextStatus === 1 && !$this->documentReviewService->shopLicenseFullyApproved($shopId)) {
            return redirect()
                ->back()
                ->with('message', self::MSG_LICENSE_REQUIRED_FOR_PUBLISH);
        }
        $existingQ = DB::table('shop_jobs')->where('shop_id', $shopId);
        $this->scopeShopJobPrimaryRow($existingQ);

        if ($existingQ->exists()) {
            $upd = DB::table('shop_jobs')->where('shop_id', $shopId);
            $this->scopeShopJobPrimaryRow($upd);
            $upd->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('shop_jobs')->insert(array_merge([
                'shop_id' => $shopId,
                'status' => $nextStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->shopJobPrimaryTypeInsertAttributes()));
        }

        return redirect()
            ->back()
            ->with('message', $nextStatus === 1 ? '求人を公開しました' : '求人を非公開にしました');
    }

    private function shopJobsHorizontalSchema(): bool
    {
        return Schema::hasTable('shop_jobs') && Schema::hasColumn('shop_jobs', 'regular_status');
    }

    private function shopJobsUseMultipleTypes(): bool
    {
        if (!Schema::hasTable('shop_jobs')) {
            return false;
        }
        if ($this->shopJobsHorizontalSchema()) {
            return true;
        }

        return Schema::hasColumn('shop_jobs', 'job_type');
    }

    /**
     * 本入（job_type=1）の行に絞る。job_type カラムが無いスキーマでは付与しない。
     */
    private function scopeShopJobPrimaryRow(\Illuminate\Database\Query\Builder $query): void
    {
        if (Schema::hasColumn('shop_jobs', 'job_type')) {
            $query->where('job_type', 1);
        }
    }

    /**
     * shop_jobs 挿入時の job_type（カラムがあるときのみ）。
     *
     * @return array<string, int>
     */
    private function shopJobPrimaryTypeInsertAttributes(): array
    {
        if (!Schema::hasColumn('shop_jobs', 'job_type')) {
            return [];
        }

        return ['job_type' => 1];
    }

    private function getRecruitMetaForJobType(string $shopId, int $jobType): array
    {
        if ($this->shopJobsHorizontalSchema()) {
            $raw = DB::table('shop_jobs')->where('shop_id', $shopId)->value('noruma_cond');

            return $this->decodeMeta($raw);
        }
        if (!$this->shopJobsUseMultipleTypes()) {
            return $this->getRecruitMeta($shopId);
        }

        $raw = DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->where('job_type', $jobType)
            ->value('noruma_cond');

        return $this->decodeMeta($raw);
    }

    private function buildJobPayloadFromValidated(Request $request, string $shopId, array $data, array $payload, ?bool $published = null): array
    {
        $pub = $published ?? $request->boolean('published');
        $base = [
            'shop_id' => $shopId,
            'status' => $pub ? 1 : 0,
            'hourly_wage_regular' => (string) ($data['hourly_wage_regular'] ?? 0),
            'trial_hourly_wage' => $request->filled('trial_hourly_wage') ? (string) $data['trial_hourly_wage'] : null,
            'has_trial' => $request->filled('trial_hourly_wage') ? 1 : 0,
            'help_hourly_wage' => $request->filled('help_hourly_wage') ? (string) $data['help_hourly_wage'] : null,
            'has_help' => $request->boolean('has_help') && $request->filled('help_hourly_wage') ? 1 : 0,
            'noruma_reward' => $request->filled('noruma_reward') ? (string) $data['noruma_reward'] : null,
            'salary' => $data['salary_text'] ?? '',
            'noruma_cond' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $base['pr'] = (string) ($data['message'] ?? '');
        }

        if (Schema::hasColumn('shop_jobs', 'job_content')) {
            $base['job_content'] = trim((string) ($request->input('job_content', $data['job_content'] ?? '')));
        }

        if (Schema::hasColumn('shop_jobs', 'working_hours')) {
            $base['working_hours'] = $data['working_hours'];
        }
        if (Schema::hasColumn('shop_jobs', 'working_day')) {
            $base['working_day'] = $data['working_days'];
        }
        if (Schema::hasColumn('shop_jobs', 'regular_holiday')) {
            $base['regular_holiday'] = $data['regular_holiday'] ?? '';
        }
        if (Schema::hasColumn('shop_jobs', 'qualification')) {
            $base['qualification'] = $data['qualification'];
        }

        return $base;
    }

    private function upsertShopJobRow(string $shopId, int $jobType, array $jobPayload, array $data, string $kind): int
    {
        $payload = $jobPayload;
        if ($kind === 'trial') {
            $payload['trial_hourly_wage'] = (string) $data['trial_hourly_wage'];
            $payload['has_trial'] = 1;
            $payload['help_hourly_wage'] = null;
            $payload['has_help'] = 0;
            $reg = $data['hourly_wage_regular'] ?? null;
            $payload['hourly_wage_regular'] = (string) (($reg !== null && $reg !== '') ? $reg : $data['trial_hourly_wage']);
        } else {
            $payload['help_hourly_wage'] = (string) $data['help_hourly_wage'];
            $payload['has_help'] = 1;
            $payload['trial_hourly_wage'] = null;
            $payload['has_trial'] = 0;
            $payload['hourly_wage_regular'] = (string) $data['help_hourly_wage'];
        }

        $existingId = (int) DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->where('job_type', $jobType)
            ->value('id');

        if ($existingId > 0) {
            DB::table('shop_jobs')
                ->where('id', $existingId)
                ->update($payload);

            return $existingId;
        }

        return (int) DB::table('shop_jobs')->insertGetId(array_merge($payload, [
            'job_type' => $jobType,
            'created_at' => now(),
        ]));
    }

    /**
     * job_type 2 / 3 のレコード内容をプレビュー用に本入ベースへ上書きマージする。
     */
    private function applyJobVariantToRecruit(array $base, ?object $variantRow, string $kind): array
    {
        if (!$variantRow) {
            return $base;
        }

        $out = $base;
        $meta = $this->decodeMeta($variantRow->noruma_cond ?? null);
        foreach (['catch_copy', 'working_hours', 'working_days', 'regular_holiday', 'qualification'] as $k) {
            if (array_key_exists($k, $meta) && $meta[$k] !== null && (string) $meta[$k] !== '') {
                $out[$k] = (string) $meta[$k];
            }
        }
        if (Schema::hasColumn('shop_jobs', 'catch_copy')) {
            $ccCol = trim((string) ($variantRow->catch_copy ?? ''));
            if ($ccCol !== '') {
                $out['catch_copy'] = $ccCol;
            }
        }
        if (Schema::hasColumn('shop_jobs', 'job_content')) {
            $jcCol = trim((string) ($variantRow->job_content ?? ''));
            if ($jcCol !== '') {
                $out['job_content'] = $jcCol;
            }
        }
        foreach (['trial_hourly_wage_max', 'help_hourly_wage_max'] as $wm) {
            if (Schema::hasColumn('shop_jobs', $wm) && isset($variantRow->{$wm}) && $variantRow->{$wm} !== null && $variantRow->{$wm} !== '') {
                $out[$wm] = (int) $variantRow->{$wm};
            }
        }
        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $out['message'] = (string) ($variantRow->pr ?? '');
        }
        foreach (['bonus_total_working_days', 'bonus_total_working_hours'] as $bk) {
            if (array_key_exists($bk, $meta) && $meta[$bk] !== null && $meta[$bk] !== '') {
                $v = (string) $meta[$bk];
                $out[$bk] = $v;
                $out[$bk === 'bonus_total_working_days' ? 'bonus_working_days' : 'bonus_working_hours'] = $v;
            }
        }
        $bonusExtra = trim((string) ($meta['bonus_other_conditions'] ?? $meta['bonus_condition'] ?? ''));
        if ($bonusExtra !== '') {
            $out['bonus_other_conditions'] = $bonusExtra;
            $out['bonus_condition'] = $bonusExtra;
        }
        if (array_key_exists('noruma_reward', $meta) && $meta['noruma_reward'] !== null && $meta['noruma_reward'] !== '') {
            $out['noruma_reward'] = (int) $meta['noruma_reward'];
        }
        if (isset($variantRow->id)) {
            $jobTagIds = $this->getShopJobTagIdsByCategory((int) $variantRow->id);
            $jobTagNames = $this->resolveShopJobTagNames($jobTagIds);
            $out['store_features'] = [
                '働き方・給与' => $jobTagNames['work_style'],
                '歓迎条件'     => $jobTagNames['welcome'],
                '待遇・サポート' => $jobTagNames['benefit'],
            ];
            $out['work_style_tag_ids'] = $jobTagIds['work_style'];
            $out['welcome_tag_ids']    = $jobTagIds['welcome'];
            $out['benefit_tag_ids']    = $jobTagIds['benefit'];
        }
        if ($kind === 'trial') {
            if (!empty($variantRow->trial_hourly_wage)) {
                $out['trial_hourly_wage'] = (int) $variantRow->trial_hourly_wage;
            }
            $out['status'] = ((int) ($variantRow->status ?? 1)) === 1 ? 'active' : 'inactive';
        } elseif ($kind === 'help') {
            if (!empty($variantRow->help_hourly_wage)) {
                $out['help_hourly_wage'] = (int) $variantRow->help_hourly_wage;
            }
            $out['status'] = ((int) ($variantRow->status ?? 1)) === 1 ? 'active' : 'inactive';
        }
        if (!empty($variantRow->salary)) {
            $out['salary_text'] = (string) $variantRow->salary;
        }

        return $this->attachCatchHeroOverlay($out);
    }

    /**
     * @param  array<string, mixed>  $recruit
     * @return array<string, mixed>
     */
    private function attachCatchHeroOverlay(array $recruit): array
    {
        $recruit['catch_hero_overlay'] = RecruitCatchOverlay::buildFromMeta(
            [
                'catch_copy' => $recruit['catch_copy'] ?? '',
                'bonus_condition' => $recruit['bonus_condition'] ?? '',
                'bonus_other_conditions' => $recruit['bonus_other_conditions'] ?? '',
            ],
            (int) ($recruit['noruma_reward'] ?? 0)
        );

        return $recruit;
    }

    private function getRecruitData(string $shopId): array
    {
        if ($this->shopJobsHorizontalSchema()) {
            return $this->getRecruitDataHorizontal($shopId);
        }

        $multi = $this->shopJobsUseMultipleTypes();

        $q = DB::table('shops')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id');
        if ($multi) {
            $q->leftJoin('shop_jobs', function ($join) {
                $join->on('shops.id', '=', 'shop_jobs.shop_id')
                    ->where('shop_jobs.job_type', 1);
            });
        } else {
            $q->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id');
        }

        $row = $q->where('shops.id', $shopId)
            ->select('shops.id', 'shop_profiles.*', 'shop_jobs.*', 'shop_jobs.id as primary_job_id')
            ->first();

        $trialRow = null;
        $helpRow = null;
        if ($multi) {
            $trialRow = DB::table('shop_jobs')
                ->where('shop_id', $shopId)
                ->where('job_type', 2)
                ->first();
            $helpRow = DB::table('shop_jobs')
                ->where('shop_id', $shopId)
                ->where('job_type', 3)
                ->first();
        }

        $industryName = $this->resolveShopIndustryName($shopId, $row?->industry_id ?? null);
        $shopTagGroups = $this->resolveShopInfoTagGroups($shopId);

        $meta = $this->decodeMeta($row->noruma_cond ?? null);
        if ($row && Schema::hasColumn('shop_jobs', 'catch_copy') && trim((string) ($row->catch_copy ?? '')) !== '') {
            $meta['catch_copy'] = trim((string) $row->catch_copy);
        }
        $jobContentResolved = '';
        if ($row && Schema::hasColumn('shop_jobs', 'job_content')) {
            $jobContentResolved = trim((string) ($row->job_content ?? ''));
        }
        if ($jobContentResolved === '') {
            $jobContentResolved = trim((string) ($meta['job_content'] ?? ''));
        }
        $primaryJobId = isset($row->primary_job_id) ? (int) $row->primary_job_id : 0;
        $primaryJobTagIds = $primaryJobId ? $this->getShopJobTagIdsByCategory($primaryJobId) : ['work_style' => [], 'welcome' => [], 'benefit' => []];
        $primaryJobTagNames = $this->resolveShopJobTagNames($primaryJobTagIds);
        $subImages = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('image_path')
            ->map(fn ($path) => $this->assetPathForStored($path))
            ->filter()
            ->values()
            ->all();

        $mainImage = $subImages[0] ?? $this->assetPathForStored($row->main_image_path ?? null);
        if (empty($subImages) && $mainImage) {
            $subImages[] = $mainImage;
        }

        $galleryImages = [];
        if ($mainImage) {
            $galleryImages[] = $mainImage;
        }
        foreach ($subImages as $path) {
            if ($path && !in_array($path, $galleryImages, true)) {
                $galleryImages[] = $path;
            }
        }

        $workingHours = Schema::hasColumn('shop_jobs', 'working_hours') ? ($row->working_hours ?? '') : ($meta['working_hours'] ?? '');
        $workingDays = Schema::hasColumn('shop_jobs', 'working_day') ? ($row->working_day ?? '') : ($meta['working_days'] ?? '');
        $regularHoliday = Schema::hasColumn('shop_jobs', 'regular_holiday') ? ($row->regular_holiday ?? '') : ($meta['regular_holiday'] ?? '');
        $qualification = Schema::hasColumn('shop_jobs', 'qualification') ? ($row->qualification ?? '') : ($meta['qualification'] ?? '');

        // 達成条件（編集画面の bonus_* と旧キーの両方を参照）
        $bonusWorkingDaysRaw = $meta['bonus_total_working_days'] ?? $meta['bonus_working_days'] ?? null;
        $bonusWorkingHoursRaw = $meta['bonus_total_working_hours'] ?? $meta['bonus_working_hours'] ?? null;
        $bonusWorkingDays = $bonusWorkingDaysRaw === null || $bonusWorkingDaysRaw === '' ? '' : (string) $bonusWorkingDaysRaw;
        $bonusWorkingHours = $bonusWorkingHoursRaw === null || $bonusWorkingHoursRaw === '' ? '' : (string) $bonusWorkingHoursRaw;
        $bonusExtraCondition = trim((string) ($meta['bonus_other_conditions'] ?? $meta['bonus_condition'] ?? ''));

        $recruitBase = [
                'store_name' => $row->shop_name ?? '店舗',
                'open_date' => !empty($row->opened_on) ? date('Y年n月j日', strtotime($row->opened_on)) : null,
                'address' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null, $this->streetAddressFromProfileRow($row)]))),
                'map_embed_src' => null,
                'nearest_station' => $this->resolveNearestStationForProfile($shopId, $row),
                'hourly_wage_regular' => isset($row->hourly_wage_regular) ? (int) $row->hourly_wage_regular : 0,
                'trial_hourly_wage' =>
                    $trialRow && !empty($trialRow->trial_hourly_wage)
                        ? (int) $trialRow->trial_hourly_wage
                        : null,
                'help_hourly_wage' =>
                    $helpRow && !empty($helpRow->help_hourly_wage)
                        ? (int) $helpRow->help_hourly_wage
                        : null,
                'help_job_content' => '',
                'noruma_reward' => isset($row->noruma_reward) ? (int) $row->noruma_reward : 0,
                'bonus_condition' => $bonusExtraCondition,
                'bonus_other_conditions' => $bonusExtraCondition,
                'bonus_total_working_days' => $bonusWorkingDays,
                'bonus_total_working_hours' => $bonusWorkingHours,
                'bonus_working_days' => $bonusWorkingDays,
                'bonus_working_hours' => $bonusWorkingHours,
                'salary_text' => $row->salary ?? '',
                'working_hours' => $workingHours,
                'working_days' => $workingDays,
                'regular_holiday' => $regularHoliday,
                'job_content' => $jobContentResolved,
                'store_atmosphere' => $row->atmosphere ?? '',
                'qualification' => $qualification ?: '18歳以上（高校生不可）',
                'catch_copy' => $meta['catch_copy'] ?? '',
                'message' => Schema::hasColumn('shop_jobs', 'pr')
                    ? (string) ($row->pr ?? '')
                    : '',
                'selected_benefits' => $primaryJobTagNames['benefit'],
                'store_features' => [
                    '働き方・給与'   => $primaryJobTagNames['work_style'],
                    '歓迎条件'       => $primaryJobTagNames['welcome'],
                    '待遇・サポート' => $primaryJobTagNames['benefit'],
                ],
                'work_style_tag_ids' => $primaryJobTagIds['work_style'],
                'welcome_tag_ids'    => $primaryJobTagIds['welcome'],
                'benefit_tag_ids'    => $primaryJobTagIds['benefit'],
                'status' => ((int) ($row->status ?? 1)) === 1 ? 'active' : 'inactive',
                'updated_at' => !empty($row->updated_at) ? date('Y.m.d', strtotime($row->updated_at)) : null,
            ];
        $shiftWage = $this->shiftAndWageMaxFromShopJobRow($row);
        if ($multi) {
            if ($trialRow && Schema::hasColumn('shop_jobs', 'trial_hourly_wage_max')
                && isset($trialRow->trial_hourly_wage_max) && $trialRow->trial_hourly_wage_max !== null && $trialRow->trial_hourly_wage_max !== '') {
                $shiftWage['trial_hourly_wage_max'] = (int) $trialRow->trial_hourly_wage_max;
            }
            if ($helpRow && Schema::hasColumn('shop_jobs', 'help_hourly_wage_max')
                && isset($helpRow->help_hourly_wage_max) && $helpRow->help_hourly_wage_max !== null && $helpRow->help_hourly_wage_max !== '') {
                $shiftWage['help_hourly_wage_max'] = (int) $helpRow->help_hourly_wage_max;
            }
        }
        $recruitBase = array_merge($recruitBase, $shiftWage);
        $recruitBase = $this->attachCatchHeroOverlay($recruitBase);

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $shopHitokoto = $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '';

        return [
            'recruit' => $recruitBase,
            'recruit_trial' => $multi ? $this->applyJobVariantToRecruit($recruitBase, $trialRow, 'trial') : $recruitBase,
            'recruit_help' => $multi ? $this->applyJobVariantToRecruit($recruitBase, $helpRow, 'help') : $recruitBase,
            'shop' => [
                'id' => $shopId,
                'name' => $row->shop_name ?? '店舗',
                'word' => $shopHitokoto,
                'main_img' => $mainImage,
                'area' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null]))),
                'concept' => '',
                'review_avg' => 0,
                'review_cnt' => 0,
                'sub_images' => $subImages,
                'gallery_images' => $galleryImages,
                'zip' => $row->zip ?? '',
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'addr1' => $this->streetAddressFromProfileRow($row),
                'industry_name' => $industryName,
                'nearest_station' => $this->resolveNearestStationForProfile($shopId, $row),
                'business_hours_shop' => \App\Support\ShopBusinessHours::formatDisplay(
                    $row->open_time ?? null,
                    isset($row->close_is_last) ? (int) $row->close_is_last : 0,
                    $row->close_time ?? null
                ),
                'tag_groups' => $shopTagGroups,
            ],
        ];
    }

    /**
     * 店舗プロフィール側で選んだ shop_tag_relations を新スキーマ (shop_tags target=shop) で
     * カテゴリごとにグループ化して返す。
     *
     * @return array<int, array{label: string, tags: array<int, string>}>
     */
    private function resolveShopInfoTagGroups(string $shopId): array
    {
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('shop_tag_relations') || !$schema->hasTable('shop_tags')) {
            return [];
        }

        $definitions = [
            ['label' => '店内の雰囲気・客層', 'category' => 'atmosphere'],
            ['label' => '設備・アクセス',     'category' => 'facility'],
        ];

        $groups = [];
        foreach ($definitions as $def) {
            $names = DB::table('shop_tag_relations as r')
                ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
                ->where('r.shop_id', $shopId)
                ->where('r.tag_type', $def['category'])
                ->where('t.target', 'shop')
                ->where('t.category', $def['category'])
                ->where('t.del_flg', 0)
                ->orderBy('t.sort_order')
                ->orderBy('t.id')
                ->pluck('t.name')
                ->filter()
                ->values()
                ->all();
            if (!empty($names)) {
                $groups[] = ['label' => $def['label'], 'tags' => $names];
            }
        }

        return $groups;
    }

    private function getRecruitMeta(string $shopId): array
    {
        $q = DB::table('shop_jobs')->where('shop_id', $shopId);
        $this->scopeShopJobPrimaryRow($q);
        $raw = $q->value('noruma_cond');

        return $this->decodeMeta($raw);
    }

    private function decodeMeta(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * shop_job_tag_relations + shop_tags(target='job') から、求人票用の3カテゴリ別タグID一覧を取得。
     *
     * @return array{work_style: array<int,int>, welcome: array<int,int>, benefit: array<int,int>}
     */
    private function getShopJobTagIdsByCategory(int $shopJobId): array
    {
        $result = ['work_style' => [], 'welcome' => [], 'benefit' => []];
        if ($shopJobId <= 0
            || !Schema::hasTable('shop_job_tag_relations')
            || !Schema::hasTable('shop_tags')
        ) {
            return $result;
        }

        $rows = DB::table('shop_job_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_job_id', $shopJobId)
            ->where('t.target', 'job')
            ->where('t.del_flg', 0)
            ->whereIn('t.category', ['work_style', 'welcome', 'benefit'])
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->select('t.id', 't.category')
            ->get();

        foreach ($rows as $r) {
            $cat = (string) $r->category;
            if (isset($result[$cat])) {
                $result[$cat][] = (int) $r->id;
            }
        }

        return $result;
    }

    /**
     * @param array{work_style: array<int,int>, welcome: array<int,int>, benefit: array<int,int>} $idsByCategory
     * @return array{work_style: array<int,string>, welcome: array<int,string>, benefit: array<int,string>}
     */
    private function resolveShopJobTagNames(array $idsByCategory): array
    {
        $resolved = ['work_style' => [], 'welcome' => [], 'benefit' => []];
        if (!Schema::hasTable('shop_tags')) {
            return $resolved;
        }

        foreach ($resolved as $cat => $_) {
            $ids = $this->normalizeTagIds($idsByCategory[$cat] ?? []);
            if (empty($ids)) {
                continue;
            }
            $resolved[$cat] = DB::table('shop_tags')
                ->where('target', 'job')
                ->where('category', $cat)
                ->whereIn('id', $ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('name')
                ->all();
        }

        return $resolved;
    }

    private function normalizeTagIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 求人票（shop_jobs.id 単位）に紐づくタグを shop_job_tag_relations で同期する。
     *
     * - tag_type には category 名（work_style / welcome / benefit）をそのまま入れる
     * - 渡された tag_id が shop_tags(target='job') に該当するもののみ採用する
     *
     * @param array<string, array<int, mixed>> $tagIdsByCategory
     */
    private function syncShopJobTags(int $shopJobId, array $tagIdsByCategory): void
    {
        if ($shopJobId <= 0 || !Schema::hasTable('shop_job_tag_relations')) {
            return;
        }

        DB::table('shop_job_tag_relations')
            ->where('shop_job_id', $shopJobId)
            ->whereIn('tag_type', ['work_style', 'welcome', 'benefit'])
            ->delete();

        if (!Schema::hasTable('shop_tags')) {
            return;
        }

        $insertRows = [];
        foreach (['work_style', 'welcome', 'benefit'] as $category) {
            $ids = $this->normalizeTagIds($tagIdsByCategory[$category] ?? []);
            if (empty($ids)) {
                continue;
            }
            $validIds = DB::table('shop_tags')
                ->where('target', 'job')
                ->where('category', $category)
                ->where('del_flg', 0)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($validIds as $tagId) {
                $insertRows[] = [
                    'shop_job_id' => $shopJobId,
                    'tag_id'      => $tagId,
                    'tag_type'    => $category,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        if (!empty($insertRows)) {
            DB::table('shop_job_tag_relations')->insert($insertRows);
        }
    }

    // assetPathForStored() is now provided by ResolvesActor trait.

    private function currentShopId(): string
    {
        return (string) auth()->guard('shop')->user()->shop_id;
    }

    private function getCurrentRecruitStatus(string $shopId): int
    {
        $q = DB::table('shop_jobs')->where('shop_id', $shopId);
        $this->scopeShopJobPrimaryRow($q);
        $status = $q->value('status');

        return $status === null ? 1 : (int) $status;
    }

    /**
     * shop_jobs 横持ちスキーマ（1店舗1行）用の求人データ取得
     *
     * @return array{recruit: array, recruit_trial: array, recruit_help: array, shop: array}
     */
    private function getRecruitDataHorizontal(string $shopId): array
    {
        $row = DB::table('shops')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->where('shops.id', $shopId)
            ->select('shops.id', 'shop_profiles.*', 'shop_jobs.*', 'shop_jobs.id as primary_job_id')
            ->first();

        $meta = [];
        if ($row && Schema::hasColumn('shop_jobs', 'noruma_cond') && !empty($row->noruma_cond)) {
            $meta = $this->decodeMeta($row->noruma_cond);
        }

        $industryName = $this->resolveShopIndustryName($shopId, $row?->industry_id ?? null);
        $shopTagGroups = $this->resolveShopInfoTagGroups($shopId);

        $catchCopy = Schema::hasColumn('shop_jobs', 'catch_copy')
            ? (string) ($row->catch_copy ?? '')
            : (string) ($meta['catch_copy'] ?? '');
        $jobContent = Schema::hasColumn('shop_jobs', 'job_content')
            ? (string) ($row->job_content ?? '')
            : (string) ($meta['job_content'] ?? '');

        $regularWage = 0;
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $regularWage = (int) ($row->regular_hourly_wage ?? 0);
        } elseif ($row && isset($row->hourly_wage_regular)) {
            $regularWage = (int) $row->hourly_wage_regular;
        }

        $bonusReward = 0;
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $bonusReward = (int) ($row->bonus_reward ?? 0);
        } elseif ($row && isset($row->noruma_reward)) {
            $bonusReward = (int) $row->noruma_reward;
        }

        $bonusRemarks = Schema::hasColumn('shop_jobs', 'bonus_remarks')
            ? (string) ($row->bonus_remarks ?? '')
            : (string) ($row->noruma_reward2 ?? '');

        $normaDayVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_day') && $row && $row->norma_day !== null) {
            $normaDayVal = (int) $row->norma_day;
        } elseif ($row && Schema::hasColumn('shop_jobs', 'normal_time') && $row->normal_time !== null) {
            $normaDayVal = (int) $row->normal_time;
        }
        $normaHoursVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_hours') && $row && $row->norma_hours !== null) {
            $normaHoursVal = (int) $row->norma_hours;
        } elseif ($row && Schema::hasColumn('shop_jobs', 'hours_day') && $row->hours_day !== null) {
            $normaHoursVal = (int) $row->hours_day;
        }

        $bonusWorkingDays = $normaDayVal !== null ? (string) $normaDayVal : (string) ($meta['bonus_total_working_days'] ?? $meta['bonus_working_days'] ?? '');
        $bonusWorkingHours = $normaHoursVal !== null ? (string) $normaHoursVal : (string) ($meta['bonus_total_working_hours'] ?? $meta['bonus_working_hours'] ?? '');

        $bonusExtraCondition = '';
        if (Schema::hasColumn('shop_jobs', 'bonus_condition') && $row) {
            $bonusExtraCondition = trim((string) ($row->bonus_condition ?? ''));
        }
        if ($bonusExtraCondition === '') {
            $bonusExtraCondition = trim((string) ($meta['bonus_other_conditions'] ?? $meta['bonus_condition'] ?? ''));
        }

        $regStat = Schema::hasColumn('shop_jobs', 'regular_status')
            ? (int) ($row->regular_status ?? 0)
            : ($row ? ((int) ($row->status ?? 0) === 1 ? 1 : 0) : 0);
        $trialStat = Schema::hasColumn('shop_jobs', 'trial_status')
            ? (int) ($row->trial_status ?? 0)
            : 0;
        $helpStat = Schema::hasColumn('shop_jobs', 'help_status')
            ? (int) ($row->help_status ?? 0)
            : 0;

        $workingHours = Schema::hasColumn('shop_jobs', 'working_hours') ? ($row->working_hours ?? '') : ($meta['working_hours'] ?? '');
        $workingDays = Schema::hasColumn('shop_jobs', 'working_day') ? ($row->working_day ?? '') : ($meta['working_days'] ?? '');
        $regularHoliday = Schema::hasColumn('shop_jobs', 'regular_holiday') ? ($row->regular_holiday ?? '') : ($meta['regular_holiday'] ?? '');
        $qualification = Schema::hasColumn('shop_jobs', 'qualification') ? ($row->qualification ?? '') : ($meta['qualification'] ?? '');

        $message = Schema::hasColumn('shop_jobs', 'pr') && $row
            ? (string) ($row->pr ?? '')
            : '';

        $primaryJobId = isset($row->primary_job_id) ? (int) $row->primary_job_id : 0;
        $primaryJobTagIds = $primaryJobId ? $this->getShopJobTagIdsByCategory($primaryJobId) : ['work_style' => [], 'welcome' => [], 'benefit' => []];
        $primaryJobTagNames = $this->resolveShopJobTagNames($primaryJobTagIds);

        $subImages = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('image_path')
            ->map(fn ($path) => $this->assetPathForStored($path))
            ->filter()
            ->values()
            ->all();

        $mainImage = $subImages[0] ?? $this->assetPathForStored($row->main_image_path ?? null);
        if (empty($subImages) && $mainImage) {
            $subImages[] = $mainImage;
        }

        $galleryImages = [];
        if ($mainImage) {
            $galleryImages[] = $mainImage;
        }
        foreach ($subImages as $path) {
            if ($path && !in_array($path, $galleryImages, true)) {
                $galleryImages[] = $path;
            }
        }

        $trialWage = ($row && !empty($row->trial_hourly_wage)) ? (int) $row->trial_hourly_wage : null;
        $helpWage = ($row && !empty($row->help_hourly_wage)) ? (int) $row->help_hourly_wage : null;

        $base = [
            'store_name' => $row->shop_name ?? '店舗',
            'open_date' => !empty($row->opened_on) ? date('Y年n月j日', strtotime($row->opened_on)) : null,
            'address' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null, $this->streetAddressFromProfileRow($row)]))),
            'map_embed_src' => null,
            'nearest_station' => $this->resolveNearestStationForProfile($shopId, $row),
            'hourly_wage_regular' => $regularWage,
            'regular_hourly_wage' => $regularWage,
            'trial_hourly_wage' => $trialWage,
            'help_hourly_wage' => $helpWage,
            'help_job_content' => '',
            'noruma_reward' => $bonusReward,
            'bonus_reward' => $bonusReward,
            'bonus_remarks' => $bonusRemarks,
            'bonus_condition' => $bonusExtraCondition,
            'bonus_other_conditions' => $bonusExtraCondition,
            'bonus_total_working_days' => $bonusWorkingDays,
            'bonus_total_working_hours' => $bonusWorkingHours,
            'bonus_working_days' => $bonusWorkingDays,
            'bonus_working_hours' => $bonusWorkingHours,
            'salary_text' => $row->salary ?? '',
            'working_hours' => $workingHours,
            'working_days' => $workingDays,
            'regular_holiday' => $regularHoliday,
            'job_content' => $jobContent,
            'store_atmosphere' => '',
            'qualification' => $qualification ?: '18歳以上（高校生不可）',
            'catch_copy' => $catchCopy,
            'message' => $message,
            'regular_status' => $regStat,
            'trial_status' => $trialStat,
            'help_status' => $helpStat,
            'selected_benefits' => $primaryJobTagNames['benefit'],
            'store_features' => [
                '働き方・給与'   => $primaryJobTagNames['work_style'],
                '歓迎条件'       => $primaryJobTagNames['welcome'],
                '待遇・サポート' => $primaryJobTagNames['benefit'],
            ],
            'work_style_tag_ids' => $primaryJobTagIds['work_style'],
            'welcome_tag_ids'    => $primaryJobTagIds['welcome'],
            'benefit_tag_ids'    => $primaryJobTagIds['benefit'],
            'status' => $regStat === 1 ? 'active' : 'inactive',
            'updated_at' => $row && !empty($row->updated_at) ? date('Y.m.d', strtotime($row->updated_at)) : null,
        ];
        $base = array_merge($base, $this->shiftAndWageMaxFromShopJobRow($row));
        $base = $this->attachCatchHeroOverlay($base);

        $trialOut = $base;
        $trialOut['status'] = $trialStat === 1 ? 'active' : 'inactive';
        $helpOut = $base;
        $helpOut['status'] = $helpStat === 1 ? 'active' : 'inactive';

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $shopHitokoto = $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '';

        return [
            'recruit' => $base,
            'recruit_trial' => $trialOut,
            'recruit_help' => $helpOut,
            'shop' => [
                'id' => $shopId,
                'name' => $row->shop_name ?? '店舗',
                'word' => $shopHitokoto,
                'main_img' => $mainImage,
                'area' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null]))),
                'concept' => '',
                'review_avg' => 0,
                'review_cnt' => 0,
                'sub_images' => $subImages,
                'gallery_images' => $galleryImages,
                'zip' => $row->zip ?? '',
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'addr1' => $this->streetAddressFromProfileRow($row),
                'industry_name' => $industryName,
                'nearest_station' => $this->resolveNearestStationForProfile($shopId, $row),
                'business_hours_shop' => \App\Support\ShopBusinessHours::formatDisplay(
                    $row->open_time ?? null,
                    isset($row->close_is_last) ? (int) $row->close_is_last : 0,
                    $row->close_time ?? null
                ),
                'tag_groups' => $shopTagGroups,
            ],
        ];
    }

    private function formatTimeHhmm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $s = (string) $value;

        return preg_match('/^(\d{2}:\d{2})/', $s, $m) ? $m[1] : '';
    }

    /**
     * @return array{
     *   regular_hourly_wage_max: int|null,
     *   trial_hourly_wage_max: int|null,
     *   help_hourly_wage_max: int|null,
     *   shift_time_start: string,
     *   shift_time_end: string,
     *   shift_end_is_last: bool
     * }
     */
    private function shiftAndWageMaxFromShopJobRow(?object $row): array
    {
        $out = [
            'regular_hourly_wage_max' => null,
            'trial_hourly_wage_max' => null,
            'help_hourly_wage_max' => null,
            'shift_time_start' => '',
            'shift_time_end' => '',
            'shift_end_is_last' => false,
        ];
        if (!$row) {
            return $out;
        }
        foreach (['regular_hourly_wage_max', 'trial_hourly_wage_max', 'help_hourly_wage_max'] as $c) {
            if (Schema::hasColumn('shop_jobs', $c) && isset($row->{$c}) && $row->{$c} !== null && $row->{$c} !== '') {
                $out[$c] = (int) $row->{$c};
            }
        }
        if (Schema::hasColumn('shop_jobs', 'shift_time_start')) {
            $out['shift_time_start'] = $this->formatTimeHhmm($row->shift_time_start ?? null);
        }
        if (Schema::hasColumn('shop_jobs', 'shift_time_end')) {
            $out['shift_time_end'] = $this->formatTimeHhmm($row->shift_time_end ?? null);
        }
        if (Schema::hasColumn('shop_jobs', 'shift_end_is_last')) {
            $out['shift_end_is_last'] = (bool) (int) ($row->shift_end_is_last ?? 0);
        }

        return $out;
    }

    private function composeWorkingHoursFromShiftRequest(Request $request): string
    {
        $start = trim((string) $request->input('shift_time_start', ''));
        $endLast = $request->boolean('shift_end_is_last');
        $end = trim((string) $request->input('shift_time_end', ''));
        if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
            return '';
        }
        $endPart = $endLast ? 'LAST' : (preg_match('/^\d{2}:\d{2}$/', $end) ? $end : '');

        return trim($start . ' 〜 ' . $endPart);
    }

    private function assertShiftEndValid(Request $request): void
    {
        if ($request->boolean('shift_end_is_last')) {
            return;
        }
        $end = trim((string) $request->input('shift_time_end', ''));
        if ($end === '' || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            throw ValidationException::withMessages([
                'shift_time_end' => '終了時刻を入力するか、LAST を選択してください。',
            ]);
        }
    }

    private function applyShiftColumnsToPatch(array &$patch, Request $request): void
    {
        if (!Schema::hasColumn('shop_jobs', 'shift_time_start')) {
            return;
        }
        $start = trim((string) $request->input('shift_time_start', ''));
        $endLast = $request->boolean('shift_end_is_last') ? 1 : 0;
        $end = trim((string) $request->input('shift_time_end', ''));
        $patch['shift_time_start'] = preg_match('/^\d{2}:\d{2}$/', $start) ? ($start . ':00') : null;
        $patch['shift_end_is_last'] = $endLast;
        $patch['shift_time_end'] = (!$endLast && preg_match('/^\d{2}:\d{2}$/', $end)) ? ($end . ':00') : null;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $data
     */
    private function applyHourlyWageMaxToPatch(array &$patch, array $data): void
    {
        $map = [
            'regular_hourly_wage_max' => 'regular_hourly_wage_max',
            'trial_hourly_wage_max' => 'trial_hourly_wage_max',
            'help_hourly_wage_max' => 'help_hourly_wage_max',
        ];
        foreach ($map as $col => $key) {
            if (!Schema::hasColumn('shop_jobs', $col)) {
                continue;
            }
            $raw = $data[$key] ?? null;
            if ($raw === null || $raw === '') {
                $patch[$col] = null;
            } else {
                $patch[$col] = (string) (int) $raw;
            }
        }
    }

    private function updateVerticalMultiTypes(Request $request)
    {
        $shopId = $this->currentShopId();

        $data = $request->validate([
            'catch_copy' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'job_content' => 'nullable|string|max:2000',
            'noruma_reward' => 'nullable|integer|min:0',
            'bonus_condition' => 'nullable|string|max:1000',
            'bonus_total_working_days' => 'nullable|integer|min:0',
            'bonus_total_working_hours' => 'nullable|integer|min:0',
            'bonus_other_conditions' => 'nullable|string|max:1000',
            'salary_text' => 'nullable|string|max:1000',
            'working_days' => 'required|string|max:255',
            'regular_holiday' => 'nullable|string|max:255',
            'qualification' => 'required|string|max:255',
            'hourly_wage_regular' => 'nullable|integer|min:0',
            'trial_hourly_wage' => 'required|integer|min:0',
            'trial_hourly_wage_max' => 'nullable|integer|min:0',
            'help_hourly_wage' => 'required|integer|min:0',
            'help_hourly_wage_max' => 'nullable|integer|min:0',
            'shift_time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'shift_time_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'shift_end_is_last' => 'nullable|boolean',
            'work_style_tag_ids' => 'nullable|array',
            'work_style_tag_ids.*' => 'integer|exists:shop_tags,id',
            'welcome_tag_ids' => 'nullable|array',
            'welcome_tag_ids.*' => 'integer|exists:shop_tags,id',
            'benefit_tag_ids' => 'nullable|array',
            'benefit_tag_ids.*' => 'integer|exists:shop_tags,id',
        ]);

        $this->assertShiftEndValid($request);
        $data['working_hours'] = $this->composeWorkingHoursFromShiftRequest($request);

        $bonusOther = trim((string) ($request->input('bonus_other_conditions', $data['bonus_condition'] ?? '')));
        $payloadCommon = array_merge($this->getRecruitMeta($shopId), [
            'catch_copy' => $data['catch_copy'],
            'job_content' => trim((string) ($data['job_content'] ?? '')),
            'bonus_condition' => $bonusOther,
            'bonus_total_working_days' => $request->filled('bonus_total_working_days') ? (int) $request->input('bonus_total_working_days') : null,
            'bonus_total_working_hours' => $request->filled('bonus_total_working_hours') ? (int) $request->input('bonus_total_working_hours') : null,
            'bonus_other_conditions' => $bonusOther,
            'working_hours' => $data['working_hours'],
            'working_days' => $data['working_days'],
            'regular_holiday' => $data['regular_holiday'] ?? '',
            'qualification' => $data['qualification'],
        ]);
        unset($payloadCommon['message'], $payloadCommon['tag_ids']);

        $jobTagsPayload = [
            'work_style' => $request->input('work_style_tag_ids', []),
            'welcome'    => $request->input('welcome_tag_ids', []),
            'benefit'    => $request->input('benefit_tag_ids', []),
        ];

        $licenseOk = $this->documentReviewService->shopLicenseFullyApproved($shopId);
        $pubTrialReq = $request->boolean('published_trial');
        $pubHelpReq = $request->boolean('published_help');
        $publishSquashed = !$licenseOk && ($pubTrialReq || $pubHelpReq);

        $payloadTrial = array_merge($this->getRecruitMetaForJobType($shopId, 2), $payloadCommon);
        $jobPayloadTrial = $this->buildJobPayloadFromValidated($request, $shopId, $data, $payloadTrial, $licenseOk && $pubTrialReq);
        $this->applyShiftColumnsToPatch($jobPayloadTrial, $request);
        $this->applyHourlyWageMaxToPatch($jobPayloadTrial, $data);
        $trialJobId = $this->upsertShopJobRow($shopId, 2, $jobPayloadTrial, $data, 'trial');

        $payloadHelp = array_merge($this->getRecruitMetaForJobType($shopId, 3), $payloadCommon);
        $jobPayloadHelp = $this->buildJobPayloadFromValidated($request, $shopId, $data, $payloadHelp, $licenseOk && $pubHelpReq);
        $this->applyShiftColumnsToPatch($jobPayloadHelp, $request);
        $this->applyHourlyWageMaxToPatch($jobPayloadHelp, $data);
        $helpJobId = $this->upsertShopJobRow($shopId, 3, $jobPayloadHelp, $data, 'help');

        $this->syncShopJobTags($trialJobId, $jobTagsPayload);
        $this->syncShopJobTags($helpJobId, $jobTagsPayload);

        $mainSync = [
            'has_trial' => 1,
            'has_help' => 1,
            'trial_hourly_wage' => (string) $data['trial_hourly_wage'],
            'help_hourly_wage' => (string) $data['help_hourly_wage'],
            'updated_at' => now(),
        ];
        if (array_key_exists('hourly_wage_regular', $data) && $data['hourly_wage_regular'] !== null && (int) $data['hourly_wage_regular'] > 0) {
            $mainSync['hourly_wage_regular'] = (string) $data['hourly_wage_regular'];
        }
        if (Schema::hasColumn('shop_jobs', 'trial_hourly_wage_max')) {
            $mainSync['trial_hourly_wage_max'] = isset($data['trial_hourly_wage_max']) && $data['trial_hourly_wage_max'] !== null && $data['trial_hourly_wage_max'] !== ''
                ? (string) (int) $data['trial_hourly_wage_max'] : null;
        }
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage_max')) {
            $mainSync['help_hourly_wage_max'] = isset($data['help_hourly_wage_max']) && $data['help_hourly_wage_max'] !== null && $data['help_hourly_wage_max'] !== ''
                ? (string) (int) $data['help_hourly_wage_max'] : null;
        }
        $this->applyShiftColumnsToPatch($mainSync, $request);
        if (Schema::hasColumn('shop_jobs', 'working_hours')) {
            $mainSync['working_hours'] = $data['working_hours'];
        }
        if (Schema::hasColumn('shop_jobs', 'working_day')) {
            $mainSync['working_day'] = $data['working_days'];
        }

        DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->where('job_type', 1)
            ->update($mainSync);

        $msg = '求人情報を保存しました';
        if ($publishSquashed) {
            $msg .= ' ' . self::MSG_LICENSE_REQUIRED_FOR_PUBLISH . 'そのため体験入店・ヘルプの公開設定はオフにしました。';
        }

        return redirect()
            ->to(route('shop.recruits.edit'))
            ->with('message', $msg);
    }

    private function updateHorizontal(Request $request)
    {
        $usesMulti = $this->shopJobsUseMultipleTypes();

        $wageRules = [
            'regular_hourly_wage' => 'required|integer|min:0',
            'regular_hourly_wage_max' => 'nullable|integer|min:0',
            'trial_hourly_wage' => $usesMulti ? 'nullable|integer|min:0' : 'nullable|integer|min:0',
            'trial_hourly_wage_max' => 'nullable|integer|min:0',
            'help_hourly_wage' => $usesMulti ? 'nullable|integer|min:0' : 'nullable|integer|min:0',
            'help_hourly_wage_max' => 'nullable|integer|min:0',
        ];

        $data = $request->validate(array_merge([
            'catch_copy' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'job_content' => 'nullable|string|max:2000',
            'bonus_reward' => 'nullable|integer|min:0',
            'noruma_reward' => 'nullable|integer|min:0',
            'bonus_condition' => 'nullable|string|max:1000',
            'bonus_total_working_days' => 'nullable|integer|min:0',
            'bonus_total_working_hours' => 'nullable|integer|min:0',
            'bonus_other_conditions' => 'nullable|string|max:1000',
            'bonus_remarks' => 'nullable|string|max:255',
            'salary_text' => 'nullable|string|max:1000',
            'working_days' => 'required|string|max:255',
            'regular_holiday' => 'nullable|string|max:255',
            'qualification' => 'required|string|max:255',
            'shift_time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'shift_time_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'shift_end_is_last' => 'nullable|boolean',
            'work_style_tag_ids' => 'nullable|array',
            'work_style_tag_ids.*' => 'integer|exists:shop_tags,id',
            'welcome_tag_ids' => 'nullable|array',
            'welcome_tag_ids.*' => 'integer|exists:shop_tags,id',
            'benefit_tag_ids' => 'nullable|array',
            'benefit_tag_ids.*' => 'integer|exists:shop_tags,id',
        ], $wageRules));

        $this->assertShiftEndValid($request);
        $data['working_hours'] = $this->composeWorkingHoursFromShiftRequest($request);

        $shopId = $this->currentShopId();
        $bonusAmt = (int) ($data['bonus_reward'] ?? $data['noruma_reward'] ?? 0);
        $normaDay = $request->filled('bonus_total_working_days') ? (int) $data['bonus_total_working_days'] : null;
        $normaHours = $request->filled('bonus_total_working_hours') ? (int) $data['bonus_total_working_hours'] : null;
        $bonusCondText = trim((string) ($request->input('bonus_other_conditions', $data['bonus_condition'] ?? '')));

        $patch = ['updated_at' => now()];

        if (Schema::hasColumn('shop_jobs', 'catch_copy')) {
            $patch['catch_copy'] = $data['catch_copy'];
        }
        if (Schema::hasColumn('shop_jobs', 'job_content')) {
            $patch['job_content'] = (string) ($data['job_content'] ?? '');
        }
        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $patch['pr'] = (string) $data['message'];
        }
        if (Schema::hasColumn('shop_jobs', 'salary')) {
            $patch['salary'] = $data['salary_text'] ?? '';
        }
        if (Schema::hasColumn('shop_jobs', 'working_hours')) {
            $patch['working_hours'] = $data['working_hours'];
        }
        if (Schema::hasColumn('shop_jobs', 'working_day')) {
            $patch['working_day'] = $data['working_days'];
        }
        if (Schema::hasColumn('shop_jobs', 'regular_holiday')) {
            $patch['regular_holiday'] = $data['regular_holiday'] ?? '';
        }
        if (Schema::hasColumn('shop_jobs', 'qualification')) {
            $patch['qualification'] = $data['qualification'];
        }

        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $patch['bonus_reward'] = $bonusAmt;
        } elseif (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
            $patch['noruma_reward'] = (string) $bonusAmt;
        }

        if (Schema::hasColumn('shop_jobs', 'bonus_condition')) {
            $patch['bonus_condition'] = $bonusCondText;
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_remarks') && $request->has('bonus_remarks')) {
            $patch['bonus_remarks'] = (string) ($data['bonus_remarks'] ?? '');
        }
        if (Schema::hasColumn('shop_jobs', 'norma_day')) {
            $patch['norma_day'] = $normaDay;
        } elseif (Schema::hasColumn('shop_jobs', 'normal_time')) {
            $patch['normal_time'] = $normaDay;
        }
        if (Schema::hasColumn('shop_jobs', 'norma_hours')) {
            $patch['norma_hours'] = $normaHours;
        } elseif (Schema::hasColumn('shop_jobs', 'hours_day')) {
            $patch['hours_day'] = $normaHours;
        }

        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $patch['regular_hourly_wage'] = (string) $data['regular_hourly_wage'];
        }
        if (Schema::hasColumn('shop_jobs', 'trial_hourly_wage')) {
            $patch['trial_hourly_wage'] = isset($data['trial_hourly_wage']) && $data['trial_hourly_wage'] !== null && (string) $data['trial_hourly_wage'] !== ''
                ? (string) (int) $data['trial_hourly_wage'] : null;
        }
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage')) {
            $patch['help_hourly_wage'] = isset($data['help_hourly_wage']) && $data['help_hourly_wage'] !== null && (string) $data['help_hourly_wage'] !== ''
                ? (string) (int) $data['help_hourly_wage'] : null;
        }

        $this->applyHourlyWageMaxToPatch($patch, $data);
        $this->applyShiftColumnsToPatch($patch, $request);

        $pubReg = $request->boolean('published_regular');
        $pubTrial = $request->boolean('published_trial');
        $pubHelp = $request->boolean('published_help');
        $licenseOk = $this->documentReviewService->shopLicenseFullyApproved($shopId);
        $publishSquashed = !$licenseOk && ($pubReg || $pubTrial || $pubHelp);

        if (Schema::hasColumn('shop_jobs', 'regular_status')) {
            $patch['regular_status'] = ($licenseOk && $pubReg) ? 1 : 0;
        }
        if (Schema::hasColumn('shop_jobs', 'trial_status')) {
            $patch['trial_status'] = ($licenseOk && $pubTrial) ? 1 : 0;
        }
        if (Schema::hasColumn('shop_jobs', 'help_status')) {
            $patch['help_status'] = ($licenseOk && $pubHelp) ? 1 : 0;
        }

        $existing = DB::table('shop_jobs')->where('shop_id', $shopId)->first();
        if (!$existing) {
            $insert = array_merge($patch, [
                'shop_id' => $shopId,
                'created_at' => now(),
            ]);
            $jobId = (int) DB::table('shop_jobs')->insertGetId($insert);
        } else {
            DB::table('shop_jobs')->where('shop_id', $shopId)->update($patch);
            $jobId = (int) $existing->id;
        }

        $this->syncShopJobTags($jobId, [
            'work_style' => $request->input('work_style_tag_ids', []),
            'welcome'    => $request->input('welcome_tag_ids', []),
            'benefit'    => $request->input('benefit_tag_ids', []),
        ]);

        $msg = '求人情報を保存しました';
        if ($publishSquashed) {
            $msg .= ' ' . self::MSG_LICENSE_REQUIRED_FOR_PUBLISH . 'そのため公開にしていた種別はオフに戻しました。';
        }

        return redirect()
            ->to(route('shop.recruits.edit'))
            ->with('message', $msg);
    }

    /**
     * station1（レガシー）または shop_stations の複数行を「最寄り」表示用に解決する。
     */
    private function resolveNearestStationForProfile(string $shopId, ?object $profileRow): string
    {
        if ($profileRow && !empty($profileRow->station1)) {
            return (string) $profileRow->station1;
        }
        if (!Schema::hasTable('shop_stations')) {
            return '';
        }
        $names = DB::table('shop_stations')
            ->where('shop_id', $shopId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('station_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();

        return $names === [] ? '' : implode(' / ', $names);
    }

    private function resolveShopIndustryName(string $shopId, mixed $fallbackIndustryId = null): ?string
    {
        // industry_label が登録されていれば最優先で採用
        if (Schema::hasColumn('shop_profiles', 'industry_label')) {
            $label = trim((string) DB::table('shop_profiles')
                ->where('shop_id', $shopId)
                ->value('industry_label'));
            if ($label !== '') {
                return $label;
            }
        }

        if (!Schema::hasTable('industries')) {
            return null;
        }

        $names = DB::table('shop_profiles')
            ->join('industries', 'shop_profiles.industry_id', '=', 'industries.id')
            ->where('shop_profiles.shop_id', $shopId)
            ->pluck('industries.name')
            ->filter()
            ->values()
            ->all();

        if ($names === [] && $fallbackIndustryId !== null && $fallbackIndustryId !== '') {
            $name = DB::table('industries')
                ->where('id', (int) $fallbackIndustryId)
                ->value('name');
            if (!empty($name)) {
                $names = [$name];
            }
        }

        if ($names === []) {
            return null;
        }

        return implode(' / ', array_values(array_unique(array_map('strval', $names))));
    }

    private function streetAddressFromProfileRow(?object $row): string
    {
        if (!$row) {
            return '';
        }

        $addr = trim((string) ($row->addr ?? ''));
        $building = trim((string) ($row->building ?? ''));
        if ($addr !== '' || $building !== '') {
            return trim($addr . ' ' . $building);
        }

        return trim((string) ($row->addr2 ?? '') . ' ' . (string) ($row->addr3 ?? ''));
    }

    private function normalizeShopId(string|int $value): string
    {
        return str_starts_with((string) $value, 's')
            ? (string) $value
            : 's' . str_pad((string) $value, 8, '0', STR_PAD_LEFT);
    }

    private function toNumericShopId(string $shopId): ?int
    {
        if (!str_starts_with($shopId, 's')) {
            return is_numeric($shopId) ? (int) $shopId : null;
        }

        return (int) ltrim(substr($shopId, 1), '0');
    }

    private function isKeptByCurrentCast(string $shopId): bool
    {
        if (!auth()->guard('member')->check()) {
            return false;
        }
        if (!Schema::hasTable('favorites')) {
            return false;
        }

        $castId = (string) auth()->guard('member')->id();
        if ($castId === '' || $shopId === '') {
            return false;
        }

        // キャスト発信のキープのみ対象（店舗→キャスト KEEP は別物）
        return DB::table('favorites')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->where('action_type', Favorite::ACTION_KEEP)
            ->where('sender_type', Favorite::SENDER_CAST)
            ->exists();
    }
}
