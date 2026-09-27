@extends('layouts.admin')

@section('title', 'オコジョガイド設定')

@section('content')
@php
    $totalScreens = 0;
    $enabledScreens = 0;
    $emptyMessageScreens = 0;
    foreach ($grouped as $rows) {
        foreach ($rows as $row) {
            $totalScreens++;
            if (!empty($row['enabled'])) $enabledScreens++;
            if (empty(trim((string)($row['message'] ?? '')))) $emptyMessageScreens++;
        }
    }
    $disabledScreens = $totalScreens - $enabledScreens;
@endphp
<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'CHARACTER GUIDE',
        'title' => 'オコジョガイド設定',
        'info' => '
            <p>各画面の右下に表示されるオコジョガイドの<strong>表示／非表示</strong>と<strong>セリフ</strong>を画面ごとに設定します。</p>
            <ul>
                <li>画面名をタップすると、表示ON/OFF とセリフの入力欄が開きます。</li>
                <li>表示ON かつセリフが入力されている画面のみ、オコジョと吹き出しが表示されます。</li>
                <li>セリフが空欄、または表示OFF の画面では、オコジョは出ません（デフォルト文言はありません）。</li>
                <li>最後に画面下の<strong>「設定を保存する」</strong>で全体を一括保存します。</li>
            </ul>
        ',
    ])

    @if (session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.character-guide.update') }}" id="cg-form">
        @csrf
        @method('PUT')

        <section class="admin-card admin-card-wide cg-panel">
            {{-- 上部ツールバー：フィルタチップ（件数付き） --}}
            <div class="cg-toolbar" data-cg-filters>
                <button type="button" class="cg-filter-chip is-active" data-cg-filter="all">
                    すべて <strong>{{ $totalScreens }}</strong>
                </button>
                <button type="button" class="cg-filter-chip" data-cg-filter="on">
                    表示ON <strong>{{ $enabledScreens }}</strong>
                </button>
                <button type="button" class="cg-filter-chip" data-cg-filter="off">
                    表示OFF <strong>{{ $disabledScreens }}</strong>
                </button>
                <button type="button" class="cg-filter-chip {{ $emptyMessageScreens > 0 ? 'is-warn' : '' }}" data-cg-filter="empty">
                    セリフ未設定 <strong>{{ $emptyMessageScreens }}</strong>
                </button>
                <div class="cg-toolbar__spacer"></div>
                <button type="button" class="cg-mode-btn" data-cg-expand-all>すべて開く</button>
            </div>

            {{-- グループごとに一覧を並べる --}}
            @foreach($grouped as $groupKey => $rows)
                <div class="cg-group">
                    <div class="cg-group__title">
                        {{ $groupLabels[$groupKey] ?? $groupKey }}
                        <span class="cg-group__count">{{ count($rows) }}画面</span>
                    </div>
                    <ul class="cg-list">
                        @foreach($rows as $row)
                            @php
                                $isOn = !empty($row['enabled']);
                                $hasMessage = trim((string) ($row['message'] ?? '')) !== '';
                            @endphp
                            <li class="cg-item"
                                data-cg-row
                                data-status="{{ $isOn ? 'on' : 'off' }}"
                                data-message="{{ $hasMessage ? 'filled' : 'empty' }}">
                                <div class="cg-item__row">
                                    <button type="button" class="cg-item__label" data-item-toggle>
                                        <span class="cg-item__name">{{ $row['label'] }}</span>
                                        <span class="cg-item__badges" data-cg-badges>
                                            <span class="cg-badge {{ $isOn ? 'is-on' : 'is-off' }}" data-cg-badge-status>
                                                {{ $isOn ? '表示ON' : '表示OFF' }}
                                            </span>
                                            <span class="cg-badge {{ $hasMessage ? 'is-msg' : 'is-empty' }}" data-cg-badge-msg>
                                                {{ $hasMessage ? 'セリフあり' : 'セリフ未設定' }}
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div class="cg-item__panel" hidden>
                                    <div class="cg-item__route">
                                        <code>{{ $row['route_name'] }}</code>
                                    </div>

                                    {{-- 表示ON/OFF トグル --}}
                                    <label class="cg-item__toggle">
                                        <input type="hidden" name="settings[{{ $row['route_name'] }}][enabled]" value="0">
                                        <input type="checkbox"
                                               name="settings[{{ $row['route_name'] }}][enabled]"
                                               value="1"
                                               data-cg-enabled
                                               @checked($isOn)>
                                        <span class="cg-item__toggle-track" aria-hidden="true">
                                            <span class="cg-item__toggle-thumb"></span>
                                        </span>
                                        <span class="cg-item__toggle-label">この画面でオコジョを表示する</span>
                                    </label>

                                    {{-- セリフ入力 --}}
                                    <label class="cg-item__field">
                                        <span class="cg-item__field-label">セリフ（最長 500 文字、改行OK）</span>
                                        <textarea name="settings[{{ $row['route_name'] }}][message]"
                                                  rows="2"
                                                  maxlength="500"
                                                  data-cg-message
                                                  placeholder="この画面で表示するセリフを入力（空欄なら吹き出しを表示しません）">{{ $row['message'] }}</textarea>
                                    </label>

                                    <div class="cg-item__actions">
                                        <button type="button" class="cg-item__close" data-item-cancel>閉じる</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="cg-list__empty" id="cg-empty-row" hidden>条件に一致する画面はありません。</div>
        </section>

        {{-- 保存バー（画面下部固定） --}}
        <div class="cg-save-bar">
            <button type="submit" class="btn-action manage">
                <i class="fas fa-floppy-disk"></i> 設定を保存する
            </button>
        </div>
    </form>
</div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        // ---- タップで編集パネル開閉 ----
        function closeAllPanels(except) {
            document.querySelectorAll('.cg-item.is-open').forEach(function (li) {
                if (li === except) return;
                li.classList.remove('is-open');
                var p = li.querySelector('.cg-item__panel');
                if (p) p.hidden = true;
            });
        }

        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.cg-item');
                if (!li) return;
                var panel = li.querySelector('.cg-item__panel');
                if (!panel) return;
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
        });
        document.querySelectorAll('[data-item-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.cg-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.cg-item__panel');
                if (panel) panel.hidden = true;
            });
        });

        // ---- 「すべて開く／すべて閉じる」トグル ----
        var expandAllBtn = document.querySelector('[data-cg-expand-all]');
        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', function () {
                var items = document.querySelectorAll('.cg-item');
                var anyClosed = false;
                items.forEach(function (li) {
                    var panel = li.querySelector('.cg-item__panel');
                    if (panel && panel.hidden) anyClosed = true;
                });
                items.forEach(function (li) {
                    var panel = li.querySelector('.cg-item__panel');
                    if (!panel) return;
                    panel.hidden = !anyClosed;
                    li.classList.toggle('is-open', anyClosed);
                });
                expandAllBtn.textContent = anyClosed ? 'すべて閉じる' : 'すべて開く';
            });
        }

        // ---- 行内の入力に応じてバッジをリアルタイム更新 ----
        document.querySelectorAll('[data-cg-row]').forEach(function (row) {
            var enabledInput = row.querySelector('[data-cg-enabled]');
            var messageInput = row.querySelector('[data-cg-message]');
            var badgeStatus = row.querySelector('[data-cg-badge-status]');
            var badgeMsg = row.querySelector('[data-cg-badge-msg]');
            if (enabledInput) {
                enabledInput.addEventListener('change', function () {
                    var on = enabledInput.checked;
                    row.dataset.status = on ? 'on' : 'off';
                    if (badgeStatus) {
                        badgeStatus.textContent = on ? '表示ON' : '表示OFF';
                        badgeStatus.classList.toggle('is-on', on);
                        badgeStatus.classList.toggle('is-off', !on);
                    }
                });
            }
            if (messageInput) {
                messageInput.addEventListener('input', function () {
                    var filled = messageInput.value.trim() !== '';
                    row.dataset.message = filled ? 'filled' : 'empty';
                    if (badgeMsg) {
                        badgeMsg.textContent = filled ? 'セリフあり' : 'セリフ未設定';
                        badgeMsg.classList.toggle('is-msg', filled);
                        badgeMsg.classList.toggle('is-empty', !filled);
                    }
                });
            }
        });

        // ---- フィルタチップ ----
        var rows = document.querySelectorAll('[data-cg-row]');
        var chips = document.querySelectorAll('[data-cg-filters] [data-cg-filter]');
        var emptyRow = document.getElementById('cg-empty-row');
        var groups = document.querySelectorAll('.cg-group');
        var state = { filter: 'all' };

        function applyFilter() {
            var visibleTotal = 0;
            rows.forEach(function (r) {
                var show = true;
                if (state.filter === 'on') show = r.dataset.status === 'on';
                else if (state.filter === 'off') show = r.dataset.status === 'off';
                else if (state.filter === 'empty') show = r.dataset.message === 'empty';
                r.hidden = !show;
                if (show) visibleTotal++;
            });
            // Hide group headings if all their rows are hidden
            groups.forEach(function (g) {
                var anyVisible = Array.prototype.some.call(g.querySelectorAll('[data-cg-row]'), function (r) { return !r.hidden; });
                g.hidden = !anyVisible;
            });
            if (emptyRow) emptyRow.hidden = visibleTotal !== 0;
        }
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                state.filter = c.getAttribute('data-cg-filter') || 'all';
                chips.forEach(function (cc) { cc.classList.toggle('is-active', cc === c); });
                applyFilter();
            });
        });
        applyFilter();
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== オコジョガイド設定：シンプル版（マスタ／NG語と同じ UI パターン） ===== */

    .cg-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- ツールバー（フィルタチップ） ---- */
    .cg-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .cg-toolbar__spacer { flex: 1 1 auto; }
    .cg-filter-chip {
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
    .cg-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text, #f5f5f5);
        font-variant-numeric: tabular-nums;
    }
    .cg-filter-chip.is-active {
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.14));
        color: #c4b5fd;
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45));
    }
    .cg-filter-chip.is-active strong { color: #c4b5fd; }
    .cg-filter-chip.is-warn {
        border-color: rgba(251, 191, 36, 0.5);
        color: #fbbf24;
    }
    .cg-filter-chip.is-warn strong { color: #fbbf24; }
    .cg-filter-chip.is-warn.is-active {
        background: rgba(251, 191, 36, 0.15);
    }
    .cg-mode-btn {
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.22));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        cursor: pointer;
    }
    .cg-mode-btn:hover { color: var(--admin-text, #fff); border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.45)); }

    /* ---- グループ見出し ---- */
    .cg-group[hidden] { display: none; }
    .cg-group__title {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--admin-sub, #a1a1aa);
        margin: 4px 0 6px;
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    .cg-group__count {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--admin-muted, #8b6e77);
        padding: 1px 8px;
        border-radius: 999px;
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.20));
        background: var(--admin-surface-alt, rgba(255, 255, 255, 0.04));
    }

    /* ---- 一覧 ---- */
    .cg-list {
        list-style: none;
        margin: 0 0 16px;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .cg-item {
        border: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        border-radius: 10px;
        background: var(--admin-card, #1a1a1a);
        color: var(--admin-text, #f5f5f5);
        overflow: hidden;
    }
    .cg-item.is-open {
        border-color: var(--admin-primary-border, rgba(168, 85, 247, 0.55));
        background: var(--admin-primary-soft, rgba(168, 85, 247, 0.10));
    }
    .cg-item__row { display: flex; align-items: center; }
    .cg-item__label {
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
    .cg-item__label:hover { background: rgba(168, 85, 247, 0.08); }
    .cg-item__name {
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--admin-text, #f5f5f5);
        word-break: break-word;
    }
    .cg-item__badges {
        display: inline-flex;
        gap: 4px;
        flex: 0 0 auto;
    }
    .cg-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid;
        white-space: nowrap;
    }
    .cg-badge.is-on { color: #6ee7b7; border-color: rgba(110, 231, 183, 0.4); background: rgba(110, 231, 183, 0.10); }
    .cg-badge.is-off { color: #a1a1aa; border-color: rgba(161, 161, 170, 0.35); background: rgba(161, 161, 170, 0.08); }
    .cg-badge.is-msg { color: #c4b5fd; border-color: rgba(168, 85, 247, 0.4); background: rgba(168, 85, 247, 0.10); }
    .cg-badge.is-empty { color: #fbbf24; border-color: rgba(251, 191, 36, 0.4); background: rgba(251, 191, 36, 0.10); }

    /* ---- 編集パネル ---- */
    .cg-item__panel {
        border-top: 1px solid var(--admin-line, rgba(168, 85, 247, 0.16));
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: rgba(0, 0, 0, 0.15);
    }
    .cg-item__panel[hidden] { display: none; }
    .cg-item__route code {
        display: inline-block;
        font-size: 0.7rem;
        color: var(--admin-sub, #a1a1aa);
        background: var(--admin-surface-alt, rgba(255, 255, 255, 0.05));
        border: 1px solid var(--admin-line, rgba(255, 255, 255, 0.08));
        padding: 2px 8px;
        border-radius: 6px;
    }

    /* ---- トグル（既存スタイル維持） ---- */
    .cg-item__toggle {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        user-select: none;
    }
    .cg-item__toggle input[type="checkbox"] {
        position: absolute;
        width: 0;
        height: 0;
        opacity: 0;
        pointer-events: none;
    }
    .cg-item__toggle-track {
        width: 44px;
        height: 24px;
        background: rgba(255,255,255,0.18);
        border-radius: 999px;
        position: relative;
        transition: background 0.2s ease;
        flex: 0 0 auto;
    }
    .cg-item__toggle-thumb {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        transition: transform 0.2s ease;
    }
    .cg-item__toggle input[type="checkbox"]:checked ~ .cg-item__toggle-track {
        background: #8b5cf6;
    }
    .cg-item__toggle input[type="checkbox"]:checked ~ .cg-item__toggle-track .cg-item__toggle-thumb {
        transform: translateX(20px);
    }
    .cg-item__toggle input[type="checkbox"]:focus-visible ~ .cg-item__toggle-track {
        outline: 2px solid #a78bfa;
        outline-offset: 2px;
    }
    .cg-item__toggle-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--admin-text, #f5f5f5);
    }

    /* ---- セリフ入力 ---- */
    .cg-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .cg-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub, #a1a1aa);
    }
    .cg-item__field textarea {
        width: 100%;
        min-height: 60px;
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
    .cg-item__field textarea:focus {
        outline: none;
        border-color: #a78bfa;
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
    }

    .cg-item__actions { display: flex; justify-content: flex-end; }
    .cg-item__close {
        min-height: 36px;
        padding: 6px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line, rgba(255, 255, 255, 0.18));
        background: transparent;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.82rem;
        cursor: pointer;
    }
    .cg-item__close:hover { color: #fff; border-color: rgba(255, 255, 255, 0.4); }

    .cg-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub, #a1a1aa);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line, rgba(168, 85, 247, 0.22));
        border-radius: 10px;
    }

    /* ---- 保存バー（下部固定） ---- */
    .cg-save-bar {
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
        .cg-item {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.14);
        }
        .cg-item__name { color: #241f33; }
        .cg-item__panel { background: #faf9fd; }
        .cg-item__field textarea {
            background: #ffffff;
            color: #241f33;
            border-color: rgba(76, 29, 149, 0.20);
        }
        .cg-filter-chip strong { color: #241f33; }
        .cg-list__empty { color: #6b6478; }
        .cg-save-bar { background: linear-gradient(180deg, rgba(255,255,255,0), rgba(250,249,253,0.96) 35%); }
    }

    /* ---- スマホ ---- */
    @media (max-width: 640px) {
        .cg-item__label { flex-wrap: wrap; }
        .cg-item__badges { width: 100%; margin-top: 4px; }
    }
</style>
@endpush
