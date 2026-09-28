@extends('layouts.admin')

@section('title', '通知・タスク仕様')

@section('content')
@php
    // Summary counters shared across tabs.
    $countTotal = function ($byGroup) {
        $total = 0;
        foreach ($byGroup ?? [] as $items) { $total += count($items); }
        return $total;
    };
    $countEnabled = function ($byGroup) {
        $enabled = 0;
        foreach ($byGroup ?? [] as $items) {
            foreach ($items as $it) { if (!empty($it['current_enabled'])) $enabled++; }
        }
        return $enabled;
    };

    $notifTotal = $countTotal($notificationsByGroup ?? []);
    $notifEnabled = $countEnabled($notificationsByGroup ?? []);
    $notifDisabled = $notifTotal - $notifEnabled;

    $remindTotal = $countTotal($remindersByGroup ?? []);

    $taskTotal = $countTotal($tasksByActor ?? []);
    $taskCountsByActor = [];
    foreach ($tasksByActor ?? [] as $actorLabel => $items) {
        $taskCountsByActor[$actorLabel] = count($items);
    }
@endphp

<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'NOTIFICATIONS & TASKS',
        'title' => '通知・タスク仕様',
        'info' => '
            <p>運営から送る通知、リマインダー通知、未済タスクの仕様を確認・変更できます。</p>
            <ul>
                <li><strong>通知</strong>：ON/OFF とタイトル・本文を編集</li>
                <li><strong>リマインダー</strong>：発火タイミング（日数／時間）と本文を編集</li>
                <li><strong>未済タスク</strong>：表示文言のみ編集（条件・解消条件は仕様固定）</li>
            </ul>
            <p>各行をタップすると編集パネルが開きます。編集後は行内の<strong>「保存」</strong>で反映されます。</p>
        ',
    ])

    @if (session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    {{-- タブ切り替え --}}
    <div class="spec-tabs" role="tablist">
        <a href="{{ route('admin.notification-spec.index', ['tab' => 'notifications']) }}"
           class="spec-tab {{ $tab === 'notifications' ? 'is-active' : '' }}" role="tab"
           aria-selected="{{ $tab === 'notifications' ? 'true' : 'false' }}">
            <i class="fas fa-bell"></i> 通知
            <span class="spec-tab__badge">{{ $notifEnabled }}/{{ $notifTotal }} 有効</span>
        </a>
        <a href="{{ route('admin.notification-spec.index', ['tab' => 'reminders']) }}"
           class="spec-tab {{ $tab === 'reminders' ? 'is-active' : '' }}" role="tab"
           aria-selected="{{ $tab === 'reminders' ? 'true' : 'false' }}">
            <i class="fas fa-clock-rotate-left"></i> リマインダー通知
            <span class="spec-tab__badge">{{ $remindTotal }} 件</span>
        </a>
        <a href="{{ route('admin.notification-spec.index', ['tab' => 'tasks']) }}"
           class="spec-tab {{ $tab === 'tasks' ? 'is-active' : '' }}" role="tab"
           aria-selected="{{ $tab === 'tasks' ? 'true' : 'false' }}">
            <i class="fas fa-list-check"></i> 未済タスク
            <span class="spec-tab__badge">{{ $taskTotal }} 件</span>
        </a>
    </div>

    {{-- 通知タブ --}}
    @if($tab === 'notifications')
        <section class="admin-card admin-card-wide ns-panel">
            <div class="ns-toolbar" data-ns-filters>
                <button type="button" class="ns-filter-chip is-active" data-ns-filter="all">
                    すべて <strong>{{ $notifTotal }}</strong>
                </button>
                <button type="button" class="ns-filter-chip" data-ns-filter="on">
                    有効 <strong>{{ $notifEnabled }}</strong>
                </button>
                <button type="button" class="ns-filter-chip" data-ns-filter="off">
                    無効 <strong>{{ $notifDisabled }}</strong>
                </button>
                <div class="ns-toolbar__spacer"></div>
                <button type="button" class="ns-mode-btn" data-ns-expand-all>すべて開く</button>
            </div>

            <p class="admin-note u-mb-12">
                トリガー条件は仕様として固定です。<strong>ON/OFF</strong>、<strong>タイトル</strong>、<strong>本文</strong>のみ編集できます。本文中の <code>{token}</code> は送信時に動的な値（店舗名・キャスト名・金額など）に置換されます。
            </p>

            @foreach($notificationsByGroup as $groupLabel => $items)
                <div class="ns-group">
                    <div class="ns-group__title">
                        {{ $groupLabel }}
                        <span class="ns-group__count">{{ count($items) }}件</span>
                    </div>
                    <ul class="ns-list">
                        @foreach($items as $item)
                            @php $isOn = !empty($item['current_enabled']); @endphp
                            <li class="ns-item"
                                id="{{ $item['key'] }}"
                                data-ns-row
                                data-status="{{ $isOn ? 'on' : 'off' }}">
                                <div class="ns-item__row">
                                    <button type="button" class="ns-item__label" data-item-toggle>
                                        <span class="ns-item__name">{{ $item['label'] }}</span>
                                        <span class="ns-item__badges" data-ns-badges>
                                            <span class="ns-badge {{ $isOn ? 'is-on' : 'is-off' }}" data-ns-badge-status>
                                                {{ $isOn ? '有効' : '無効' }}
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div class="ns-item__panel" hidden>
                                    <div class="ns-item__meta">
                                        <span class="ns-item__meta-label"><i class="fas fa-circle-exclamation"></i> トリガー条件</span>
                                        <span class="ns-item__meta-value">{{ $item['condition'] }}</span>
                                    </div>

                                    <form method="POST" action="{{ route('admin.notification-spec.notifications.update', $item['key']) }}" class="ns-item__form">
                                        @csrf @method('PUT')

                                        <label class="ns-item__toggle">
                                            <input type="checkbox" name="enabled" value="1" data-ns-enabled @checked($isOn)>
                                            <span class="ns-item__toggle-track" aria-hidden="true">
                                                <span class="ns-item__toggle-thumb"></span>
                                            </span>
                                            <span class="ns-item__toggle-label">この通知を送信する</span>
                                        </label>

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">通知タイトル</span>
                                            <input type="text" name="title" value="{{ $item['current_title'] }}" maxlength="255">
                                        </label>

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">本文</span>
                                            <textarea name="body" rows="3" maxlength="5000">{{ $item['current_body'] }}</textarea>
                                        </label>

                                        <div class="ns-item__actions">
                                            <button type="button" class="ns-item__close" data-item-cancel>閉じる</button>
                                            <button type="submit" class="btn-action manage">
                                                <i class="fas fa-floppy-disk"></i> 保存
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="ns-list__empty" data-ns-empty hidden>条件に一致する項目はありません。</div>
        </section>
    @endif

    {{-- リマインダータブ --}}
    @if($tab === 'reminders')
        <section class="admin-card admin-card-wide ns-panel">
            <div class="ns-toolbar" data-ns-filters>
                <button type="button" class="ns-filter-chip is-active" data-ns-filter="all">
                    すべて <strong>{{ $remindTotal }}</strong>
                </button>
                <div class="ns-toolbar__spacer"></div>
                <button type="button" class="ns-mode-btn" data-ns-expand-all>すべて開く</button>
            </div>

            <p class="admin-note u-mb-12">
                トリガー条件は仕様として固定です。<strong>発火タイミング（数値）</strong>と<strong>本文</strong>を変更できます。
            </p>

            @foreach($remindersByGroup as $groupLabel => $items)
                <div class="ns-group">
                    <div class="ns-group__title">
                        {{ $groupLabel }}
                        <span class="ns-group__count">{{ count($items) }}件</span>
                    </div>
                    <ul class="ns-list">
                        @foreach($items as $item)
                            <li class="ns-item"
                                id="{{ $item['key'] }}"
                                data-ns-row
                                data-status="on">
                                <div class="ns-item__row">
                                    <button type="button" class="ns-item__label" data-item-toggle>
                                        <span class="ns-item__name">{{ $item['label'] }}</span>
                                        <span class="ns-item__badges">
                                            <span class="ns-badge is-info">
                                                {{ $item['current_offset'] }}{{ $unitLabel($item['unit']) }}
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div class="ns-item__panel" hidden>
                                    <div class="ns-item__meta">
                                        <span class="ns-item__meta-label"><i class="fas fa-circle-exclamation"></i> トリガー条件</span>
                                        <span class="ns-item__meta-value">{{ $item['condition'] }}</span>
                                    </div>

                                    <form method="POST" action="{{ route('admin.notification-spec.reminders.update', $item['key']) }}" class="ns-item__form">
                                        @csrf @method('PUT')

                                        <label class="ns-item__field ns-item__field--inline">
                                            <span class="ns-item__field-label">発火タイミング</span>
                                            <span class="ns-item__inline">
                                                <input type="number" name="offset" value="{{ $item['current_offset'] }}" min="0" max="9999" class="ns-input-num" required>
                                                <span class="ns-item__suffix">{{ $unitLabel($item['unit']) }}</span>
                                                <small class="ns-item__hint">（デフォルト：{{ $item['default_offset'] }}{{ $unitLabel($item['unit']) }}）</small>
                                            </span>
                                        </label>

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">通知タイトル</span>
                                            <input type="text" name="title" value="{{ $item['current_title'] }}" maxlength="255">
                                        </label>

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">本文</span>
                                            <textarea name="body" rows="3" maxlength="5000">{{ $item['current_body'] }}</textarea>
                                        </label>

                                        <div class="ns-item__actions">
                                            <button type="button" class="ns-item__close" data-item-cancel>閉じる</button>
                                            <button type="submit" class="btn-action manage">
                                                <i class="fas fa-floppy-disk"></i> 保存
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="ns-list__empty" data-ns-empty hidden>条件に一致する項目はありません。</div>
        </section>
    @endif

    {{-- タスクタブ --}}
    @if($tab === 'tasks')
        <section class="admin-card admin-card-wide ns-panel">
            <div class="ns-toolbar" data-ns-filters>
                <button type="button" class="ns-filter-chip is-active" data-ns-filter="all">
                    すべて <strong>{{ $taskTotal }}</strong>
                </button>
                @foreach($taskCountsByActor as $actorLabel => $count)
                    <button type="button" class="ns-filter-chip" data-ns-filter="actor:{{ $actorLabel }}">
                        {{ $actorLabel }} <strong>{{ $count }}</strong>
                    </button>
                @endforeach
                <div class="ns-toolbar__spacer"></div>
                <button type="button" class="ns-mode-btn" data-ns-expand-all>すべて開く</button>
            </div>

            <p class="admin-note u-mb-12">
                タスクが<strong>発生する条件</strong>と<strong>解消する条件</strong>は仕様として固定（変更不可）です。各タスクの<strong>表示タイトル・説明文</strong>のみ変更できます。
            </p>

            @foreach($tasksByActor as $actorLabel => $items)
                <div class="ns-group" data-ns-actor-group="{{ $actorLabel }}">
                    <div class="ns-group__title">
                        <i class="fas fa-user-cog"></i> {{ $actorLabel }} 向けタスク
                        <span class="ns-group__count">{{ count($items) }}件</span>
                    </div>
                    <ul class="ns-list">
                        @foreach($items as $item)
                            <li class="ns-item"
                                id="{{ $item['key'] }}"
                                data-ns-row
                                data-actor="{{ $actorLabel }}"
                                data-status="on">
                                <div class="ns-item__row">
                                    <button type="button" class="ns-item__label" data-item-toggle>
                                        <span class="ns-item__name">{{ $item['label'] }}</span>
                                    </button>
                                </div>

                                <div class="ns-item__panel" hidden>
                                    <div class="ns-item__meta">
                                        <span class="ns-item__meta-label"><i class="fas fa-circle-exclamation"></i> 発生条件</span>
                                        <span class="ns-item__meta-value">{{ $item['condition'] }}</span>
                                    </div>
                                    <div class="ns-item__meta">
                                        <span class="ns-item__meta-label"><i class="fas fa-circle-check"></i> 解消条件</span>
                                        <span class="ns-item__meta-value">{{ $item['resolution'] }}</span>
                                    </div>

                                    <form method="POST" action="{{ route('admin.notification-spec.tasks.update', $item['key']) }}" class="ns-item__form">
                                        @csrf @method('PUT')

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">表示タイトル</span>
                                            <input type="text" name="title" value="{{ $item['current_title'] }}" maxlength="255">
                                        </label>

                                        <label class="ns-item__field">
                                            <span class="ns-item__field-label">説明文</span>
                                            <textarea name="body" rows="3" maxlength="5000">{{ $item['current_body'] }}</textarea>
                                        </label>

                                        <div class="ns-item__actions">
                                            <button type="button" class="ns-item__close" data-item-cancel>閉じる</button>
                                            <button type="submit" class="btn-action manage">
                                                <i class="fas fa-floppy-disk"></i> 保存
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="ns-list__empty" data-ns-empty hidden>条件に一致する項目はありません。</div>
        </section>
    @endif
</div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        // ---- Toggle per-item edit panels ----
        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ns-item');
                if (!li) return;
                var panel = li.querySelector('.ns-item__panel');
                if (!panel) return;
                var opening = panel.hidden;
                panel.hidden = !opening;
                li.classList.toggle('is-open', opening);
                if (opening) {
                    var firstInput = panel.querySelector('input[type="text"], input[type="number"], textarea');
                    if (firstInput) window.setTimeout(function () { firstInput.focus(); }, 30);
                }
            });
        });
        document.querySelectorAll('[data-item-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ns-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.ns-item__panel');
                if (panel) panel.hidden = true;
            });
        });

        // ---- Expand-all / Collapse-all toggle ----
        var expandAllBtn = document.querySelector('[data-ns-expand-all]');
        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', function () {
                var items = document.querySelectorAll('.ns-item');
                var anyClosed = false;
                items.forEach(function (li) {
                    if (li.hidden) return;
                    var panel = li.querySelector('.ns-item__panel');
                    if (panel && panel.hidden) anyClosed = true;
                });
                items.forEach(function (li) {
                    if (li.hidden) return;
                    var panel = li.querySelector('.ns-item__panel');
                    if (!panel) return;
                    panel.hidden = !anyClosed;
                    li.classList.toggle('is-open', anyClosed);
                });
                expandAllBtn.textContent = anyClosed ? 'すべて閉じる' : 'すべて開く';
            });
        }

        // ---- Real-time badge update as user toggles enabled ----
        document.querySelectorAll('[data-ns-row]').forEach(function (row) {
            var enabledInput = row.querySelector('[data-ns-enabled]');
            var badgeStatus = row.querySelector('[data-ns-badge-status]');
            if (enabledInput && badgeStatus) {
                enabledInput.addEventListener('change', function () {
                    var on = enabledInput.checked;
                    row.dataset.status = on ? 'on' : 'off';
                    badgeStatus.textContent = on ? '有効' : '無効';
                    badgeStatus.classList.toggle('is-on', on);
                    badgeStatus.classList.toggle('is-off', !on);
                });
            }
        });

        // ---- Filter chips ----
        var rows = document.querySelectorAll('[data-ns-row]');
        var chips = document.querySelectorAll('[data-ns-filters] [data-ns-filter]');
        var groups = document.querySelectorAll('.ns-group');
        var emptyRow = document.querySelector('[data-ns-empty]');
        var state = { filter: 'all' };

        function applyFilter() {
            var visibleTotal = 0;
            rows.forEach(function (r) {
                var show = true;
                var f = state.filter;
                if (f === 'on') show = r.dataset.status === 'on';
                else if (f === 'off') show = r.dataset.status === 'off';
                else if (f.indexOf('actor:') === 0) {
                    var actor = f.substring('actor:'.length);
                    show = r.dataset.actor === actor;
                }
                r.hidden = !show;
                if (show) visibleTotal++;
            });
            groups.forEach(function (g) {
                var anyVisible = Array.prototype.some.call(
                    g.querySelectorAll('[data-ns-row]'),
                    function (r) { return !r.hidden; }
                );
                g.hidden = !anyVisible;
            });
            if (emptyRow) emptyRow.hidden = visibleTotal !== 0;
        }
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                state.filter = c.getAttribute('data-ns-filter') || 'all';
                chips.forEach(function (cc) { cc.classList.toggle('is-active', cc === c); });
                applyFilter();
            });
        });
        applyFilter();

        // ---- Auto-open anchored item after redirect (?_anchor=key or #key) ----
        var anchor = null;
        var params = new URLSearchParams(window.location.search);
        if (params.get('_anchor')) anchor = params.get('_anchor');
        else if (window.location.hash) anchor = window.location.hash.substring(1);
        if (anchor) {
            var target = document.getElementById(anchor);
            if (target) {
                var panel = target.querySelector('.ns-item__panel');
                if (panel) {
                    panel.hidden = false;
                    target.classList.add('is-open');
                }
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== 通知・タスク仕様：オコジョ設定と同じ collapsible list パターン ===== */

    .ns-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- Toolbar (filter chips) ---- */
    .ns-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .ns-toolbar__spacer { flex: 1 1 auto; }
    .ns-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line);
        background: transparent;
        color: var(--admin-sub);
        cursor: pointer;
    }
    .ns-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text);
        font-variant-numeric: tabular-nums;
    }
    .ns-filter-chip.is-active {
        background: var(--admin-primary-soft);
        color: var(--admin-primary-hover);
        border-color: var(--admin-primary-border);
    }
    .ns-filter-chip.is-active strong { color: var(--admin-primary-hover); }
    .ns-mode-btn {
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        border: 1px solid var(--admin-line);
        background: transparent;
        color: var(--admin-sub);
        cursor: pointer;
    }
    .ns-mode-btn:hover { color: var(--admin-text); border-color: var(--admin-primary-border); }

    /* ---- Group heading ---- */
    .ns-group[hidden] { display: none; }
    .ns-group__title {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--admin-sub);
        margin: 4px 0 6px;
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    .ns-group__count {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--admin-muted);
        padding: 1px 8px;
        border-radius: 999px;
        border: 1px solid var(--admin-line);
        background: var(--admin-surface-alt);
    }

    /* ---- List ---- */
    .ns-list {
        list-style: none;
        margin: 0 0 16px;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ns-item {
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-card);
        color: var(--admin-text);
        overflow: hidden;
    }
    .ns-item.is-open {
        border-color: var(--admin-primary-border);
        background: var(--admin-primary-soft);
    }
    .ns-item__row { display: flex; align-items: center; }
    .ns-item__label {
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
    .ns-item__label:hover { background: var(--admin-primary-soft); }
    .ns-item__name {
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--admin-text);
        word-break: break-word;
    }
    .ns-item__badges {
        display: inline-flex;
        gap: 4px;
        flex: 0 0 auto;
    }
    .ns-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid;
        white-space: nowrap;
    }
    .ns-badge.is-on { color: var(--status-success-fg); border-color: var(--status-success-bd); background: var(--status-success-bg); }
    .ns-badge.is-off { color: var(--status-neutral-fg); border-color: var(--status-neutral-bd); background: var(--status-neutral-bg); }
    .ns-badge.is-info { color: var(--status-info-fg); border-color: var(--status-info-bd); background: var(--status-info-bg); }

    /* ---- Edit panel ---- */
    .ns-item__panel {
        border-top: 1px solid var(--admin-line);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: var(--admin-bg);
    }
    .ns-item__panel[hidden] { display: none; }

    /* Readonly meta rows (trigger/resolution conditions) */
    .ns-item__meta {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 8px 10px;
        border-radius: 8px;
        background: var(--admin-surface-alt);
        border: 1px dashed var(--admin-line);
    }
    .ns-item__meta-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        color: var(--admin-sub);
    }
    .ns-item__meta-value {
        font-size: 0.82rem;
        line-height: 1.5;
        color: var(--admin-text);
        white-space: pre-wrap;
        word-break: break-word;
    }

    .ns-item__form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* ---- Toggle switch ---- */
    .ns-item__toggle {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        user-select: none;
    }
    .ns-item__toggle input[type="checkbox"] {
        position: absolute;
        width: 0; height: 0;
        opacity: 0;
        pointer-events: none;
    }
    .ns-item__toggle-track {
        width: 44px;
        height: 24px;
        background: var(--status-neutral-bg);
        border: 1px solid var(--status-neutral-bd);
        border-radius: 999px;
        position: relative;
        transition: background 0.2s ease, border-color 0.2s ease;
        flex: 0 0 auto;
    }
    .ns-item__toggle-thumb {
        position: absolute;
        top: 2px; left: 2px;
        width: 18px; height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        transition: transform 0.2s ease;
    }
    .ns-item__toggle input[type="checkbox"]:checked ~ .ns-item__toggle-track {
        background: var(--admin-primary);
        border-color: var(--admin-primary);
    }
    .ns-item__toggle input[type="checkbox"]:checked ~ .ns-item__toggle-track .ns-item__toggle-thumb {
        transform: translateX(20px);
    }
    .ns-item__toggle input[type="checkbox"]:focus-visible ~ .ns-item__toggle-track {
        outline: 2px solid var(--admin-primary);
        outline-offset: 2px;
    }
    .ns-item__toggle-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--admin-text);
    }

    /* ---- Fields ---- */
    .ns-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ns-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub);
    }
    .ns-item__field input[type="text"],
    .ns-item__field input[type="number"],
    .ns-item__field textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-surface);
        font-size: 16px;
        color: var(--admin-text);
        font-family: inherit;
        line-height: 1.5;
        box-sizing: border-box;
    }
    .ns-item__field textarea {
        min-height: 72px;
        resize: vertical;
    }
    .ns-item__field input:focus,
    .ns-item__field textarea:focus {
        outline: none;
        border-color: var(--admin-primary);
        box-shadow: 0 0 0 3px var(--admin-primary-soft);
    }

    /* Inline (offset + suffix + hint) */
    .ns-item__field--inline .ns-item__inline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ns-item__field--inline .ns-input-num {
        width: 96px;
    }
    .ns-item__suffix {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--admin-text);
    }
    .ns-item__hint {
        font-size: 0.72rem;
        color: var(--admin-sub);
    }

    /* ---- Actions ---- */
    .ns-item__actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }
    .ns-item__close {
        min-height: 36px;
        padding: 6px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line);
        background: transparent;
        color: var(--admin-sub);
        font-size: 0.82rem;
        cursor: pointer;
    }
    .ns-item__close:hover { color: var(--admin-text); border-color: var(--admin-primary-border); }

    .ns-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line);
        border-radius: 10px;
    }

    /* ---- Mobile ---- */
    @media (max-width: 640px) {
        .ns-item__label { flex-wrap: wrap; }
        .ns-item__badges { width: 100%; margin-top: 4px; }
    }
</style>
@endpush
