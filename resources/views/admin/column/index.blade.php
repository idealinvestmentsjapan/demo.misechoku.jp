@extends('layouts.admin')

@section('title', 'コラム管理')

@section('content')
    @php
        $publishedCount = 0;
        $draftCount = 0;
        $scheduledCount = 0;
        foreach ($columns as $c) {
            $statusKey = 'published';
            if (method_exists($c, 'getAttribute')) {
                $isPublished = (bool) $c->is_published;
                $publishedAt = $c->published_at ?? null;
                if (!$isPublished) {
                    $draftCount++;
                } elseif ($publishedAt && $publishedAt->isFuture()) {
                    $scheduledCount++;
                } else {
                    $publishedCount++;
                }
            }
        }
    @endphp

    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'COLUMNS',
            'title' => 'コラム管理',
            'info' => '
                <p>キャスト・店舗向けの<strong>お役立ちコラム</strong>を作成・公開します。</p>
                <p>未ログイン向けに表示する場合は「<strong>未ログイン表示</strong>」をオンにしてください。</p>
            ',
        ])

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- KPI（クリックでフィルタ） --}}
        <section class="dashboard-kpi-grid content-kpi-grid" data-column-kpis>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link is-active" data-column-filter="all" aria-pressed="true">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">登録数（合計）</div>
                    <i class="fas fa-pen-nib"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($columns->total() ?? $columns->count()) }}</span>
                    <span class="dashboard-kpi-unit">本</span>
                </div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-column-filter="published" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">公開中</div>
                    <i class="fas fa-eye"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($publishedCount) }}</span>
                    <span class="dashboard-kpi-unit">本</span>
                </div>
                <div class="dashboard-kpi-trend is-up">ユーザーが閲覧可</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-column-filter="scheduled" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">予約公開</div>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($scheduledCount) }}</span>
                    <span class="dashboard-kpi-unit">本</span>
                </div>
                <div class="dashboard-kpi-trend">公開待ち</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-column-filter="draft" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">下書き</div>
                    <i class="fas fa-pen"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($draftCount) }}</span>
                    <span class="dashboard-kpi-unit">本</span>
                </div>
            </button>
        </section>

        <div class="admin-toolbar-with-cta">
            <a href="{{ route('admin.columns.create') }}" class="btn-action manage">
                <i class="fas fa-plus"></i> 新規作成
            </a>
        </div>

        <div class="table-wrapper">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>タイトル（ID）</th>
                        <th>カテゴリ</th>
                        <th>公開状態</th>
                        <th>閲覧対象</th>
                        <th>公開日時</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="column-table-body">
                    @forelse($columns as $column)
                        @php
                            if (!$column->is_published) {
                                $statusKey = 'draft';
                                $statusBadge = 'is-inactive';
                                $statusIcon = 'fa-pen';
                                $statusText = '下書き／非公開';
                            } elseif ($column->published_at && $column->published_at->isFuture()) {
                                $statusKey = 'scheduled';
                                $statusBadge = 'is-warning';
                                $statusIcon = 'fa-clock';
                                $statusText = '予約公開';
                            } else {
                                $statusKey = 'published';
                                $statusBadge = 'is-success';
                                $statusIcon = 'fa-eye';
                                $statusText = '公開中';
                            }
                            $catName = $column->columnCategory?->name ?? '';
                            $search = mb_strtolower(($column->title ?? '') . ' ' . $catName);
                        @endphp
                        <tr data-column-row data-status="{{ $statusKey }}" data-search="{{ $search }}">
                            <td>
                                {{ $column->title }}
                                <div class="admin-table-sub"><code>#{{ $column->id }}</code></div>
                            </td>
                            <td data-label="カテゴリ">{{ $catName ?: '-' }}</td>
                            <td data-label="公開状態">
                                <span class="admin-status-badge {{ $statusBadge }}"><i class="fas {{ $statusIcon }}"></i> {{ $statusText }}</span>
                            </td>
                            <td data-label="閲覧対象">
                                <div class="audience-chips">
                                    @if($column->visible_to_cast)<span class="audience-chip audience-chip--cast"><i class="fas fa-user"></i> キャスト</span>@endif
                                    @if($column->visible_to_shop)<span class="audience-chip audience-chip--shop"><i class="fas fa-store"></i> 店舗</span>@endif
                                    @if($column->visible_to_guest)<span class="audience-chip audience-chip--guest"><i class="fas fa-globe"></i> 未ログイン</span>@endif
                                    @if(!$column->visible_to_cast && !$column->visible_to_shop && !$column->visible_to_guest)
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="公開日時">
                                @if($column->published_at)
                                    {{ $column->published_at->format('Y-m-d H:i') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right stack-actions">
                                <a href="{{ route('admin.columns.edit', $column) }}" class="row-action-link">編集</a>
                                <form action="{{ route('admin.columns.destroy', $column) }}" method="post" onsubmit="return confirm('削除しますか？');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="row-action-link is-danger">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">コラム記事がありません。</td>
                        </tr>
                    @endforelse
                    <tr id="column-empty-row" hidden>
                        <td colspan="6" class="text-center text-muted">条件に一致するコラムはありません。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($columns->hasPages())
            <div style="margin-top:16px;">
                {{ $columns->links() }}
            </div>
        @endif
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var kpis = document.querySelectorAll('[data-column-kpis] [data-column-filter]');
    var rows = document.querySelectorAll('[data-column-row]');
    var searchInput = document.getElementById('column-search');
    var hitsEl = document.getElementById('column-hits');
    var emptyRow = document.getElementById('column-empty-row');
    var state = { filter: 'all', search: '' };

    function refresh() {
        var visible = 0;
        rows.forEach(function (r) {
            var statusMatch = state.filter === 'all' || r.dataset.status === state.filter;
            var searchMatch = !state.search || (r.dataset.search || '').indexOf(state.search) !== -1;
            var show = statusMatch && searchMatch;
            r.hidden = !show;
            if (show) visible++;
        });
        if (hitsEl) hitsEl.textContent = visible + ' 本表示中';
        if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
    }
    kpis.forEach(function (k) {
        k.addEventListener('click', function () {
            state.filter = k.getAttribute('data-column-filter') || 'all';
            kpis.forEach(function (kk) {
                var on = kk === k;
                kk.classList.toggle('is-active', on);
                kk.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            refresh();
        });
    });
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.search = searchInput.value.trim().toLowerCase();
            refresh();
        });
    }
    refresh();
});
</script>
@endpush
