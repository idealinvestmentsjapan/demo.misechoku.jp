@extends('layouts.admin')

@section('title', 'ユーザー通報管理')

@section('content')
@php
    $tabs = [
        ['key' => 'all',       'label' => 'すべて',   'count' => array_sum($counts)],
        ['key' => 'pending',   'label' => '未対応',   'count' => $counts['pending']],
        ['key' => 'in_review', 'label' => '対応中',   'count' => $counts['in_review']],
        ['key' => 'resolved',  'label' => '完了',     'count' => $counts['resolved']],
        ['key' => 'dismissed', 'label' => '却下',     'count' => $counts['dismissed']],
    ];
    $statusToneMap = [
        \App\Models\UserReport::STATUS_PENDING   => 'is-danger',
        \App\Models\UserReport::STATUS_IN_REVIEW => 'is-warning',
        \App\Models\UserReport::STATUS_RESOLVED  => 'is-success',
        \App\Models\UserReport::STATUS_DISMISSED => 'is-inactive',
    ];
@endphp

<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'USER REPORTS',
        'title' => 'ユーザー通報管理',
        'info' => '
            <p>キャスト・店舗から寄せられたユーザー間の通報を確認・対応します。</p>
            <ul>
                <li>行をタップすると、通報本文・運営メモ・対応ボタンが開きます。</li>
                <li>対象アカウントは <strong>「対象アカウントを開く」</strong> で詳細画面に遷移できます（別タブで開きます）。</li>
                <li>ステータス更新と同時に <strong>「対象アカウントも同時に停止する」</strong> にチェックすれば、その場で停止処理を行えます。</li>
            </ul>
        ',
    ])

    @if (session('message'))
        <div class="admin-alert admin-alert-success">{{ session('message') }}</div>
    @endif

    <section class="admin-card admin-card-wide ur-panel">
        {{-- フィルタチップ --}}
        <div class="ur-toolbar">
            @foreach($tabs as $t)
                <a href="{{ route('admin.user_reports.index', ['status' => $t['key']]) }}"
                   class="ur-filter-chip {{ $currentTab === $t['key'] ? 'is-active' : '' }} {{ $t['key'] === 'pending' && $t['count'] > 0 ? 'is-warn' : '' }}">
                    {{ $t['label'] }} <strong>{{ $t['count'] }}</strong>
                </a>
            @endforeach
        </div>

        {{-- 一覧 --}}
        @if($reports->isEmpty())
            <div class="ur-list__empty">該当する通報はありません。</div>
        @else
            <ul class="ur-list">
                @foreach($reports as $r)
                    @php
                        $reporterName = $names[$r->reporter_type . ':' . $r->reporter_id] ?? $r->reporter_id;
                        $targetName   = $names[$r->target_type . ':' . $r->target_id] ?? $r->target_id;
                        $isSuspended  = !empty($suspended[$r->target_type . ':' . $r->target_id]);
                        $reasonLabel  = $reasonLabels[$r->reason] ?? $r->reason;
                        $statusTone   = $statusToneMap[(int) $r->status] ?? 'is-inactive';
                        $targetUrl    = $r->target_type === 'cast'
                            ? route('admin.casts.show', $r->target_id)
                            : ($r->target_type === 'shop' ? route('admin.shops.show', $r->target_id) : null);
                        $targetLabel  = $r->target_type === 'cast' ? 'キャスト' : ($r->target_type === 'shop' ? '店舗' : $r->target_type);
                        $reporterLabel = $r->reporter_type === 'cast' ? 'キャスト' : ($r->reporter_type === 'shop' ? '店舗' : $r->reporter_type);
                    @endphp
                    <li class="ur-item">
                        <div class="ur-item__row">
                            <button type="button" class="ur-item__label" data-item-toggle>
                                <span class="ur-item__badges">
                                    <span class="admin-status-badge {{ $statusTone }}">{{ $r->statusLabel() }}</span>
                                    <span class="ur-item__reason">{{ $reasonLabel }}</span>
                                    @if($isSuspended)
                                        <span class="ur-item__suspended">対象停止中</span>
                                    @endif
                                </span>
                                <span class="ur-item__body">
                                    <span class="ur-item__title">
                                        {{ $targetLabel }}：{{ $targetName }}
                                        <span class="ur-item__id">#{{ $r->target_id }}</span>
                                    </span>
                                    <span class="ur-item__meta">
                                        通報者：{{ $reporterLabel }} {{ $reporterName }}（{{ $r->reporter_id }}）
                                        ・#{{ $r->id }} ・{{ $r->created_at?->format('Y/m/d H:i') }}
                                    </span>
                                </span>
                            </button>
                        </div>

                        <div class="ur-item__panel" hidden>
                            {{-- 本文・追加情報 --}}
                            <div class="ur-item__section">
                                <div class="ur-item__section-title">通報本文</div>
                                @if($r->detail)
                                    <div class="ur-item__prose">{{ $r->detail }}</div>
                                @else
                                    <div class="ur-item__prose ur-item__prose--empty">（本文未入力）</div>
                                @endif
                                @if($r->context_type === 'talk' && $r->context_message_id)
                                    <div class="ur-item__context">
                                        <i class="fas fa-comment" aria-hidden="true"></i>
                                        トーク由来（message #{{ $r->context_message_id }}）
                                    </div>
                                @endif
                            </div>

                            @if($r->admin_note)
                                <div class="ur-item__section">
                                    <div class="ur-item__section-title">現在の運営メモ</div>
                                    <div class="ur-item__note">
                                        {{ $r->admin_note }}
                                        @if($r->handled_at)
                                            <div class="ur-item__note-time">{{ $r->handled_at->format('Y/m/d H:i') }} 更新</div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- 対象アカウント操作 --}}
                            <div class="ur-item__section">
                                <div class="ur-item__section-title">対象アカウント</div>
                                <div class="ur-item__actions-line">
                                    @if($targetUrl)
                                        <a href="{{ $targetUrl }}" target="_blank" rel="noopener" class="ur-item__link-btn">
                                            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                            対象アカウントを開く（{{ $targetLabel }} {{ $r->target_id }}）
                                        </a>
                                    @else
                                        <span class="ur-item__link-disabled">対象アカウント種別が不明のためリンクできません。</span>
                                    @endif
                                </div>
                            </div>

                            {{-- 対応フォーム --}}
                            <form method="POST" action="{{ route('admin.user_reports.status', ['id' => $r->id]) }}" class="ur-item__form">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ $currentTab }}">

                                <label class="ur-item__field">
                                    <span class="ur-item__field-label">運営メモ（任意・保存されます）</span>
                                    <textarea name="admin_note" rows="3" maxlength="2000"
                                              placeholder="対応履歴のメモ...">{{ old('admin_note', $r->admin_note) }}</textarea>
                                </label>

                                @if($targetUrl && !$isSuspended)
                                    <label class="ur-item__suspend-check">
                                        <input type="checkbox" name="suspend_target" value="1">
                                        <span>対象アカウントも同時に停止する（{{ $targetLabel }} {{ $r->target_id }}）</span>
                                    </label>
                                @elseif($isSuspended)
                                    <div class="ur-item__suspend-note">
                                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                                        対象アカウントは既に停止中です。
                                    </div>
                                @endif

                                <div class="ur-item__actions">
                                    @foreach ([
                                        ['status' => \App\Models\UserReport::STATUS_IN_REVIEW, 'label' => '対応中に変更', 'kind' => 'warning'],
                                        ['status' => \App\Models\UserReport::STATUS_RESOLVED,  'label' => '完了',         'kind' => 'success'],
                                        ['status' => \App\Models\UserReport::STATUS_DISMISSED, 'label' => '却下',         'kind' => 'neutral'],
                                    ] as $act)
                                        @if((int) $r->status !== $act['status'])
                                            <button type="submit" name="status" value="{{ $act['status'] }}"
                                                    class="ur-item__act ur-item__act--{{ $act['kind'] }}">
                                                {{ $act['label'] }}
                                            </button>
                                        @endif
                                    @endforeach
                                    <button type="button" class="ur-item__close" data-item-cancel>閉じる</button>
                                </div>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        function closeAllPanels(except) {
            document.querySelectorAll('.ur-item.is-open').forEach(function (li) {
                if (li === except) return;
                li.classList.remove('is-open');
                var p = li.querySelector('.ur-item__panel');
                if (p) p.hidden = true;
            });
        }
        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ur-item');
                if (!li) return;
                var panel = li.querySelector('.ur-item__panel');
                if (!panel) return;
                var opening = panel.hidden;
                closeAllPanels(li);
                if (opening) {
                    panel.hidden = false;
                    li.classList.add('is-open');
                } else {
                    panel.hidden = true;
                    li.classList.remove('is-open');
                }
            });
        });
        document.querySelectorAll('[data-item-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.ur-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.ur-item__panel');
                if (panel) panel.hidden = true;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeAllPanels(null);
        });

        // 「対象を同時に停止」チェック時は確認ダイアログ
        document.querySelectorAll('.ur-item__form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var chk = form.querySelector('input[name="suspend_target"]');
                if (chk && chk.checked) {
                    if (!confirm('対象アカウントを停止します。よろしいですか？\n停止後は対象アカウントはログインできなくなります。')) {
                        e.preventDefault();
                    }
                }
            });
        });
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== ユーザー通報：一覧＋インライン詳細（管理テーマトークンに準拠） ===== */
    .ur-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- ツールバー ---- */
    .ur-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .ur-filter-chip {
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
        text-decoration: none;
    }
    .ur-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text);
        font-variant-numeric: tabular-nums;
    }
    .ur-filter-chip.is-active {
        background: var(--admin-primary-soft);
        color: var(--admin-primary-hover);
        border-color: var(--admin-primary-border);
    }
    .ur-filter-chip.is-active strong { color: var(--admin-primary-hover); }
    .ur-filter-chip.is-warn:not(.is-active) {
        border-color: var(--status-danger-bd);
        color: var(--status-danger-fg);
    }
    .ur-filter-chip.is-warn:not(.is-active) strong { color: var(--status-danger-fg); }

    /* ---- 一覧 ---- */
    .ur-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ur-item {
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-card);
        color: var(--admin-text);
        overflow: hidden;
    }
    .ur-item.is-open {
        border-color: var(--admin-primary-border);
        background: var(--admin-primary-soft);
    }
    .ur-item__row { display: flex; }
    .ur-item__label {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 12px 14px;
        border: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .ur-item__label:hover { background: var(--admin-primary-soft); }

    .ur-item__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    .ur-item__reason {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--status-info-fg);
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--status-info-bd);
        background: var(--status-info-bg);
    }
    .ur-item__suspended {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--status-danger-fg);
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--status-danger-bd);
        background: var(--status-danger-bg);
    }
    .ur-item__body {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .ur-item__title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--admin-text);
    }
    .ur-item__id {
        margin-left: 6px;
        font-size: 0.72rem;
        color: var(--admin-sub);
        font-variant-numeric: tabular-nums;
        font-weight: 500;
    }
    .ur-item__meta {
        font-size: 0.75rem;
        color: var(--admin-sub);
    }

    /* ---- パネル ---- */
    .ur-item__panel {
        border-top: 1px solid var(--admin-line);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: var(--admin-bg);
    }
    .ur-item__panel[hidden] { display: none; }
    .ur-item__section {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .ur-item__section-title {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--admin-sub);
    }
    .ur-item__prose {
        padding: 12px 14px;
        border-radius: 8px;
        background: var(--admin-surface);
        border-left: 3px solid var(--admin-primary);
        font-size: 0.9rem;
        color: var(--admin-text);
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .ur-item__prose--empty { color: var(--admin-muted); font-style: italic; border-left-color: var(--admin-line); }
    .ur-item__context {
        font-size: 0.75rem;
        color: var(--admin-sub);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ur-item__note {
        padding: 12px 14px;
        border-radius: 8px;
        background: var(--status-warning-bg);
        border: 1px dashed var(--status-warning-bd);
        color: var(--status-warning-fg);
        font-size: 0.88rem;
        white-space: pre-wrap;
    }
    .ur-item__note-time {
        margin-top: 6px;
        font-size: 0.7rem;
        opacity: 0.8;
    }

    /* ---- 対象アカウントリンク ---- */
    .ur-item__actions-line {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ur-item__link-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-primary-border);
        background: var(--admin-primary-soft);
        color: var(--admin-primary-hover);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }
    .ur-item__link-btn:hover { background: var(--admin-primary-soft-hover); color: var(--admin-text); }
    .ur-item__link-disabled {
        font-size: 0.82rem;
        color: var(--admin-muted);
    }

    /* ---- 対応フォーム ---- */
    .ur-item__form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .ur-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ur-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub);
    }
    .ur-item__field textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-surface);
        font-size: 16px;
        color: var(--admin-text);
        resize: vertical;
        font-family: inherit;
        line-height: 1.5;
        box-sizing: border-box;
    }
    .ur-item__field textarea:focus {
        outline: none;
        border-color: var(--admin-primary);
        box-shadow: 0 0 0 3px var(--admin-primary-soft);
    }

    .ur-item__suspend-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid var(--status-danger-bd);
        background: var(--status-danger-bg);
        color: var(--status-danger-fg);
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
    }
    .ur-item__suspend-check input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
    .ur-item__suspend-note {
        font-size: 0.78rem;
        color: var(--status-danger-fg);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--status-danger-bd);
        background: var(--status-danger-bg);
    }

    .ur-item__actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .ur-item__act {
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid;
    }
    .ur-item__act--warning { background: var(--status-warning-bg); color: var(--status-warning-fg); border-color: var(--status-warning-bd); }
    .ur-item__act--warning:hover { filter: brightness(1.1); }
    .ur-item__act--success { background: var(--status-success-bg); color: var(--status-success-fg); border-color: var(--status-success-bd); }
    .ur-item__act--success:hover { filter: brightness(1.1); }
    .ur-item__act--neutral { background: var(--status-neutral-bg); color: var(--status-neutral-fg); border-color: var(--status-neutral-bd); }
    .ur-item__act--neutral:hover { filter: brightness(1.15); }

    .ur-item__close {
        margin-left: auto;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line);
        background: transparent;
        color: var(--admin-sub);
        font-size: 0.82rem;
        cursor: pointer;
    }
    .ur-item__close:hover { color: var(--admin-text); border-color: var(--admin-primary-border); }

    .ur-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line);
        border-radius: 10px;
    }

    /* ---- スマホ ---- */
    @media (max-width: 640px) {
        .ur-item__close { margin-left: 0; width: 100%; text-align: center; }
    }
</style>
@endpush
