@extends('layouts.admin')

@section('title', 'マスタ設定管理')

@section('content')
    @php
        $hasDirectory = $selectedCatalog
            ? collect($selectedCatalog['fields'])->contains(fn ($field) => $field['input'] === 'directory')
            : false;
        $hasActive = $selectedCatalog
            ? (!empty($selectedCatalog['uses_del_flg']) || !empty($selectedCatalog['uses_is_active']))
            : false;
        $hasSortOrder = $selectedCatalog ? !empty($selectedCatalog['uses_sort_order']) : false;
        // 並び替えは「表示順（初期）」ソートのときのみ意味を持つ
        $canReorder = $hasSortOrder && $selectedSort === 'created_desc';
    @endphp
    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'MASTER',
            'title' => 'マスタコントロール',
        ])

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        @if (!empty($error))
            <div class="admin-alert admin-alert-error">{{ $error }}</div>
        @endif

        {{-- マスタ選択（プルダウン1本で切替） --}}
        <section class="admin-card admin-card-wide">
            <select
                id="master-catalog-select"
                class="master-picker__select"
                onchange="if(this.value){window.location.href=this.value;}"
                aria-label="管理するマスタを選択"
            >
                <option value="">マスタを選択してください</option>
                @foreach ($catalogs as $catalog)
                    <option
                        value="{{ route('admin.masters.index', ['catalog' => $catalog['key']]) }}"
                        @selected(($selectedCatalog['key'] ?? null) === $catalog['key'])
                    >
                        {{ $catalog['title'] }}（{{ number_format($catalog['count']) }}件）
                    </option>
                @endforeach
            </select>
        </section>

        @if ($selectedCatalog)
            <section class="admin-card admin-card-wide master-panel">
                {{-- 追加：1行の入力 + プラスボタン --}}
                <form method="POST"
                      action="{{ route('admin.masters.catalogs.store', $selectedCatalog['key']) }}"
                      class="master-add">
                    @csrf
                    <input type="hidden" name="current_sort" value="{{ $selectedSort }}">
                    @foreach ($selectedCatalog['fields'] as $index => $field)
                        <input
                            type="text"
                            name="{{ $field['input'] }}"
                            value="{{ old($field['input']) }}"
                            placeholder="{{ $index === 0 ? '追加：' . $field['label'] : $field['label'] }}"
                            class="master-add__input {{ $field['input'] === 'directory' ? 'is-narrow' : '' }}"
                            @if ($index === 0) required @endif
                        >
                    @endforeach
                    <button type="submit" class="master-add__btn" aria-label="追加">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>

                {{-- 上部ツールバー：件数 + 並び替え切替 + 並べ替えモード --}}
                <div class="master-toolbar">
                    <span class="master-toolbar__count">{{ number_format($selectedCatalog['records']->count()) }}件</span>
                    <div class="master-toolbar__spacer"></div>
                    <div class="master-sort-switch" role="group" aria-label="並び順">
                        <a href="{{ route('admin.masters.index', ['catalog' => $selectedCatalog['key'], 'sort' => 'created_desc']) }}"
                           class="master-sort-link {{ $selectedSort === 'created_desc' ? 'is-active' : '' }}">
                            {{ $hasSortOrder ? '表示順' : '登録日順' }}
                        </a>
                        <a href="{{ route('admin.masters.index', ['catalog' => $selectedCatalog['key'], 'sort' => 'name_asc']) }}"
                           class="master-sort-link {{ $selectedSort === 'name_asc' ? 'is-active' : '' }}">
                            あいうえお順
                        </a>
                    </div>
                    @if ($canReorder)
                        <button type="button" class="master-reorder-toggle" data-reorder-toggle>
                            <i class="fas fa-grip-lines" aria-hidden="true"></i>
                            <span data-reorder-label>並び替え</span>
                        </button>
                    @endif
                </div>

                {{-- 一覧：単語だけ表示。タップで編集/削除パネルが開く --}}
                <ul class="master-list"
                    id="master-records-body"
                    @if ($canReorder) data-reorder-url="{{ route('admin.masters.catalogs.reorder', $selectedCatalog['key']) }}" @endif>
                    @forelse ($selectedCatalog['records'] as $item)
                        <li class="master-item"
                            data-record-id="{{ $item->id }}">
                            <div class="master-item__row">
                                @if ($canReorder)
                                    <button type="button" class="master-item__drag" data-drag-handle
                                            title="ドラッグして並び替え（スマホは長押し）"
                                            aria-label="ドラッグして並び替え"
                                            tabindex="-1">
                                        <i class="fas fa-grip-lines" aria-hidden="true"></i>
                                    </button>
                                @endif
                                <button type="button" class="master-item__label" data-item-toggle>
                                    <span class="master-item__name">{{ $item->name }}</span>
                                    @if ($hasDirectory && !empty($item->directory))
                                        <span class="master-item__dir">/{{ $item->directory }}</span>
                                    @endif
                                    @if ($hasActive && !($item->is_active ?? 1))
                                        <span class="master-item__inactive">無効</span>
                                    @endif
                                </button>
                            </div>

                            {{-- タップで開く：編集 + 削除 --}}
                            <div class="master-item__panel" hidden>
                                <form method="POST"
                                      action="{{ route('admin.masters.catalogs.update', [$selectedCatalog['key'], $item->id]) }}"
                                      class="master-item__form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="current_sort" value="{{ $selectedSort }}">
                                    @foreach ($selectedCatalog['fields'] as $field)
                                        <label class="master-item__field">
                                            <span class="master-item__field-label">{{ $field['label'] }}</span>
                                            <input type="text"
                                                   name="{{ $field['input'] }}"
                                                   value="{{ $item->{$field['column']} ?? '' }}"
                                                   placeholder="{{ $field['placeholder'] ?? '' }}"
                                                   required>
                                        </label>
                                    @endforeach
                                    <div class="master-item__meta">
                                        <span class="master-item__chip">ID {{ $item->id }}</span>
                                        <span class="master-item__chip">{{ $item->created_at ? \Illuminate\Support\Carbon::parse($item->created_at)->format('Y-m-d') : '-' }}</span>
                                    </div>
                                    <div class="master-item__actions">
                                        <button type="submit" class="master-item__save">保存</button>
                                        <button type="button" class="master-item__cancel" data-item-cancel>キャンセル</button>
                                    </div>
                                </form>
                                <form method="POST"
                                      action="{{ route('admin.masters.catalogs.destroy', [$selectedCatalog['key'], $item->id]) }}"
                                      class="master-item__delete-form"
                                      onsubmit="return confirm('「{{ addslashes($item->name) }}」を削除しますか？\nこの操作は取り消せません。');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="current_sort" value="{{ $selectedSort }}">
                                    <button type="submit" class="master-item__delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i> 削除
                                    </button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="master-list__empty">まだ登録されていません。上の入力欄から追加してください。</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>

