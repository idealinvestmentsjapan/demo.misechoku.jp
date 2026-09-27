@extends('layouts.admin')

@section('title', 'NGワード管理')

@section('content')
    @php
        $activeCount = 0;
        $inactiveCount = 0;
        foreach ($words as $w) {
            if ((int) ($w->is_active ?? 0) === 1) $activeCount++;
            else $inactiveCount++;
        }
    @endphp
    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'NG WORDS',
            'title' => 'NGワード管理',
            'info' => '
                <p>メッセージ・レビュー・プロフィールで検出をブロックする<strong>キーワード</strong>を管理します。</p>
                <ul>
                    <li>単語をタップすると編集・削除ができます</li>
                    <li>削除は論理削除（無効化）です。無効化した単語は検出処理から除外されます</li>
                </ul>
            ',
        ])

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        @if (!empty($error))
            <div class="admin-alert admin-alert-error">{{ $error }}</div>
        @endif

        <section class="admin-card admin-card-wide ngword-panel">
            {{-- 追加：1行の入力 + プラスボタン --}}
            <form method="POST" action="{{ route('admin.ngwords.store') }}" class="ngword-add">
                @csrf
                <input
                    type="text"
                    name="word"
                    value="{{ old('word') }}"
                    placeholder="追加：NGワード（例：連絡先）"
                    class="ngword-add__input"
                    maxlength="255"
                    autocomplete="off"
                    required
                >
                <button type="submit" class="ngword-add__btn" aria-label="追加">
                    <i class="fas fa-plus"></i>
                </button>
            </form>

            {{-- 上部ツールバー：件数チップ（すべて / 有効 / 無効） --}}
            <div class="ngword-toolbar" data-ngword-filters>
                <button type="button" class="ngword-filter-chip is-active" data-ngword-filter="all">
                    すべて <strong>{{ $words->count() }}</strong>
                </button>
                <button type="button" class="ngword-filter-chip" data-ngword-filter="active">
                    有効 <strong>{{ $activeCount }}</strong>
                </button>
                <button type="button" class="ngword-filter-chip" data-ngword-filter="inactive">
                    無効 <strong>{{ $inactiveCount }}</strong>
                </button>
            </div>

            {{-- 一覧：単語だけ表示。タップで編集/削除パネルが開く --}}
            <ul class="ngword-list" id="ngword-list-body">
                @forelse ($words as $word)
                    @php $isActive = (int) ($word->is_active ?? 0) === 1; @endphp
                    <li class="ngword-item {{ $isActive ? '' : 'is-inactive' }}"
                        data-ngword-row
                        data-status="{{ $isActive ? 'active' : 'inactive' }}"
                        data-record-id="{{ $word->id }}">
                        <div class="ngword-item__row">
                            <button type="button" class="ngword-item__label" data-item-toggle>
                                <span class="ngword-item__name">{{ $word->word }}</span>
                                @if (!$isActive)
                                    <span class="ngword-item__inactive">無効</span>
                                @endif
                            </button>
                        </div>

                        <div class="ngword-item__panel" hidden>
                            <form method="POST" action="{{ route('admin.ngwords.update', $word->id) }}" class="ngword-item__form">
                                @csrf
                                @method('PUT')
                                <label class="ngword-item__field">
                                    <span class="ngword-item__field-label">NGワード</span>
                                    <input type="text" name="word" value="{{ $word->word }}" maxlength="255" required>
                                </label>
                                <div class="ngword-item__meta">
                                    <span class="ngword-item__chip">ID {{ $word->id }}</span>
                                    <span class="ngword-item__chip">{{ $word->created_at ? \Illuminate\Support\Carbon::parse($word->created_at)->format('Y-m-d') : '-' }}</span>
                                    <span class="ngword-item__chip {{ $isActive ? 'is-ok' : 'is-off' }}">{{ $isActive ? '有効' : '無効' }}</span>
                                </div>
                                <div class="ngword-item__actions">
                                    <button type="submit" class="ngword-item__save">保存</button>
                                    <button type="button" class="ngword-item__cancel" data-item-cancel>キャンセル</button>
                                </div>
                            </form>
                            @if ($isActive)
                                <form method="POST" action="{{ route('admin.ngwords.destroy', $word->id) }}"
                                      class="ngword-item__delete-form"
                                      onsubmit="return confirm('「{{ addslashes($word->word) }}」を削除（無効化）しますか？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ngword-item__delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i> 削除（無効化）
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="ngword-list__empty">まだ登録されていません。上の入力欄から追加してください。</li>
                @endforelse
                <li class="ngword-list__empty" id="ngword-empty-row" hidden>条件に一致するNGワードはありません。</li>
            </ul>
        </section>
    </div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        // ---- 単語タップで編集/削除パネルを開閉 ----
        function closeAllPanels(except) {
            document.querySelectorAll('.ngword-item.is-open').forEach(function (li) {
                if (li === except) return;
                li.classList.remove('is-open');
                var p = li.querySelector('.ngword-item__panel');
                if (p) p.hidden = true;
            });
        }

        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ngword-item');
                if (!li) return;
                var panel = li.querySelector('.ngword-item__panel');
                if (!panel) return;
                var opening = panel.hidden;
                closeAllPanels(li);
                if (opening) {
                    panel.hidden = false;
                    li.classList.add('is-open');
                    var input = panel.querySelector('input[type="text"]');
                    if (input) { window.setTimeout(function () { input.focus(); input.select(); }, 30); }
                } else {
                    panel.hidden = true;
                    li.classList.remove('is-open');
                }
            });
        });

        document.querySelectorAll('[data-item-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ngword-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.ngword-item__panel');
                if (panel) panel.hidden = true;
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeAllPanels(null);
        });

        // ---- フィルタチップ（すべて / 有効 / 無効） ----
        var rows = document.querySelectorAll('[data-ngword-row]');
        var chips = document.querySelectorAll('[data-ngword-filters] [data-ngword-filter]');
        var emptyRow = document.getElementById('ngword-empty-row');
        var state = { filter: 'all' };

        function refresh() {
            var visible = 0;
            rows.forEach(function (r) {
                var show = state.filter === 'all' || r.dataset.status === state.filter;
                r.hidden = !show;
                if (show) visible++;
            });
            if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
        }

        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                state.filter = c.getAttribute('data-ngword-filter') || 'all';
                chips.forEach(function (cc) { cc.classList.toggle('is-active', cc === c); });
                closeAllPanels(null);
                refresh();
            });
        });
        refresh();
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== NGワード管理：シンプル版（マスタメンテナンスと同じ UI パターン） ===== */
    .ngword-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- 追加：1行フォーム ---- */
    .ngword-add {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .ngword-add__input {
        flex: 1 1 auto;
        min-width: 0;
        height: 44px;
        padding: 8px 12px;
        font-size: 16px;
        border-radius: 10px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.22));
        background: var(--admin-surface-alt, rgba(255, 255, 255, 0.04));
        color: var(--admin-text, #f5f5f5);
    }
    .ngword-add__input:focus {
        outline: 2px solid rgba(196, 181, 253, 0.6);
        outline-offset: 1px;
    }
    .ngword-add__btn {
        flex: 0 0 auto;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        border: 0;
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        color: #fff;
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: filter 0.15s ease;
    }
    .ngword-add__btn:hover { filter: brightness(1.08); }

    /* ---- ツールバー（フィルタチップ） ---- */
    .ngword-toolbar {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .ngword-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.22));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .ngword-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text, #f5f5f5);
        font-variant-numeric: tabular-nums;
    }
    .ngword-filter-chip.is-active {
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.14));
        color: #c4b5fd;
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45));
    }
    .ngword-filter-chip.is-active strong { color: #c4b5fd; }

    /* ---- 一覧 ---- */
    .ngword-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ngword-item {
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        border-radius: 10px;
        background: var(--admin-card, #1a1a1a);
        color: var(--admin-text, #f5f5f5);
        overflow: hidden;
    }
    .ngword-item.is-open {
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.55));
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.10));
    }
    .ngword-item.is-inactive { opacity: 0.75; }
    .ngword-item__row { display: flex; align-items: center; }
    .ngword-item__label {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        min-height: 44px;
        border: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .ngword-item__label:hover { background: rgba(168, 85, 247, 0.08); }
    .ngword-item__name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--admin-text, #f5f5f5);
        word-break: break-all;
    }
    .ngword-item.is-inactive .ngword-item__name { text-decoration: line-through; }
    .ngword-item__inactive {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.10);
    }

    /* ---- 編集/削除パネル ---- */
    .ngword-item__panel {
        border-top: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: rgba(0, 0, 0, 0.15);
    }
    .ngword-item__panel[hidden] { display: none; }
    .ngword-item__form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ngword-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ngword-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub, #a1a1aa);
    }
    .ngword-item__field input {
        width: 100%;
        height: 40px;
        padding: 8px 10px;
        font-size: 16px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.30));
        background: rgba(20, 14, 24, 0.6);
        color: var(--admin-text, #f5f5f5);
    }
    .ngword-item__field input:focus {
        outline: 2px solid rgba(196, 181, 253, 0.6);
        outline-offset: 1px;
    }
    .ngword-item__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .ngword-item__chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.20));
        background: var(--admin-surface-alt, rgba(255, 255, 255, 0.04));
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.68rem;
    }
    .ngword-item__chip.is-ok { color: #6ee7b7; border-color: rgba(110, 231, 183, 0.4); background: rgba(110, 231, 183, 0.10); }
    .ngword-item__chip.is-off { color: #fca5a5; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.10); }
    .ngword-item__actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .ngword-item__save {
        flex: 1 1 auto;
        min-height: 40px;
        padding: 8px 16px;
        border: 0;
        border-radius: 8px;
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }
    .ngword-item__save:hover { filter: brightness(1.08); }
    .ngword-item__cancel {
        min-height: 40px;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(255, 255, 255, 0.18));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .ngword-item__cancel:hover { color: #fff; border-color: rgba(255, 255, 255, 0.4); }
    .ngword-item__delete-form { display: flex; }
    .ngword-item__delete {
        flex: 1 1 auto;
        min-height: 40px;
        padding: 8px 16px;
        border: 1px solid rgba(239, 68, 68, 0.5);
        border-radius: 8px;
        background: transparent;
        color: #fca5a5;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .ngword-item__delete:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #fecaca;
    }

    .ngword-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line, rgba(168, 85, 247, 0.22));
        border-radius: 10px;
    }

    /* ---- ライトテーマ保険 ---- */
    @media (prefers-color-scheme: light) {
        .ngword-item {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.14);
        }
        .ngword-item__name { color: #241f33; }
        .ngword-item__panel { background: #faf9fd; }
        .ngword-item__field input {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.20);
        }
        .ngword-filter-chip strong { color: #241f33; }
        .ngword-list__empty { color: #6b6478; }
    }
</style>
@endpush
