@extends('layouts.admin')

@section('title', 'ダッシュボード')
@section('admin_page_title', 'ダッシュボード')

@section('content')
    @php
        // タスクカテゴリ → 遷移先ルート + アイコンのマッピング（プレゼンテーション層）
        // サイドバー「オペレーション」グループと 1:1 対応（purge はバッチ扱いで除外）
        $catRoutes = [
            'invoices'      => ['route' => 'admin.invoices.index',           'icon' => 'fa-file-invoice'],
            'confirmations' => ['route' => 'admin.deposits.confirmations',   'icon' => 'fa-money-bill-wave'],
            'transfers'     => ['route' => 'admin.deposits.transfers',       'icon' => 'fa-paper-plane'],
            'plans'         => ['route' => 'admin.plans.index',              'icon' => 'fa-crown'],
            'verification'  => ['route' => 'admin.verification.index',       'icon' => 'fa-id-card'],
            'inquiries'     => ['route' => 'admin.support-inquiries.index',  'icon' => 'fa-envelope-open-text'],
            'user_reports'  => ['route' => 'admin.user_reports.index',       'icon' => 'fa-flag'],
        ];
        $taskCount = collect($taskSummary ?? [])->sum('count');

        // ============================================================
        // チャート描画ヘルパー（過去7ヶ月の月次推移）
        // ============================================================
        $buildLineChart = function (array $data, array $series, array $opts = []) {
            $width = $opts['width'] ?? 600;
            $height = $opts['height'] ?? 130;
            $padX = $opts['padX'] ?? 36;
            $padRight = $opts['padRight'] ?? 16;
            $padTop = $opts['padTop'] ?? 22;
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

        // デュアル軸チャート（左軸: 棒グラフ / 右軸: 折れ線）
        $buildDualChart = function (array $data, array $opts = []) {
            $width = $opts['width'] ?? 600;
            $height = $opts['height'] ?? 150;
            $padLeft = $opts['padLeft'] ?? 36;
            $padRight = $opts['padRight'] ?? 44;
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

        $registrationChart = $buildLineChart($chartData ?? [], [
            ['key' => 'cast_new', 'label' => 'キャスト', 'color' => '#4A122A', 'text_color' => '#4A122A'],
            ['key' => 'shop_new', 'label' => '店舗',    'color' => '#7c3aed', 'text_color' => '#92590a'],
        ]);
        $transactionChart = $buildDualChart($chartData ?? []);
    @endphp

    <div class="dashboard-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'OVERVIEW',
            'title' => 'ダッシュボード',
            'info' => '
                <p>要対応タスクの件数と、会員数・取引件数・売上の推移を表示します。</p>
                <p>詳細な収益分析は <a href="' . route('admin.sales.index') . '">売上管理</a> をご確認ください。</p>
            ',
        ])

        {{-- データ更新時刻（軽量な注記） --}}
        @if(!empty($dashboardUpdatedAt))
            <div class="dashboard-updated-bar" aria-label="データ更新時刻">
                <span class="dashboard-updated-bar__icon"><i class="fas fa-rotate"></i></span>
                <span class="dashboard-updated-bar__label">データ更新</span>
                <strong class="dashboard-updated-bar__time">{{ $dashboardUpdatedAt }}</strong>
                <a href="{{ route('admin.dashboard') }}" class="dashboard-updated-bar__refresh" aria-label="再読み込み">更新</a>
            </div>
        @endif

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- ============================================================
             要対応タスク（件数 + 各管理画面へのショートカット）
             ============================================================ --}}
        <section class="task-shortcut-panel">
            <div class="task-shortcut-panel__head">
                <h2 class="task-shortcut-panel__title">要対応タスク</h2>
                <span class="task-shortcut-panel__count">合計 {{ $taskCount }} 件</span>
            </div>

            @if ($taskCount === 0)
                <div class="task-shortcut-empty">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <span>要対応タスクはありません</span>
                </div>
            @else
                <div class="task-shortcut-grid">
                    @foreach ($taskSummary as $summary)
                        @php
                            $meta = $catRoutes[$summary['id']] ?? null;
                            $href = $meta ? route($meta['route']) : null;
                            $icon = $meta['icon'] ?? 'fa-list-check';
                            $count = (int) $summary['count'];
                            $isZero = $count === 0;
                        @endphp
                        <a href="{{ $href ?: '#' }}"
                           class="task-shortcut-card tone-{{ $summary['id'] }} {{ $isZero ? 'is-zero' : '' }}"
                           @if (!$href) aria-disabled="true" @endif>
                            <span class="task-shortcut-card__icon" aria-hidden="true">
                                <i class="fas {{ $icon }}"></i>
                            </span>
                            <span class="task-shortcut-card__body">
                                <span class="task-shortcut-card__title">{{ $summary['title'] }}</span>
                                <span class="task-shortcut-card__count">
                                    <strong>{{ $count }}</strong><span class="task-shortcut-card__unit">件</span>
                                </span>
                            </span>
                            <span class="task-shortcut-card__arrow" aria-hidden="true">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ============================================================
             月別 新規登録（キャスト + 店舗、共通スケール折れ線）
             ============================================================ --}}
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
             取引推移（デュアル軸：件数=棒 / 売上=折れ線）
             ============================================================ --}}
        <section class="dashboard-chart-card">
            <div class="dashboard-chart-head">
                <h3>取引推移</h3>
                <div class="dashboard-chart-legend">
                    <span><i style="background: #4A122A"></i>件数（左軸）</span>
                    <span><i style="background: #16a34a"></i>売上 M円（右軸）</span>
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

                        {{-- 折れ線：売上（右軸） --}}
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
    </div>
@endsection
