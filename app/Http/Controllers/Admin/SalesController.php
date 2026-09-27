<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 売上管理（運営の収益サイドの画面）。
 *
 * 収益ソースは3系統に分解して集計する:
 *   - bonus_commission  … 通常採用ボーナスの system_fee_amount（10%上乗せ分）
 *   - help_commission   … ヘルプ仲介の system_fee_amount（時給ベース、talk_job_kind='help'）
 *   - plan_revenue      … Premium プラン（shop_plan_subscriptions.paid_confirmed_at 基準）
 *
 * 回収サイクル指標:
 *   - issued_total  … 期間内に請求書が発行された総額（GMV基準の売上「見込み」）
 *   - paid_total    … 内、店舗入金確認済み（STATUS_SHOP_PAYMENT_CONFIRMED 以上）
 *   - overdue       … 発行済みだが支払期日超過かつ未入金の件数と金額
 *   - collection_rate … paid_total / issued_total
 */
class SalesController extends Controller
{
    private const PAID_STATUS_MIN = BillingManagementService::STATUS_SHOP_PAYMENT_CONFIRMED;

    public function index(Request $request)
    {
        $now = Carbon::now();
        $period = $request->query('period', 'last_3m');
        if (!in_array($period, ['this_month', 'last_month', 'last_3m', 'last_6m', 'last_12m'], true)) {
            $period = 'last_3m';
        }

        [$periodStart, $periodEnd, $periodLabel] = $this->resolvePeriod($period, $now);
        [$prevStart, $prevEnd, $prevLabel] = $this->resolvePreviousPeriod($period, $now);

        $schema = $this->detectSchema();

        $current = $this->aggregate($periodStart, $periodEnd, $schema);
        $previous = $this->aggregate($prevStart, $prevEnd, $schema);

        $kpis = $this->buildKpis($current, $previous, $prevLabel);

        // 月別推移（直近12ヶ月固定）: 収益ミックスをスタック棒で描くためのシリーズ
        $monthlyChart = $this->buildMonthlyMixSeries($now, 12, $schema);

        // Top 10 shops / casts + 直近6ヶ月のミニ折れ線用データ
        $topShops = $this->topContributors('shop', $periodStart, $periodEnd, $schema);
        $topCasts = $this->topContributors('cast', $periodStart, $periodEnd, $schema);
        $this->attachSparklines($topShops, 'shop', $now, 6, $schema);
        $this->attachSparklines($topCasts, 'cast', $now, 6, $schema);

        return view('admin.sales.index', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'prevLabel' => $prevLabel,
            'periodOptions' => [
                'this_month' => '今月',
                'last_month' => '先月',
                'last_3m' => '直近3ヶ月',
                'last_6m' => '直近6ヶ月',
                'last_12m' => '直近12ヶ月',
            ],
            'kpis' => $kpis,
            'monthlyChart' => $monthlyChart,
            'topShops' => $topShops,
            'topCasts' => $topCasts,
            'current' => $current,
            'previous' => $previous,
        ]);
    }

    private function detectSchema(): array
    {
        $hasDeposits = Schema::hasTable('application_deposits');
        return [
            'deposits' => $hasDeposits,
            'system_fee' => $hasDeposits && Schema::hasColumn('application_deposits', 'system_fee_amount'),
            'invoice_amount' => $hasDeposits && Schema::hasColumn('application_deposits', 'invoice_amount'),
            'cast_transfer' => $hasDeposits && Schema::hasColumn('application_deposits', 'cast_transfer_amount'),
            'deposit_status' => $hasDeposits && Schema::hasColumn('application_deposits', 'status'),
            'invoice_due_date' => $hasDeposits && Schema::hasColumn('application_deposits', 'invoice_due_date'),
            'applications' => Schema::hasTable('shop_job_applications'),
            'jobs' => Schema::hasTable('shop_jobs'),
            'job_kind' => Schema::hasTable('shop_job_applications') && Schema::hasColumn('shop_job_applications', 'talk_job_kind'),
            'plans' => Schema::hasTable('shop_plan_subscriptions'),
        ];
    }

    /**
     * 集計本体。期間を渡すと収益ミックス + 回収指標を返す。
     *
     * @return array{
     *   bonus_commission:int, help_commission:int, plan_revenue:int, total_revenue:int,
     *   issued_total:int, paid_total:int, unpaid_total:int, overdue_amount:int, overdue_count:int,
     *   collection_rate:float, count_bonus:int, count_help:int, count_plan:int, count_total:int
     * }
     */
    private function aggregate(Carbon $from, Carbon $to, array $schema): array
    {
        $zero = [
            'bonus_commission' => 0, 'help_commission' => 0, 'plan_revenue' => 0, 'total_revenue' => 0,
            'issued_total' => 0, 'paid_total' => 0, 'unpaid_total' => 0,
            'overdue_amount' => 0, 'overdue_count' => 0,
            'collection_rate' => 0.0,
            'count_bonus' => 0, 'count_help' => 0, 'count_plan' => 0, 'count_total' => 0,
        ];

        if ($schema['deposits'] && $schema['applications'] && $schema['jobs']) {
            $feeExpr = $this->feeExpression($schema);
            $invoiceCol = $schema['invoice_amount'] ? 'application_deposits.invoice_amount' : DB::raw('0');

            $rows = DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
                ->whereBetween('application_deposits.invoice_issued_at', [$from, $to])
                ->whereNotNull('application_deposits.invoice_issued_at')
                ->select(
                    ($schema['job_kind'] ? DB::raw('shop_job_applications.talk_job_kind as kind') : DB::raw("'bonus' as kind")),
                    DB::raw($feeExpr . ' as fee'),
                    DB::raw($this->rawSum($invoiceCol) . ' as invoice_amt'),
                    ($schema['deposit_status']
                        ? DB::raw('application_deposits.status as st')
                        : DB::raw('0 as st')),
                    ($schema['invoice_due_date']
                        ? DB::raw('application_deposits.invoice_due_date as due')
                        : DB::raw('NULL as due'))
                )
                ->get();

            $today = Carbon::today();
            foreach ($rows as $r) {
                $kind = (string) ($r->kind ?? '');
                $fee = (int) $r->fee;
                $invoice = (int) $r->invoice_amt;
                $st = (int) $r->st;

                if ($kind === 'help') {
                    $zero['help_commission'] += $fee;
                    $zero['count_help']++;
                } else {
                    $zero['bonus_commission'] += $fee;
                    $zero['count_bonus']++;
                }

                $zero['issued_total'] += $invoice;
                if ($st >= self::PAID_STATUS_MIN) {
                    $zero['paid_total'] += $invoice;
                } else {
                    $zero['unpaid_total'] += $invoice;
                    $due = $r->due ? Carbon::parse($r->due) : null;
                    if ($due && $due->lt($today)) {
                        $zero['overdue_amount'] += $invoice;
                        $zero['overdue_count']++;
                    }
                }
            }
        }

        // Premium plan revenue: recognize on paid_confirmed_at (business event)
        if ($schema['plans']) {
            $planAgg = DB::table('shop_plan_subscriptions')
                ->whereBetween('paid_confirmed_at', [$from, $to])
                ->whereNotNull('paid_confirmed_at')
                ->selectRaw('COALESCE(SUM(amount), 0) as revenue, COUNT(*) as cnt')
                ->first();
            $zero['plan_revenue'] = (int) ($planAgg->revenue ?? 0);
            $zero['count_plan'] = (int) ($planAgg->cnt ?? 0);
        }

        $zero['total_revenue'] = $zero['bonus_commission'] + $zero['help_commission'] + $zero['plan_revenue'];
        $zero['count_total'] = $zero['count_bonus'] + $zero['count_help'] + $zero['count_plan'];
        $zero['collection_rate'] = $zero['issued_total'] > 0
            ? $zero['paid_total'] / $zero['issued_total']
            : 0.0;

        return $zero;
    }

    private function feeExpression(array $schema): string
    {
        if ($schema['system_fee']) {
            return 'COALESCE(application_deposits.system_fee_amount, 0)';
        }
        if ($schema['invoice_amount'] && $schema['cast_transfer']) {
            return 'COALESCE(application_deposits.invoice_amount, 0) - COALESCE(application_deposits.cast_transfer_amount, 0)';
        }
        return '0';
    }

    private function rawSum($col): string
    {
        // Wrap a column/DB::raw in COALESCE for portable NULL handling.
        if ($col instanceof \Illuminate\Contracts\Database\Query\Expression) {
            return 'COALESCE(' . $col->getValue(DB::connection()->getQueryGrammar()) . ', 0)';
        }
        return 'COALESCE(' . $col . ', 0)';
    }

    private function buildKpis(array $current, array $previous, string $prevLabel): array
    {
        $delta = fn (int $c, int $p): array => [
            'delta' => $c - $p,
            'pct' => $p > 0 ? (($c - $p) / $p) * 100 : ($c > 0 ? 100.0 : 0.0),
            'has_prev' => $p > 0,
        ];

        $tot = $delta($current['total_revenue'], $previous['total_revenue']);
        $bonus = $delta($current['bonus_commission'], $previous['bonus_commission']);
        $help = $delta($current['help_commission'], $previous['help_commission']);
        $plan = $delta($current['plan_revenue'], $previous['plan_revenue']);

        return [
            [
                'id' => 'total',
                'title' => '総売上',
                'value' => $current['total_revenue'],
                'delta' => $tot['delta'],
                'pct' => $tot['pct'],
                'has_prev' => $tot['has_prev'],
                'sub' => $current['count_total'] . '件',
                'is_primary' => true,
                'trend_caption' => $prevLabel . '比',
            ],
            [
                'id' => 'bonus',
                'title' => '採用ボーナス仲介料',
                'value' => $current['bonus_commission'],
                'delta' => $bonus['delta'],
                'pct' => $bonus['pct'],
                'has_prev' => $bonus['has_prev'],
                'sub' => $current['count_bonus'] . '件',
                'trend_caption' => $prevLabel . '比',
            ],
            [
                'id' => 'help',
                'title' => 'ヘルプ仲介金',
                'value' => $current['help_commission'],
                'delta' => $help['delta'],
                'pct' => $help['pct'],
                'has_prev' => $help['has_prev'],
                'sub' => $current['count_help'] . '件',
                'trend_caption' => $prevLabel . '比',
            ],
            [
                'id' => 'plan',
                'title' => 'Premiumプラン売上',
                'value' => $current['plan_revenue'],
                'delta' => $plan['delta'],
                'pct' => $plan['pct'],
                'has_prev' => $plan['has_prev'],
                'sub' => $current['count_plan'] . '契約',
                'trend_caption' => $prevLabel . '比',
            ],
        ];
    }

    /**
     * 月別のミックスシリーズ（直近 $months ヶ月）。
     */
    private function buildMonthlyMixSeries(Carbon $now, int $months, array $schema): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $s = $now->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $e = $s->copy()->endOfMonth();
            $agg = $this->aggregate($s, $e, $schema);
            $rows[] = [
                'month' => $s->format('n月'),
                'year_month' => $s->format('Y-m'),
                'bonus' => $agg['bonus_commission'],
                'help' => $agg['help_commission'],
                'plan' => $agg['plan_revenue'],
                'total' => $agg['total_revenue'],
                'issued_total' => $agg['issued_total'],
                'paid_total' => $agg['paid_total'],
                'count' => $agg['count_total'],
            ];
        }
        return $rows;
    }

    /**
     * @return array<int, array{id:string, name:string, commission:int, count:int, sparkline:array}>
     */
    private function topContributors(string $entity, Carbon $from, Carbon $to, array $schema): array
    {
        if (!$schema['deposits'] || !$schema['applications'] || !$schema['jobs']) {
            return [];
        }

        $feeExpr = $this->feeExpression($schema);

        $query = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->whereBetween('application_deposits.invoice_issued_at', [$from, $to])
            ->whereNotNull('application_deposits.invoice_issued_at');

        if ($entity === 'shop') {
            $query->leftJoin('shop_profiles', 'shop_jobs.shop_id', '=', 'shop_profiles.shop_id')
                ->select(
                    'shop_jobs.shop_id as id',
                    DB::raw("COALESCE(shop_profiles.shop_name, '未設定') as name"),
                    DB::raw('SUM(' . $feeExpr . ') as commission'),
                    DB::raw('COUNT(application_deposits.id) as count')
                )
                ->groupBy('shop_jobs.shop_id', 'shop_profiles.shop_name');
        } else {
            $query->leftJoin('cast_profiles', 'shop_job_applications.cast_id', '=', 'cast_profiles.cast_id')
                ->select(
                    'shop_job_applications.cast_id as id',
                    DB::raw("COALESCE(cast_profiles.nickname, cast_profiles.name, '未設定') as name"),
                    DB::raw('SUM(' . $feeExpr . ') as commission'),
                    DB::raw('COUNT(application_deposits.id) as count')
                )
                ->groupBy('shop_job_applications.cast_id', 'cast_profiles.nickname', 'cast_profiles.name');
        }

        return $query->orderByDesc('commission')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'name' => (string) $r->name,
                'commission' => (int) $r->commission,
                'count' => (int) $r->count,
                'sparkline' => [], // filled later by attachSparklines
            ])
            ->values()
            ->all();
    }

    /**
     * Batch-load monthly commission for the given top contributors (last N months).
     * Uses a single grouped query to avoid N+1.
     */
    private function attachSparklines(array &$rows, string $entity, Carbon $now, int $months, array $schema): void
    {
        if (!$rows || !$schema['deposits'] || !$schema['applications'] || !$schema['jobs']) {
            return;
        }

        $ids = array_map(fn ($r) => $r['id'], $rows);
        $start = $now->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $feeExpr = $this->feeExpression($schema);
        $idCol = $entity === 'shop' ? 'shop_jobs.shop_id' : 'shop_job_applications.cast_id';

        // Use a portable "year-month" bucket key (avoids DATE_FORMAT vs strftime differences)
        // by fetching per-row invoice date and bucketing in PHP.
        $agg = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->whereBetween('application_deposits.invoice_issued_at', [$start, $end])
            ->whereNotNull('application_deposits.invoice_issued_at')
            ->whereIn($idCol, $ids)
            ->select(
                DB::raw("{$idCol} as owner_id"),
                'application_deposits.invoice_issued_at as issued_at',
                DB::raw($feeExpr . ' as fee')
            )
            ->get();

        // Initialize month buckets
        $bucketKeys = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $bucketKeys[] = $now->copy()->subMonthsNoOverflow($i)->format('Y-m');
        }
        $byOwner = [];
        foreach ($ids as $id) {
            $byOwner[$id] = array_fill_keys($bucketKeys, 0);
        }

        foreach ($agg as $row) {
            $key = Carbon::parse($row->issued_at)->format('Y-m');
            $ownerId = (string) $row->owner_id;
            if (isset($byOwner[$ownerId][$key])) {
                $byOwner[$ownerId][$key] += (int) $row->fee;
            }
        }

        foreach ($rows as &$r) {
            $series = array_values($byOwner[$r['id']] ?? array_fill_keys($bucketKeys, 0));
            $r['sparkline'] = $series;
            $r['sparkline_labels'] = $bucketKeys;
        }
        unset($r);
    }

    private function resolvePeriod(string $period, Carbon $now): array
    {
        return match ($period) {
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                '今月（' . $now->format('Y年n月') . '）',
            ],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                '先月（' . $now->copy()->subMonthNoOverflow()->format('Y年n月') . '）',
            ],
            'last_3m' => [
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->endOfMonth(),
                '直近3ヶ月',
            ],
            'last_6m' => [
                $now->copy()->subMonthsNoOverflow(5)->startOfMonth(),
                $now->copy()->endOfMonth(),
                '直近6ヶ月',
            ],
            default => [
                $now->copy()->subMonthsNoOverflow(11)->startOfMonth(),
                $now->copy()->endOfMonth(),
                '直近12ヶ月',
            ],
        };
    }

    private function resolvePreviousPeriod(string $period, Carbon $now): array
    {
        return match ($period) {
            'this_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                '前月',
            ],
            'last_month' => [
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(2)->endOfMonth(),
                '前月',
            ],
            'last_3m' => [
                $now->copy()->subMonthsNoOverflow(5)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(3)->endOfMonth(),
                '前期間',
            ],
            'last_6m' => [
                $now->copy()->subMonthsNoOverflow(11)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(6)->endOfMonth(),
                '前期間',
            ],
            default => [
                $now->copy()->subMonthsNoOverflow(23)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(12)->endOfMonth(),
                '前年同期',
            ],
        };
    }
}
