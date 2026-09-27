@extends('layouts.admin')

@section('title', '問合せ対応')

@section('content')
@php
    $statusLabels = \App\Models\SupportInquiry::STATUS_LABELS;
    $tabs = [
        ['key' => 'all',         'label' => 'すべて',     'count' => $counts['all']],
        ['key' => \App\Models\SupportInquiry::STATUS_NEW,         'label' => '新着（未対応）', 'count' => $counts['new']],
        ['key' => \App\Models\SupportInquiry::STATUS_IN_PROGRESS, 'label' => '対応中',        'count' => $counts['in_progress']],
        ['key' => \App\Models\SupportInquiry::STATUS_RESOLVED,    'label' => '完了',          'count' => $counts['resolved']],
    ];
    $statusToneMap = [
        \App\Models\SupportInquiry::STATUS_NEW         => 'is-danger',
        \App\Models\SupportInquiry::STATUS_IN_PROGRESS => 'is-warning',
        \App\Models\SupportInquiry::STATUS_RESOLVED    => 'is-success',
    ];
@endphp

<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'SUPPORT',
        'title' => '問合せ対応',
        'info' => '
            <p>ユーザー・ゲストから寄せられた問合せを一覧表示します。</p>
            <ul>
                <li>行をタップすると、問合せ本文・送信者情報・対応ステータス変更・メモ入力欄が開きます。</li>
                <li>返信は本文中の返信先メールアドレスに直接行ってください。</li>
                <li>「対応中」「完了」に初めて更新した瞬間の時刻が一次応対時刻として記録されます。</li>
            </ul>
        ',
    ])

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    <section class="admin-card admin-card-wide si-panel">
        {{-- フィルタチップ --}}
        <div class="si-toolbar">
            @foreach($tabs as $t)
                <a href="{{ route('admin.support-inquiries.index', ['status' => $t['key']]) }}"
                   class="si-filter-chip {{ $status === $t['key'] ? 'is-active' : '' }} {{ $t['key'] === \App\Models\SupportInquiry::STATUS_NEW && $t['count'] > 0 ? 'is-warn' : '' }}">
                    {{ $t['label'] }} <strong>{{ $t['count'] }}</strong>
                </a>
            @endforeach
        </div>

        @if($inquiries->isEmpty())
            <div class="si-list__empty">該当する問合せはありません。</div>
        @else
            <ul class="si-list">
                @foreach($inquiries as $inquiry)
                    @php
                        $createdAt = $inquiry->created_at;
                        $days = $createdAt ? (int) $createdAt->diffInDays(now()) : null;
                        $ageTone = $days === null ? '' : ($days >= 7 ? 'is-critical' : ($days >= 3 ? 'is-warning' : ''));
                        $senderBadge = match ($inquiry->sender_type) {
                            \App\Models\SupportInquiry::SENDER_CAST => 'キャスト',
                            \App\Models\SupportInquiry::SENDER_SHOP => '店舗',
                            default => 'ゲスト',
                        };
                        $statusTone = $statusToneMap[$inquiry->status] ?? 'is-inactive';
                        $preview = mb_strimwidth((string) $inquiry->body, 0, 60, '…');
                    @endphp
                    <li class="si-item">
                        <div class="si-item__row">
                            <button type="button" class="si-item__label" data-item-toggle>
                                <span class="si-item__badges">
                                    <span class="admin-status-badge {{ $statusTone }}">{{ $inquiry->statusLabel() }}</span>
                                    <span class="si-item__sender">{{ $senderBadge }}</span>
                                    <span class="si-item__category">{{ $inquiry->categoryLabel() }}</span>
                                    @if($days !== null)
                                        <span class="si-item__age {{ $ageTone }}">経過 {{ $days }}日</span>
                                    @endif
                                </span>
                                <span class="si-item__preview">{{ $preview }}</span>
                                <span class="si-item__meta">
                                    #{{ $inquiry->id }} ・{{ optional($createdAt)->format('Y-m-d H:i') }}
                                    @if($inquiry->sender_id)
                                        ・{{ $inquiry->sender_id }}
                                    @endif
                                </span>
                            </button>
                        </div>

                        <div class="si-item__panel" hidden>
                            {{-- 送信者情報 --}}
                            <div class="si-item__section">
                                <div class="si-item__section-title">受信内容</div>
                                <dl class="si-item__def">
                                    <div><dt>受付日時</dt><dd>{{ optional($createdAt)->format('Y-m-d H:i:s') }}</dd></div>
                                    <div><dt>送信者</dt><dd>{{ $senderBadge }}@if($inquiry->sender_id) <small>（{{ $inquiry->sender_id }}）</small>@endif</dd></div>
                                    <div><dt>カテゴリ</dt><dd>{{ $inquiry->categoryLabel() }}</dd></div>
                                    <div><dt>返信先</dt><dd><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></dd></div>
                                    @if($inquiry->responded_at)
                                        <div><dt>一次応対</dt><dd>{{ $inquiry->responded_at->format('Y-m-d H:i') }}</dd></div>
                                    @endif
                                </dl>
                            </div>

                            {{-- 本文 --}}
                            <div class="si-item__section">
                                <div class="si-item__section-title">本文</div>
                                <div class="si-item__prose">{{ $inquiry->body }}</div>
                            </div>

                            {{-- ステータス更新 --}}
                            <form method="POST" action="{{ route('admin.support-inquiries.status', $inquiry->id) }}" class="si-item__form">
                                @csrf
                                <input type="hidden" name="redirect_to" value="index">
                                <input type="hidden" name="return_tab" value="{{ $status }}">
                                <label class="si-item__field">
                                    <span class="si-item__field-label">対応ステータス</span>
                                    <select name="status" required>
                                        @foreach($statusLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($inquiry->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="si-item__actions">
                                    <button type="submit" class="si-item__act si-item__act--primary">
                                        <i class="fas fa-check" aria-hidden="true"></i> ステータス更新
                                    </button>
                                </div>
                            </form>

                            {{-- メモ --}}
                            <form method="POST" action="{{ route('admin.support-inquiries.note', $inquiry->id) }}" class="si-item__form">
                                @csrf
                                <input type="hidden" name="redirect_to" value="index">
                                <input type="hidden" name="return_tab" value="{{ $status }}">
                                <label class="si-item__field">
                                    <span class="si-item__field-label">対応メモ（最長 4000 文字）</span>
                                    <textarea name="admin_note" rows="4" maxlength="4000" placeholder="応対履歴・経緯などを記録">{{ old('admin_note', $inquiry->admin_note) }}</textarea>
                                </label>
                                <div class="si-item__actions">
                                    <button type="submit" class="si-item__act si-item__act--primary">
                                        <i class="fas fa-save" aria-hidden="true"></i> メモ保存
                                    </button>
                                    <a href="{{ route('admin.support-inquiries.show', $inquiry->id) }}"
                                       class="si-item__act si-item__act--ghost">
                                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> 詳細ページを開く
                                    </a>
                                    <button type="button" class="si-item__close" data-item-cancel>閉じる</button>
                                </div>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($inquiries->hasPages())
            <div class="admin-pagination">
                {{ $inquiries->links() }}
            </div>
        @endif
    </section>
</div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        function closeAllPanels(except) {
            document.querySelectorAll('.si-item.is-open').forEach(function (li) {
                if (li === except) return;
                li.classList.remove('is-open');
                var p = li.querySelector('.si-item__panel');
                if (p) p.hidden = true;
            });
        }
        document.querySelectorAll('[data-item-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.si-item');
                if (!li) return;
                var panel = li.querySelector('.si-item__panel');
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
                var li = btn.closest('.si-item');
                if (!li) return;
                li.classList.remove('is-open');
                var panel = li.querySelector('.si-item__panel');
                if (panel) panel.hidden = true;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeAllPanels(null);
        });
    })();
