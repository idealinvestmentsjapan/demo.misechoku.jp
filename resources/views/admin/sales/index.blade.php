@extends('layouts.admin')

@section('title', '売上管理')
@section('admin_page_title', '売上管理')

@section('content')
@php
    // ---- Stacked bar geometry (monthly revenue mix) ----
    $chartWidth = 720;
    $chartHeight = 240;
    $padLeft = 60;
    $padRight = 16;
    $padTop = 20;
    $padBottom = 34;
    $months = $monthlyChart;
    $monthCount = max(count($months), 1);
    $maxStack = max(array_map(fn ($r) => (int) $r['total'], $months) ?: [0]);
    if ($maxStack >= 100_000_000) { $scaleUnit = '億'; $scaleDiv = 100_000_000; }
    elseif ($maxStack >= 10_000)  { $scaleUnit = '万'; $scaleDiv = 10_000; }
    elseif ($maxStack >= 1_000)   { $scaleUnit = '千'; $scaleDiv = 1_000; }
    else                            { $scaleUnit = '';  $scaleDiv = 1; }
    $niceMax = $maxStack > 0 ? (ceil($maxStack * 1.15 / max($scaleDiv, 1)) * max($scaleDiv, 1)) : 1;
    if ($niceMax <= 0) { $niceMax = 1; }

    $plotLeft = $padLeft;
    $plotRight = $chartWidth - $padRight;
    $plotTop = $padTop;
    $plotBottom = $chartHeight - $padBottom;
    $plotWidth = $plotRight - $plotLeft;
    $plotHeight = $plotBottom - $plotTop;
    $bandWidth = $plotWidth / $monthCount;
    $barGap = 5;
    $barWidth = max(6, $bandWidth - $barGap * 2);
    $latestIndex = $monthCount - 1;

    $fmtScaled = function ($v) use ($scaleDiv, $scaleUnit) {
        if ($scaleDiv <= 1) return number_format((int) $v);
        $n = $v / $scaleDiv;
        $s = $n >= 10 ? number_format(round($n)) : rtrim(rtrim(number_format($n, 1), '0'), '.');
        return $s . $scaleUnit;
    };

    // Revenue mix colors (bank-report palette: muted, restrained)
    $colorBonus = '#334155'; // slate-700
    $colorHelp  = '#64748b'; // slate-500
    $colorPlan  = '#94a3b8'; // slate-400
    $gridLine   = '#e2e8f0';
    $axisText   = '#64748b';

    // Sparkline builder for top ranking rows (small inline SVG)
    $sparkSvg = function (array $series, string $stroke = '#334155') {
        $w = 120; $h = 32; $pad = 3;
        $max = max($series) ?: 1;
        $n = count($series);
        if ($n < 2) $n = 2;
        $step = ($w - $pad * 2) / ($n - 1);
        $points = [];
        foreach ($series as $i => $v) {
            $x = $pad + $step * $i;
            $y = $h - $pad - (($v / $max) * ($h - $pad * 2));
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        $line = implode(' ', $points);
        // Area fill (soft)
        $area = 'M ' . $pad . ',' . ($h - $pad) . ' L ' . implode(' L ', $points) . ' L ' . round($pad + $step * (count($series) - 1), 1) . ',' . ($h - $pad) . ' Z';
        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" class="sales-spark" aria-hidden="true">'
            . '<path d="' . $area . '" fill="' . $stroke . '" fill-opacity="0.08"/>'
            . '<polyline points="' . $line . '" fill="none" stroke="' . $stroke . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg>';
    };

    $collectionRate = ($current['issued_total'] ?? 0) > 0
        ? round(($current['paid_total'] ?? 0) / $current['issued_total'] * 100, 1)
        : 0;
@endphp

<div class="admin-page sales-page sales-page--report">
    {{-- Header block: title, brief, period control --}}
    <header class="sales-report-header">
        <div class="sales-report-header__title">
            <span class="sales-report-header__eyebrow">SALES &amp; REVENUE</span>
            <h1 class="sales-report-header__h1">売上管理</h1>
            <p class="sales-report-header__desc">
                運営の収益サイドを 3 系統に分解して可視化します：
                <strong>採用ボーナス仲介料</strong>（10% 上乗せ分）／
                <strong>ヘルプ仲介金</strong>（時給ベース）／
                <strong>Premium プラン売上</strong>（月額・年額）
            </p>
        </div>

        {{-- Period selector: pulldown only --}}
        <div class="sales-period-control" role="group" aria-label="期間の選択">
            <label class="sales-period-control__label" for="sales-period-select">期間</label>
            <div class="sales-period-control__wrap">
                <select id="sales-period-select" class="sales-period-control__select" onchange="location.href=this.value">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ route('admin.sales.index', ['period' => $key]) }}" {{ $period === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <span class="sales-period-control__range">
                    {{ $periodStart->format('Y/n/j') }} 〜 {{ $periodEnd->format('Y/n/j') }}
                </span>
            </div>
        </div>
    </header>

    {{-- Bank-style KPI ledger: primary + 3 sub-metrics --}}
    <section class="sales-ledger" aria-label="売上サマリー">
        @foreach($kpis as $kpi)
            @php
                $isPrimary = !empty($kpi['is_primary']);
                $pct = $kpi['pct'] ?? 0;
                $pctAbs = abs($pct);
                $up = ($kpi['delta'] ?? 0) >= 0;
                $arrow = $up ? '▲' : '▼';
                $pctText = $kpi['has_prev']
                    ? ($up ? '+' : '−') . number_format($pctAbs, 1) . '%'
                    : '—';
            @endphp
            <div class="sales-ledger__cell {{ $isPrimary ? 'is-primary' : '' }}">
                <div class="sales-ledger__label">{{ $kpi['title'] }}</div>
                <div class="sales-ledger__value">
                    <span class="sales-ledger__currency">¥</span>{{ number_format((int) $kpi['value']) }}
                </div>
                <div class="sales-ledger__meta">
                    <span class="sales-ledger__delta {{ $up ? 'is-up' : 'is-down' }}">
                        {{ $kpi['has_prev'] ? $arrow : '' }} {{ $pctText }}
                    </span>
                    <span class="sales-ledger__caption">{{ $kpi['trend_caption'] }}</span>
                    <span class="sales-ledger__sub">{{ $kpi['sub'] }}</span>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Collection cycle: paid / unpaid / overdue（回収サイクル） --}}
    <section class="sales-collection">
        <div class="sales-collection__head">
            <h2 class="sales-collection__title">回収サイクル（{{ $periodLabel }}）</h2>
            <div class="sales-collection__rate">
                <span class="sales-collection__rate-label">回収率</span>
                <span class="sales-collection__rate-value">{{ number_format($collectionRate, 1) }}<small>%</small></span>
            </div>
        </div>
        @php
            $issued = (int) ($current['issued_total'] ?? 0);
            $paid = (int) ($current['paid_total'] ?? 0);
            $unpaid = (int) ($current['unpaid_total'] ?? 0);
            $overdueAmt = (int) ($current['overdue_amount'] ?? 0);
            $overdueCnt = (int) ($current['overdue_count'] ?? 0);
            $paidPct = $issued > 0 ? round($paid / $issued * 100, 1) : 0;
            $unpaidPct = $issued > 0 ? round($unpaid / $issued * 100, 1) : 0;
            $overduePct = $issued > 0 ? round($overdueAmt / $issued * 100, 1) : 0;
        @endphp
        <div class="sales-collection__bar" role="img"
             aria-label="発行 {{ number_format($issued) }}円 うち入金済み {{ number_format($paid) }}円">
            <div class="sales-collection__bar-paid" style="width: {{ $paidPct }}%" title="入金確認済み ¥{{ number_format($paid) }}"></div>
            <div class="sales-collection__bar-unpaid" style="width: {{ $unpaidPct }}%" title="未回収 ¥{{ number_format($unpaid) }}"></div>
        </div>
        <dl class="sales-collection__grid">
            <div class="sales-collection__row">
                <dt>発行済み総額</dt>
                <dd class="u-num">¥{{ number_format($issued) }}</dd>
            </div>
            <div class="sales-collection__row is-paid">
                <dt>入金確認済み</dt>
                <dd class="u-num">¥{{ number_format($paid) }} <span class="sales-collection__row-pct">{{ $paidPct }}%</span></dd>
            </div>
            <div class="sales-collection__row is-unpaid">
                <dt>未回収</dt>
                <dd class="u-num">¥{{ number_format($unpaid) }} <span class="sales-collection__row-pct">{{ $unpaidPct }}%</span></dd>
            </div>
            <div class="sales-collection__row {{ $overdueCnt > 0 ? 'is-overdue' : '' }}">
                <dt>支払期日超過</dt>
                <dd class="u-num">
                    ¥{{ number_format($overdueAmt) }}
                    <span class="sales-collection__row-pct">{{ $overduePct }}% ／ {{ $overdueCnt }}件</span>
                    @if($overdueCnt > 0)
                        <a href="{{ route('admin.deposits.index') }}" class="sales-collection__cta">未回収一覧へ →</a>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    {{-- Monthly stacked bar chart（収益ミックスの推移） --}}
    <section class="sales-panel">
        <header class="sales-panel__head">
            <h2 class="sales-panel__title">月別 売上ミックス（直近12ヶ月）</h2>
            <div class="sales-panel__legend">
                <span><i class="sales-panel__legend-dot" style="background:{{ $colorBonus }}"></i>採用ボーナス</span>
                <span><i class="sales-panel__legend-dot" style="background:{{ $colorHelp }}"></i>ヘルプ</span>
                <span><i class="sales-panel__legend-dot" style="background:{{ $colorPlan }}"></i>Premium</span>
                @if($scaleUnit)<span class="sales-panel__legend-unit">単位：{{ $scaleUnit }}円</span>@endif
            </div>
        </header>

        <div class="sales-chart-wrap">
            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="xMidYMid meet" class="sales-chart" role="img" aria-label="月別 収益ミックスの棒グラフ">
                @for($g = 0; $g <= 4; $g++)
                    @php
                        $gy = $plotTop + ($plotHeight * $g / 4);
                        $yv = $niceMax * (1 - $g / 4);
                    @endphp
                    <line x1="{{ $plotLeft }}" y1="{{ $gy }}" x2="{{ $plotRight }}" y2="{{ $gy }}"
                          stroke="{{ $gridLine }}" stroke-width="1" stroke-dasharray="{{ $g === 4 ? '' : '3,4' }}"/>
                    <text x="{{ $plotLeft - 8 }}" y="{{ $gy + 3.5 }}" text-anchor="end" font-size="10" fill="{{ $axisText }}" font-family="Menlo, monospace">
                        {{ $fmtScaled($yv) }}
                    </text>
                @endfor

                @foreach($months as $i => $row)
                    @php
                        $x = $plotLeft + $bandWidth * $i + $barGap;
                        $isLatest = ($i === $latestIndex);
                        $tot = (int) $row['total'];
                        $hTotal = $niceMax > 0 ? ($tot / $niceMax) * $plotHeight : 0;
                        $yBase = $plotBottom;
                        // Stack order: bonus at bottom, help middle, plan top
                        $hBonus = $niceMax > 0 ? ($row['bonus'] / $niceMax) * $plotHeight : 0;
                        $hHelp  = $niceMax > 0 ? ($row['help']  / $niceMax) * $plotHeight : 0;
                        $hPlan  = $niceMax > 0 ? ($row['plan']  / $niceMax) * $plotHeight : 0;
                    @endphp
                    @if($row['bonus'] > 0)
                        <rect x="{{ round($x, 1) }}" y="{{ round($yBase - $hBonus, 1) }}" width="{{ round($barWidth, 1) }}" height="{{ round($hBonus, 1) }}" fill="{{ $colorBonus }}">
                            <title>{{ $row['month'] }} 採用ボーナス ¥{{ number_format($row['bonus']) }}</title>
                        </rect>
                    @endif
                    @if($row['help'] > 0)
                        <rect x="{{ round($x, 1) }}" y="{{ round($yBase - $hBonus - $hHelp, 1) }}" width="{{ round($barWidth, 1) }}" height="{{ round($hHelp, 1) }}" fill="{{ $colorHelp }}">
                            <title>{{ $row['month'] }} ヘルプ ¥{{ number_format($row['help']) }}</title>
                        </rect>
                    @endif
                    @if($row['plan'] > 0)
                        <rect x="{{ round($x, 1) }}" y="{{ round($yBase - $hBonus - $hHelp - $hPlan, 1) }}" width="{{ round($barWidth, 1) }}" height="{{ round($hPlan, 1) }}" fill="{{ $colorPlan }}">
                            <title>{{ $row['month'] }} Premium ¥{{ number_format($row['plan']) }}</title>
                        </rect>
                    @endif
                    @if($tot > 0)
                        <text x="{{ round($x + $barWidth / 2, 1) }}" y="{{ round($yBase - $hTotal - 6, 1) }}" text-anchor="middle" font-size="10" font-weight="600" fill="{{ $isLatest ? '#111827' : $axisText }}" font-family="Menlo, monospace">
                            {{ $fmtScaled($tot) }}
                        </text>
                    @endif
                    <text x="{{ round($x + $barWidth / 2, 1) }}" y="{{ $plotBottom + 18 }}" text-anchor="middle" font-size="10" fill="{{ $axisText }}" font-weight="{{ $isLatest ? '600' : '400' }}">
                        {{ $row['month'] }}
                    </text>
                @endforeach
            </svg>
        </div>

        {{-- Monthly breakdown table --}}
        <div class="sales-table-wrap u-mt-16">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th class="u-text-left">月</th>
                        <th class="u-text-right">採用ボーナス</th>
                        <th class="u-text-right">ヘルプ</th>
                        <th class="u-text-right">Premium</th>
                        <th class="u-text-right sales-table__total">総売上</th>
                        <th class="u-text-right">発行総額</th>
                        <th class="u-text-right">入金確認</th>
                        <th class="u-text-right">件数</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($months as $i => $row)
                        <tr class="{{ $i === $latestIndex ? 'is-latest' : '' }}">
                            <td class="u-text-left">{{ $row['year_month'] }}</td>
                            <td class="u-text-right u-num">{{ number_format($row['bonus']) }}</td>
                            <td class="u-text-right u-num">{{ number_format($row['help']) }}</td>
                            <td class="u-text-right u-num">{{ number_format($row['plan']) }}</td>
                            <td class="u-text-right u-num sales-table__total">{{ number_format($row['total']) }}</td>
                            <td class="u-text-right u-num sales-table__muted">{{ number_format($row['issued_total']) }}</td>
                            <td class="u-text-right u-num sales-table__muted">{{ number_format($row['paid_total']) }}</td>
                            <td class="u-text-right u-num sales-table__muted">{{ number_format($row['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Top contributors: shops & casts with drill-down sparkline --}}
    @php
        $medalIcon = ['①', '②', '③'];
    @endphp
    <div class="sales-top-grid">
        <section class="sales-panel">
            <header class="sales-panel__head">
                <h2 class="sales-panel__title">店舗別 仲介料貢献（{{ $periodLabel }}・上位{{ count($topShops) }}店舗）</h2>
                @if(!empty($topShops))
                    <button type="button" class="sales-btn-outline" data-sales-csv="shops" title="CSV ファイルとしてダウンロード">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                @endif
            </header>
            @if(empty($topShops))
                <p class="admin-note u-mb-0">対象期間に取引のある店舗はありません。</p>
            @else
                <div class="sales-table-wrap">
                <table class="sales-rank-table" id="sales-rank-shops">
                    <thead>
                        <tr>
                            <th class="sales-rank-table__rank">#</th>
                            <th class="u-text-left">店舗名</th>
                            <th class="u-text-right">件数</th>
                            <th class="u-text-right">仲介料</th>
                            <th class="sales-rank-table__spark">直近6ヶ月</th>
                            <th class="sales-rank-table__more"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topShops as $i => $shop)
                            <tr class="sales-rank-row"
                                data-name="{{ $shop['name'] }}" data-id="{{ $shop['id'] }}"
                                data-count="{{ $shop['count'] }}" data-commission="{{ $shop['commission'] }}">
                                <td class="sales-rank-table__rank">{{ $i < 3 ? $medalIcon[$i] : ($i + 1) }}</td>
                                <td class="u-text-left">
                                    <a href="{{ route('admin.shops.show', $shop['id']) }}" class="sales-rank-link">{{ $shop['name'] }}</a>
                                </td>
                                <td class="u-text-right u-num sales-table__muted">{{ number_format($shop['count']) }}</td>
                                <td class="u-text-right u-num sales-rank-table__amount">¥{{ number_format($shop['commission']) }}</td>
                                <td class="sales-rank-table__spark">
                                    {!! $sparkSvg($shop['sparkline'] ?? [], $colorBonus) !!}
                                </td>
                                <td class="sales-rank-table__more">
                                    <button type="button" class="sales-rank-drill" data-sales-drill aria-expanded="false" aria-label="月別内訳を表示">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="sales-rank-drilldown" hidden>
                                <td colspan="6">
                                    <div class="sales-rank-drilldown__grid">
                                        @foreach(($shop['sparkline_labels'] ?? []) as $k => $lbl)
                                            <div class="sales-rank-drilldown__cell">
                                                <div class="sales-rank-drilldown__label">{{ $lbl }}</div>
                                                <div class="sales-rank-drilldown__value">¥{{ number_format($shop['sparkline'][$k] ?? 0) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>

        <section class="sales-panel">
            <header class="sales-panel__head">
                <h2 class="sales-panel__title">キャスト別 仲介料貢献（{{ $periodLabel }}・上位{{ count($topCasts) }}名）</h2>
                @if(!empty($topCasts))
                    <button type="button" class="sales-btn-outline" data-sales-csv="casts" title="CSV ファイルとしてダウンロード">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                @endif
            </header>
            @if(empty($topCasts))
                <p class="admin-note u-mb-0">対象期間に取引のあるキャストはいません。</p>
            @else
                <div class="sales-table-wrap">
                <table class="sales-rank-table" id="sales-rank-casts">
                    <thead>
                        <tr>
                            <th class="sales-rank-table__rank">#</th>
                            <th class="u-text-left">キャスト</th>
                            <th class="u-text-right">件数</th>
                            <th class="u-text-right">仲介料</th>
                            <th class="sales-rank-table__spark">直近6ヶ月</th>
                            <th class="sales-rank-table__more"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topCasts as $i => $cast)
                            <tr class="sales-rank-row"
                                data-name="{{ $cast['name'] }}" data-id="{{ $cast['id'] }}"
                                data-count="{{ $cast['count'] }}" data-commission="{{ $cast['commission'] }}">
                                <td class="sales-rank-table__rank">{{ $i < 3 ? $medalIcon[$i] : ($i + 1) }}</td>
                                <td class="u-text-left">
                                    <a href="{{ route('admin.casts.show', $cast['id']) }}" class="sales-rank-link">{{ $cast['name'] }}</a>
                                </td>
                                <td class="u-text-right u-num sales-table__muted">{{ number_format($cast['count']) }}</td>
                                <td class="u-text-right u-num sales-rank-table__amount">¥{{ number_format($cast['commission']) }}</td>
                                <td class="sales-rank-table__spark">
                                    {!! $sparkSvg($cast['sparkline'] ?? [], $colorHelp) !!}
                                </td>
                                <td class="sales-rank-table__more">
                                    <button type="button" class="sales-rank-drill" data-sales-drill aria-expanded="false" aria-label="月別内訳を表示">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="sales-rank-drilldown" hidden>
                                <td colspan="6">
                                    <div class="sales-rank-drilldown__grid">
                                        @foreach(($cast['sparkline_labels'] ?? []) as $k => $lbl)
                                            <div class="sales-rank-drilldown__cell">
                                                <div class="sales-rank-drilldown__label">{{ $lbl }}</div>
                                                <div class="sales-rank-drilldown__value">¥{{ number_format($cast['sparkline'][$k] ?? 0) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Row drill-down (monthly breakdown)
    document.querySelectorAll('[data-sales-drill]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('tr');
            if (!row) return;
            var drill = row.nextElementSibling;
            if (!drill || !drill.classList.contains('sales-rank-drilldown')) return;
            var opened = drill.hasAttribute('hidden') ? false : true;
            if (opened) {
                drill.setAttribute('hidden', '');
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('is-open');
            } else {
                drill.removeAttribute('hidden');
                btn.setAttribute('aria-expanded', 'true');
                btn.classList.add('is-open');
            }
        });
    });

    // CSV export (client-side)
    function exportCsv(scope) {
        var listId = scope === 'shops' ? 'sales-rank-shops' : 'sales-rank-casts';
        var table = document.getElementById(listId);
        if (!table) return;
        var headerLabel = scope === 'shops' ? '店舗名' : 'キャスト名';
        var rows = [['順位', headerLabel, 'ID', '取引件数', '仲介料(円)']];
        Array.prototype.forEach.call(table.querySelectorAll('.sales-rank-row'), function (tr, idx) {
            rows.push([
                idx + 1,
                tr.dataset.name || '',
                tr.dataset.id || '',
                tr.dataset.count || '0',
                tr.dataset.commission || '0',
            ]);
        });
        var csv = rows.map(function (r) {
            return r.map(function (v) {
                var s = String(v == null ? '' : v).replace(/"/g, '""');
                return /[,"\n]/.test(s) ? '"' + s + '"' : s;
            }).join(',');
        }).join('\n');
        var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        var dt = new Date();
        var stamp = dt.getFullYear() + ('0'+(dt.getMonth()+1)).slice(-2) + ('0'+dt.getDate()).slice(-2);
        a.href = url;
        a.download = 'sales_top_' + scope + '_' + stamp + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    document.querySelectorAll('[data-sales-csv]').forEach(function (btn) {
        btn.addEventListener('click', function () { exportCsv(btn.dataset.salesCsv); });
    });
});
</script>
@endpush

@push('admin-styles')
<style>
/* ============================================================
   Sales report page - bank ledger aesthetic
   Palette: slate grey / off-white / thin borders / tabular nums
   ============================================================ */
.sales-page--report {
    --sales-border: #e5e7eb;
    --sales-border-strong: #cbd5e1;
    --sales-fg: #0f172a;
    --sales-fg-muted: #64748b;
    --sales-bg: #ffffff;
    --sales-bg-alt: #f8fafc;
    --sales-accent: #1e293b;
    --sales-up: #047857;
    --sales-down: #b91c1c;
    color: var(--sales-fg);
}

/* Numbers use tabular figures everywhere */
.sales-page--report .u-num,
.sales-page--report .sales-ledger__value,
.sales-page--report .sales-collection__rate-value,
.sales-page--report .sales-rank-table__amount,
.sales-page--report .sales-table td.u-num,
.sales-page--report .sales-table__total {
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum" 1;
}

/* --- Header --- */
.sales-report-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 24px;
    align-items: end;
    padding: 20px 24px;
    background: var(--sales-bg);
    border: 1px solid var(--sales-border);
    border-radius: 12px;
    margin-bottom: 16px;
}
.sales-report-header__eyebrow {
    font-size: 0.68rem;
    letter-spacing: 0.14em;
    color: var(--sales-fg-muted);
    font-weight: 700;
}
.sales-report-header__h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 4px 0 8px;
    letter-spacing: -0.01em;
    color: var(--sales-fg);
}
.sales-report-header__desc {
    font-size: 0.82rem;
    line-height: 1.7;
    color: var(--sales-fg-muted);
    margin: 0;
    max-width: 640px;
}
.sales-report-header__desc strong { color: var(--sales-fg); font-weight: 700; }

/* --- Period control (bank-like: labeled select + range + chips) --- */
.sales-period-control {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 260px;
}
.sales-period-control__label {
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    color: var(--sales-fg-muted);
    text-transform: uppercase;
    font-weight: 700;
}
.sales-period-control__wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sales-period-control__select {
    appearance: none;
    -webkit-appearance: none;
    padding: 7px 32px 7px 12px;
    border: 1px solid var(--sales-border-strong);
    border-radius: 6px;
    background: var(--sales-bg) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='7' viewBox='0 0 12 7'><path d='M1 1l5 5 5-5' stroke='%23334155' stroke-width='1.5' fill='none' stroke-linecap='round'/></svg>") no-repeat right 10px center;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--sales-fg);
    min-width: 160px;
    cursor: pointer;
}
.sales-period-control__select:focus {
    outline: 2px solid var(--sales-accent);
    outline-offset: 1px;
}
.sales-period-control__range {
    font-size: 0.78rem;
    color: var(--sales-fg-muted);
    font-variant-numeric: tabular-nums;
}

@media (max-width: 900px) {
    .sales-report-header {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 16px;
    }
}

/* --- KPI ledger (bank statement style) --- */
.sales-ledger {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    background: var(--sales-bg);
    border: 1px solid var(--sales-border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
}
.sales-ledger__cell {
    padding: 18px 20px;
    border-right: 1px solid var(--sales-border);
    background: var(--sales-bg);
}
.sales-ledger__cell:last-child { border-right: 0; }
.sales-ledger__cell.is-primary { background: var(--sales-bg-alt); }
.sales-ledger__label {
    font-size: 0.72rem;
    color: var(--sales-fg-muted);
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}
.sales-ledger__value {
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--sales-fg);
    letter-spacing: -0.01em;
    line-height: 1.15;
    display: flex;
    align-items: baseline;
    gap: 2px;
}
.sales-ledger__cell.is-primary .sales-ledger__value { font-size: 1.85rem; }
.sales-ledger__currency {
    font-size: 0.85em;
    color: var(--sales-fg-muted);
    font-weight: 500;
    margin-right: 2px;
}
.sales-ledger__meta {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.sales-ledger__delta {
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.sales-ledger__delta.is-up { color: var(--sales-up); }
.sales-ledger__delta.is-down { color: var(--sales-down); }
.sales-ledger__caption {
    font-size: 0.7rem;
    color: var(--sales-fg-muted);
}
.sales-ledger__sub {
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--sales-fg-muted);
    font-variant-numeric: tabular-nums;
}
@media (max-width: 900px) {
    .sales-ledger { grid-template-columns: repeat(2, 1fr); }
    .sales-ledger__cell:nth-child(-n+2) { border-bottom: 1px solid var(--sales-border); }
    .sales-ledger__cell:nth-child(2) { border-right: 0; }
}
@media (max-width: 500px) {
    .sales-ledger { grid-template-columns: 1fr; }
    .sales-ledger__cell { border-right: 0; border-bottom: 1px solid var(--sales-border); }
    .sales-ledger__cell:last-child { border-bottom: 0; }
}

/* --- Collection cycle --- */
.sales-collection {
    background: var(--sales-bg);
    border: 1px solid var(--sales-border);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 16px;
}
.sales-collection__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 12px;
}
.sales-collection__title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--sales-fg);
    margin: 0;
}
.sales-collection__rate { display: flex; align-items: baseline; gap: 8px; }
.sales-collection__rate-label {
    font-size: 0.7rem;
    color: var(--sales-fg-muted);
    font-weight: 700;
    letter-spacing: 0.06em;
}
.sales-collection__rate-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--sales-fg);
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
}
.sales-collection__rate-value small { font-size: 0.6em; color: var(--sales-fg-muted); margin-left: 2px; font-weight: 500; }
.sales-collection__bar {
    display: flex;
    height: 10px;
    background: var(--sales-bg-alt);
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 14px;
}
.sales-collection__bar-paid { background: #334155; }
.sales-collection__bar-unpaid { background: #cbd5e1; }
.sales-collection__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    margin: 0;
    border-top: 1px solid var(--sales-border);
}
.sales-collection__row {
    padding: 12px 14px;
    border-right: 1px solid var(--sales-border);
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.sales-collection__row:last-child { border-right: 0; }
.sales-collection__row dt {
    font-size: 0.72rem;
    color: var(--sales-fg-muted);
    font-weight: 600;
    margin: 0;
}
.sales-collection__row dd {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--sales-fg);
    margin: 0;
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
}
.sales-collection__row-pct {
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--sales-fg-muted);
}
.sales-collection__row.is-paid dd { color: #047857; }
.sales-collection__row.is-unpaid dd { color: #92400e; }
.sales-collection__row.is-overdue { background: rgba(220, 38, 38, 0.06); }
.sales-collection__row.is-overdue dd { color: #b91c1c; }
.sales-collection__cta {
    display: inline-block;
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--sales-down);
    text-decoration: none;
    font-weight: 700;
}
@media (max-width: 900px) {
    .sales-collection__grid { grid-template-columns: repeat(2, 1fr); }
    .sales-collection__row:nth-child(-n+2) { border-bottom: 1px solid var(--sales-border); }
    .sales-collection__row:nth-child(2) { border-right: 0; }
}

/* --- Panel (chart + tables + top rank) --- */
.sales-panel {
    background: var(--sales-bg);
    border: 1px solid var(--sales-border);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 16px;
}
.sales-panel__head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}
.sales-panel__title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--sales-fg);
    margin: 0;
}
.sales-panel__legend {
    display: flex;
    gap: 14px;
    font-size: 0.72rem;
    color: var(--sales-fg-muted);
    flex-wrap: wrap;
}
.sales-panel__legend-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 2px;
    margin-right: 5px;
    vertical-align: middle;
}
.sales-panel__legend-unit { color: var(--sales-fg-muted); font-style: italic; }
.sales-chart-wrap { overflow-x: auto; }
.sales-chart { width: 100%; height: auto; display: block; }

