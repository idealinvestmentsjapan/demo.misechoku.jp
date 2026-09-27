@extends('layouts.admin')

@section('title', '売上管理')
@section('admin_page_title', '売上管理')

@section('content')
@php
    // ---- Bar chart (monthly commission) geometry ----
    $chartWidth = 720;
    $chartHeight = 220;
    $padLeft = 56;
    $padRight = 16;
    $padTop = 24;
    $padBottom = 30;
    $months = $monthlyChart;
    $monthCount = max(count($months), 1);
    $maxCommission = max(array_map(fn ($r) => (int) $r['commission'], $months) ?: [0]);
    // Y-axis unit auto-scale
    if ($maxCommission >= 100_000_000) { $scaleUnit = '億'; $scaleDiv = 100_000_000; }
    elseif ($maxCommission >= 10_000)  { $scaleUnit = '万'; $scaleDiv = 10_000; }
    elseif ($maxCommission >= 1_000)   { $scaleUnit = '千'; $scaleDiv = 1_000; }
    else                                { $scaleUnit = '';  $scaleDiv = 1; }
    // Y-axis top: round up to a nice number
    $niceMax = $maxCommission > 0 ? (ceil($maxCommission * 1.1 / max($scaleDiv, 1)) * max($scaleDiv, 1)) : 1;
    if ($niceMax <= 0) { $niceMax = 1; }

    $plotLeft = $padLeft;
    $plotRight = $chartWidth - $padRight;
    $plotTop = $padTop;
    $plotBottom = $chartHeight - $padBottom;
    $plotWidth = $plotRight - $plotLeft;
    $plotHeight = $plotBottom - $plotTop;
    $bandWidth = $plotWidth / $monthCount;
    $barGap = 4;
    $barWidth = max(4, $bandWidth - $barGap * 2);
    $peakIndex = 0; $peakValue = -1;
    foreach ($months as $i => $row) {
        if ((int) $row['commission'] > $peakValue) { $peakValue = (int) $row['commission']; $peakIndex = $i; }
    }
    $latestIndex = $monthCount - 1;
    $fmtScaled = function ($v) use ($scaleDiv, $scaleUnit) {
        if ($scaleDiv <= 1) return number_format((int) $v);
        $n = $v / $scaleDiv;
        // Show 1 decimal only when needed
        $s = $n >= 10 ? number_format(round($n)) : rtrim(rtrim(number_format($n, 1), '0'), '.');
        return $s . $scaleUnit;
    };
@endphp

