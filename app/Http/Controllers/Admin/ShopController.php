<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopPlanSubscription;
use App\Services\AdminOperationLogService;
use App\Services\AdminPrivateAccessService;
use App\Services\BillingManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShopController extends Controller
{
    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly AdminPrivateAccessService $privateAccessService,
        private readonly AdminOperationLogService $opLog,
    ) {
    }

    /**
     * 店舗管理一覧
     */
    public function index()
    {
        $operationSummaries = $this->billingManagementService->getOperationSummaryByEntity('shop');
        $horizontal = Schema::hasTable('shop_jobs') && Schema::hasColumn('shop_jobs', 'regular_status');

        $hasApprovalColumn = Schema::hasColumn('shops', 'approval');
        $managerLastLoginSub = DB::table('shop_managers')
            ->selectRaw('shop_id, MAX(last_login_at) as last_login_at')
            ->groupBy('shop_id');

        $query = DB::table('shops')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->leftJoinSub($managerLastLoginSub, 'manager_login', function ($join) {
                $join->on('shops.id', '=', 'manager_login.shop_id');
            })
            ->select(
                'shops.id',
                'shops.created_at',
                'shops.updated_at',
                'shops.status as shop_account_status',
                'shop_profiles.shop_name',
                'manager_login.last_login_at as latest_manager_login_at',
            );

        if ($hasApprovalColumn) {
            $query->addSelect('shops.approval');
        }

        if ($horizontal) {
            $query->addSelect(
                'shop_jobs.regular_status',
                'shop_jobs.trial_status',
                'shop_jobs.help_status'
            );
            if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
                $query->addSelect('shop_jobs.regular_hourly_wage as job_hourly_wage');
            } elseif (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
                $query->addSelect('shop_jobs.hourly_wage_regular as job_hourly_wage');
            }
        } else {
            if (Schema::hasColumn('shop_jobs', 'status')) {
                $query->addSelect('shop_jobs.status as recruit_status');
            }
            if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
                $query->addSelect('shop_jobs.hourly_wage_regular as job_hourly_wage');
            }
        }

        // 書類確認状況：shops.approval 列は現行スキーマに存在しないため、
        // shop_license_documents（全書類が承認済みか）から導出する。
        // approval 列があるスキーマではそちらを優先（マルチスキーマ対応）。
        $licenseApprovedByShop = [];
        if (Schema::hasTable('shop_license_documents')) {
            DB::table('shop_license_documents')
                ->select('shop_id', 'status')
                ->get()
                ->groupBy('shop_id')
                ->each(function ($rows, $sid) use (&$licenseApprovedByShop) {
                    $licenseApprovedByShop[(string) $sid] = $rows->isNotEmpty()
                        && $rows->every(fn ($r) => (int) $r->status === 2);
                });
        }

        // プラン契約状況：店舗ごとに最も関連度の高い1件を選ぶ（有効 > 入金待ち > 期間満了 > キャンセル）
        $subsByShop = [];
        if (Schema::hasTable('shop_plan_subscriptions')) {
            $priority = [
                ShopPlanSubscription::STATUS_ACTIVE          => 0,
                ShopPlanSubscription::STATUS_PENDING_PAYMENT => 1,
                ShopPlanSubscription::STATUS_EXPIRED         => 2,
                ShopPlanSubscription::STATUS_CANCELED        => 3,
            ];
            foreach (DB::table('shop_plan_subscriptions')->get() as $r) {
                $key = (string) $r->shop_id;
                $newP = $priority[(int) $r->status] ?? 99;
                $curP = isset($subsByShop[$key]) ? ($priority[(int) $subsByShop[$key]->status] ?? 99) : 99;
                if (!isset($subsByShop[$key]) || $newP < $curP) {
                    $subsByShop[$key] = $r;
                }
            }
        }

        $shops = $query
            ->orderByDesc('shops.created_at')
            ->get()
            ->map(function ($shop) use ($operationSummaries, $horizontal, $hasApprovalColumn, $licenseApprovedByShop, $subsByShop) {
                $shopId = (string) $shop->id;

                if ($horizontal) {
                    $reg = (int) ($shop->regular_status ?? 0);
                    $jobStatusLabel = sprintf(
                        '本入:%s / 体験:%s / ヘルプ:%s',
                        $reg === 1 ? '公開' : '非公開',
                        (int) ($shop->trial_status ?? 0) === 1 ? '公開' : '非公開',
                        (int) ($shop->help_status ?? 0) === 1 ? '公開' : '非公開'
                    );
                    $jobStatusKey = $reg === 1 ? 'active' : 'inactive';
                    $adminRecruitToggles = [
                        [
                            'job_type' => 1,
                            'label' => '本入',
                            'is_on' => $reg === 1,
                        ],
                        [
                            'job_type' => 2,
                            'label' => '体験',
                            'is_on' => (int) ($shop->trial_status ?? 0) === 1,
                        ],
                        [
                            'job_type' => 3,
                            'label' => 'ヘルプ',
                            'is_on' => (int) ($shop->help_status ?? 0) === 1,
                        ],
                    ];
                } else {
                    $st = (int) ($shop->recruit_status ?? 0);
                    $jobStatusLabel = $st === 1 ? '公開中' : '非公開';
                    $jobStatusKey = $st === 1 ? 'active' : 'inactive';
                    $adminRecruitToggles = [
                        [
                            'job_type' => 1,
                            'label' => '求人',
                            'is_on' => $st === 1,
                        ],
                    ];
                }

                $sub = $subsByShop[$shopId] ?? null;
                $planInfo = null;
                if ($sub) {
                    $cycleLabel = ($sub->billing_cycle ?? '') === ShopPlanSubscription::CYCLE_YEARLY ? '年払い' : '月払い';
                    $planName = strtoupper((string) ($sub->plan ?? 'premium'));
                    $planInfo = [
                        'status'     => (int) $sub->status,
                        'plan'       => $sub->plan,
                        'cycle'      => $sub->billing_cycle,
                        'label'      => $planName . '（' . $cycleLabel . '）',
                        'starts_at'  => $sub->starts_at,
                        'ends_at'    => $sub->ends_at,
                        'paid_at'    => $sub->paid_confirmed_at,
                    ];
                }

                return [
                    'id' => $shop->id,
                    'name' => $shop->shop_name ?: '未設定',
                    'plan' => '未設定',
                    'fee' => 0,
                    'published_at' => $shop->created_at,
                    'registered_at' => $shop->created_at,
                    'last_login_at' => $shop->latest_manager_login_at ?? null,
                    'account_status' => (int) ($shop->shop_account_status ?? 0),
                    'document_status' => ($hasApprovalColumn
                            ? ((int) ($shop->approval ?? 0)) === 1
                            : ($licenseApprovedByShop[$shopId] ?? false))
                        ? '確認済み' : '未確認',
                    'job_status' => $jobStatusLabel,
                    'job_status_key' => $jobStatusKey,
                    'hourly_wage_regular' => (int) ($shop->job_hourly_wage ?? 0),
                    'recruit_schema_horizontal' => $horizontal,
                    'admin_recruit_toggles' => $adminRecruitToggles,
                    'operation_summary' => $operationSummaries[$shopId] ?? null,
                    'plan_info' => $planInfo,
                ];
            });

        return view('admin.shops.index', [
            'shops' => $shops,
        ]);
    }

    /**
     * 店舗詳細（公開情報＋運用実績は常時表示、非公開情報はパスワード解除制）
     */
    public function show(string $shopId)
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        abort_unless($shop, 404);

        $profile = DB::table('shop_profiles')->where('shop_id', $shopId)->first();
        $job = DB::table('shop_jobs')->where('shop_id', $shopId)->first();
        $managers = DB::table('shop_managers')
            ->where('shop_id', $shopId)
            ->orderByDesc('last_login_at')
            ->get();
        $bank = DB::table('bank_accounts')
            ->where('holder_type', 'shops')
            ->where('holder_id', $shopId)
            ->first();

        // 取引履歴
        $applicationDeposits = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->leftJoin('cast_profiles', 'shop_job_applications.cast_id', '=', 'cast_profiles.cast_id')
            ->where('shop_jobs.shop_id', $shopId)
            ->orderByDesc('application_deposits.created_at')
            ->get([
                'application_deposits.id',
                'application_deposits.status',
                'application_deposits.invoice_number',
                'application_deposits.invoice_amount',
                'application_deposits.bonus_amount',
                'application_deposits.invoice_issued_at',
                'application_deposits.shop_payment_confirmed_at',
                'application_deposits.completed_at',
                'shop_job_applications.cast_id',
                'cast_profiles.nickname as cast_nickname',
            ]);

        $operationSummary = $this->billingManagementService->getOperationSummaryByEntity('shop')[$shopId] ?? null;

        $isUnlocked = $this->privateAccessService->isUnlocked('shop', $shopId);
        $unlockTtl = $this->privateAccessService->unlockedSecondsRemaining('shop', $shopId);

        return view('admin.shops.show', [
            'shopId' => $shopId,
            'shop' => $shop,
            'profile' => $profile,
            'job' => $job,
            'managers' => $managers,
            'bank' => $bank,
            'applicationDeposits' => $applicationDeposits,
            'operationSummary' => $operationSummary,
            'isUnlocked' => $isUnlocked,
            'unlockTtlSeconds' => $unlockTtl,
            'totalBilled' => (int) DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
                ->where('shop_jobs.shop_id', $shopId)
                ->whereNotNull('application_deposits.invoice_amount')
                ->sum('application_deposits.invoice_amount'),
            'displayName' => $profile->shop_name ?? '未設定',
        ]);
    }

    public function unlockPrivate(Request $request, string $shopId): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $ok = $this->privateAccessService->unlockWithPassword('shop', $shopId, (string) $request->input('password'));
        if (!$ok) {
            $this->opLog->record('shop.private_unlock.fail', 'shop', $shopId, 'パスワード認証失敗');
            return redirect()->route('admin.shops.show', $shopId)
                ->with('private_unlock_error', '管理者パスワードが一致しません。');
        }
        $this->opLog->record('shop.private_unlock', 'shop', $shopId, '店舗非公開情報の解除');
        return redirect()->route('admin.shops.show', $shopId)
            ->with('status', '非公開情報を解除しました（' . (int) ($this->privateAccessService->ttlSeconds() / 60) . '分間有効）。');
    }

    public function lockPrivate(string $shopId): RedirectResponse
    {
        $this->privateAccessService->lock('shop', $shopId);
        return redirect()->route('admin.shops.show', $shopId)
            ->with('status', '非公開情報を再度ロックしました。');
    }

    /**
     * 店舗アカウントを停止（status = 2）。傘下の shop_managers も無効化。
     */
    public function suspend(Request $request, string $shopId): RedirectResponse
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        abort_unless($shop, 404);

        DB::table('shops')->where('id', $shopId)->update([
            'status' => 2,
            'updated_at' => now(),
        ]);
        // 紐づく管理者ログインも無効化（status = 0）
        DB::table('shop_managers')->where('shop_id', $shopId)->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
        $this->opLog->record('shop.suspend', 'shop', $shopId, '店舗停止: ' . $shopId);

        $redirect = $request->input('redirect_to') === 'show'
            ? redirect()->route('admin.shops.show', $shopId)
            : redirect()->route('admin.shops.index');
        return $redirect->with('status', '店舗アカウントを停止しました。');
    }

    /**
     * 店舗アカウントの停止を解除（status = 1）。傘下の shop_managers も再有効化。
     */
    public function unsuspend(Request $request, string $shopId): RedirectResponse
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        abort_unless($shop, 404);

        DB::table('shops')->where('id', $shopId)->update([
            'status' => 1,
            'updated_at' => now(),
        ]);
        DB::table('shop_managers')->where('shop_id', $shopId)->update([
            'status' => 1,
            'updated_at' => now(),
        ]);
        $this->opLog->record('shop.unsuspend', 'shop', $shopId, '店舗停止解除: ' . $shopId);

        $redirect = $request->input('redirect_to') === 'show'
            ? redirect()->route('admin.shops.show', $shopId)
            : redirect()->route('admin.shops.index');
        return $redirect->with('status', '店舗アカウントの停止を解除しました。');
    }

}