@endsection

@push('admin-scripts')
<script>
    (function () {
        // ---- 単語タップで編集/削除パネルを開閉 ----
        function closeAllPanels(except) {
            document.querySelectorAll('.master-item.is-open').forEach(function (li) {
                if (li === except) return;
                li.classList.remove('is-open');
                var p = li.querySelector('.master-item__panel');
                if (p) p.hidden = true;
            });
        }

        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // 並び替えモード中は開閉しない（誤タップ防止）
                if (document.body.classList.contains('master-reorder-mode')) return;
                var li = btn.closest('.master-item');
                if (!li) return;
                var panel = li.querySelector('.master-item__panel');
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
                var li = btn.closest('.master-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.master-item__panel');
                if (panel) panel.hidden = true;
            });
        });

        // Escape で閉じる
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeAllPanels(null);
        });

        // ---- 並び替えモードの切替 ----
        var reorderToggle = document.querySelector('[data-reorder-toggle]');
        if (reorderToggle) {
            reorderToggle.addEventListener('click', function () {
                var on = document.body.classList.toggle('master-reorder-mode');
                reorderToggle.classList.toggle('is-active', on);
                var label = reorderToggle.querySelector('[data-reorder-label]');
                if (label) label.textContent = on ? '完了' : '並び替え';
                if (on) closeAllPanels(null);
            });
        }

        // ---- ドラッグ＆ドロップ並び替え（並び替えモード時のみ有効） ----
        (function () {
            var list = document.getElementById('master-records-body');
            if (!list || !list.dataset.reorderUrl) return;
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfMeta ? csrfMeta.content : '';

            var toast = document.createElement('div');
            toast.className = 'master-reorder-toast';
            document.body.appendChild(toast);
            var toastTimer = null;
            function showToast(message, isError) {
                toast.textContent = message;
                toast.classList.toggle('is-error', !!isError);
                toast.classList.add('is-show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () { toast.classList.remove('is-show'); }, 2400);
            }

            function allRows() {
                return Array.prototype.slice.call(list.querySelectorAll('.master-item'));
            }
            function currentOrder() {
                return allRows().map(function (r) { return r.dataset.recordId; }).join(',');
            }

            var dragging = null;
            var orderBeforeDrag = '';
            var holdTimer = null;
            var startY = 0;

            function startDrag(row) {
                dragging = row;
                orderBeforeDrag = currentOrder();
                row.classList.add('is-dragging');
                document.body.classList.add('master-reorder-active');
                if (navigator.vibrate) navigator.vibrate(12);
            }
            function moveAt(clientY) {
                if (!dragging) return;
                if (clientY < 90) window.scrollBy(0, -14);
                else if (clientY > window.innerHeight - 90) window.scrollBy(0, 14);

                var siblings = allRows().filter(function (r) { return r !== dragging; });
                for (var i = 0; i < siblings.length; i++) {
                    var rect = siblings[i].getBoundingClientRect();
                    if (clientY < rect.top + rect.height / 2) {
                        if (dragging.nextElementSibling !== siblings[i]) {
                            list.insertBefore(dragging, siblings[i]);
                        }
                        return;
                    }
                }
                var last = siblings[siblings.length - 1];
                if (last && last.nextElementSibling !== dragging) {
                    list.insertBefore(dragging, last.nextElementSibling);
                }
            }
            function endDrag() {
                clearTimeout(holdTimer);
                holdTimer = null;
                if (!dragging) return;
                var row = dragging;
                dragging = null;
                row.classList.remove('is-dragging');
                document.body.classList.remove('master-reorder-active');
                var newOrder = currentOrder();
                if (newOrder === orderBeforeDrag) return;
                persist(newOrder.split(','), orderBeforeDrag);
            }
            function persist(ids, previousOrder) {
                fetch(list.dataset.reorderUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ ids: ids }),
                }).then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    showToast('表示順を保存しました');
                }).catch(function () {
                    var byId = {};
                    allRows().forEach(function (r) { byId[r.dataset.recordId] = r; });
                    previousOrder.split(',').forEach(function (id) {
                        if (byId[id]) list.appendChild(byId[id]);
                    });
                    showToast('並び替えの保存に失敗しました', true);
                });
            }

            list.querySelectorAll('[data-drag-handle]').forEach(function (handle) {
                handle.addEventListener('pointerdown', function (e) {
                    // 並び替えモード中でなければドラッグ開始しない
                    if (!document.body.classList.contains('master-reorder-mode')) return;
                    if (dragging) return;
                    var row = handle.closest('.master-item');
                    if (!row) return;
                    startY = e.clientY;
                    try { handle.setPointerCapture(e.pointerId); } catch (_) {}
                    if (e.pointerType === 'touch') {
                        holdTimer = setTimeout(function () { startDrag(row); }, 260);
                    } else {
                        startDrag(row);
                        e.preventDefault();
                    }
                });
                handle.addEventListener('pointermove', function (e) {
                    if (!dragging) {
                        if (holdTimer && Math.abs(e.clientY - startY) > 12) {
                            clearTimeout(holdTimer);
                            holdTimer = null;
                        }
                        return;
                    }
                    e.preventDefault();
                    moveAt(e.clientY);
                });
                handle.addEventListener('pointerup', endDrag);
                handle.addEventListener('pointercancel', endDrag);
                handle.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            });
        })();
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== マスタコントロール：シンプル版 =====
       方針：単語の一覧を並べるだけ。タップでインラインの編集/削除パネルが開く。
       並び替えは「並び替え」トグル時のみドラッグハンドルを表示。 */

    /* ---- マスタ選択プルダウン ---- */
    .master-picker__select {
        width: 100%;
        min-height: 48px;
        font-size: 16px;
        border-radius: 12px;
        padding: 12px 40px 12px 14px;
    }

    /* ---- カード内側 ---- */
    .master-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- 追加：1行フォーム ---- */
    .master-add {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .master-add__input {
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
    .master-add__input.is-narrow {
        flex: 0 0 34%;
        max-width: 200px;
    }
    .master-add__input:focus {
        outline: 2px solid rgba(196, 181, 253, 0.6);
        outline-offset: 1px;
    }
    .master-add__btn {
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
    .master-add__btn:hover { filter: brightness(1.08); }

    /* ---- ツールバー ---- */
    .master-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .master-toolbar__spacer { flex: 1 1 auto; }
    .master-toolbar__count {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--admin-sub, #a1a1aa);
    }
    .master-sort-switch { display: inline-flex; gap: 4px; }
    .master-sort-link {
        display: inline-block;
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.22));
        color: var(--admin-sub, #a1a1aa);
        background: transparent;
        text-decoration: none;
        cursor: pointer;
    }
    .master-sort-link.is-active {
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.14));
        color: #c4b5fd;
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45));
    }
    .master-reorder-toggle {
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
    .master-reorder-toggle.is-active {
        background: #a78bfa;
        color: #1a0f2e;
        border-color: #a78bfa;
    }

    /* ---- 一覧 ---- */
    .master-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .master-item {
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        border-radius: 10px;
        background: var(--admin-card, #1a1a1a);
        color: var(--admin-text, #f5f5f5);
        overflow: hidden;
    }
    .master-item.is-open {
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.55));
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.10));
    }
    .master-item__row {
        display: flex;
        align-items: center;
        gap: 0;
    }
    .master-item__drag {
        display: none; /* 通常時は非表示 */
        width: 44px;
        height: 44px;
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: grab;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        align-items: center;
        justify-content: center;
    }
    body.master-reorder-mode .master-item__drag { display: inline-flex; }
    .master-item__drag:active { cursor: grabbing; color: #c4b5fd; }

    .master-item__label {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 8px;
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
    .master-item__label:hover {
        background: rgba(168, 85, 247, 0.08);
    }
    body.master-reorder-mode .master-item__label { cursor: default; }
    body.master-reorder-mode .master-item__label:hover { background: transparent; }

    .master-item__name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--admin-text, #f5f5f5);
        word-break: break-all;
    }
    .master-item__dir {
        font-size: 0.75rem;
        color: var(--admin-sub, #a1a1aa);
        font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
    }
    .master-item__inactive {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.10);
    }

    /* ---- 編集/削除パネル ---- */
    .master-item__panel {
        border-top: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: rgba(0, 0, 0, 0.15);
    }
    .master-item__panel[hidden] { display: none; }
    .master-item__form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .master-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .master-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub, #a1a1aa);
    }
    .master-item__field input {
        width: 100%;
        height: 40px;
        padding: 8px 10px;
        font-size: 16px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.30));
        background: rgba(20, 14, 24, 0.6);
        color: var(--admin-text, #f5f5f5);
    }
    .master-item__field input:focus {
        outline: 2px solid rgba(196, 181, 253, 0.6);
        outline-offset: 1px;
    }
    .master-item__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .master-item__chip {
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
    .master-item__actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .master-item__save {
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
    .master-item__save:hover { filter: brightness(1.08); }
    .master-item__cancel {
        min-height: 40px;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(255, 255, 255, 0.18));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .master-item__cancel:hover { color: #fff; border-color: rgba(255, 255, 255, 0.4); }
    .master-item__delete-form { display: flex; }
    .master-item__delete {
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
    .master-item__delete:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #fecaca;
    }

    .master-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line, rgba(168, 85, 247, 0.22));
        border-radius: 10px;
    }

    /* ---- 並び替え中の視覚 ---- */
    .master-item.is-dragging {
        border-color: rgba(168, 85, 247, 0.65);
        background: rgba(168, 85, 247, 0.14);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5);
        opacity: 0.96;
        position: relative;
        z-index: 3;
    }
    body.master-reorder-active {
        user-select: none;
        -webkit-user-select: none;
        overscroll-behavior: contain;
    }
    .master-reorder-toast {
        position: fixed;
        left: 50%;
        bottom: 28px;
        transform: translateX(-50%) translateY(8px);
        background: #1a1a1a;
        border: 1px solid rgba(168, 85, 247, 0.5);
        color: #f5f5f5;
        padding: 10px 18px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
        z-index: 1000;
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.5);
        opacity: 0;
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
    }
    .master-reorder-toast.is-show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .master-reorder-toast.is-error { border-color: #e15c5c; color: #fecaca; }

    /* ---- ライトテーマ保険 ---- */
    @media (prefers-color-scheme: light) {
        .master-item {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.14);
        }
        .master-item__name { color: #241f33; }
        .master-item__dir { color: #6b6478; }
        .master-item__panel { background: #faf9fd; }
        .master-item__field input {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.20);
        }
        .master-list__empty { color: #6b6478; }
    }

    /* ---- スマホ ---- */
    @media (max-width: 640px) {
        .master-toolbar { gap: 6px; }
        .master-sort-link, .master-reorder-toggle { padding: 6px 10px; font-size: 0.72rem; }
        .master-add__input.is-narrow { flex: 0 0 40%; }
    }
</style>
@endpush