<div class="admin-page sales-page">
    <div class="u-flex-between u-flex-wrap u-gap-12">
        @include('admin.parts.page-title', [
            'eyebrow' => 'SALES & REVENUE',
            'title' => '売上管理',
            'info' => '
                <p><strong>この画面の役割：</strong>運営の収益サイドを可視化します。仲介料収益・取引総額（GMV）の推移と、貢献度上位の店舗・キャストを確認できます。</p>
                <p><strong>💡 売上の構造</strong></p>
                <p>ミセチョクの仲介料収益は次の 2 種類の合算です。</p>
                <p><strong>① 採用ボーナス（通常採用・体験入店）</strong></p>
                <ul>
                    <li>店舗への請求 ＝ ボーナス金 × <strong>1.10</strong>（システム手数料 10% を上乗せ）</li>
                    <li>キャストへの振込 ＝ ボーナス金 − ￥220（銀行振込手数料）</li>
                    <li>運営取り分 ＝ ボーナス金 × 10% ＋ ￥220</li>
                </ul>
                <p><strong>② ヘルプ仲介金（talk_job_kind = help）</strong></p>
                <ul>
                    <li>ボーナス金なし。ヘルプ時給 1 時間分をベースに清算</li>
                    <li>店舗への請求 ＝ ヘルプ時給 × <strong>135%</strong>（システム手数料の上乗せなし）</li>
                    <li>キャストへの振込 ＝ 時給 × <strong>50%</strong></li>
                    <li>運営取り分 ＝ 時給 × 85%</li>
                </ul>
                <p><strong>取引総額（GMV）</strong>＝ 店舗への請求額合計。ここからキャスト振込原資と運営取り分が生まれます。</p>
            ',
        ])
    </div>

    {{-- 期間切替 --}}
    <div class="sales-period-bar">
        <span class="sales-period-bar__label">期間：</span>
        <div class="sales-period-tabs" role="tablist">
            @foreach($periodOptions as $key => $label)
                <a href="{{ route('admin.sales.index', ['period' => $key]) }}"
                   class="sales-period-tab {{ $period === $key ? 'is-active' : '' }}"
                   role="tab" aria-selected="{{ $period === $key ? 'true' : 'false' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <span class="sales-period-bar__range">
            ({{ $periodStart->format('Y/n/j') }} 〜 {{ $periodEnd->format('Y/n/j') }})
        </span>
    </div>

    {{-- KPI カード（説明は info に集約したので、カードは値の一目確認に特化） --}}
    <div class="sales-kpi-grid">
        @foreach($kpis as $kpi)
            <article class="sales-kpi-card {{ !empty($kpi['is_primary']) ? 'is-primary' : '' }}">
                <div class="sales-kpi-card__head">
                    <span class="sales-kpi-card__icon"><i class="fas {{ $kpi['icon'] }}"></i></span>
                    <span class="sales-kpi-card__title">{{ $kpi['title'] }}</span>
                </div>
                <p class="sales-kpi-card__value">
                    {{ $kpi['value'] }}<span class="sales-kpi-card__unit">{{ $kpi['unit'] }}</span>
                </p>
                <p class="sales-kpi-card__trend {{ $kpi['is_up'] ? 'is-up' : 'is-down' }}">
                    @if($kpi['trend_label'] !== '—')
                        <i class="fas {{ $kpi['is_up'] ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                    @endif
                    {{ $kpi['trend_label'] }} <span>{{ $kpi['trend_caption'] }}</span>
                </p>
            </article>
        @endforeach
    </div>

    {{-- サブスクリプション情報（未連携の説明） --}}
    @if(!$subscriptionAvailable)
        <div class="admin-alert admin-alert-warning">
            <i class="fas fa-info-circle"></i>
            <strong>サブスクリプション収益</strong>は課金システム連携前のため未表示です。連携後にこの画面の指標として追加されます。
        </div>
    @endif

    {{-- 月別推移チャート（棒グラフ + Y軸ラベル） --}}
    <section class="admin-panel">
        <div class="u-flex-between u-mb-12">
            <h2 class="admin-panel-title u-mb-0">月別 仲介料推移（直近12ヶ月）</h2>
            <span class="sales-chart-hint">最新月・最大月をハイライト表示{!! $scaleUnit ? '・単位：' . $scaleUnit . '円' : '' !!}</span>
        </div>
        <div class="sales-chart-wrap">
            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="xMidYMid meet" class="sales-chart" role="img" aria-label="月別仲介料の棒グラフ">
                {{-- Grid lines + Y-axis labels --}}
                @for($g = 0; $g <= 4; $g++)
                    @php
                        $gy = $plotTop + ($plotHeight * $g / 4);
                        $yv = $niceMax * (1 - $g / 4);
                        $label = $fmtScaled($yv);
                    @endphp
                    <line x1="{{ $plotLeft }}" y1="{{ $gy }}" x2="{{ $plotRight }}" y2="{{ $gy }}"
                          stroke="#e7d4d8" stroke-width="1" stroke-dasharray="{{ $g === 4 ? '' : '3,4' }}"/>
                    <text x="{{ $plotLeft - 6 }}" y="{{ $gy + 3.5 }}" text-anchor="end" font-size="10" fill="#8b6e77">
                        {{ $label }}
                    </text>
                @endfor

                {{-- Bars --}}
                @foreach($months as $i => $row)
                    @php
                        $v = (int) $row['commission'];
                        $x = $plotLeft + $bandWidth * $i + $barGap;
                        $h = $niceMax > 0 ? ($v / $niceMax) * $plotHeight : 0;
                        $y = $plotBottom - $h;
                        $isPeak = ($i === $peakIndex && $v > 0);
                        $isLatest = ($i === $latestIndex);
                        $fill = $isPeak ? '#7c3aed' : ($isLatest ? '#a78bfa' : '#c4b5fd');
                    @endphp
                    <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($barWidth, 1) }}" height="{{ round(max(0, $h), 1) }}"
                          fill="{{ $fill }}" rx="3">
                        <title>{{ $row['month'] }} 仲介料 {{ number_format($v) }}円 / GMV {{ number_format((int) $row['gmv']) }}円 / {{ number_format((int) $row['count']) }}件</title>
                    </rect>
                    {{-- Value labels above peak/latest only to avoid clutter --}}
                    @if(($isPeak || $isLatest) && $v > 0)
                        <text x="{{ round($x + $barWidth / 2, 1) }}" y="{{ round($y - 5, 1) }}" text-anchor="middle"
                              font-size="10" font-weight="700" fill="{{ $isPeak ? '#5b21b6' : '#6d28d9' }}">
                            {{ $fmtScaled($v) }}
                        </text>
                    @endif
                    {{-- Month label --}}
                    <text x="{{ round($x + $barWidth / 2, 1) }}" y="{{ $plotBottom + 16 }}" text-anchor="middle"
                          font-size="10" fill="{{ $isLatest ? '#5b21b6' : '#6b4f55' }}"
                          font-weight="{{ $isLatest ? '700' : '400' }}">
                        {{ $row['month'] }}
                    </text>
                @endforeach
            </svg>
        </div>

        {{-- 凡例（配色の意味） --}}
        <div class="sales-chart-key">
            <span><i class="sales-chart-key__dot" style="background:#7c3aed;"></i>最大月</span>
            <span><i class="sales-chart-key__dot" style="background:#a78bfa;"></i>最新月</span>
            <span><i class="sales-chart-key__dot" style="background:#c4b5fd;"></i>その他</span>
            <span class="sales-chart-key__note">バーにカーソルを重ねると GMV と件数も表示されます。</span>
        </div>

        {{-- 月別テーブル（常時表示・コンパクト） --}}
        <div class="sales-monthly-table-wrap u-mt-16">
            <table class="admin-table admin-table--stack sales-monthly-table">
                <thead>
                    <tr>
                        <th>月</th>
                        <th class="u-text-right">仲介料</th>
                        <th class="u-text-right">取引総額（GMV）</th>
                        <th class="u-text-right">件数</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($months as $i => $row)
                        <tr class="{{ $i === $latestIndex ? 'is-latest' : '' }} {{ $i === $peakIndex && (int) $row['commission'] > 0 ? 'is-peak' : '' }}">
                            <td>{{ $row['year_month'] }}</td>
                            <td data-label="仲介料" class="u-text-right u-num">{{ number_format((int) $row['commission']) }} 円</td>
                            <td data-label="GMV" class="u-text-right u-num">{{ number_format((int) $row['gmv']) }} 円</td>
                            <td data-label="件数" class="u-text-right u-num">{{ number_format((int) $row['count']) }} 件</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Top 店舗・キャスト --}}
    @php
        $maxShopCommission = !empty($topShops) ? max(array_column($topShops, 'commission')) ?: 1 : 1;
        $maxCastCommission = !empty($topCasts) ? max(array_column($topCasts, 'commission')) ?: 1 : 1;
        $medalIcon = ['🥇', '🥈', '🥉'];
    @endphp
    <div class="sales-top-grid">
        <section class="admin-panel">
            <div class="u-flex-between u-mb-12">
                <h2 class="admin-panel-title u-mb-0">店舗別 仲介料貢献（{{ $periodLabel }}・上位{{ count($topShops) }}店舗）</h2>
                @if(!empty($topShops))
                    <button type="button" class="btn-action btn-action-secondary" data-sales-csv="shops"
                        title="CSV ファイルとしてダウンロード">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                @endif
            </div>
            @if(empty($topShops))
                <p class="admin-note u-mb-0">対象期間に取引のある店舗はありません。</p>
            @else
                <ol class="sales-rank-list is-compact" id="sales-rank-shops">
                    @foreach($topShops as $i => $shop)
                        @php
                            $pct = max(2, round($shop['commission'] / max($maxShopCommission, 1) * 100));
                            $isTop = $i < 3;
                        @endphp
                        <li class="sales-rank-item {{ $isTop ? 'is-top is-rank-' . ($i + 1) : '' }}"
                            data-name="{{ $shop['name'] }}" data-id="{{ $shop['id'] }}"
                            data-count="{{ $shop['count'] }}" data-commission="{{ $shop['commission'] }}">
                            <span class="sales-rank-item__rank">
                                @if($isTop)
                                    <span class="sales-rank-item__medal" aria-hidden="true">{{ $medalIcon[$i] }}</span>
                                @else
                                    <span class="sales-rank-item__num">{{ $i + 1 }}</span>
                                @endif
                            </span>
                            <div class="sales-rank-item__body">
                                <div class="sales-rank-item__head">
                                    <a href="{{ route('admin.shops.show', $shop['id']) }}" class="sales-rank-item__name">{{ $shop['name'] }}</a>
                                    <span class="sales-rank-item__count">{{ number_format($shop['count']) }}件</span>
                                    <span class="sales-rank-item__commission">¥{{ number_format($shop['commission']) }}</span>
                                </div>
                                <div class="sales-rank-item__bar" aria-hidden="true">
                                    <div class="sales-rank-item__bar-fill" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="admin-panel">
            <div class="u-flex-between u-mb-12">
                <h2 class="admin-panel-title u-mb-0">キャスト別 仲介料貢献（{{ $periodLabel }}・上位{{ count($topCasts) }}名）</h2>
                @if(!empty($topCasts))
                    <button type="button" class="btn-action btn-action-secondary" data-sales-csv="casts"
                        title="CSV ファイルとしてダウンロード">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                @endif
            </div>
            @if(empty($topCasts))
                <p class="admin-note u-mb-0">対象期間に取引のあるキャストはいません。</p>
            @else
                <ol class="sales-rank-list is-compact" id="sales-rank-casts">
                    @foreach($topCasts as $i => $cast)
                        @php
                            $pct = max(2, round($cast['commission'] / max($maxCastCommission, 1) * 100));
                            $isTop = $i < 3;
                        @endphp
                        <li class="sales-rank-item {{ $isTop ? 'is-top is-rank-' . ($i + 1) : '' }}"
                            data-name="{{ $cast['name'] }}" data-id="{{ $cast['id'] }}"
                            data-count="{{ $cast['count'] }}" data-commission="{{ $cast['commission'] }}">
                            <span class="sales-rank-item__rank">
                                @if($isTop)
                                    <span class="sales-rank-item__medal" aria-hidden="true">{{ $medalIcon[$i] }}</span>
                                @else
                                    <span class="sales-rank-item__num">{{ $i + 1 }}</span>
                                @endif
                            </span>
                            <div class="sales-rank-item__body">
                                <div class="sales-rank-item__head">
                                    <a href="{{ route('admin.casts.show', $cast['id']) }}" class="sales-rank-item__name">{{ $cast['name'] }}</a>
                                    <span class="sales-rank-item__count">{{ number_format($cast['count']) }}件</span>
                                    <span class="sales-rank-item__commission">¥{{ number_format($cast['commission']) }}</span>
                                </div>
                                <div class="sales-rank-item__bar" aria-hidden="true">
                                    <div class="sales-rank-item__bar-fill sales-rank-item__bar-fill--cast" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
