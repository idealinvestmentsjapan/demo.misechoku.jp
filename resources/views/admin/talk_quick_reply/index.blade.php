@extends('layouts.admin')

@section('title', 'クイック定型文マスタ')

@section('content')
@php
    $totalRows = 0;
    $castRows = 0;
    $shopRows = 0;
    foreach ($groups as $g) {
        $totalRows += count($g['rows']);
        if ($g['owner_type'] === 'cast') $castRows += count($g['rows']);
        elseif ($g['owner_type'] === 'shop') $shopRows += count($g['rows']);
    }
    $previewLimit = 42;
@endphp
<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'TALK QUICK REPLY',
        'title' => 'トーククイック定型文マスタ',
        'info' => '
            <p>トークルーム下部のクイック定型文パネルに表示される候補文を、<strong>役割 (キャスト／店舗) × 応募ステータス</strong>ごとに管理します。</p>
            <ul>
                <li>行をタップすると、カテゴリと本文の編集欄が開きます。</li>
                <li>並び順はそのまま画面上の表示順になります (上から順に保存)。</li>
                <li>本文が空欄・「この行を削除する」チェックONの行は保存時に削除されます。</li>
                <li>グループを空にすると、コード内の既定値 (SPEC 準拠) にフォールバックします。「既定値に戻す」で任意のグループをリセット可能。</li>
            </ul>
        ',
    ])

    @if (session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.talk-quick-replies.update') }}" id="tqr-form">
        @csrf
        @method('PUT')

        <section class="admin-card admin-card-wide tqr-panel">
            {{-- フィルタチップ（役割で絞り込み） --}}
            <div class="tqr-toolbar" data-tqr-filters>
                <button type="button" class="tqr-filter-chip is-active" data-tqr-filter="all">
                    すべて <strong>{{ $totalRows }}</strong>
                </button>
                <button type="button" class="tqr-filter-chip" data-tqr-filter="cast">
                    キャスト <strong>{{ $castRows }}</strong>
                </button>
                <button type="button" class="tqr-filter-chip" data-tqr-filter="shop">
                    店舗 <strong>{{ $shopRows }}</strong>
                </button>
                <div class="tqr-toolbar__spacer"></div>
                <button type="button" class="tqr-mode-btn" data-tqr-expand-all>すべて開く</button>
            </div>

            @foreach($groups as $group)
                @php
                    $groupKey = $group['owner_type'] . '|' . $group['status_code'];
                    $isDefault = collect($group['rows'])->every(fn ($r) => !empty($r['is_default']));
                @endphp
                <div class="tqr-group"
                     data-tqr-group
                     data-owner="{{ $group['owner_type'] }}">
                    <div class="tqr-group__title">
                        <span class="tqr-group__owner tqr-group__owner--{{ $group['owner_type'] }}">{{ $group['owner_label'] }}</span>
                        <span class="tqr-group__status">{{ $group['status_label'] }}</span>
                        <span class="tqr-group__count" data-tqr-group-count>{{ count($group['rows']) }}件</span>
                        @if($isDefault)
                            <span class="tqr-group__default-flag">既定値表示中</span>
                        @endif
                        <span class="tqr-group__actions">
                            <button type="button" class="tqr-add-btn" data-group-key="{{ $groupKey }}">
                                <i class="fas fa-plus"></i> 追加
                            </button>
                            <button type="submit"
                                    form="tqr-reset-{{ $group['owner_type'] }}-{{ $group['status_code'] }}"
                                    class="tqr-reset-btn"
                                    onclick="return confirm('このグループの登録内容をすべて削除し、既定値に戻します。よろしいですか？')">
                                <i class="fas fa-rotate-left"></i> 既定値に戻す
                            </button>
                        </span>
                    </div>

                    <ul class="tqr-list" data-group-list="{{ $groupKey }}">
                        @foreach($group['rows'] as $index => $row)
                            @php
                                $body = (string) $row['body'];
                                $bodyPreview = mb_strimwidth($body, 0, $previewLimit, '…');
                                $categoryLabel = $categories[$row['category']] ?? $row['category'];
                            @endphp
                            <li class="tqr-item"
                                data-tqr-row
                                data-owner="{{ $group['owner_type'] }}">
                                <div class="tqr-item__row">
                                    <button type="button" class="tqr-item__label" data-item-toggle>
                                        <span class="tqr-item__idx">{{ $index + 1 }}</span>
                                        <span class="tqr-item__cat" data-tqr-cat-label>{{ $categoryLabel }}</span>
                                        <span class="tqr-item__preview" data-tqr-preview>{{ $bodyPreview !== '' ? $bodyPreview : '（本文未入力）' }}</span>
                                        <span class="tqr-item__mark tqr-item__mark--delete" data-tqr-delete-mark hidden>削除予定</span>
                                    </button>
                                </div>

                                <div class="tqr-item__panel" hidden>
                                    @if(!empty($row['id']))
                                        <input type="hidden" name="groups[{{ $groupKey }}][{{ $index }}][id]" value="{{ $row['id'] }}">
                                    @endif

                                    <label class="tqr-item__field">
                                        <span class="tqr-item__field-label">カテゴリ</span>
                                        <select name="groups[{{ $groupKey }}][{{ $index }}][category]" class="tqr-item__category" data-tqr-category>
                                            @foreach($categories as $cKey => $cLabel)
                                                <option value="{{ $cKey }}" @selected($row['category'] === $cKey)>{{ $cLabel }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="tqr-item__field">
                                        <span class="tqr-item__field-label">本文（最長 400 文字、改行OK。空欄で削除扱い）</span>
                                        <textarea name="groups[{{ $groupKey }}][{{ $index }}][body]"
                                                  rows="3"
                                                  maxlength="400"
                                                  data-tqr-body
                                                  placeholder="本文（空欄で削除）">{{ $row['body'] }}</textarea>
                                    </label>

                                    <label class="tqr-item__delete">
                                        <input type="hidden" name="groups[{{ $groupKey }}][{{ $index }}][delete]" value="0">
                                        <input type="checkbox" name="groups[{{ $groupKey }}][{{ $index }}][delete]" value="1" data-tqr-delete>
                                        <span>この行を削除する（保存時に反映）</span>
                                    </label>

                                    <div class="tqr-item__actions">
                                        <button type="button" class="tqr-item__close" data-item-cancel>閉じる</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="tqr-list__empty" id="tqr-empty-row" hidden>条件に一致する定型文はありません。</div>
        </section>

        <div class="tqr-save-bar">
            <button type="submit" class="btn-action manage">
                <i class="fas fa-floppy-disk"></i> 全グループを保存する
            </button>
        </div>
    </form>

    {{-- 「既定値に戻す」用のサブフォーム（1グループごと、メイン form の外に置く） --}}
    @foreach($groups as $group)
        <form id="tqr-reset-{{ $group['owner_type'] }}-{{ $group['status_code'] }}"
              method="POST"
              action="{{ route('admin.talk-quick-replies.reset') }}"
              style="display:none;">
            @csrf
            <input type="hidden" name="owner_type" value="{{ $group['owner_type'] }}">
            <input type="hidden" name="status_code" value="{{ $group['status_code'] }}">
        </form>
    @endforeach

    {{-- 「追加」ボタン用のテンプレート行 --}}
    <template id="tqr-row-template">
        <li class="tqr-item" data-tqr-row>
            <div class="tqr-item__row">
                <button type="button" class="tqr-item__label" data-item-toggle>
                    <span class="tqr-item__idx"></span>
                    <span class="tqr-item__cat" data-tqr-cat-label>{{ $categories[array_key_first($categories)] ?? '' }}</span>
                    <span class="tqr-item__preview" data-tqr-preview>（本文未入力）</span>
                    <span class="tqr-item__mark tqr-item__mark--delete" data-tqr-delete-mark hidden>削除予定</span>
                </button>
            </div>
            <div class="tqr-item__panel">
                <label class="tqr-item__field">
                    <span class="tqr-item__field-label">カテゴリ</span>
                    <select name="__NAME__[category]" class="tqr-item__category" data-tqr-category>
                        @foreach($categories as $cKey => $cLabel)
                            <option value="{{ $cKey }}">{{ $cLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="tqr-item__field">
                    <span class="tqr-item__field-label">本文（最長 400 文字、改行OK。空欄で削除扱い）</span>
                    <textarea name="__NAME__[body]" rows="3" maxlength="400" data-tqr-body placeholder="本文（空欄で削除）"></textarea>
                </label>
                <label class="tqr-item__delete">
                    <input type="hidden" name="__NAME__[delete]" value="0">
                    <input type="checkbox" name="__NAME__[delete]" value="1" data-tqr-delete>
                    <span>この行を削除する（保存時に反映）</span>
                </label>
                <div class="tqr-item__actions">
                    <button type="button" class="tqr-item__close" data-item-cancel>閉じる</button>
                </div>
            </div>
        </li>
    </template>
</div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        'use strict';

        // ---- タップで編集パネル開閉 ----
        function bindToggle(li) {
            var toggleBtn = li.querySelector('[data-item-toggle]');
            var cancelBtn = li.querySelector('[data-item-cancel]');
            var panel = li.querySelector('.tqr-item__panel');
            if (!toggleBtn || !panel) return;

            toggleBtn.addEventListener('click', function () {
                var opening = panel.hidden;
                if (opening) {
                    panel.hidden = false;
                    li.classList.add('is-open');
                    var ta = panel.querySelector('textarea');
                    if (ta) { window.setTimeout(function () { ta.focus(); }, 30); }
                } else {
                    panel.hidden = true;
                    li.classList.remove('is-open');
                }
            });
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    panel.hidden = true;
                    li.classList.remove('is-open');
                });
            }
        }

        // ---- 行内入力に応じてヘッダのカテゴリ/本文プレビュー/削除マークを更新 ----
        function bindLivePreview(li) {
            var catSelect = li.querySelector('[data-tqr-category]');
            var bodyInput = li.querySelector('[data-tqr-body]');
            var delInput = li.querySelector('[data-tqr-delete]');
            var catLabel = li.querySelector('[data-tqr-cat-label]');
            var preview = li.querySelector('[data-tqr-preview]');
            var delMark = li.querySelector('[data-tqr-delete-mark]');

            if (catSelect && catLabel) {
                catSelect.addEventListener('change', function () {
                    var opt = catSelect.options[catSelect.selectedIndex];
                    catLabel.textContent = opt ? opt.text : '';
                });
            }
            if (bodyInput && preview) {
                var limit = 42;
                var update = function () {
                    var val = (bodyInput.value || '').trim();
                    if (val === '') {
                        preview.textContent = '（本文未入力）';
                        preview.classList.add('is-empty');
                    } else {
                        preview.textContent = val.length > limit ? val.slice(0, limit) + '…' : val;
                        preview.classList.remove('is-empty');
                    }
                };
                bodyInput.addEventListener('input', update);
                update();
            }
            if (delInput && delMark) {
                var syncDelete = function () {
                    delMark.hidden = !delInput.checked;
                    li.classList.toggle('is-marked-delete', delInput.checked);
                };
                delInput.addEventListener('change', syncDelete);
                syncDelete();
            }
        }

        // 初期化：既存の全アイテム
        document.querySelectorAll('.tqr-item').forEach(function (li) {
            bindToggle(li);
            bindLivePreview(li);
        });

        // ---- 「すべて開く／すべて閉じる」トグル ----
        var expandAllBtn = document.querySelector('[data-tqr-expand-all]');
        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', function () {
                var items = document.querySelectorAll('.tqr-item');
                var anyClosed = false;
                items.forEach(function (li) {
                    var p = li.querySelector('.tqr-item__panel');
                    if (p && p.hidden) anyClosed = true;
                });
                items.forEach(function (li) {
                    var p = li.querySelector('.tqr-item__panel');
                    if (!p) return;
                    p.hidden = !anyClosed;
                    li.classList.toggle('is-open', anyClosed);
                });
                expandAllBtn.textContent = anyClosed ? 'すべて閉じる' : 'すべて開く';
            });
        }

        // ---- フィルタチップ（役割で絞り込み） ----
        var chips = document.querySelectorAll('[data-tqr-filters] [data-tqr-filter]');
        var emptyRow = document.getElementById('tqr-empty-row');
        var groups = document.querySelectorAll('[data-tqr-group]');
        var state = { filter: 'all' };

        function applyFilter() {
            var visibleTotal = 0;
            document.querySelectorAll('[data-tqr-row]').forEach(function (r) {
                var show = state.filter === 'all' || r.dataset.owner === state.filter;
                r.hidden = !show;
                if (show) visibleTotal++;
            });
            groups.forEach(function (g) {
                var show = state.filter === 'all' || g.dataset.owner === state.filter;
                g.hidden = !show;
            });
            if (emptyRow) emptyRow.hidden = visibleTotal !== 0;
        }
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                state.filter = c.getAttribute('data-tqr-filter') || 'all';
                chips.forEach(function (cc) { cc.classList.toggle('is-active', cc === c); });
                applyFilter();
            });
        });
        applyFilter();

        // ---- 「追加」でテンプレートから新規行を挿入 ----
        var template = document.getElementById('tqr-row-template');
        document.querySelectorAll('.tqr-add-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!template) return;
                var groupKey = btn.getAttribute('data-group-key');
                var list = document.querySelector('[data-group-list="' + CSS.escape(groupKey) + '"]');
                if (!list) return;
                var index = list.querySelectorAll('.tqr-item').length;
                var frag = template.content.cloneNode(true);
                // Fix name attributes
                frag.querySelectorAll('[name]').forEach(function (el) {
                    var name = el.getAttribute('name').replace(/__NAME__/g, 'groups[' + groupKey + '][' + index + ']');
                    el.setAttribute('name', name);
                });
                var li = frag.querySelector('.tqr-item');
                if (!li) return;
                // Set row index chip
                var idxEl = li.querySelector('.tqr-item__idx');
                if (idxEl) idxEl.textContent = String(index + 1);
                // Ensure panel is closed initially, then auto-open after append
                var panel = li.querySelector('.tqr-item__panel');
                if (panel) panel.hidden = true;
                list.appendChild(li);

                bindToggle(li);
                bindLivePreview(li);

                // Auto-open the new row for immediate typing
                if (panel) {
                    panel.hidden = false;
                    li.classList.add('is-open');
                    var ta = li.querySelector('textarea');
                    if (ta) { window.setTimeout(function () { ta.focus(); }, 30); }
                }

                // Update group count
                var group = btn.closest('[data-tqr-group]');
                if (group) {
                    var cnt = group.querySelector('[data-tqr-group-count]');
                    if (cnt) cnt.textContent = list.querySelectorAll('.tqr-item').length + '件';
                }
                applyFilter();
            });
        });
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== トーククイック定型文マスタ：シンプル版 ===== */

    .tqr-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- ツールバー（フィルタチップ） ---- */
    .tqr-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .tqr-toolbar__spacer { flex: 1 1 auto; }
    .tqr-filter-chip {
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
    .tqr-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text, #f5f5f5);
        font-variant-numeric: tabular-nums;
    }
    .tqr-filter-chip.is-active {
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.14));
        color: #c4b5fd;
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45));
    }
    .tqr-filter-chip.is-active strong { color: #c4b5fd; }
    .tqr-mode-btn {
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.22));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .tqr-mode-btn:hover { color: var(--admin-text, #fff); border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45)); }

    /* ---- グループ見出し ---- */
    .tqr-group[hidden] { display: none; }
    .tqr-group__title {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--admin-sub, #a1a1aa);
        margin: 4px 0 6px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .tqr-group__owner {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .tqr-group__owner--cast { background: rgba(214, 112, 162, 0.20); color: #f0a6c4; border: 1px solid rgba(214, 112, 162, 0.40); }
    .tqr-group__owner--shop { background: rgba(139, 92, 246, 0.20); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.40); }
    .tqr-group__status {
        color: var(--admin-text, #f5f5f5);
        font-weight: 700;
    }
    .tqr-group__count {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--admin-muted, #8b6e77);
        padding: 1px 8px;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.20));
        background: var(--admin-surface-alt, rgba(255, 255, 255, 0.04));
    }
    .tqr-group__default-flag {
        font-size: 0.7rem;
        color: #fbbf24;
        padding: 1px 8px;
        border-radius: 999px;
        border: 1px solid rgba(251, 191, 36, 0.4);
        background: rgba(251, 191, 36, 0.10);
    }
    .tqr-group__actions {
        margin-left: auto;
        display: inline-flex;
        gap: 6px;
    }
    .tqr-add-btn,
    .tqr-reset-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        font-size: 0.74rem;
        font-weight: 700;
        border-radius: 8px;
        cursor: pointer;
    }
    .tqr-add-btn {
        border: 1px solid rgba(168, 85, 247, 0.45);
        background: rgba(168, 85, 247, 0.10);
        color: #c4b5fd;
    }
    .tqr-add-btn:hover { background: rgba(168, 85, 247, 0.22); }
    .tqr-reset-btn {
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: transparent;
        color: #a1a1aa;
    }
    .tqr-reset-btn:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }

    /* ---- 一覧 ---- */
    .tqr-list {
        list-style: none;
        margin: 0 0 16px;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .tqr-item {
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        border-radius: 10px;
        background: var(--admin-card, #1a1a1a);
        color: var(--admin-text, #f5f5f5);
        overflow: hidden;
    }
    .tqr-item.is-open {
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.55));
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.10));
    }
    .tqr-item.is-marked-delete {
        opacity: 0.55;
        border-color: rgba(239, 68, 68, 0.5);
    }
    .tqr-item.is-marked-delete .tqr-item__preview { text-decoration: line-through; }

    .tqr-item__row { display: flex; align-items: center; }
    .tqr-item__label {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        min-height: 44px;
        border: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .tqr-item__label:hover { background: rgba(168, 85, 247, 0.08); }

    .tqr-item__idx {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: rgba(139, 92, 246, 0.20);
        color: #c4b5fd;
        font-size: 0.72rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .tqr-item__cat {
        flex: 0 0 auto;
        font-size: 0.7rem;
        font-weight: 700;
        color: #c4b5fd;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid rgba(168, 85, 247, 0.35);
        background: rgba(168, 85, 247, 0.10);
    }
    .tqr-item__preview {
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.88rem;
        color: var(--admin-text, #f5f5f5);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tqr-item__preview.is-empty { color: var(--admin-muted, #8b6e77); font-style: italic; }
    .tqr-item__mark {
        flex: 0 0 auto;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid;
    }
    .tqr-item__mark--delete {
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.4);
        background: rgba(239, 68, 68, 0.10);
    }

    /* ---- 編集パネル ---- */
    .tqr-item__panel {
        border-top: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: rgba(0, 0, 0, 0.15);
    }
    .tqr-item__panel[hidden] { display: none; }
    .tqr-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .tqr-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub, #a1a1aa);
    }
    .tqr-item__category {
        padding: 8px 10px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.30));
        background: rgba(20, 14, 24, 0.6);
        color: var(--admin-text, #f5f5f5);
        border-radius: 8px;
        font-size: 0.9rem;
        max-width: 260px;
    }
    .tqr-item__field textarea {
        width: 100%;
        min-height: 68px;
        padding: 10px 12px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.30));
        border-radius: 10px;
        background: rgba(20, 14, 24, 0.6);
        font-size: 16px;
        color: var(--admin-text, #f5f5f5);
        resize: vertical;
        font-family: inherit;
        line-height: 1.5;
        box-sizing: border-box;
    }
    .tqr-item__field textarea:focus,
    .tqr-item__category:focus {
        outline: none;
        border-color: #a78bfa;
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
    }
    .tqr-item__delete {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .tqr-item__delete input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }

    .tqr-item__actions { display: flex; justify-content: flex-end; }
    .tqr-item__close {
        min-height: 36px;
        padding: 6px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(255, 255, 255, 0.18));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.82rem;
        cursor: pointer;
    }
    .tqr-item__close:hover { color: #fff; border-color: rgba(255, 255, 255, 0.4); }

    .tqr-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line, rgba(168, 85, 247, 0.22));
        border-radius: 10px;
    }

    /* ---- 保存バー（下部固定） ---- */
    .tqr-save-bar {
        position: sticky;
        bottom: 0;
        background: linear-gradient(180deg, rgba(10,10,10,0), rgba(10,10,10,0.96) 35%);
        padding: 16px 0 8px;
        margin-top: 8px;
        text-align: right;
        z-index: 5;
    }

    /* ---- ライトテーマ保険 ---- */
    @media (prefers-color-scheme: light) {
        .tqr-item {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.14);
        }
        .tqr-item__preview { color: #241f33; }
        .tqr-item__panel { background: #faf9fd; }
        .tqr-item__field textarea,
        .tqr-item__category {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.20);
        }
        .tqr-filter-chip strong { color: #241f33; }
        .tqr-group__status { color: #241f33; }
        .tqr-list__empty { color: #6b6478; }
        .tqr-save-bar { background: linear-gradient(180deg, rgba(255,255,255,0), rgba(250,249,253,0.96) 35%); }
    }

    /* ---- スマホ ---- */
    @media (max-width: 640px) {
        .tqr-group__actions { margin-left: 0; width: 100%; }
        .tqr-item__label { flex-wrap: wrap; }
        .tqr-item__preview { width: 100%; }
    }
</style>
@endpush
