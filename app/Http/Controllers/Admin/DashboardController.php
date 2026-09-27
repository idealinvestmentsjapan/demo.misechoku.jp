<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminOperationalSummaryService;
use App\Services\BillingManagementService;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly DocumentReviewService $documentReviewService,
        private readonly AdminOperationalSummaryService $adminOperationalSummaryService
    ) {
    }

    /** 管理者ダッシュボード */
    public function index()
    {
        $now = Carbon::now();
        $startThisMonth = $now->copy()->startOfMonth();
        $startPrevMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endPrevMonth = $startThisMonth->copy()->subSecond();

        // ---- 登録KPI（キャスト・店舗の総数と当月増分）
        $castTotal = Schema::hasTable('casts')
            ? (int) DB::table('casts')->count()
            : 0;
        $castThisMonthNew = Schema::hasTable('casts')
            ? (int) DB::table('casts')->whereBetween('created_at', [$startThisMonth, $now])->count()
            : 0;
        $castPrevMonthNew = Schema::hasTable('casts')
            ? (int) DB::table('casts')->whereBetween('created_at', [$startPrevMonth, $endPrevMonth])->count()
            : 0;

        $shopTotal = Schema::hasTable('shops')
            ? (int) DB::table('shops')->count()
            : 0;
        $shopThisMonthNew = Schema::hasTable('shops')
            ? (int) DB::table('shops')->whereBetween('created_at', [$startThisMonth, $now])->count()
            : 0;
        $shopPrevMonthNew = Schema::hasTable('shops')
            ? (int) DB::table('shops')->whereBetween('created_at', [$startPrevMonth, $endPrevMonth])->count()
            : 0;

        // 優良店（プレミアム）：直近3ヶ月のapplication_depositsがすべてstatus>=5かつ少なくとも1件成立
        $premiumShopCount = $this->countPremiumShops();

        // ---- 取引KPI
        $hasDepositAmountColumn = Schema::hasTable('application_deposits') && Schema::hasColumn('application_deposits', 'invoice_amount');
        $trxCountThisMonth = 0;
        $trxCountPrevMonth = 0;
        $trxAmountThisMonth = 0;
        $trxAmountPrevMonth = 0;
        if (Schema::hasTable('application_deposits')) {
            $trxCountThisMonth = (int) DB::table('application_deposits')
                ->whereBetween('created_at', [$startThisMonth, $now])
                ->count();
            $trxCountPrevMonth = (int) DB::table('application_deposits')
                ->whereBetween('created_at', [$startPrevMonth, $endPrevMonth])
                ->count();
            if ($hasDepositAmountColumn) {
                $trxAmountThisMonth = (int) DB::table('application_deposits')
                    ->whereBetween('created_at', [$startThisMonth, $now])
                    ->sum('invoice_amount');
                $trxAmountPrevMonth = (int) DB::table('application_deposits')
                    ->whereBetween('created_at', [$startPrevMonth, $endPrevMonth])
                    ->sum('invoice_amount');
            }
        }

        $registrationKpis = [
            [
                'id' => 'cast',
                'title' => '登録キャスト',
                'value' => number_format($castTotal),
                'unit' => '名',
                'trend_label' => $this->signedDelta($castThisMonthNew - $castPrevMonthNew),
                'trend_caption' => '今月の新規',
                'is_up' => ($castThisMonthNew - $castPrevMonthNew) >= 0,
                'icon' => 'fa-users',
                'href' => route('admin.casts.index'),
            ],
            [
                'id' => 'shop',
                'title' => '登録店舗',
                'value' => number_format($shopTotal),
                'unit' => '店',
                'sub_value' => number_format($premiumShopCount),
                'sub_label' => 'プレミアム',
                'trend_label' => $this->signedDelta($shopThisMonthNew - $shopPrevMonthNew),
                'trend_caption' => '今月の新規',
                'is_up' => ($shopThisMonthNew - $shopPrevMonthNew) >= 0,
                'icon' => 'fa-building',
                'href' => route('admin.shops.index'),
            ],
        ];

        $trxAmountDeltaPct = $trxAmountPrevMonth > 0
            ? (($trxAmountThisMonth - $trxAmountPrevMonth) / $trxAmountPrevMonth) * 100
            : null;

        $transactionKpis = [
            [
                'id' => 'trx_count',
                'title' => '今月の取引件数',
                'value' => number_format($trxCountThisMonth),
                'unit' => '件',
                'trend_label' => $this->signedDelta($trxCountThisMonth - $trxCountPrevMonth),
                'trend_caption' => '前月比',
                'is_up' => ($trxCountThisMonth - $trxCountPrevMonth) >= 0,
                'icon' => 'fa-chart-line',
                'href' => route('admin.sales.index'),
            ],
            [
                'id' => 'trx_amount',
                'title' => '今月の取引総額（GMV）',
                'value' => number_format($trxAmountThisMonth / 1000000, 2),
                'unit' => 'M円',
                'trend_label' => $trxAmountDeltaPct === null
                    ? '—'
                    : $this->signedPct($trxAmountDeltaPct),
                'trend_caption' => '前月比',
                'is_up' => ($trxAmountThisMonth - $trxAmountPrevMonth) >= 0,
                'icon' => 'fa-yen-sign',
                'href' => route('admin.sales.index'),
            ],
        ];

        // ---- チャートデータ（過去7ヶ月の月別新規登録数 / 取引件数 / 取引金額）
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $castNew = Schema::hasTable('casts')
                ? (int) DB::table('casts')->whereBetween('created_at', [$monthStart, $monthEnd])->count()
                : 0;
            $shopNew = Schema::hasTable('shops')
                ? (int) DB::table('shops')->whereBetween('created_at', [$monthStart, $monthEnd])->count()
                : 0;
            $monthTrxCount = Schema::hasTable('application_deposits')
                ? (int) DB::table('application_deposits')->whereBetween('created_at', [$monthStart, $monthEnd])->count()
                : 0;
            $monthTrxAmount = $hasDepositAmountColumn
                ? (int) DB::table('application_deposits')->whereBetween('created_at', [$monthStart, $monthEnd])->sum('invoice_amount')
                : 0;

            $chartData[] = [
                'month' => $monthStart->format('n月'),
                'cast_new' => $castNew,
                'shop_new' => $shopNew,
                'count' => $monthTrxCount,
                'amount' => round($monthTrxAmount / 1000000, 2),
            ];
        }

        // サイドバーの未対応バッジ（AdminOperationalSummaryService）を単一の集計元とする。
        // これによりダッシュボードの未済タスク合計と、サイドバーの各バッジ合計が完全一致する。
        // ラインナップはサイドバー「オペレーション」グループと 1:1（purge のみ実運用バッチ扱いで除外）。
        $badges = $this->adminOperationalSummaryService->getOperationBadgeCounts();
        $taskSummary = [
            ['id' => 'invoices',      'title' => '請求書発行',       'count' => (int) ($badges['admin.invoices.index'] ?? 0)],
            ['id' => 'confirmations', 'title' => '入金確認',         'count' => (int) ($badges['admin.deposits.confirmations'] ?? 0)],
            ['id' => 'transfers',     'title' => 'キャスト振込',     'count' => (int) ($badges['admin.deposits.transfers'] ?? 0)],
            ['id' => 'plans',         'title' => 'プラン入金管理',   'count' => (int) ($badges['admin.plans.index'] ?? 0)],
            ['id' => 'verification',  'title' => '身分証・書類審査', 'count' => (int) ($badges['admin.verification.index'] ?? 0)],
            ['id' => 'inquiries',     'title' => '問合せ対応',       'count' => (int) ($badges['admin.support-inquiries.index'] ?? 0)],
            ['id' => 'user_reports',  'title' => 'ユーザー通報',     'count' => (int) ($badges['admin.user_reports.index'] ?? 0)],
        ];
        // $tasks は現在ダッシュボード Blade で参照されないが、後方互換のため空配列を渡す
        $tasks = [];

        $dashboardUpdatedAt = now()->format('Y/m/d H:i');

        return view('admin.dashboard', compact(
            'registrationKpis',
            'transactionKpis',
            'chartData',
            'taskSummary',
            'tasks',
            'dashboardUpdatedAt'
        ));
    }

    /** 直近3ヶ月の取引が全て status>=5（確定）で1件以上ある店舗の数 */
    private function countPremiumShops(): int
    {
        if (! Schema::hasTable('application_deposits')
            || ! Schema::hasTable('shop_job_applications')
            || ! Schema::hasTable('shop_jobs')) {
            return 0;
        }

        $threeMonthsAgo = now()->subMonths(3);

        $disqualified = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs as sj_badge', 'shop_job_applications.shop_job_id', '=', 'sj_badge.id')
            ->where('application_deposits.created_at', '>=', $threeMonthsAgo)
            ->where('application_deposits.status', '<', 5)
            ->pluck('sj_badge.shop_id')
            ->unique();

        $confirmed = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs as sj_badge', 'shop_job_applications.shop_job_id', '=', 'sj_badge.id')
            ->where('application_deposits.created_at', '>=', $threeMonthsAgo)
            ->where('application_deposits.status', '>=', 5)
            ->pluck('sj_badge.shop_id')
            ->unique();

        return $confirmed->diff($disqualified)->count();
    }

    /** 整数差分を「+12」「±0」「−5」で整形 */
    private function signedDelta(int $delta): string
    {
        if ($delta > 0) return '+' . number_format($delta);
        if ($delta < 0) return '−' . number_format(abs($delta));
        return '±0';
    }

    /** 百分率を「+8.4%」「±0.0%」「−2.1%」で整形 */
    private function signedPct(float $pct): string
    {
        if ($pct > 0) return '+' . number_format($pct, 1) . '%';
        if ($pct < 0) return '−' . number_format(abs($pct), 1) . '%';
        return '±0.0%';
    }
}
