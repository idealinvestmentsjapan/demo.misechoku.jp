@extends('layouts.admin')

@section('title', 'お知らせ管理')

@section('content')
    @php
        $publishedCount = 0;
        $draftCount = 0;
        $scheduledCount = 0;
        foreach ($notices as $n) {
            if (!$n->is_published) {
                $draftCount++;
            } elseif ($n->published_at && $n->published_at->isFuture()) {
                $scheduledCount++;
            } else {
                $publishedCount++;
            }
        }
    @endphp

    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'NOTICES',
            'title' => 'お知らせ管理',
            'info' => '
                <p>キャスト・店舗・未ログインユーザー向けの<strong>お知らせ</strong>を作成・公開します。</p>
                <p>「<strong>未ログイン表示</strong>」をオンにすると <code>/support/notices</code> にも表示されます。</p>
            ',
        ])

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- KPI（クリックでフィルタ） --}}
        <section class="dashboard-kpi-grid content-kpi-grid" data-notice-kpis>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link is-active" data-notice-filter="all" aria-pressed="true">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">登録数（合計）</div>
                    <i class="fas fa-bell"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($notices->total() ?? $notices->count()) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-notice-filter="published" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">公開中</div>
                    <i class="fas fa-eye"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($publishedCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend is-up">ユーザーが閲覧可</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-notice-filter="scheduled" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">予約公開</div>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($scheduledCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend">公開待ち</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-notice-filter="draft" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">下書き</div>
                    <i class="fas fa-pen"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($draftCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </button>
        </section>

        <div class="admin-toolbar-with-cta">
            <a href="{{ route('admin.notices.create') }}" class="btn-action manage">
                <i class="fas fa-plus"></i> 新規作成
            </a>
        </div>

        <div class="table-wrapper">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>タイトル（ID）</th>
                        <th>公開状態</th>
                        <th>閲覧対象</th>
                        <th>公開日時</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="notice-table-body">
                    @forelse($notices as $notice)
                        @php
                            if (!$notice->is_published) {
                                $statusKey = 'draft';
                                $statusBadge = 'is-inactive';
                                $statusIcon = 'fa-pen';
                                $statusText = '下書き／非公開';
                            } elseif ($notice->published_at && $notice->published_at->isFuture()) {
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
                            $search = mb_strtolower(($notice->title ?? '') . ' ' . substr(strip_tags((string) ($notice->body ?? '')), 0, 200));
                        @endphp
                        <tr data-notice-row data-status="{{ $statusKey }}" data-search="{{ $search }}">
                            <td>
                                {{ $notice->title }}
                                <div class="admin-table-sub"><code>#{{ $notice->id }}</code></div>
                            </td>
                            <td data-label="公開状態">
                                <span class="admin-status-badge {{ $statusBadge }}"><i class="fas {{ $statusIcon }}"></i> {{ $statusText }}</span>
                            </td>
                            <td data-label="閲覧対象">
                                <div class="audience-chips">
                                    @if($notice->visible_to_cast)<span class="audience-chip audience-chip--cast"><i class="fas fa-user"></i> キャスト</span>@endif
                                    @if($notice->visible_to_shop)<span class="audience-chip audience-chip--shop"><i class="fas fa-store"></i> 店舗</span>@endif
                                    @if($notice->visible_to_guest)<span class="audience-chip audience-chip--guest"><i class="fas fa-globe"></i> 未ログイン</span>@endif
                                    @if(!$notice->visible_to_cast && !$notice->visible_to_shop && !$notice->visible_to_guest)
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="公開日時">
                                @if($notice->published_at)
                                    {{ $notice->published_at->format('Y-m-d H:i') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right stack-actions">
                                <a href="{{ route('admin.notices.edit', $notice) }}" class="row-action-link">編集</a>
                                <form action="{{ route('admin.notices.destroy', $notice) }}" method="post" onsubmit="return confirm('削除しますか？');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="row-action-link is-danger">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">お知らせが登録されていません。</td>
                        </tr>
                    @endforelse
                    <tr id="notice-empty-row" hidden>
                        <td colspan="5" class="text-center text-muted">条件に一致するお知らせはありません。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($notices->hasPages())
            <div style="margin-top:16px;">
                {{ $notices->links() }}
            </div>
        @endif
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var kpis = document.querySelectorAll('[data-notice-kpis] [data-notice-filter]');
    var rows = document.querySelectorAll('[data-notice-row]');
    var searchInput = document.getElementById('notice-search');
    var hitsEl = document.getElementById('notice-hits');
    var emptyRow = document.getElementById('notice-empty-row');
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
        if (hitsEl) hitsEl.textContent = visible + ' 件表示中';
        if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
    }

    kpis.forEach(function (k) {
        k.addEventListener('click', function () {
            state.filter = k.getAttribute('data-notice-filter') || 'all';
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