// CSV エクスポート（クライアントサイド）
document.addEventListener('DOMContentLoaded', function () {
    function exportCsv(scope) {
        var listId = scope === 'shops' ? 'sales-rank-shops' : 'sales-rank-casts';
        var list = document.getElementById(listId);
        if (!list) return;
        var headerLabel = scope === 'shops' ? '店舗名' : 'キャスト名';
        var rows = [['順位', headerLabel, 'ID', '取引件数', '仲介料(円)']];
        Array.prototype.forEach.call(list.querySelectorAll('.sales-rank-item'), function (li, idx) {
            rows.push([
                idx + 1,
                li.dataset.name || '',
                li.dataset.id || '',
                li.dataset.count || '0',
                li.dataset.commission || '0',
            ]);
        });
        var csv = rows.map(function (r) {
            return r.map(function (v) {
                var s = String(v == null ? '' : v).replace(/"/g, '""');
                return /[,"\n]/.test(s) ? '"' + s + '"' : s;
            }).join(',');
        }).join('\n');
        // BOM 付与で Excel が UTF-8 を正しく読む
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
    /* ===== 売上管理：可読性リファイン ===== */

    /* KPI カードは説明文を info 側に集約したので値を主役に */
    .sales-kpi-card__value { margin-top: 6px; }

    /* チャートヒント（右上の小さな説明） */
    .sales-chart-hint {
        font-size: 0.72rem;
        color: var(--admin-muted, #8b6e77);
        font-weight: 500;
    }

    /* バーチャートのキー（配色凡例） */
    .sales-chart-key {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        font-size: 0.74rem;
        color: var(--admin-muted, #8b6e77);
        margin-top: 8px;
    }
    .sales-chart-key__dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        margin-right: 5px;
        vertical-align: middle;
    }
    .sales-chart-key__note {
        margin-left: auto;
        font-style: italic;
    }
    @media (max-width: 640px) {
        .sales-chart-key__note { margin-left: 0; width: 100%; }
    }

    /* 月別テーブル：数値は右寄せ・等幅・現在月/最大月をハイライト */
    .sales-monthly-table th,
    .sales-monthly-table td { font-size: 0.85rem; }
    .sales-monthly-table .u-text-right { text-align: right; }
    .sales-monthly-table .u-num { font-variant-numeric: tabular-nums; }
    .sales-monthly-table tr.is-latest td {
        background: rgba(168, 85, 247, 0.06);
        font-weight: 600;
    }
    .sales-monthly-table tr.is-peak td {
        background: rgba(124, 58, 237, 0.10);
    }
    .sales-monthly-table tr.is-latest.is-peak td {
        background: rgba(124, 58, 237, 0.14);
    }

    /* ランキング：3行→2行のコンパクト表示。名前/件数/金額を1段に。 */
    .sales-rank-list.is-compact .sales-rank-item {
        padding: 8px 12px;
        gap: 10px;
    }
    .sales-rank-list.is-compact .sales-rank-item__rank { min-width: 32px; height: 32px; }
    .sales-rank-list.is-compact .sales-rank-item__medal { font-size: 20px; }
    .sales-rank-list.is-compact .sales-rank-item__num { width: 26px; height: 26px; }

    .sales-rank-list.is-compact .sales-rank-item__head {
        margin-bottom: 4px;
        gap: 10px;
        align-items: center;
    }
    .sales-rank-list.is-compact .sales-rank-item__count {
        font-size: 0.72rem;
        color: var(--admin-muted, #8b6e77);
        font-variant-numeric: tabular-nums;
    }
    .sales-rank-list.is-compact .sales-rank-item__commission {
        margin-left: auto;
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--admin-primary, #7c3aed);
        font-variant-numeric: tabular-nums;
    }
    .sales-rank-list.is-compact .sales-rank-item__bar {
        margin-bottom: 0;
        height: 4px;
    }
    @media (max-width: 640px) {
        .sales-rank-list.is-compact .sales-rank-item__commission {
            margin-left: 0;
        }
    }
</style>
@endpush