</script>
@endpush

@push('admin-styles')
<style>
    /* ===== 問合せ対応：一覧＋インライン詳細（ユーザー通報と同じ UI パターン） ===== */
    .si-panel { display: flex; flex-direction: column; gap: 14px; }

    /* ---- ツールバー ---- */
    .si-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .si-filter-chip {
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
    .si-filter-chip strong {
        font-weight: 800;
        color: var(--admin-text);
        font-variant-numeric: tabular-nums;
    }
    .si-filter-chip.is-active {
        background: var(--admin-primary-soft);
        color: var(--admin-primary-hover);
        border-color: var(--admin-primary-border);
    }
    .si-filter-chip.is-active strong { color: var(--admin-primary-hover); }
    .si-filter-chip.is-warn:not(.is-active) {
        border-color: var(--status-danger-bd);
        color: var(--status-danger-fg);
    }
    .si-filter-chip.is-warn:not(.is-active) strong { color: var(--status-danger-fg); }

    /* ---- 一覧 ---- */
    .si-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .si-item {
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-card);
        color: var(--admin-text);
        overflow: hidden;
    }
    .si-item.is-open {
        border-color: var(--admin-primary-border);
        background: var(--admin-primary-soft);
    }
    .si-item__row { display: flex; }
    .si-item__label {
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
    .si-item__label:hover { background: var(--admin-primary-soft); }

    .si-item__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    .si-item__sender,
    .si-item__category {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--admin-line);
        background: var(--admin-surface-alt);
        color: var(--admin-sub);
    }
    .si-item__category {
        color: var(--status-info-fg);
        border-color: var(--status-info-bd);
        background: var(--status-info-bg);
    }
    .si-item__age {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--admin-line);
        background: var(--admin-surface-alt);
        color: var(--admin-sub);
    }
    .si-item__age.is-warning {
        color: var(--status-warning-fg);
        border-color: var(--status-warning-bd);
        background: var(--status-warning-bg);
    }
    .si-item__age.is-critical {
        color: var(--status-danger-fg);
        border-color: var(--status-danger-bd);
        background: var(--status-danger-bg);
    }
    .si-item__preview {
        font-size: 0.88rem;
        color: var(--admin-text);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .si-item__meta {
        font-size: 0.72rem;
        color: var(--admin-sub);
    }

    /* ---- パネル ---- */
    .si-item__panel {
        border-top: 1px solid var(--admin-line);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: var(--admin-bg);
    }
    .si-item__panel[hidden] { display: none; }
    .si-item__section {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .si-item__section-title {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--admin-sub);
    }
    .si-item__def {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 8px 16px;
        margin: 0;
    }
    .si-item__def > div {
        display: flex;
        gap: 8px;
        align-items: baseline;
    }
    .si-item__def dt {
        font-size: 0.72rem;
        color: var(--admin-sub);
        min-width: 60px;
    }
    .si-item__def dd {
        margin: 0;
        font-size: 0.86rem;
        color: var(--admin-text);
        font-weight: 500;
        word-break: break-all;
    }
    .si-item__def dd a { color: var(--admin-primary-hover); }
    .si-item__prose {
        padding: 12px 14px;
        border-radius: 8px;
        background: var(--admin-surface);
        border-left: 3px solid var(--admin-primary);
        font-size: 0.92rem;
        color: var(--admin-text);
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* ---- フォーム ---- */
    .si-item__form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--admin-line);
    }
    .si-item__field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .si-item__field-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--admin-sub);
    }
    .si-item__field textarea,
    .si-item__field select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--admin-line);
        border-radius: 10px;
        background: var(--admin-surface);
        font-size: 16px;
        color: var(--admin-text);
        font-family: inherit;
        box-sizing: border-box;
    }
    .si-item__field textarea {
        min-height: 90px;
        resize: vertical;
        line-height: 1.5;
    }
    .si-item__field select {
        max-width: 240px;
    }
    .si-item__field textarea:focus,
    .si-item__field select:focus {
        outline: none;
        border-color: var(--admin-primary);
        box-shadow: 0 0 0 3px var(--admin-primary-soft);
    }

    .si-item__actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .si-item__act {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid;
    }
    .si-item__act--primary {
        background: var(--admin-primary-soft);
        color: var(--admin-primary-hover);
        border-color: var(--admin-primary-border);
    }
    .si-item__act--primary:hover { background: var(--admin-primary-soft-hover); }
    .si-item__act--ghost {
        background: transparent;
        color: var(--admin-sub);
        border-color: var(--admin-line);
    }
    .si-item__act--ghost:hover { color: var(--admin-text); border-color: var(--admin-primary-border); }
    .si-item__close {
        margin-left: auto;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--admin-line);
        background: transparent;
        color: var(--admin-sub);
        font-size: 0.82rem;
        cursor: pointer;
    }
    .si-item__close:hover { color: var(--admin-text); border-color: var(--admin-primary-border); }

    .si-list__empty {
        padding: 28px 12px;
        text-align: center;
        color: var(--admin-sub);
        font-size: 0.85rem;
        border: 1px dashed var(--admin-line);
        border-radius: 10px;
    }

    /* ---- スマホ ---- */
    @media (max-width: 640px) {
        .si-item__close { margin-left: 0; width: 100%; text-align: center; }
        .si-item__def { grid-template-columns: 1fr; }
    }
</style>
@endpush