/* Horizontally-scrollable table wrapper (monthly breakdown + rank tables) */
.sales-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.sales-table-wrap .sales-table,
.sales-table-wrap .sales-rank-table {
    min-width: 640px;
}

/* --- Bank-style tables --- */
.sales-table,
.sales-rank-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.sales-table thead th,
.sales-rank-table thead th {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--sales-fg-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    text-align: right;
    padding: 8px 10px;
    border-bottom: 1px solid var(--sales-border-strong);
    background: var(--sales-bg-alt);
}
.sales-table thead th.u-text-left,
.sales-rank-table thead th.u-text-left { text-align: left; }
.sales-table tbody td,
.sales-rank-table tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid var(--sales-border);
    color: var(--sales-fg);
}
.sales-table tbody tr:last-child td,
.sales-rank-table tbody tr:last-child td { border-bottom: 0; }
.sales-table tbody tr.is-latest td { background: var(--sales-bg-alt); font-weight: 600; }
.sales-table__total { font-weight: 700; }
.sales-table__muted { color: var(--sales-fg-muted); font-weight: 500; }

.sales-rank-table__rank {
    text-align: center;
    width: 42px;
    color: var(--sales-fg-muted);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.sales-rank-table__amount {
    color: var(--sales-fg);
    font-weight: 700;
}
.sales-rank-link {
    color: var(--sales-fg);
    text-decoration: none;
    font-weight: 600;
}
.sales-rank-link:hover { text-decoration: underline; }
.sales-rank-table__spark { width: 130px; text-align: center; }
.sales-rank-table__spark .sales-spark { display: inline-block; vertical-align: middle; }
.sales-rank-table__more { width: 40px; text-align: center; }
.sales-rank-drill {
    background: transparent;
    border: 1px solid var(--sales-border);
    color: var(--sales-fg-muted);
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: transform 0.15s, background 0.15s;
}
.sales-rank-drill:hover { background: var(--sales-bg-alt); color: var(--sales-fg); }
.sales-rank-drill.is-open { transform: rotate(180deg); }

.sales-rank-drilldown td {
    background: var(--sales-bg-alt);
    padding: 12px 16px !important;
}
.sales-rank-drilldown__grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
}
.sales-rank-drilldown__cell {
    background: var(--sales-bg);
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid var(--sales-border);
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.sales-rank-drilldown__label { font-size: 0.68rem; color: var(--sales-fg-muted); font-weight: 600; }
.sales-rank-drilldown__value { font-size: 0.82rem; font-weight: 700; font-variant-numeric: tabular-nums; }
@media (max-width: 700px) {
    .sales-rank-drilldown__grid { grid-template-columns: repeat(3, 1fr); }
    .sales-rank-table__spark { display: none; }
}

/* --- Small outline button (bank-like) --- */
.sales-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--sales-fg);
    background: var(--sales-bg);
    border: 1px solid var(--sales-border-strong);
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
}
.sales-btn-outline:hover { background: var(--sales-bg-alt); }

/* Top grid: two panels side by side */
.sales-top-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 1100px) {
    .sales-top-grid { grid-template-columns: 1fr; }
}
</style>
@endpush
