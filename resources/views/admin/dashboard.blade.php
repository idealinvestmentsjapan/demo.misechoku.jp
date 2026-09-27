@extends('layouts.admin')

@section('title', 'ダッシュボード')
@section('admin_page_title', 'ダッシュボード')

@section('content')
    @php
        // ============================================================
        // チャート描画ヘルパー
        // ============================================================
        $taskCount = count($tasks);

        // 単一スケール折れ線チャート（複数系列共有スケール）
        $buildLineChart = function (array $data, array $series, array $opts = []) {
            $width = $opts['width'] ?? 600;
            $height = $opts['height'] ?? 130;
            $padX = $opts['padX'] ?? 36;        // Y軸ラベル分の左余白
            $padRight = $opts['padRight'] ?? 16;
            $padTop = $opts['padTop'] ?? 22;    // データ点の値ラベル分の上余白
            $padBottom = $opts['padBottom'] ?? 20;

            $allValues = [];
            foreach ($data as $row) {
                foreach ($series as $item) {
                    $allValues[] = (float) ($row[$item['key']] ?? 0);
                }
            }
            $rawMax = !empty($allValues) ? max($allValues) : 0;
            $maxScale = $rawMax > 0 ? $rawMax * 1.15 : 1;
            $axisMax = $rawMax > 0 ? $rawMax : 0;
            $count = max(count($data) - 1, 1);

            $plotLeft = $padX;
            $plotRight = $width - $padRight;
            $plotTop = $padTop;
            $plotBottom = $height - $padBottom;
            $plotWidth = $plotRight - $plotLeft;
            $plotHeight = $plotBottom - $plotTop;

            $getX = fn ($index) => $plotLeft + ($index / $count) * $plotWidth;
            $getY = fn ($value) => $plotBottom - (($value / $maxScale) * $plotHeight);

            $grid = [
                ['y' => $plotTop, 'label' => $axisMax > 0 ? (string) (int) round($axisMax) : ''],
                ['y' => $plotTop + $plotHeight * 0.5, 'label' => $axisMax > 0 ? (string) (int) round($axisMax / 2) : ''],
                ['y' => $plotBottom, 'label' => '0'],
            ];

            $seriesData = [];
            foreach ($series as $item) {
                $points = [];
                foreach ($data as $index => $row) {
                    $val = (float) ($row[$item['key']] ?? 0);
                    $points[] = ['x' => $getX($index), 'y' => $getY($val), 'value' => $val];
                }
                $polyline = implode(' ', array_map(fn ($p) => round($p['x'], 1) . ',' . round($p['y'], 1), $points));
                $area = round($getX(0), 1) . ',' . $plotBottom . ' ' . $polyline . ' ' . round($getX(count($data) - 1), 1) . ',' . $plotBottom;
                $seriesData[] = $item + ['points' => $points, 'polyline' => $polyline, 'area' => $area];
            }

            return [
                'width' => $width, 'height' => $height,
                'padX' => $padX, 'padRight' => $padRight, 'padTop' => $padTop, 'padBottom' => $padBottom,
                'plotLeft' => $plotLeft, 'plotRight' => $plotRight, 'plotTop' => $plotTop, 'plotBottom' => $plotBottom,
                'grid' => $grid,
                'series' => $seriesData,
            ];
        };

        // デュアル軸チャート（左軸: 棒グラフ、右軸: 折れ線）
        $buildDualChart = function (array $data, array $opts = []) {
            $width = $opts['width'] ?? 600;
            $height = $opts['height'] ?? 150;
            $padLeft = $opts['padLeft'] ?? 36;
            $padRight = $opts['padRight'] ?? 44; // 右Y軸ラベル分の余白
            $padTop = $opts['padTop'] ?? 22;
            $padBottom = $opts['padBottom'] ?? 20;

            $countMax = 0;
            $amountMax = 0.0;
            foreach ($data as $row) {
                $countMax = max($countMax, (int) ($row['count'] ?? 0));
                $amountMax = max($amountMax, (float) ($row['amount'] ?? 0));
            }
            $countScale = $countMax > 0 ? $countMax * 1.2 : 1;
            $amountScale = $amountMax > 0 ? $amountMax * 1.2 : 1;

            $count = max(count($data), 1);
            $plotLeft = $padLeft;
            $plotRight = $width - $padRight;
            $plotTop = $padTop;
            $plotBottom = $height - $padBottom;
            $plotWidth = $plotRight - $plotLeft;
            $plotHeight = $plotBottom - $plotTop;

            // 棒の中央X
            $colWidth = $plotWidth / $count;
            $barWidth = max(min($colWidth * 0.5, 28), 8);
            $getCenterX = fn ($index) => $plotLeft + ($index + 0.5) * $colWidth;
            $getCountY = fn ($value) => $plotBottom - (($value / $countScale) * $plotHeight);
            $getAmountY = fn ($value) => $plotBottom - (($value / $amountScale) * $plotHeight);

            $bars = [];
            $linePoints = [];
            foreach ($data as $index => $row) {
                $cx = $getCenterX($index);
                $countVal = (int) ($row['count'] ?? 0);
                $amountVal = (float) ($row['amount'] ?? 0);
                $bars[] = [
                    'x' => $cx - $barWidth / 2,
                    'y' => $getCountY($countVal),
                    'h' => $plotBottom - $getCountY($countVal),
                    'w' => $barWidth,
                    'value' => $countVal,
                    'centerX' => $cx,
                ];
                $linePoints[] = [
                    'x' => $cx,
                    'y' => $getAmountY($amountVal),
                    'value' => $amountVal,
                ];
            }
            $polyline = implode(' ', array_map(fn ($p) => round($p['x'], 1) . ',' . round($p['y'], 1), $linePoints));

            $leftGrid = [
                ['y' => $plotTop, 'label' => $countMax > 0 ? (string) (int) round($countMax) : ''],
                ['y' => $plotTop + $plotHeight * 0.5, 'label' => $countMax > 0 ? (string) (int) round($countMax / 2) : ''],
                ['y' => $plotBottom, 'label' => '0'],
            ];
            $rightLabels = [
                ['y' => $plotTop, 'label' => $amountMax > 0 ? number_format($amountMax, $amountMax < 10 ? 1 : 0) . 'M' : ''],
                ['y' => $plotTop + $plotHeight * 0.5, 'label' => $amountMax > 0 ? number_format($amountMax / 2, $amountMax < 10 ? 1 : 0) . 'M' : ''],
                ['y' => $plotBottom, 'label' => '0'],
            ];

            return [
                'width' => $width, 'height' => $height,
                'plotLeft' => $plotLeft, 'plotRight' => $plotRight, 'plotTop' => $plotTop, 'plotBottom' => $plotBottom,
                'leftGrid' => $leftGrid, 'rightLabels' => $rightLabels,
                'bars' => $bars, 'linePoints' => $linePoints, 'polyline' => $polyline,
            ];
        };

        $registrationChart = $buildLineChart($chartData, [
            ['key' => 'cast_new', 'label' => 'キャスト', 'color' => '#4A122A', 'text_color' => '#4A122A'],
            ['key' => 'shop_new', 'label' => '店舗', 'color' => '#7c3aed', 'text_color' => '#92590a'],
        ]);
        $transactionChart = $buildDualChart($chartData);
        $allKpis = array_merge($registrationKpis, $transactionKpis);
    @endphp

    <div class="dashboard-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'OVERVIEW',
            'title' => 'ダッシュボード',
            'info' => '
                <p>今この瞬間の運用状況と要対応タスクを表示します。</p>
                <p>収益推移の詳細は <a href="' . route('admin.sales.index') . '">売上管理</a> をご確認ください。</p>
            ',
        ])

        {{-- データ更新時刻（鮮度の可視化） --}}
        @if(!empty($dashboardUpdatedAt))
            <div class="dashboard-updated-bar" aria-label="データ更新時刻">
                <span class="dashboard-updated-bar__icon"><i class="fas fa-rotate"></i></span>
                <span class="dashboard-updated-bar__label">データ更新</span>
                <strong class="dashboard-updated-bar__time">{{ $dashboardUpdatedAt }}</strong>
                <a href="{{ route('admin.dashboard') }}" class="dashboard-updated-bar__refresh" aria-label="再読み込み">
                    <i class="fas fa-arrows-rotate"></i>更新
                </a>
            </div>
        @endif

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- ============================================================
             KPI（4枚）— 各カードは詳細画面へのリンク
             ============================================================ --}}
        <section class="dashboard-kpi-grid">
            @foreach ($allKpis as $kpi)
                @php
                    $kpiHref = $kpi['href'] ?? null;
                    $kpiTag  = $kpiHref ? 'a' : 'article';
                @endphp
                <{{ $kpiTag }}
                    class="dashboard-kpi-card {{ $kpiHref ? 'dashboard-kpi-card--link' : '' }}"
                    @if($kpiHref) href="{{ $kpiHref }}" @endif
                >
                    <div class="dashboard-kpi-head">
                        <div class="dashboard-kpi-title">{{ $kpi['title'] }}</div>
                        <i class="fas {{ $kpi['icon'] }}"></i>
                    </div>
                    <div class="dashboard-kpi-main">
                        <span class="dashboard-kpi-value">{{ $kpi['value'] }}</span>
                        <span class="dashboard-kpi-unit">{{ $kpi['unit'] }}</span>
                    </div>
                    @if (!empty($kpi['sub_value']))
                        <div class="dashboard-kpi-sub">
                            <span class="dashboard-kpi-sub-label">{{ $kpi['sub_label'] ?? '' }}</span>
                            <span class="dashboard-kpi-sub-value">{{ $kpi['sub_value'] }}</span>
                            <span class="dashboard-kpi-sub-unit">{{ $kpi['unit'] }}</span>
                        </div>
                    @endif
                    <div class="dashboard-kpi-trend {{ $kpi['is_up'] ? 'is-up' : 'is-down' }}">
                        <i class="fas {{ $kpi['is_up'] ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                        <span>{{ $kpi['trend_label'] }}</span>
                        @if (!empty($kpi['trend_caption']))
                            <span class="dashboard-kpi-trend-caption">{{ $kpi['trend_caption'] }}</span>
                        @endif
                    </div>
                    @if($kpiHref)
                        <span class="dashboard-kpi-card__arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                    @endif
                </{{ $kpiTag }}>
            @endforeach
        </section>

        {{-- ============================================================
             チャート群（既定は折りたたみ・ノイズ削減）
             ============================================================ --}}
        <details class="dashboard-charts-fold">
            <summary class="dashboard-charts-fold__summary">
                <span class="dashboard-charts-fold__label">グラフを表示</span>
                <span class="dashboard-charts-fold__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
            </summary>

        <section class="dashboard-chart-card">
            <div class="dashboard-chart-head">
                <h3>月別 新規登録数</h3>
                <div class="dashboard-chart-legend">
                    @foreach ($registrationChart['series'] as $series)
                        <span><i style="background: {{ $series['color'] }}"></i>{{ $series['label'] }}</span>
                    @endforeach
                </div>
            </div>
            <div class="dashboard-chart-scroll">
                <div class="dashboard-chart-canvas" style="height: {{ $registrationChart['height'] + 18 }}px;">
                    <svg viewBox="0 0 {{ $registrationChart['width'] }} {{ $registrationChart['height'] }}" preserveAspectRatio="none" class="dashboard-chart-svg" style="height: {{ $registrationChart['height'] }}px;">
                        <defs>
                            @foreach ($registrationChart['series'] as $series)
                                <linearGradient id="grad-{{ $series['key'] }}" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="{{ $series['color'] }}" stop-opacity="0.22"></stop>
                                    <stop offset="100%" stop-color="{{ $series['color'] }}" stop-opacity="0"></stop>
                                </linearGradient>
                            @endforeach
                        </defs>

                        {{-- グリッド + Y軸ラベル --}}
                        @foreach ($registrationChart['grid'] as $g)
                            <line x1="{{ $registrationChart['plotLeft'] }}" y1="{{ $g['y'] }}" x2="{{ $registrationChart['plotRight'] }}" y2="{{ $g['y'] }}" class="dashboard-chart-grid"></line>
                            @if ($g['label'] !== '')
                                <text x="{{ $registrationChart['plotLeft'] - 6 }}" y="{{ $g['y'] + 3 }}" class="dashboard-chart-axis-label" text-anchor="end">{{ $g['label'] }}</text>
                            @endif
                        @endforeach

                        {{-- 系列描画 --}}
                        @foreach ($registrationChart['series'] as $series)
                            <polygon points="{{ $series['area'] }}" fill="url(#grad-{{ $series['key'] }})"></polygon>
                            <polyline points="{{ $series['polyline'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></polyline>
                            @foreach ($series['points'] as $idx => $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="#ffffff" stroke="{{ $series['color'] }}" stroke-width="1.5"></circle>
                                <text x="{{ $point['x'] }}" y="{{ $point['y'] - 8 }}" class="dashboard-chart-value-label" text-anchor="middle" fill="{{ $series['text_color'] ?? $series['color'] }}">{{ (int) $point['value'] }}</text>
                            @endforeach
                        @endforeach
                    </svg>
                    <div class="dashboard-chart-labels" style="left: {{ $registrationChart['plotLeft'] }}px; right: {{ $registrationChart['padRight'] }}px;">
                        @foreach ($chartData as $point)
                            <span>{{ $point['month'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ============================================================
             取引件数 / 取引金額（デュアル軸：棒＋折れ線）
             ============================================================ --}}
        <section class="dashboard-chart-card">
            <div class="dashboard-chart-head">
                <h3>取引推移</h3>
                <div class="dashboard-chart-legend">
                    <span><i style="background: #4A122A"></i>件数（左軸）</span>
                    <span><i style="background: #16a34a"></i>金額 M円（右軸）</span>
                </div>
            </div>
            <div class="dashboard-chart-scroll">
                <div class="dashboard-chart-canvas" style="height: {{ $transactionChart['height'] + 18 }}px;">
                    <svg viewBox="0 0 {{ $transactionChart['width'] }} {{ $transactionChart['height'] }}" preserveAspectRatio="none" class="dashboard-chart-svg" style="height: {{ $transactionChart['height'] }}px;">
                        {{-- グリッド線（左軸） --}}
                        @foreach ($transactionChart['leftGrid'] as $g)
                            <line x1="{{ $transactionChart['plotLeft'] }}" y1="{{ $g['y'] }}" x2="{{ $transactionChart['plotRight'] }}" y2="{{ $g['y'] }}" class="dashboard-chart-grid"></line>
                            @if ($g['label'] !== '')
                                <text x="{{ $transactionChart['plotLeft'] - 6 }}" y="{{ $g['y'] + 3 }}" class="dashboard-chart-axis-label" text-anchor="end">{{ $g['label'] }}</text>
                            @endif
                        @endforeach

                        {{-- 右軸ラベル --}}
                        @foreach ($transactionChart['rightLabels'] as $g)
                            @if ($g['label'] !== '')
                                <text x="{{ $transactionChart['plotRight'] + 6 }}" y="{{ $g['y'] + 3 }}" class="dashboard-chart-axis-label dashboard-chart-axis-label--right" text-anchor="start">{{ $g['label'] }}</text>
                            @endif
                        @endforeach

                        {{-- 棒グラフ：取引件数 --}}
                        @foreach ($transactionChart['bars'] as $bar)
                            @if ($bar['h'] > 0)
                                <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] }}" width="{{ $bar['w'] }}" height="{{ $bar['h'] }}" rx="2" class="dashboard-chart-bar"></rect>
                                <text x="{{ $bar['centerX'] }}" y="{{ $bar['y'] - 5 }}" class="dashboard-chart-value-label" text-anchor="middle" fill="#4A122A">{{ $bar['value'] }}</text>
                            @endif
                        @endforeach

                        {{-- 折れ線：取引金額（右軸） --}}
                        <polyline points="{{ $transactionChart['polyline'] }}" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        @foreach ($transactionChart['linePoints'] as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="#ffffff" stroke="#16a34a" stroke-width="1.5"></circle>
                            @if ($point['value'] > 0)
                                <text x="{{ $point['x'] }}" y="{{ $point['y'] - 8 }}" class="dashboard-chart-value-label" text-anchor="middle" fill="#15803d">{{ number_format($point['value'], $point['value'] < 10 ? 1 : 0) }}M</text>
                            @endif
                        @endforeach
                    </svg>
                    <div class="dashboard-chart-labels" style="left: {{ $transactionChart['plotLeft'] }}px; right: {{ $transactionChart['width'] - $transactionChart['plotRight'] }}px;">
                        @foreach ($chartData as $point)
                            <span>{{ $point['month'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        </details>

        {{-- ============================================================
             要対応タスク
             ============================================================ --}}
        <section class="task-panel">
            <div class="task-panel-head">
                <h2 class="task-panel-title">要対応タスク</h2>
                <span class="task-panel-count">{{ $taskCount }}件</span>
            </div>

            <div class="task-summary-row">
                <button type="button" class="task-filter-chip is-active" data-filter="all">
                    <span>すべて</span>
                    <strong>{{ $taskCount }}</strong>
                </button>
                @foreach ($taskSummary as $summary)
                    <button type="button" class="task-filter-chip" data-filter="{{ $summary['id'] }}">
                        <span>{{ $summary['title'] }}</span>
                        <strong>{{ $summary['count'] }}</strong>
                    </button>
                @endforeach
            </div>

            <div class="task-table-wrap">
                <table class="task-table">
                    <thead>
                        <tr>
                            <th>カテゴリ</th>
                            <th>対象</th>
                            <th>ステータス</th>
                            <th>金額</th>
                            <th>申請日時</th>
                            <th class="text-right">アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr class="task-row task-row--{{ $task['urgency'] ?? 'normal' }}" data-category="{{ $task['cat_id'] }}" data-urgency="{{ $task['urgency'] ?? 'normal' }}">
                                <td data-label="カテゴリ">
                                    <span class="task-category-badge tone-{{ $task['cat_id'] }}">{{ $task['category'] }}</span>
                                </td>
                                <td data-label="対象">
                                    <div class="task-target">
                                        <div class="task-target-avatar">{{ $task['type'] === '店舗' ? '店' : 'C' }}</div>
                                        <div>
                                            <div class="task-target-name">{{ $task['target'] }}</div>
                                            <div class="task-target-meta">{{ $task['id'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="ステータス">
                                    <div class="task-status {{ $task['urgency'] === 'critical' ? 'is-critical' : ($task['urgency'] === 'high' ? 'is-high' : '') }}">
                                        <i class="fas {{ $task['urgency'] === 'critical' ? 'fa-circle-exclamation' : 'fa-clock' }}"></i>
                                        <span>{{ $task['status'] }}</span>
                                    </div>
                                </td>
                                <td data-label="金額" class="task-amount">{{ $task['amount'] ?: '-' }}</td>
                                <td data-label="申請日時" class="task-date">{{ $task['date'] }}</td>
                                <td data-label="アクション" class="text-right">
                                    @if(!empty($task['url']))
                                        <a href="{{ $task['url'] }}" class="task-action-button {{ $task['urgency'] === 'critical' ? 'is-critical' : '' }}">{{ $task['action'] }}</a>
                                    @else
                                        <button type="button" class="task-action-button {{ $task['urgency'] === 'critical' ? 'is-critical' : '' }}">{{ $task['action'] }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr id="task-empty-row" hidden>
                            <td colspan="6">
                                <div class="task-empty">
                                    <i class="fas fa-circle-check"></i>
                                    <div>このカテゴリの対応タスクはありません</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        var filterButtons = document.querySelectorAll('[data-filter]');
        var taskRows = document.querySelectorAll('.task-row');
        var emptyRow = document.getElementById('task-empty-row');

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-filter');
                var visibleCount = 0;

                filterButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });

                taskRows.forEach(function (row) {
                    var matches = target === 'all' || row.getAttribute('data-category') === target;
                    row.classList.toggle('is-hidden', !matches);
                    if (matches) visibleCount += 1;
                });

                if (emptyRow) {
                    emptyRow.hidden = visibleCount !== 0;
                }
            });
        });
    })();
</script>
@endpush
