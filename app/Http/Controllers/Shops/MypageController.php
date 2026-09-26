<?php

namespace App\Http\Controllers\Shops;

use App\Models\AvailabilityDate;
use App\Models\ShopLicenseDocument;
use App\Rules\KouzaMeig;
use App\Services\AvailabilityService;
use App\Services\BillingManagementService;
use App\Services\DocumentReviewService;
use App\Services\GeocodingService;
use App\Services\UserLocationService;
use App\Http\Controllers\Controller;
use App\Support\ShopBusinessHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MypageController extends Controller
{
    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly DocumentReviewService $documentReviewService
    )
    {
    }

    /**
     * 許可証の提出・管理（専用ページ／ライトモード）
     */
    public function documents()
    {
        $shopId = $this->currentShopId();
        $documentData = $this->documentReviewService->getShopLicensePageData($shopId);

        return view('shops.mypage.documents', [
            'pageId' => 'mypage',
            'documents' => $documentData['documents'],
            'allDocumentsApproved' => $documentData['all_approved'],
        ]);
    }

    public function index()
    {
        $shopId = $this->currentShopId();

        $rowQ = DB::table('shops')
            ->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('reviews', 'shops.id', '=', 'reviews.shop_id')
            ->where('shops.id', $shopId)
            ->select(
                'shops.id',
                'shops.status',
                'shops.license_status',
                'shop_profiles.shop_name',
                'shop_profiles.zip',
                'shop_profiles.pref',
                'shop_profiles.city',
                'shop_profiles.industry_id',
                'shop_profiles.updated_at as profile_updated_at',
                DB::raw('AVG(reviews.eva) as avg_eva'),
                DB::raw('COUNT(reviews.id) as review_count')
            )
            ->groupBy(
                'shops.id',
                'shops.status',
                'shops.license_status',
                'shop_profiles.shop_name',
                'shop_profiles.zip',
                'shop_profiles.pref',
                'shop_profiles.city',
                'shop_profiles.industry_id',
                'shop_profiles.updated_at'
            );
        if (Schema::hasColumn('shop_profiles', 'addr')) {
            $rowQ->addSelect('shop_profiles.addr as addr2');
            if (Schema::hasColumn('shop_profiles', 'building')) {
                $rowQ->addSelect('shop_profiles.building as addr3');
            } else {
                $rowQ->addSelect(DB::raw("'' as addr3"));
            }
            $rowQ->groupBy('shop_profiles.addr');
            if (Schema::hasColumn('shop_profiles', 'building')) {
                $rowQ->groupBy('shop_profiles.building');
            }
        } else {
            if (Schema::hasColumn('shop_profiles', 'addr2')) {
                $rowQ->addSelect('shop_profiles.addr2');
                $rowQ->groupBy('shop_profiles.addr2');
            } else {
                $rowQ->addSelect(DB::raw("'' as addr2"));
            }
            if (Schema::hasColumn('shop_profiles', 'addr3')) {
                $rowQ->addSelect('shop_profiles.addr3');
                $rowQ->groupBy('shop_profiles.addr3');
            } else {
                $rowQ->addSelect(DB::raw("'' as addr3"));
            }
        }
        if (Schema::hasColumn('shop_profiles', 'station1')) {
            $rowQ->addSelect('shop_profiles.station1');
            $rowQ->groupBy('shop_profiles.station1');
        } else {
            $rowQ->addSelect(DB::raw("'' as station1"));
        }
        $row = $rowQ->first();

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $hitokotoBody = $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '';
        $hitokotoUpdated = $shopPost && !empty($shopPost->updated_at)
            ? $shopPost->updated_at
            : ($shopPost && !empty($shopPost->created_at) ? $shopPost->created_at : null);

        $documentData = $this->documentReviewService->getShopLicensePageData($shopId);
        $badges = $this->billingManagementService->getShopBadges($shopId);

        $jobIds = DB::table('shop_jobs')->where('shop_id', $shopId)->pluck('id');
        $applicantCount = 0;
        $hiredCount = 0;
        $recruitStatus = '未設定';
        $paymentPendingCount = 0;
        if ($jobIds->isNotEmpty()) {
            $applicantCount = DB::table('shop_job_applications')
                ->whereIn('shop_job_id', $jobIds)
                ->select('cast_id')
                ->groupBy('cast_id')
                ->get()
                ->count();
            $hiredCount = (int) DB::table('shop_job_applications')
                ->whereIn('shop_job_id', $jobIds)
                ->where('status', 4)
                ->count();

            $paymentPendingCount = (int) DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->whereIn('shop_job_applications.shop_job_id', $jobIds)
                ->where('application_deposits.status', 3)
                ->count();
        }

        $jobStatusQ = DB::table('shop_jobs')->where('shop_id', $shopId);
        if (Schema::hasColumn('shop_jobs', 'job_type') && !Schema::hasColumn('shop_jobs', 'regular_status')) {
            $jobStatusQ->where('job_type', 1);
        }
        if (Schema::hasColumn('shop_jobs', 'regular_status')) {
            $jobStatus = $jobStatusQ->value('regular_status');
        } else {
            $jobStatus = $jobStatusQ->value('status');
        }
        if ($jobStatus !== null) {
            $recruitStatus = ((int) $jobStatus) === 1 ? '掲載中' : '掲載停止中';
        }

        $shopData = [
            'shop_name'    => $row->shop_name ?? 'ショップ',
            'view_cnt'     => app(\App\Services\ProfileViewService::class)
                ->countFor(\App\Models\ProfileView::TYPE_SHOP, $shopId),
            'word'         => $hitokotoBody !== '' ? $hitokotoBody : '最高級の空間で、最高の出会いを。',
            'review_avg'   => $row && $row->avg_eva ? round((float)$row->avg_eva, 1) : 0.0,
            'review_count' => $row ? (int)$row->review_count : 0,
            'applicant_count' => $applicantCount,
            'hired_count'  => $hiredCount,
            'pref'         => $row->pref ?? '',
            'city'         => $row->city ?? '',
            'addr1'        => trim(($row->addr2 ?? '') . ' ' . ($row->addr3 ?? '')),
            'overview'     => '',
            'appeal_updated_at' => $hitokotoUpdated
                ? Carbon::parse($hitokotoUpdated)->format('Y/m/d H:i')
                : (!empty($row?->profile_updated_at)
                    ? Carbon::parse($row->profile_updated_at)->format('Y/m/d H:i')
                    : null),
            'approval'     => $documentData['all_approved'] ? 1 : 0,
            'badges'       => [
                'good_payer' => !empty($badges['good_payer']),
            ],
        ];

        $subImages = [];
        $shopImages = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->get();
        foreach ($shopImages as $i => $img) {
            $subImages[] = ['id' => $img->id, 'url' => $this->shopImageUrl($img->image_path)];
        }
        if (empty($subImages)) {
            $subImages = [];
        }

        // 求人票サマリ用に必要なカラムを動的に取得（マルチスキーマ対応）
        $jobCols = ['id', 'working_hours', 'working_day', 'regular_holiday'];
        foreach (['qualification', 'pr', 'job_content', 'catch_copy',
                  'regular_hourly_wage', 'hourly_wage_regular',
                  'trial_hourly_wage', 'help_hourly_wage',
                  'bonus_reward', 'noruma_reward', 'bonus_condition',
                  'regular_status', 'status'] as $optCol) {
            if (Schema::hasColumn('shop_jobs', $optCol)) {
                $jobCols[] = $optCol;
            }
        }
        $jobRowQ = DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->select($jobCols);
        if (Schema::hasColumn('shop_jobs', 'job_type') && !Schema::hasColumn('shop_jobs', 'regular_status')) {
            $jobRowQ->where('job_type', 1);
        }
        $jobRow = $jobRowQ->first();

        // 求人票の表示用サマリ
        $jobSummary = [
            'is_published'    => $recruitStatus === '掲載中',
            'status_label'    => $recruitStatus,
            'catch_copy'      => trim((string) ($jobRow->catch_copy ?? '')),
            'pr_message'      => trim((string) ($jobRow->pr ?? '')),
            'job_content'     => trim((string) ($jobRow->job_content ?? '')),
            'regular_wage'    => (int) ($jobRow->regular_hourly_wage ?? $jobRow->hourly_wage_regular ?? 0),
            'trial_wage'      => (int) ($jobRow->trial_hourly_wage ?? 0),
            'help_wage'       => (int) ($jobRow->help_hourly_wage ?? 0),
            'bonus_reward'    => (int) ($jobRow->bonus_reward ?? $jobRow->noruma_reward ?? 0),
            'bonus_condition' => trim((string) ($jobRow->bonus_condition ?? '')),
            'working_hours'   => trim((string) ($jobRow->working_hours ?? '')),
            'working_days'    => trim((string) ($jobRow->working_day ?? '')),
            'regular_holiday' => trim((string) ($jobRow->regular_holiday ?? '')),
            'qualification'   => trim((string) ($jobRow->qualification ?? '')),
            'applicant_count' => $applicantCount,
            'hired_count'     => $hiredCount,
        ];
        $industryNames = $this->resolveShopIndustryNames($shopId, $row?->industry_id ?? null);
        $shopTagGroups = $this->resolveShopInfoTagGroups($shopId);

        $profileRow = DB::table('shop_profiles')->where('shop_id', $shopId)->first();
        $businessHoursShop = '';
        if ($profileRow && Schema::hasColumn('shop_profiles', 'open_time')) {
            $businessHoursShop = ShopBusinessHours::formatDisplay(
                $profileRow->open_time ?? null,
                (int) ($profileRow->close_is_last ?? 0),
                $profileRow->close_time ?? null
            );
        }
        $stationLines = [];
        if (Schema::hasTable('shop_stations')) {
            $stationLines = DB::table('shop_stations')
                ->where('shop_id', $shopId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('station_name')
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->values()
                ->all();
        }
        $addrStreetOnly = '';
        if ($profileRow && Schema::hasColumn('shop_profiles', 'addr')) {
            $addrStreetOnly = trim(implode(' ', array_filter([
                $profileRow->addr ?? '',
                $profileRow->building ?? '',
            ])));
        }
        if ($addrStreetOnly === '' && $row) {
            $addrStreetOnly = trim(($row->addr2 ?? '') . ' ' . ($row->addr3 ?? ''));
        }

        $searchLocationSettings = app(UserLocationService::class)->loadProfileSettings();

        $availabilityService = app(AvailabilityService::class);
        $availabilityDates = $availabilityService->getDates(AvailabilityDate::OWNER_SHOP, (string) $shopId);

        return view('shops.mypage.index', [
            'pageId'    => 'mypage',
            'shopData'  => $shopData,
            'subImages' => $subImages,
            'documents' => $documentData['documents'],
            'allDocumentsApproved' => $documentData['all_approved'],
            'searchLocationSettings' => $searchLocationSettings,
            'searchLocationDistanceOptions' => UserLocationService::DISTANCE_OPTIONS_KM,
            'availabilityDates' => $availabilityDates,
            'shopInfo' => [
                'shop_name' => $row->shop_name ?? '',
                'word' => $hitokotoBody,
                'industry' => $industryNames === [] ? null : implode(' / ', $industryNames),
                'zip' => $row->zip ?? '',
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'addr1' => $addrStreetOnly,
                'nearest_station' => $stationLines[0] ?? ($row->station1 ?? ''),
                'nearest_stations' => $stationLines,
                'business_hours_shop' => $businessHoursShop,
                'tel' => ($profileRow && Schema::hasColumn('shop_profiles', 'tel')) ? (string) ($profileRow->tel ?? '') : '',
                'working_hours' => $jobRow?->working_hours ?? '',
                'working_days' => $jobRow?->working_day ?? '',
                'regular_holiday' => $jobRow?->regular_holiday ?? '',
                'concept' => '',
                'tag_groups' => $shopTagGroups,
            ],
            'menuData' => [
                'recruit_status' => $recruitStatus,
                'hired_count' => $hiredCount,
                'payment_pending_count' => $paymentPendingCount,
            ],
            'jobSummary' => $jobSummary,
        ]);
    }

    /**
     * 新規登録直後など：許可証2種の提出に誘導する画面
     */
    /**
     * ひとこと（キャッチコピー）をモーダルから更新
     */
    public function updateWord(Request $request)
    {
        $request->validate([
            'word' => 'nullable|string|max:50',
        ]);

        $shopId = $this->currentShopId();
        $word = $request->input('word', '');
        $word = is_string($word) ? trim($word) : '';

        $now = Carbon::now();
        DB::table('shop_posts')->updateOrInsert(
            ['shop_id' => $shopId],
            [
                'body'       => $word,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return response()->json([
            'success' => true,
            'appeal_updated_at' => $now->format('Y/m/d H:i'),
        ]);
    }

    /**
     * 店舗側の振込先口座情報登録
     *
     * 入力された口座情報は BillingManagementService::saveShopBankAccount() を経由して
     * bank_accounts テーブル（holder_type = shop）に永続化される。
     * 請求書発行・入金確認フローで参照される。
     */
    public function updateBank(Request $request)
    {
        $request->merge(
            $this->billingManagementService->normalizeBankAccountData($request->all())
        );

        $request->validate([
            'bank_code'      => ['required', 'regex:/^\d{4}$/'],
            'bank_name'      => 'required|string|max:100',
            'branch_code'    => ['required', 'regex:/^\d{3}$/'],
            'branch_name'    => 'required|string|max:100',
            'account_type'   => 'required|in:ordinary,current',
            'account_number' => ['required', 'regex:/^\d{7,8}$/'],
            'account_name'   => ['required', 'string', 'max:100', new KouzaMeig()],
        ], [
            'bank_code.required' => '金融機関を候補から選択してください。',
            'bank_code.regex' => '金融機関コードが不正です。',
            'branch_code.required' => '支店を候補から選択してください。',
            'branch_code.regex' => '支店コードが不正です。',
            'account_number.required' => '口座番号を入力してください。',
            'account_number.regex' => '口座番号は7桁または8桁の数字で入力してください。',
            'account_name.required' => '口座名義（カナ）を入力してください。',
        ]);

        $this->billingManagementService->saveShopBankAccount($this->currentShopId(), $request->only([
            'bank_code',
            'bank_name',
            'bank_name_kana',
            'branch_code',
            'branch_name',
            'branch_name_kana',
            'account_type',
            'account_number',
            'account_name',
        ]));

        return response()->json([
            'success' => true,
            'message' => '店舗口座情報を保存しました。',
        ]);
    }

    /**
     * 自店の許可証ファイルを閲覧（ログイン店舗のみ・storage 直 URL に依存しない）
     */
    public function viewLicenseDocument(string $type)
    {
        if (!in_array($type, ['business', 'entertainment'], true)) {
            abort(404);
        }

        $shopId = $this->currentShopId();
        $document = ShopLicenseDocument::query()
            ->where('shop_id', $shopId)
            ->where('type', $type)
            ->first();

        if (!$document || empty($document->image_path)) {
            abort(404, '書類が見つかりません。');
        }

        // 新形式 (private/...) と旧形式 (public/...) の両方に対応する
        $resolved = $this->documentReviewService->resolveDocumentDiskPath($document->image_path);
        if ($resolved === null) {
            abort(404, 'ファイルが見つかりません。');
        }
        [$disk, $relative] = $resolved;
        if (!Storage::disk($disk)->exists($relative)) {
            abort(404, 'ファイルが見つかりません。');
        }

        $absolute = Storage::disk($disk)->path($relative);
        $mime = @mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode(basename($relative)),
        ]);
    }

    /**
     * 営業許可証・風営許可証のアップロード
     *
     * 提出ファイルは storage に保存し、shop_license_documents テーブルに status=pending で登録される。
     * 承認/差戻しは運営画面（Admin\VerificationController）で行う。
     * 承認済みドキュメントは取り下げないと差し替え不可。
     */
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:business,entertainment',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'expired_at' => 'nullable|date|after_or_equal:today',
        ], [
            'expired_at.date' => '有効期限は日付形式で入力してください。',
            'expired_at.after_or_equal' => '有効期限には本日以降の日付を入力してください。',
        ]);

        if ($request->hasFile('file')) {
            $type = (string) $request->input('type');
            $current = ShopLicenseDocument::query()
                ->where('shop_id', $this->currentShopId())
                ->where('type', $type)
                ->first();
            // 承認済みは差し替え不可（先に取り下げが必要）
            if ($current && (int) $current->status === ShopLicenseDocument::STATUS_APPROVED) {
                if (!$request->expectsJson()) {
                    return redirect()
                        ->route('shop.mypage.index')
                        ->withErrors(['file' => '承認済みのため差し替えできません。先に「提出取り下げ」を行ってください。']);
                }

                return response()->json([
                    'success' => false,
                    'message' => '承認済みのため差し替えできません。先に「提出取り下げ」を行ってください。',
                ], 422);
            }

            $document = $this->documentReviewService->uploadShopLicenseDocument(
                $this->currentShopId(),
                $type,
                $request->file('file'),
                $request->input('expired_at')
            );

            if (!$request->expectsJson()) {
                return redirect()
                    ->route('shop.mypage.index')
                    ->with('message', '書類をアップロードしました。内容を確認して「提出」を押してください。');
            }

            return response()->json([
                'success' => true,
                'message' => '書類をアップロードしました。内容を確認して「提出」を押してください。',
                'type' => $type,
                'view_url' => route('shop.mypage.documents.show', ['type' => $type]),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'ファイルが選択されていません。',
        ], 400);
    }

    public function requestDocumentReview(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:business,entertainment',
            'expired_at' => 'nullable|date|required_if:type,business|after_or_equal:today',
        ], [
            'expired_at.required_if' => '飲食店営業許可書の有効期限を入力してください。',
            'expired_at.date' => '有効期限は日付形式で入力してください。',
            'expired_at.after_or_equal' => '有効期限には本日以降の日付を入力してください。',
        ]);

        try {
            $this->documentReviewService->requestShopDocumentReview(
                $this->currentShopId(),
                (string) $data['type'],
                isset($data['expired_at']) ? (string) $data['expired_at'] : null
            );
        } catch (\RuntimeException $e) {
            if (!$request->expectsJson()) {
                return redirect()
                    ->route('shop.mypage.index')
                    ->withErrors(['document' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (!$request->expectsJson()) {
            return redirect()
                ->route('shop.mypage.index')
                ->with('message', '提出が完了しました。運営の審査をお待ちください。');
        }

        return response()->json([
            'success' => true,
            'message' => '提出が完了しました。運営の審査をお待ちください。',
        ]);
    }

    public function withdrawDocumentReview(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:business,entertainment',
        ]);

        try {
            $this->documentReviewService->withdrawShopDocumentReview($this->currentShopId(), (string) $data['type']);
        } catch (\RuntimeException $e) {
            if (!$request->expectsJson()) {
                return redirect()
                    ->route('shop.mypage.index')
                    ->withErrors(['document' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (!$request->expectsJson()) {
            return redirect()
                ->route('shop.mypage.index')
                ->with('message', '提出を取り下げました。ファイルを再アップロードしてから審査依頼してください。');
        }

        return response()->json([
            'success' => true,
            'message' => '提出を取り下げました。ファイルを再アップロードしてから審査依頼してください。',
        ]);
    }

    /**
     * 店舗側：ノルマ達成・店舗審査完了
     */
    public function approveDeposit(Request $request)
    {
        $result = $this->billingManagementService->confirmDepositForShop(
            $this->currentShopId(),
            $request->all()
        );

        return redirect()
            ->route('shop.mypage.management', ['tab' => 'payment'])
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 店舗側：運営へ入金完了
     */
    public function payToPlatform(Request $request)
    {
        $payload = $request->validate([
            'reported_amount' => 'required|integer|min:1',
            'reported_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
        ]);

        $result = $this->billingManagementService->reportShopPayment($this->currentShopId(), $payload);

        return redirect()
            ->route('shop.mypage.management', ['tab' => 'payment'])
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * ログイン中店舗に紐づく直近の application_deposits.id を取得
     */
    private function getLatestDepositIdForShop(): ?int
    {
        $row = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->where('shop_jobs.shop_id', $this->currentShopId())
            ->orderByDesc('application_deposits.id')
            ->value('application_deposits.id');

        return $row ? (int)$row : null;
    }

    /**
     * shop_job_applications.status をラベル・タグ・次アクションに変換
     * 1:やり取り中, 2:面談日調整中, 3:面談日決定, 4:採用, 5:不採用
     */
    private function mapApplicationStatus(int $status): array
    {
        return match ($status) {
            1 => ['label' => 'やり取り中',       'tag' => 'in_progress',     'next' => 'メッセージで日程調整'],
            2 => ['label' => '面談日調整中',     'tag' => 'interview_pending', 'next' => '候補日の返信待ち'],
            3 => ['label' => '面談日決定',       'tag' => 'interview_fixed',  'next' => '当日の来店フォロー'],
            4 => ['label' => '採用',             'tag' => 'hired',           'next' => '入店手続き'],
            5 => ['label' => '不採用',           'tag' => 'rejected',        'next' => '—'],
            default => ['label' => 'やり取り中', 'tag' => 'in_progress',     'next' => '—'],
        };
    }

    /**
     * application_deposits.status から3者分のステータスを構成（キャスト側と同一マッピング）
     */
    private function buildDepositFlowStateFromDb($deposit): array
    {
        if (!$deposit) {
            return ['cast' => '未申請', 'shop' => '未稼働', 'admin' => '未稼働'];
        }

        $status = (int)$deposit->status;

        return match ($status) {
            1 => ['cast' => '申請中', 'shop' => '承認待ち', 'admin' => '提出待ち'],
            2 => ['cast' => '申請中', 'shop' => '店舗確認中', 'admin' => '請求待ち'],
            3 => ['cast' => 'お振込準備中', 'shop' => 'お支払い待ち', 'admin' => '店舗へ請求中'],
            4 => ['cast' => 'お振込準備中', 'shop' => '入金報告済', 'admin' => '店舗入金確認中'],
            5 => ['cast' => 'お振込準備中', 'shop' => 'お支払い完了', 'admin' => '店舗入金確認済'],
            6 => ['cast' => 'お振込手続き中', 'shop' => 'お支払い完了', 'admin' => 'キャスト振込済'],
            7 => ['cast' => '完了', 'shop' => '完了', 'admin' => '完了'],
            default => ['cast' => '未申請', 'shop' => '未稼働', 'admin' => '未稼働'],
        };
    }

    /** 店舗画像パスを表示用URLに変換（uploads/ または storage 対応） */
    private function shopImageUrl(?string $path): string
    {
        if (empty($path)) {
            return asset('assets/images/common/no-image.png');
        }
        if (str_starts_with($path, 'uploads/')) {
            return asset($path);
        }
        if (str_starts_with($path, 'public/')) {
            return asset('storage/' . substr($path, 7));
        }
        return asset(ltrim($path, '/'));
    }

    private function currentShopId(): string
    {
        return (string) auth()->guard('shop')->user()->shop_id;
    }

    /**
     * 店舗プロフィールに紐づく shop_tag_relations を、新スキーマ (shop_tags target=shop) で
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
            $names = $this->fetchShopTagNames($shopId, $def['category']);
            if (!empty($names)) {
                $groups[] = ['label' => $def['label'], 'tags' => $names];
            }
        }

        return $groups;
    }

    /**
     * shop_tag_relations -> shop_tags(target='shop') から名前のみ取得する。
     */
    private function fetchShopTagNames(string $shopId, string $category): array
    {
        return DB::table('shop_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_id', $shopId)
            ->where('r.tag_type', $category)
            ->where('t.target', 'shop')
            ->where('t.category', $category)
            ->where('t.del_flg', 0)
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->pluck('t.name')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveShopIndustryNames(string $shopId, $fallbackIndustryId = null): array
    {
        // industry_label が登録されていれば最優先で採用
        if (Schema::hasColumn('shop_profiles', 'industry_label')) {
            $label = trim((string) DB::table('shop_profiles')
                ->where('shop_id', $shopId)
                ->value('industry_label'));
            if ($label !== '') {
                return [$label];
            }
        }

        $names = DB::table('shop_profiles')
            ->join('industries', 'shop_profiles.industry_id', '=', 'industries.id')
            ->where('shop_profiles.shop_id', $shopId)
            ->pluck('industries.name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
        if ($names !== []) {
            return $names;
        }

        $single = (int) ($fallbackIndustryId ?? 0);
        if ($single > 0) {
            $name = DB::table('industries')->where('id', $single)->value('name');
            return $name ? [trim((string) $name)] : [];
        }

        return [];
    }

    /**
     * 位置情報フィルタ（住所/パスポート/現在地 + 半径）の保存（店舗側）
     */
    public function updateSearchLocation(Request $request, UserLocationService $userLocation, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:profile,passport,current'],
            'passport_address' => ['nullable', 'string', 'max:255'],
            'passport_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'passport_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'max_distance_km' => ['nullable', 'integer', 'in:0,1,3,5,10,20,30,50,100'],
        ]);

        $payload = [
            'mode' => $data['mode'],
            'max_distance_km' => $data['max_distance_km'] ?? 0,
        ];

        if ($data['mode'] === 'passport') {
            $address = trim((string) ($data['passport_address'] ?? ''));
            $lat = isset($data['passport_lat']) ? (float) $data['passport_lat'] : null;
            $lng = isset($data['passport_lng']) ? (float) $data['passport_lng'] : null;
            if (($lat === null || $lng === null) && $address !== '') {
                $coords = $geocoding->fromAddress($address);
                if (!$coords) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定の住所から緯度経度を取得できませんでした。別の表現で試してください。',
                    ], 422);
                }
                $lat = (float) $coords['latitude'];
                $lng = (float) $coords['longitude'];
            }
            if ($lat === null || $lng === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'パスポートモードでは住所または緯度経度が必要です。',
                ], 422);
            }
            $payload['passport_address'] = $address !== '' ? $address : null;
            $payload['passport_latitude'] = $lat;
            $payload['passport_longitude'] = $lng;
            $payload['passport_label'] = $address !== '' ? $address : '指定位置';
        }

        if ($data['mode'] === 'current') {
            $lat = isset($data['current_lat']) ? (float) $data['current_lat'] : null;
            $lng = isset($data['current_lng']) ? (float) $data['current_lng'] : null;
            if ($lat !== null && $lng !== null) {
                $userLocation->setManualLocation(UserLocationService::MODE_CURRENT, $lat, $lng, '現在地');
            }
        }

        // プロフィール住所モード：緯度経度が未取得なら住所からジオコーディングして latitude/longitude を埋める。
        if ($data['mode'] === 'profile') {
            $current = $userLocation->loadProfileSettings();
            if ($current && empty($current['profile_location']) && !empty($current['has_address'])) {
                $userLocation->geocodeAndSaveProfileLocation($geocoding);
            }
        }

        $userLocation->saveSearchSettings($payload);

        return response()->json([
            'success' => true,
            'settings' => $userLocation->loadProfileSettings(),
        ]);
    }
}