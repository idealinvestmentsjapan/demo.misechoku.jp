@extends('layouts.admin')

@section('title', 'NGワード管理')

@section('content')
    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'NG WORDS',
            'title' => 'NGワード管理',
            'info' => '
                <p>メッセージなどで使用できない<strong>キーワード</strong>を登録・管理します。</p>
                <ul>
                    <li>新規追加・編集・削除（無効化）が可能</li>
                    <li>表示順は ID 昇順で固定</li>
                </ul>
            ',
        ])

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif
        @if (!empty($error))
            <div class="admin-alert admin-alert-error">{{ $error }}</div>
        @endif

        {{-- 新規追加 --}}
        <section class="admin-card admin-card-wide">
            <div class="admin-card-head">
                <div>
                    <h2>NGワードを追加</h2>
                    <p>メッセージ送信時にブロックされるキーワードを追加します。</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.ngwords.store') }}" class="ngword-add-form">
                @csrf
                <label class="ngword-add-form__field">
                    <span>NGワード</span>
                    <input type="text" name="word" required maxlength="255" placeholder="例: 連絡先" autocomplete="off">
                </label>
                <button type="submit" class="btn-action manage">
                    <i class="fas fa-plus"></i> 追加
                </button>
            </form>
        </section>

        {{-- 編集中フォーム（行内編集の代わりに上に出す） --}}
        @if (!empty($editingWord))
            <section class="admin-card admin-card-wide ngword-edit-card">
                <div class="admin-card-head">
                    <div>
                        <h2>NGワードを編集（ID: {{ $editingWord->id }}）</h2>
                        <p>内容を変更して「変更を保存」を押してください。無効に切り替えると検出処理から除外されます。</p>
                    </div>
                    <a href="{{ route('admin.ngwords.index') }}" class="admin-master-cancel">編集をやめる</a>
                </div>
                <form method="POST" action="{{ route('admin.ngwords.update', $editingWord->id) }}" class="ngword-edit-form">
                    @csrf
                    @method('PUT')
                    <label class="ngword-edit-form__field">
                        <span>NGワード</span>
                        <input type="text" name="word" value="{{ old('word', $editingWord->word) }}" required maxlength="255">
                    </label>
                    <div class="u-flex u-gap-8">
                        <button type="submit" class="btn-action manage">
                            <i class="fas fa-floppy-disk"></i> 変更を保存
                        </button>
                        <a href="{{ route('admin.ngwords.index') }}" class="btn-action btn-action-secondary">キャンセル</a>
                    </div>
                </form>
            </section>
        @endif

        {{-- 一覧 --}}
        @php
            $activeCount = 0;
            $inactiveCount = 0;
            foreach ($words as $w) {
                if ((int) ($w->is_active ?? 0) === 1) $activeCount++;
                else $inactiveCount++;
            }
        @endphp
        <section class="admin-card admin-card-wide">
            <div class="admin-card-head">
                <div>
                    <h2>登録済みNGワード（{{ $words->count() }}件）</h2>
                    <p>削除すると一覧上は「無効」と表示され、検出処理からは除外されます。</p>
                </div>
            </div>

            <div class="admin-page-toolbar" style="margin-top:0;">
                <div class="admin-page-toolbar-filters" data-ngword-filters>
                    <button type="button" class="admin-filter-chip is-active" data-ngword-filter="all">
                        <span>すべて</span><strong>{{ $words->count() }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip" data-ngword-filter="active">
                        <span>有効</span><strong>{{ $activeCount }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip" data-ngword-filter="inactive">
                        <span>無効</span><strong>{{ $inactiveCount }}</strong>
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table admin-table--stack">
                    <thead>
                        <tr>
                            <th>NGワード（ID）</th>
                            <th class="u-w-100">状態</th>
                            <th>登録日</th>
                            <th class="u-w-140">操作</th>
                        </tr>
                    </thead>
                    <tbody id="ngword-table-body">
                        @forelse($words as $word)
                            @php $isActive = (int) ($word->is_active ?? 0) === 1; @endphp
                            <tr class="{{ $isActive ? '' : 'is-suspended' }}"
                                data-ngword-row
                                data-status="{{ $isActive ? 'active' : 'inactive' }}"
                                data-search="{{ mb_strtolower((string) $word->word) }}">
                                <td>
                                    {{ $word->word }}
                                    <div class="admin-table-sub"><code>#{{ $word->id }}</code></div>
                                </td>
                                <td data-label="状態">
                                    <span class="admin-status-badge {{ $isActive ? 'is-success' : 'is-inactive' }}">
                                        {{ $isActive ? '有効' : '無効' }}
                                    </span>
                                </td>
                                <td data-label="登録日">{{ $word->created_at ? \Illuminate\Support\Carbon::parse($word->created_at)->format('Y-m-d') : '-' }}</td>
                                <td class="stack-actions">
                                    <div class="u-flex u-gap-6">
                                        <a href="{{ route('admin.ngwords.index', ['edit' => $word->id]) }}"
                                           class="admin-row-icon-btn {{ ($editingWord->id ?? null) === $word->id ? 'is-active' : '' }}"
                                           title="編集">
                                            <i class="fas fa-pen"></i> 編集
                                        </a>
                                        @if($isActive)
                                            <form method="POST" action="{{ route('admin.ngwords.destroy', $word->id) }}"
                                                  onsubmit="return confirm('このNGワードを削除（無効化）しますか？');" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="admin-row-icon-btn admin-row-icon-delete" title="削除">
                                                    <i class="fas fa-times"></i> 削除
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">登録されているNGワードはありません。</td>
                            </tr>
                        @endforelse
                        <tr id="ngword-empty-row" hidden>
                            <td colspan="4" class="text-center text-muted">条件に一致するNGワードはありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = document.querySelectorAll('[data-ngword-row]');
    var chips = document.querySelectorAll('[data-ngword-filters] [data-ngword-filter]');
    var searchInput = document.getElementById('ngword-search');
    var emptyRow = document.getElementById('ngword-empty-row');
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
        if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
    }
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            state.filter = c.getAttribute('data-ngword-filter') || 'all';
            chips.forEach(function (cc) { cc.classList.toggle('is-active', cc === c); });
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

@push('admin-styles')
<style>
.ngword-add-form,
.ngword-edit-form {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}
.ngword-add-form__field,
.ngword-edit-form__field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 260px;
    min-width: 240px;
}
.ngword-add-form__field span,
.ngword-edit-form__field span {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--admin-muted);
    letter-spacing: 0.05em;
}
.ngword-add-form__field input,
.ngword-edit-form__field input {
    height: 40px;
    padding: 0 12px;
    border: 1px solid rgba(74, 18, 42, 0.18);
    border-radius: 10px;
    background: #fff;
    font-size: 0.95rem;
    color: var(--admin-text);
}
.ngword-edit-card { border-left: 4px solid #a78bfa; }
</style>
@endpush
