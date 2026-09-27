@extends('layouts.admin')

@section('title', 'ダッシュボード')
@section('admin_page_title', 'ダッシュボード')

@section('content')
    @php
        // タスクカテゴリ → 遷移先ルート + アイコンのマッピング（プレゼンテーション層）
        // サイドバーの未対応バッジと 1:1 対応する 4 カテゴリ
        $catRoutes = [
            'verification' => ['route' => 'admin.verification.index',      'icon' => 'fa-id-card'],
            'invoices'     => ['route' => 'admin.invoices.index',          'icon' => 'fa-file-invoice'],
            'deposits'     => ['route' => 'admin.deposits.index',          'icon' => 'fa-money-bill-wave'],
            'inquiries'    => ['route' => 'admin.support-inquiries.index', 'icon' => 'fa-envelope-open-text'],
        ];
        $taskCount = collect($taskSummary ?? [])->sum('count');
    @endphp

    <div class="dashboard-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'OVERVIEW',
            'title' => 'ダッシュボード',
            'info' => '
                <p>要対応タスクの件数と、各管理画面へのショートカットを表示します。</p>
                <p>収益推移など分析は <a href="' . route('admin.sales.index') . '">売上管理</a> をご確認ください。</p>
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
    </div>
@endsection
