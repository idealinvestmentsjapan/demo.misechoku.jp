@extends('layouts.app-v2')

@php
    $isCast = request()->is('cast/*');
@endphp

@section('title', $partnerName ?? 'トーク')
@section('header_title', $partnerName)
@section('header_avatar', $partnerAvatar ?? asset('assets/images/common/no-image.png'))
@section('body-class', 'page-talk page-talk-room')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/talk.css') }}?v=20260928-interview-15min-select">
<link rel="stylesheet" href="{{ asset('assets/css/talk-light.css') }}?v=20260928-interview-15min-select">
@if($isCast)
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/review-modal.css') }}?v=20260927-scroll-fix">
@endif
<style>
    /* 結果テンプレ（自動送信候補）：mypage と同じ紫アクセントに統一 */
    .result-template-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 12px 0;
    }
    .result-template-button {
        border: 1px solid rgba(168, 85, 247, 0.40);
        background: rgba(168, 85, 247, 0.08);
        color: #c4b5fd;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
    }
    .result-template-button:hover {
        background: rgba(168, 85, 247, 0.16);
        border-color: rgba(168, 85, 247, 0.65);
        transform: translateY(-1px);
    }
    .result-template-button:active {
        transform: translateY(0);
    }
    .result-message-textarea {
        width: 100%;
        min-height: 140px;
        border-radius: 14px;
        border: 1px solid rgba(168, 85, 247, 0.30);
        background: linear-gradient(to bottom right, #1a1a1a, #050505);
        color: #f5f5f5;
        padding: 14px;
        font-size: 0.9rem;
        resize: vertical;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
    }
    .result-message-textarea:focus {
        outline: none;
        border-color: rgba(168, 85, 247, 0.55);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.5), 0 0 0 3px rgba(168, 85, 247, 0.12);
    }
    .hired-wage-field-wrap {
        display: none;
        margin: 14px 0 4px;
    }
    .hired-wage-field-wrap.is-visible {
        display: block;
    }
    .hired-wage-field-wrap label {
        display: block;
        font-size: 0.80rem;
        color: #c4b5fd;
        font-weight: 600;
        letter-spacing: 0.04em;
        margin-bottom: 6px;
    }
    .hired-wage-field-wrap input {
        width: 100%;
        border-radius: 12px;
        border: 1px solid rgba(168, 85, 247, 0.30);
        background: linear-gradient(to bottom right, #1a1a1a, #050505);
        color: #f5f5f5;
        padding: 10px 14px;
        font-size: 0.95rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
    }
    .hired-wage-field-wrap input:focus {
        outline: none;
        border-color: rgba(168, 85, 247, 0.55);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.5), 0 0 0 3px rgba(168, 85, 247, 0.12);
    }

    /* ===== 通報モーダル ===== */
    .user-report-modal {
        position: fixed; inset: 0; z-index: 3500;
        display: none;
        align-items: center; justify-content: center;
        padding: 24px 16px;
    }
    .user-report-modal:not([hidden]) { display: flex; }
    .user-report-modal__overlay {
        position: absolute; inset: 0;
        background: rgba(0,0,0,0.72);
        backdrop-filter: blur(4px);
    }
    .user-report-modal__panel {
        position: relative;
        width: min(480px, 100%);
        background: #fff;
        border-radius: 16px;
        padding: 22px 20px 20px;
        box-shadow: 0 24px 64px rgba(0,0,0,0.4);
    }
    .user-report-modal__close {
        position: absolute; top: 12px; right: 12px;
        background: transparent; border: 0;
        font-size: 1.5rem; color: #8b84a1;
        cursor: pointer; padding: 4px 8px;
        line-height: 1;
    }
    .user-report-modal__title {
        margin: 0 0 10px;
        font-size: 1.05rem; font-weight: 800;
        color: #1e1a30;
        display: flex; align-items: center; gap: 8px;
    }
    .user-report-modal__title i { color: #dc2626; }
    .user-report-modal__lead {
        margin: 0 0 18px;
        font-size: 0.82rem; color: #4a4560;
        line-height: 1.6;
    }
    .user-report-modal__label {
        display: block;
        font-size: 0.78rem; font-weight: 700; color: #1e1a30;
        margin: 0 0 6px;
    }
    .user-report-modal__select,
    .user-report-modal__textarea {
        width: 100%; box-sizing: border-box;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(124,58,237,0.24);
        background: #fff; color: #1e1a30;
        font-size: 0.9rem; font-family: inherit;
    }
    .user-report-modal__select:focus,
    .user-report-modal__textarea:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124,58,237,0.14);
    }
    .user-report-modal__textarea { resize: vertical; min-height: 90px; line-height: 1.6; }
    .user-report-modal__feedback {
        margin: 12px 0 0; padding: 10px 12px;
        border-radius: 10px;
        font-size: 0.82rem; line-height: 1.5;
    }
    .user-report-modal__feedback.is-error {
        background: rgba(220,38,38,0.08); color: #b91c1c;
        border: 1px solid rgba(220,38,38,0.32);
    }
    .user-report-modal__feedback.is-success {
        background: rgba(16,185,129,0.08); color: #047857;
        border: 1px solid rgba(16,185,129,0.32);
    }
    .user-report-modal__actions {
        margin-top: 18px; display: flex; gap: 8px; justify-content: flex-end;
    }
    .user-report-modal__btn {
        min-height: 44px; padding: 10px 18px;
        border-radius: 10px; border: 1px solid transparent;
        font-size: 0.88rem; font-weight: 700;
        cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .user-report-modal__btn--ghost {
        background: #fff; border-color: rgba(124,58,237,0.24); color: #4a4560;
    }
    .user-report-modal__btn--primary {
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        color: #fff; border-color: rgba(124,58,237,0.35);
    }
    .user-report-modal__btn:disabled { opacity: 0.55; cursor: not-allowed; }

    /* ===== 採用連絡カードの 種別・時給リスト ===== */
    .message-bubble-hired .hire-terms {
        margin: 8px 0 0;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid rgba(168, 85, 247, 0.35);
        background: rgba(168, 85, 247, 0.08);
        display: grid;
        gap: 4px;
    }
    .message-bubble-hired .hire-terms__row {
        display: flex;
        align-items: baseline;
        gap: 10px;
        font-size: 0.78rem;
    }
    .message-bubble-hired .hire-terms__row dt {
        min-width: 68px;
        margin: 0;
        color: rgba(196, 181, 253, 0.85);
        font-weight: 700;
    }
    .message-bubble-hired .hire-terms__row dd {
        margin: 0;
        font-weight: 800;
        color: #fff5e0;
        font-variant-numeric: tabular-nums;
    }
    body.theme-light .message-bubble-hired .hire-terms {
        background: rgba(124, 58, 237, 0.06);
        border-color: rgba(124, 58, 237, 0.32);
    }
    body.theme-light .message-bubble-hired .hire-terms__row dt { color: #6d28d9; }
    body.theme-light .message-bubble-hired .hire-terms__row dd { color: #241f33; }
</style>
@endpush

@push('scripts')
<script>
    window.isCastTalkRoom = {!! request()->is('cast/*') ? 'true' : 'false' !!};
    window.talkResultMessageTemplates = @json($resultMessageTemplates ?? []);
    window.initialTalkTopic = @json($initialTalkTopic ?? null);
    window.initialTalkJobKind = @json($initialTalkJobKind ?? null);
    window.hasTalkMessages = @json($hasMessages ?? false);
    window.selectedTalkJobKind = @json($selectedTalkJobKind ?? null);
    window.canSelectTalkJobKind = @json($canSelectTalkJobKind ?? false);
    window.currentTalkStatusCode = @json($currentStatusCode ?? 'chatting');
    window.talkQuickTemplates = @json($quickTemplates ?? []);
    window.talkAllQuickReplies = @json($allQuickReplySuggestions ?? []);
    window.talkNgPayload = @json($ngWordPayload ?? ['patterns' => [], 'words' => []]);
</script>
<script src="{{ asset('assets/js/talk-room.js') }}?v=20260928-interview-15min-select"></script>
@endpush

@section('content')
@php
    $isCast = request()->is('cast/*');
    $sendUrl = $isCast ? route('cast.talk.send') : route('shop.talk.send');
    $deleteUrl = $isCast ? route('cast.talk.delete') : route('shop.talk.delete');
    $actionUrl = $actionUrl ?? ($isCast ? route('cast.talk.action') : route('shop.talk.action'));
    $blockUrl = $blockUrl ?? ($isCast ? route('cast.talk.block') : route('shop.talk.block'));
    $partnerAvatar = $partnerAvatar ?? asset('assets/images/common/no-image.png');
    // Partner profile route — cast talks with shops (→ shop profile),
    // shops talk with casts (→ cast profile view).
    $partnerProfileRoute = $isCast ? 'cast.shopprofile.show' : 'shop.castprofileview.show';
    $partnerProfileUrl = Route::has($partnerProfileRoute) ? route($partnerProfileRoute, $partnerId) : null;
    $partnerLabel = $isCast ? 'お店のプロフィール' : 'キャストのプロフィール';
    $talkJobKindLabelMap = ['trial' => '新規入店', 'fulltime' => '本入店', 'help' => 'ヘルプ'];
    $currentTalkJobKindValue = $selectedTalkJobKind ?? $initialTalkJobKind ?? null;
    $currentTalkJobKindLabel = $talkJobKindLabelMap[$currentTalkJobKindValue] ?? '未選択';
    $isInterviewOfferLocked = in_array(($currentStatusCode ?? ''), ['hired', 'rejected'], true);
    // 種別スイッチ表示判定：本入店（fulltime）確定済みはロック表示、
    // それ以外（未選択 / trial / help）は 新規入店 ⇔ ヘルプ の2択スイッチ
    $isJobKindFulltimeLocked = $currentTalkJobKindValue === 'fulltime';
@endphp

<div id="talk-room-container" class="flex flex-col h-full bg-base">
    <div class="talk-room-header">
        <div class="talk-room-shop-badges">
                <span class="talk-status-label">
                    <span class="talk-status-dot"></span>
                    <span class="talk-status-caption">状況:</span>
                    <span class="talk-status-value">{{ $currentStatusLabel ?? 'やり取り中' }}</span>
                </span>
                @if(!$isCast && !$isJobKindFulltimeLocked)
                    {{-- 店舗側かつ本入店未確定：新規入店 / ヘルプ の2択スイッチ（インライン） --}}
                    @php $canSwitchJobKind = !empty($canSelectTalkJobKind); @endphp
                    <div class="talk-job-kind-switch {{ $canSwitchJobKind ? '' : 'is-disabled' }}"
                         role="radiogroup" aria-label="種別"
                         data-current="{{ $currentTalkJobKindValue ?? '' }}"
                         data-action-url="{{ $actionUrl }}"
                         data-partner-id="{{ $partnerId }}">
                        <span class="talk-job-kind-switch__caption">種別</span>
                        <div class="talk-job-kind-switch__track">
                            <span class="talk-job-kind-switch__thumb" aria-hidden="true"></span>
                            <button type="button" class="talk-job-kind-switch__segment {{ $currentTalkJobKindValue === 'trial' ? 'is-active' : '' }}"
                                    data-job-kind="trial"
                                    role="radio" aria-checked="{{ $currentTalkJobKindValue === 'trial' ? 'true' : 'false' }}"
                                    @if(!$canSwitchJobKind) disabled @endif>新規入店</button>
                            <button type="button" class="talk-job-kind-switch__segment {{ $currentTalkJobKindValue === 'help' ? 'is-active' : '' }}"
                                    data-job-kind="help"
                                    role="radio" aria-checked="{{ $currentTalkJobKindValue === 'help' ? 'true' : 'false' }}"
                                    @if(!$canSwitchJobKind) disabled @endif>ヘルプ</button>
                        </div>
                        <span id="talk-job-kind-current" data-job-kind-current="{{ $currentTalkJobKindValue ?? '' }}" hidden></span>
                    </div>
                @else
                    @php
                        // 店舗側かつ本入店ロック済みでは、チップをそのまま「種別変更モーダル」の
                        // 起動ボタンに切り替える（採用／不採用が確定するまでは変更可能）。
                        $chipAsButton = !$isCast && $isJobKindFulltimeLocked && !empty($canSelectTalkJobKind);
                    @endphp
                    @if($chipAsButton)
                        <button type="button"
                                id="open-job-kind-modal"
                                class="talk-job-kind-chip talk-job-kind-chip--locked talk-job-kind-chip--interactive"
                                aria-label="求人種別を変更"
                                title="求人種別を変更">
                            <span class="talk-job-kind-chip__caption">種別</span>
                            <span id="talk-job-kind-current" class="talk-job-kind-chip__value" data-job-kind-current="{{ $currentTalkJobKindValue ?? '' }}">{{ $currentTalkJobKindLabel }}</span>
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                    @else
                        {{-- キャスト側 or 変更不可（採用／不採用 確定後）：読み取り専用チップ --}}
                        <span class="talk-job-kind-chip {{ $isJobKindFulltimeLocked ? 'talk-job-kind-chip--locked' : '' }}">
                            <span class="talk-job-kind-chip__caption">種別</span>
                            <span id="talk-job-kind-current" class="talk-job-kind-chip__value" data-job-kind-current="{{ $currentTalkJobKindValue ?? '' }}">{{ $currentTalkJobKindLabel }}</span>
                            @if($isJobKindFulltimeLocked)
                                <i class="fas fa-lock" aria-hidden="true"></i>
                            @endif
                        </span>
                    @endif
                @endif
                @if($partnerProfileUrl)
                    {{-- プロフィール導線：相手のプロフィール画面へ遷移（2026-09-26 追加） --}}
                    <a
                        href="{{ $partnerProfileUrl }}"
                        class="talk-block-icon-btn talk-block-icon-btn--plain talk-profile-jump-btn"
                        title="{{ $partnerLabel }}を開く"
                        aria-label="{{ $partnerLabel }}を開く"
                    >
                        <i class="fas fa-user"></i>
                    </a>
                @endif
                @if(empty($blockState['blocked_by_other']))
                    {{-- 通報ボタン（相手を運営に報告） --}}
                    <button
                        type="button"
                        class="talk-block-icon-btn talk-block-icon-btn--plain"
                        data-user-report-open
                        data-target-type="{{ $isCast ? 'shop' : 'cast' }}"
                        data-target-id="{{ $partnerId }}"
                        title="この相手を通報"
                        aria-label="この相手を通報"
                    >
                        <i class="fas fa-bullhorn"></i>
                    </button>
                    <form action="{{ $blockUrl }}" method="POST" class="talk-block-inline-form">
                        @csrf
                        <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                        <button
                            type="submit"
                            class="talk-block-icon-btn talk-block-icon-btn--plain {{ !empty($blockState['blocked_by_me']) ? 'is-active' : '' }}"
                            title="{{ !empty($blockState['blocked_by_me']) ? 'ブロック解除' : 'ブロック' }}"
                            aria-label="{{ !empty($blockState['blocked_by_me']) ? 'ブロック解除' : 'ブロック' }}"
                        >
                            <i class="fas fa-ban"></i>
                        </button>
                    </form>
                @endif
        </div>
    </div>

    {{-- ===== 通報モーダル ===== --}}
    <div class="user-report-modal" data-user-report-modal hidden role="dialog" aria-modal="true" aria-labelledby="user-report-title">
        <div class="user-report-modal__overlay" data-user-report-close></div>
        <div class="user-report-modal__panel">
            <button type="button" class="user-report-modal__close" data-user-report-close aria-label="閉じる">×</button>
            <h3 id="user-report-title" class="user-report-modal__title">
                <i class="fas fa-bullhorn"></i> この相手を通報する
            </h3>
            <p class="user-report-modal__lead">
                悪質な行為・ルール違反があれば運営にお知らせください。<br>
                内容は運営で確認します（通報したことは相手に通知されません）。
            </p>
            <form data-user-report-form data-endpoint="{{ route('pages.user-report.store') }}">
                @csrf
                <input type="hidden" name="target_type" data-target-type>
                <input type="hidden" name="target_id" data-target-id>
                <input type="hidden" name="context_type" value="talk">

                <label class="user-report-modal__label">通報理由</label>
                <select name="reason" required class="user-report-modal__select">
                    <option value="">選択してください</option>
                    <option value="harassment">ハラスメント／脅迫</option>
                    <option value="contact_info">連絡先誘導（LINE ID・電話番号等）</option>
                    <option value="inappropriate">不適切な発言・画像</option>
                    <option value="fake">なりすまし・虚偽情報</option>
                    <option value="other">その他</option>
                </select>

                <label class="user-report-modal__label" style="margin-top:14px;">詳細（任意・1000文字まで）</label>
                <textarea name="detail" rows="4" maxlength="1000"
                          placeholder="具体的な状況を記入いただけると対応がスムーズです。"
                          class="user-report-modal__textarea"></textarea>

                <p class="user-report-modal__feedback" data-user-report-feedback hidden></p>

                <div class="user-report-modal__actions">
                    <button type="button" class="user-report-modal__btn user-report-modal__btn--ghost" data-user-report-close>キャンセル</button>
                    <button type="submit" class="user-report-modal__btn user-report-modal__btn--primary" data-user-report-submit>
                        <i class="fas fa-paper-plane"></i> 通報する
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(session('message'))
        <div class="px-4 pt-3">
            <div class="profile-edit-flash">{{ session('message') }}</div>
        </div>
    @endif

    @if(!empty($blockState['is_blocked']))
        <div class="px-4 pt-3">
            <div class="talk-block-notice">
                {{ !empty($blockState['blocked_by_me']) ? 'この相手をブロック中です。解除するまでメッセージ送信はできません。' : 'このトークは相手によってブロックされています。' }}
            </div>
        </div>
    @endif

    {{-- メッセージ表示エリア --}}
    <div class="chat-messages" id="chat-messages" data-delete-url="{{ $deleteUrl }}">
        @forelse($messages as $msg)
            @php
                $isAutoTypeMessage = in_array((int) $msg->type, [2, 3, 7, 8], true);
                $isAutoTextMessage = in_array((int) $msg->type, [1, 4, 5], true)
                    && \Illuminate\Support\Str::startsWith(trim((string) $msg->content), '【自動送信】');
                $renderAsIncoming = $isAutoTypeMessage || $isAutoTextMessage;
                $isMineForLayout = $msg->is_mine && !$renderAsIncoming;
            @endphp
            <div class="message-row {{ $isMineForLayout ? 'msg-right' : 'msg-left' }}" data-message-id="{{ $msg->id }}"@if(!empty($msg->can_delete)) data-can-delete="1" data-deletable-until="{{ $msg->created_at->copy()->addMinutes(10)->getTimestamp() * 1000 }}"@endif>
                @if(!$isMineForLayout)
                    <div class="msg-avatar-wrap">
                        @if($renderAsIncoming)
                            <div class="msg-avatar msg-avatar-auto" aria-label="自動送信">
                                <i class="fas fa-robot"></i>
                            </div>
                        @else
                            <img src="{{ $partnerAvatar }}" alt="" class="msg-avatar">
                        @endif
                    </div>
                @endif
                <div class="message-block">
                    <div class="message-inline">
                        @if($isMineForLayout)
                            <div class="msg-meta">
                                @if($msg->is_mine)
                                    <span class="msg-status"><i class="fas fa-check"></i></span>
                                @endif
                                <span class="msg-time">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                        @endif
                    @if($msg->type === 2)
                        @php
                            $selectedOption = $msg->selected_option ? \Carbon\Carbon::parse($msg->selected_option) : null;
                            $isInvalidatedOffer = !empty($msg->is_invalidated);
                        @endphp
                        <div class="message-bubble message-bubble-interview message-bubble-auto">
                            <div class="interview-card-head">
                                <div class="interview-title">
                                    <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                    <span>面談候補日をお送りします</span>
                                </div>
                                <span class="interview-badge">日程調整</span>
                            </div>
                            <p class="interview-body-copy">ご都合の良い日時をお選びください。</p>
                            <ul class="interview-option-list">
                                @foreach($msg->interview_options as $option)
                                    @php
                                        $optionDate = \Carbon\Carbon::parse($option);
                                        $isSelectedOption = $msg->selected_option === $option;
                                    @endphp
                                    <li>
                                        @if(!empty($canConfirmInterview) && !$msg->selected_option && !$msg->is_mine && empty($blockState['is_blocked']) && !$isInvalidatedOffer)
                                            <button
                                                type="button"
                                                class="interview-option-btn"
                                                data-offer-token="{{ $msg->offer_token }}"
                                                data-option-label="{{ $option }}"
                                                data-option-display="{{ $optionDate->format('Y年n月j日 H:i') }}"
                                            >
                                                <span class="interview-option-main">
                                                    <span class="interview-option-date">{{ $optionDate->format('Y年n月j日') }}</span>
                                                    <span class="interview-option-time">{{ $optionDate->format('H:i') }}</span>
                                                </span>
                                                <span class="interview-option-action">選択する</span>
                                            </button>
                                        @else
                                            <div class="interview-option-btn {{ $isSelectedOption ? 'is-selected' : '' }}">
                                                <span class="interview-option-main">
                                                    <span class="interview-option-date">{{ $optionDate->format('Y年n月j日') }}</span>
                                                    <span class="interview-option-time">{{ $optionDate->format('H:i') }}</span>
                                                </span>
                                                @if($isSelectedOption)
                                                    <span class="interview-option-action">決定済み</span>
                                                @elseif($isInvalidatedOffer)
                                                    <span class="interview-option-action">無効</span>
                                                @endif
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($selectedOption)
                                <p class="interview-note">確定日時: {{ $selectedOption->format('Y年n月j日 H:i') }}</p>
                            @elseif($isInvalidatedOffer)
                                <p class="interview-note">この候補日は更新により無効になりました。</p>
                            @endif
                            @if(!$isCast && !empty($canCancelStatus) && $msg->is_mine && $msg->selected_option)
                                <p class="interview-change-schedule-wrap">
                                    <button type="button" class="js-interview-change-schedule interview-change-schedule-btn">日程を変更</button>
                                </p>
                            @endif
                            @if($isMineForLayout)
                                <span class="message-bubble-tail" aria-hidden="true">
                                    <svg viewBox="0 0 8 12" fill="currentColor"><path d="M0 0V12C3 12 8 8 8 0H0Z"/></svg>
                                </span>
                            @endif
                        </div>
                    @elseif($msg->type === 3)
                        <div class="message-bubble message-bubble-interview message-bubble-confirmed message-bubble-auto">
                            <div class="interview-card-head">
                                <div class="interview-title">
                                    <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                    <span>面談日が確定しました</span>
                                </div>
                            </div>
                            <p class="interview-body-copy">以下の日時で面談日が確定しました。</p>
                            <p class="interview-confirmed-date">{{ \Carbon\Carbon::parse($msg->selected_option)->format('Y年n月j日 H:i') }}</p>
                            @if(!$isCast && !empty($canSelectResult))
                                <p class="interview-body-copy" style="margin-top:10px;">
                                    面談結果が確定したら、以下から採用／不採用を送信してください。
                                </p>
                                <div class="talk-result-panel-actions" style="margin-top:8px; gap:8px;">
                                    <button type="button" class="btn-interview btn-interview-result js-open-result-action" data-result-action="hired">
                                        <i class="fas fa-circle-check"></i>
                                        <span>採用を送る</span>
                                    </button>
                                    <button type="button" class="btn-interview btn-interview-result--negative js-open-result-action" data-result-action="rejected">
                                        <i class="fas fa-circle-xmark"></i>
                                        <span>不採用を送る</span>
                                    </button>
                                </div>
                            @endif
            {{-- 勤務完了報告 CTA は type=4 (採用連絡) メッセージ側で表示する。
                 面談確定メッセージには置かない（採用が確定した後の情報として一体化） --}}
                            @if($isMineForLayout)
                                <span class="message-bubble-tail" aria-hidden="true">
                                    <svg viewBox="0 0 8 12" fill="currentColor"><path d="M0 0V12C3 12 8 8 8 0H0Z"/></svg>
                                </span>
                            @endif
                        </div>
                    @elseif($msg->type === 4)
                        @php
                            // 採用連絡: 本文と 確定情報 (種別・時給) を分離。確定情報は
                            // メッセージ本文にも残っているが、application 側の値を
                            // 正としてライブ描画する（店舗が編集した場合の反映のため）。
                            $hireDisplayContent = trim((string) $msg->content);
                            $hireDisplayContent = str_replace(["\r\n", "\r"], "\n", $hireDisplayContent);
                            $hireIsAuto = \Illuminate\Support\Str::startsWith($hireDisplayContent, '【自動送信】');
                            $hireBody = $hireIsAuto ? trim(mb_substr($hireDisplayContent, mb_strlen('【自動送信】'))) : $hireDisplayContent;
                            $hireSepPos = mb_strpos($hireBody, "\n\n【確定情報】");
                            if ($hireSepPos !== false) {
                                $hireBody = trim(mb_substr($hireBody, 0, $hireSepPos));
                            }
                            $hireKindLabel = match ($hiredEmploymentKind ?? '') {
                                'trial' => '体験入店',
                                'help' => 'ヘルプ',
                                'fulltime' => '本入店',
                                default => '未確定',
                            };
                            $isLatestHiredCard = ((int) ($msg->id ?? 0) === (int) ($latestHiredMessageId ?? 0));
                            $showHireEditActions = $isLatestHiredCard && !empty($canEditHireTerms);
                            $showWorkCompleteCta = $isLatestHiredCard && $isCast && !empty($reviewApplicationId)
                                && in_array(($currentStatusCode ?? ''), ['hired'], true)
                                && empty($hasDepositForHireTerms);
                        @endphp
                        <div class="message-bubble message-bubble-interview message-bubble-auto message-bubble-hired">
                            <div class="interview-card-head">
                                <div class="interview-title">
                                    <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                    <span>採用連絡</span>
                                </div>
                                <span class="interview-badge">採用確定</span>
                            </div>
                            @if($hireBody !== '')
                                <p class="interview-body-copy">{!! nl2br(e($hireBody)) !!}</p>
                            @endif
                            <dl class="hire-terms">
                                <div class="hire-terms__row">
                                    <dt>採用区分</dt>
                                    <dd data-hire-terms-kind>{{ $hireKindLabel }}</dd>
                                </div>
                                <div class="hire-terms__row">
                                    <dt>時給</dt>
                                    <dd data-hire-terms-wage>{{ isset($hiredHourlyWage) ? '¥' . number_format((int) $hiredHourlyWage) : '未設定' }}</dd>
                                </div>
                            </dl>
                            @if($showHireEditActions)
                                <p class="interview-change-schedule-wrap">
                                    <button type="button" class="interview-change-schedule-btn js-open-hire-terms-edit"
                                            data-employment-kind="{{ $hiredEmploymentKind ?? 'fulltime' }}"
                                            data-hourly-wage="{{ $hiredHourlyWage ?? '' }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i> 種別・時給を修正
                                    </button>
                                </p>
                            @elseif($isLatestHiredCard && !$isCast && !empty($hasDepositForHireTerms))
                                <p class="interview-note">勤務完了報告が送信されたため、種別・時給は変更できません。</p>
                            @endif
                            @if($showWorkCompleteCta)
                                {{-- Work-completion is reported from the mypage flow so review
                                     posting and deposit creation happen through the same
                                     BillingManagementService::requestDepositForCast path.
                                     Doing it inline here previously bypassed review/deposit
                                     creation and left the status stuck for help/trial. --}}
                                <p class="interview-body-copy" style="margin-top:10px;">
                                    勤務が完了したら、採用・入金管理から勤務完了を報告してください。レビュー投稿とボーナス金申請までまとめて行えます。
                                </p>
                                <p class="interview-change-schedule-wrap">
                                    <a
                                        href="{{ route('cast.mypage.management') }}"
                                        class="interview-change-schedule-btn"
                                        data-application-id="{{ $reviewApplicationId }}"
                                    >
                                        <i class="fas fa-yen-sign" aria-hidden="true"></i>
                                        採用・入金管理を開く
                                    </a>
                                </p>
                            @endif
                        </div>
                    @elseif($msg->type === 6)
                        <div class="message-bubble message-bubble-image">
                            @if(!empty($msg->image_url))
                                <a href="{{ $msg->image_url }}" target="_blank" rel="noopener noreferrer" class="message-image-link">
                                    <img src="{{ $msg->image_url }}" alt="送信画像" class="message-image">
                                </a>
                            @endif
                            @if(!empty($msg->content))
                                <p class="message-image-caption">{!! nl2br(e($msg->content)) !!}</p>
                            @endif
                        </div>
                    @elseif($msg->type === 7)
                        <div class="message-bubble message-bubble-interview message-bubble-cancel-request message-bubble-auto">
                            <div class="interview-card-head">
                                <div class="interview-title">
                                    <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                    <span>面談キャンセル依頼</span>
                                </div>
                                <span class="interview-badge">確認待ち</span>
                            </div>
                            <p class="interview-body-copy">この面談をキャンセルして、候補日を送りなおします。承諾してください。</p>
                            @if($isCast && !$msg->is_mine && empty($blockState['is_blocked']))
                                <button type="button" class="interview-change-schedule-btn js-interview-cancel-accept">承諾する</button>
                            @endif
                        </div>
                    @elseif($msg->type === 8)
                        @php
                            $billingAudience = $msg->billing_audience ?? ($isCast ? 'cast' : 'shop');
                            $audienceLabel = $billingAudience === 'shop' ? '店舗向け' : 'キャスト向け';
                            $audienceChipClass = $billingAudience === 'shop' ? 'auto-msg-chip-shop' : 'auto-msg-chip-cast';
                        @endphp
                        <div class="message-bubble message-bubble-interview message-bubble-auto message-bubble-billing">
                            <div class="auto-msg-tag-row">
                                <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                <span class="auto-msg-chip auto-msg-chip-category">採用ボーナス</span>
                                <span class="auto-msg-chip {{ $audienceChipClass }}">{{ $audienceLabel }}</span>
                            </div>
                            <p class="auto-msg-title">{{ $msg->billing_title ?? '入金手続きの進捗' }}</p>
                            <p class="auto-msg-body">{!! nl2br(e($msg->content)) !!}</p>
                            <p class="auto-msg-link-wrap">
                                <a
                                    href="{{ $isCast ? route('cast.mypage.management') : route('shop.mypage.management', ['tab' => 'payment']) }}"
                                    class="interview-change-schedule-btn"
                                >採用・入金管理を開く</a>
                            </p>
                        </div>
                    @else
                        @php
                            $displayContent = trim((string) $msg->content);
                            $displayContent = str_replace(["\r\n", "\r"], "\n", $displayContent);
                            $displayContent = preg_replace('/\n{2,}/', "\n", $displayContent);
                            $isAutoMessage = \Illuminate\Support\Str::startsWith($displayContent, '【自動送信】');
                            $autoMessageBody = $isAutoMessage ? trim(mb_substr($displayContent, mb_strlen('【自動送信】'))) : $displayContent;
                        @endphp
                        @if($isAutoMessage)
                            {{-- 自動送信：文字プレフィックスではなくチップ + 専用背景で一目で区別 --}}
                            <div class="message-bubble message-bubble-auto">
                                <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                <p class="m-0">{!! nl2br(e($autoMessageBody)) !!}</p>
                                @if($isMineForLayout)<span class="message-bubble-tail" aria-hidden="true"><svg viewBox="0 0 8 12" fill="currentColor"><path d="M0 0V12C3 12 8 8 8 0H0Z"/></svg></span>@endif
                            </div>
                        @else
                            <div class="message-bubble"><p class="m-0">{!! nl2br(e($displayContent)) !!}</p>@if($isMineForLayout)<span class="message-bubble-tail" aria-hidden="true"><svg viewBox="0 0 8 12" fill="currentColor"><path d="M0 0V12C3 12 8 8 8 0H0Z"/></svg></span>@endif</div>
                        @endif
                    @endif
                        @if(!$isMineForLayout)
                            <div class="msg-meta">
                                <span class="msg-time">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-gray-500 mt-20 talk-empty-state">
                <i class="fas fa-comments opacity-10 text-6xl mb-4 block"></i>
                <p>メッセージはまだありません</p>
            </div>
        @endforelse
    </div>

    @if($isCast && !empty($canCancelStatus))
        <input type="hidden" id="send-cancel-status-enabled" value="1">
    @endif

    {{-- 入力エリア --}}
    @if(!empty($canSend))
        <div class="chat-input-area">
            <form id="chat-form" data-url="{{ $sendUrl }}" data-action-url="{{ $actionUrl }}" data-partner-id="{{ $partnerId }}" data-initiate="{{ request()->boolean('initiate') ? '1' : '0' }}">
                @csrf
                <div class="chat-input-row">
                    @if($isCast)
                        <input type="hidden" name="talk_topic" value="{{ $initialTalkTopic ?? '' }}">
                        <input type="hidden" name="talk_job_kind" value="{{ $initialTalkJobKind ?? '' }}">
                    @endif
                    {{-- ＋メニューは廃止（2026-07-20）。ボーナス報告は採用確定の自動送信カード内CTA。
                         定型文は左の「定型文」ボタン → ポップアップから選択（2026-08-23：常設パネルを撤去し
                         デフォルトはキーボード入力のみ）。面談候補日（店舗）は隣のカレンダーボタンから。 --}}
                    <button type="button" id="open-quick-reply-popup" class="btn-chat-action btn-chat-action--template" aria-label="定型文を選ぶ" title="定型文を選ぶ" aria-haspopup="dialog">
                        <i class="far fa-file-alt" aria-hidden="true"></i>
                    </button>
                    @if(!$isCast && !$isInterviewOfferLocked)
                        <button type="button" id="open-interview-modal-inline" class="btn-chat-action btn-chat-action--interview" aria-label="面談候補日を送信" title="面談候補日を送信" aria-haspopup="dialog">
                            <i class="far fa-calendar-alt" aria-hidden="true"></i>
                        </button>
                    @endif
                    <div class="chat-input-wrapper">
                        <textarea name="message" rows="1" placeholder="メッセージを入力..." class="focus:outline-none"></textarea>
                    </div>
                    <button type="submit" id="talk-send-btn" class="btn-send" aria-label="送信"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="talk-ng-warn" class="talk-ng-warn" role="alert" aria-live="polite" hidden>
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <span class="talk-ng-warn-text">使用できない表現が含まれています。</span>
                </div>
            </form>
        </div>
    @endif
</div>

{{-- 面談日候補 送信モーダル（店舗側のみ利用） --}}
<div id="talk-action-menu-overlay" role="dialog" aria-modal="true" aria-label="トークの操作" class="interview-modal-overlay interview-modal-overlay-sheet" aria-hidden="true">
    <div class="interview-modal interview-menu-sheet">
        <div class="interview-modal-header">
            <h2>メニュー</h2>
            <button type="button" class="interview-modal-close js-talk-action-menu-close" aria-label="閉じる">&times;</button>
        </div>
        <div class="talk-action-grid">
            @if(!$isCast)
                <button type="button" id="open-interview-modal" class="talk-action-item talk-action-item-primary {{ $isInterviewOfferLocked ? 'talk-action-item-disabled' : '' }}" @if($isInterviewOfferLocked) disabled @endif>
                    <span class="talk-action-icon"><i class="far fa-calendar-alt"></i></span>
                    <span>面談候補日を送信</span>
                </button>
            @endif
            <button type="button" id="open-template-send-menu" class="talk-action-item">
                <span class="talk-action-icon"><i class="far fa-file-alt"></i></span>
                <span>定型文を使う</span>
            </button>
            {{-- 勤務完了報告 CTA は「採用連絡」自動送信メッセージ側に集約したため
                 このメニューからは撤去。採用連絡が画面外にある時はスクロールで戻る。 --}}
            @if(!empty($canSelectResult))
                <button type="button" id="open-hire-modal-menu" class="talk-action-item">
                    <span class="talk-action-icon"><i class="fas fa-circle-check"></i></span>
                    <span>採用を送る</span>
                </button>
                <button type="button" id="open-reject-modal-menu" class="talk-action-item">
                    <span class="talk-action-icon"><i class="fas fa-circle-xmark"></i></span>
                    <span>不採用を送る</span>
                </button>
            @endif
        </div>
    </div>
</div>

<div id="talk-template-menu-overlay" role="dialog" aria-modal="true" aria-label="定型文を選択" class="interview-modal-overlay interview-modal-overlay-sheet" aria-hidden="true">
    <div class="interview-modal interview-menu-sheet">
        <div class="interview-modal-header">
            <h2>定型文を選択</h2>
            <button type="button" class="interview-modal-close js-talk-template-close" aria-label="閉じる">&times;</button>
        </div>
        <p class="talk-template-menu-hint">状況ごとの定型文をタップすると入力欄に挿入されます。「マイ定型文」タブでは自分用の定型文を編集できます。</p>
        <div id="talk-template-menu-list" class="talk-template-list"></div>
    </div>
</div>

@if(!$isCast)
<div id="job-kind-modal-overlay" role="dialog" aria-modal="true" aria-label="求人種別の設定" class="interview-modal-overlay interview-modal-overlay-sheet" aria-hidden="true">
    <div class="interview-modal interview-menu-sheet">
        <div class="interview-modal-header">
            <h2>求人種別の設定</h2>
            <button type="button" class="interview-modal-close js-job-kind-close" aria-label="閉じる">&times;</button>
        </div>
        <p id="talk-job-kind-guidance" class="interview-modal-desc">面談日を送る前に求人種別を確定してください。面談日確定後は変更できません。</p>
        <div class="interview-option-group">
            <label for="talk-room-job-kind">現在の求人種別</label>
            <select id="talk-room-job-kind" @if(empty($canSelectTalkJobKind)) disabled @endif>
                <option value="">未選択</option>
                <option value="trial">体験入店</option>
                <option value="help">ヘルプ</option>
            </select>
        </div>
        @if(!empty($canSelectTalkJobKind))
            <div class="interview-modal-footer">
                <button type="button" class="btn-interview-cancel js-job-kind-close">閉じる</button>
                <button type="button" id="save-talk-job-kind" class="btn-interview-submit">この内容で設定する</button>
            </div>
            <p id="talk-job-kind-save-status" class="talk-job-kind-save-status">未保存</p>
        @else
            <p class="talk-job-kind-lock-note">面談日確定後は変更できません。</p>
        @endif
    </div>
</div>

<div id="interview-modal-overlay" role="dialog" aria-modal="true" aria-label="面談候補日を送信" class="interview-modal-overlay" aria-hidden="true">
    <div class="interview-modal">
        <div class="interview-modal-header">
            <h2>面談候補日を送信</h2>
            <button type="button" class="interview-modal-close" aria-label="閉じる">&times;</button>
        </div>
        <p class="interview-modal-desc">
            求人種別を選んだうえで、面談の候補日時を最大3件まで送れます。キャストはこの中から1つ選んで確定します。
        </p>
        <form id="interview-form">
            <div class="interview-option-group interview-job-kind-selector">
                <label>求人種別 <em class="interview-option-req">必須</em></label>
                <div class="interview-job-kind-choices" role="radiogroup" aria-label="求人種別を選ぶ">
                    @foreach(['trial' => '体験入店', 'help' => 'ヘルプ'] as $kindValue => $kindLabel)
                        <label class="interview-job-kind-choice">
                            <input type="radio" name="modal_job_kind" value="{{ $kindValue }}"
                                   @if($currentTalkJobKindValue === $kindValue) checked @endif>
                            <span>{{ $kindLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="interview-job-kind-note">求人種別は体験入店またはヘルプの2種類です。採用／不採用が確定するまでは変更できます。</p>
            </div>
            {{-- 時刻は15分刻みで固定。<input type="time" step="900"> は
                 Firefox/一部モバイル系で step が無視され分単位入力を許してしまう
                 ため、<select> で候補スロットのみを提示する。サーバ側にも
                 TalkController@action で 15 分丸めガードを維持。 --}}
            @php
                $interviewTimeSlots15Min = [];
                for ($h = 0; $h < 24; $h++) {
                    foreach ([0, 15, 30, 45] as $m) {
                        $interviewTimeSlots15Min[] = sprintf('%02d:%02d', $h, $m);
                    }
                }
            @endphp
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">1</span>候補1 <em class="interview-option-req">必須</em></label>
                <input type="date" name="option1_date" aria-label="候補1の日付" required>
                <select name="option1_time" aria-label="候補1の時刻" class="interview-time-select" required>
                    <option value="">--:--</option>
                    @foreach($interviewTimeSlots15Min as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">2</span>候補2（任意）</label>
                <input type="date" name="option2_date" aria-label="候補2の日付">
                <select name="option2_time" aria-label="候補2の時刻" class="interview-time-select">
                    <option value="">--:--</option>
                    @foreach($interviewTimeSlots15Min as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">3</span>候補3（任意）</label>
                <input type="date" name="option3_date" aria-label="候補3の日付">
                <select name="option3_time" aria-label="候補3の時刻" class="interview-time-select">
                    <option value="">--:--</option>
                    @foreach($interviewTimeSlots15Min as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
            <div class="interview-modal-footer">
                <button type="button" class="btn-interview-cancel">キャンセル</button>
                <button type="submit" class="btn-interview-submit"><i class="far fa-calendar-check"></i> 候補日を送信</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($isCast)
<div id="interview-confirm-overlay" role="dialog" aria-modal="true" aria-label="面談日時の確認" class="interview-modal-overlay" aria-hidden="true">
    <div class="interview-modal interview-confirm-modal">
        <div class="interview-modal-header">
            <h2>この日時で確定しますか？</h2>
            <button type="button" class="interview-modal-close js-interview-confirm-close" aria-label="閉じる">&times;</button>
        </div>
        <p class="interview-modal-desc">確定後、この面談日時が店舗へ送信されます。</p>
        <div class="interview-confirm-summary">
            <span class="interview-confirm-summary-label">選択した日時</span>
            <strong id="interview-confirm-selected" class="interview-confirm-summary-value">-</strong>
        </div>
        <div class="interview-modal-footer">
            <button type="button" class="btn-interview-cancel js-interview-confirm-close">戻る</button>
            <button type="button" id="interview-confirm-submit" class="btn-interview-submit">この日時で確定</button>
        </div>
    </div>
</div>
@endif

@if(!$isCast && !empty($canEditHireTerms))
{{-- 採用連絡の 種別・時給 修正モーダル（店舗側のみ・勤務完了報告前まで） --}}
<div id="hire-terms-edit-overlay" role="dialog" aria-modal="true" aria-label="採用連絡の内容を修正" class="interview-modal-overlay" aria-hidden="true">
    <div class="interview-modal">
        <div class="interview-modal-header">
            <h2>採用区分・時給の修正</h2>
            <button type="button" class="interview-modal-close js-hire-terms-close" aria-label="閉じる">&times;</button>
        </div>
        <p class="interview-modal-desc">
            採用連絡送信後に、採用区分（種別）と時給を修正できます。キャストが勤務完了報告を送るまでの間のみ変更できます。
        </p>
        <form id="hire-terms-edit-form">
            @csrf
            <div class="interview-option-group interview-job-kind-selector">
                <label>採用区分 <em class="interview-option-req">必須</em></label>
                <div class="interview-job-kind-choices" role="radiogroup" aria-label="採用区分を選ぶ">
                    @foreach(['trial' => '体験入店', 'fulltime' => '本入店', 'help' => 'ヘルプ'] as $kindValue => $kindLabel)
                        <label class="interview-job-kind-choice">
                            <input type="radio" name="employment_kind" value="{{ $kindValue }}"
                                   @if(($hiredEmploymentKind ?? '') === $kindValue) checked @endif>
                            <span>{{ $kindLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="interview-option-group">
                <label for="hire-terms-hourly-wage">時給（円） <em class="interview-option-req">必須</em></label>
                <input type="text" id="hire-terms-hourly-wage" name="hired_regular_hourly_wage"
                       inputmode="numeric" placeholder="例: 5000" autocomplete="off"
                       value="{{ $hiredHourlyWage ?? '' }}">
            </div>
            <p id="hire-terms-edit-error" class="interview-note" style="color:#fca5a5; display:none;"></p>
            <div class="interview-modal-footer">
                <button type="button" class="btn-interview-cancel js-hire-terms-close">キャンセル</button>
                <button type="submit" class="btn-interview-submit" id="hire-terms-submit">この内容で更新する</button>
            </div>
        </form>
    </div>
</div>
@endif

@if(!$isCast && !empty($canSelectResult))
<div id="result-message-overlay" role="dialog" aria-modal="true" aria-label="採用結果の送信" class="interview-modal-overlay" aria-hidden="true">
    <div class="interview-modal">
        <div class="interview-modal-header">
            <h2 id="result-message-title">結果メッセージを送信</h2>
            <button type="button" class="interview-modal-close js-result-message-close" aria-label="閉じる">&times;</button>
        </div>
        <p id="result-message-desc" class="interview-modal-desc">テンプレートを選択し、必要に応じて文面を編集してください。</p>
        <div id="hired-hourly-wage-wrap" class="hired-wage-field-wrap" aria-hidden="true">
            <label for="hired-hourly-wage-input">採用時給（円・確定）</label>
            <input type="text" id="hired-hourly-wage-input" inputmode="numeric" placeholder="例: 5000" autocomplete="off">
            <p style="margin-top:6px; font-size:12px; color:#c4b5fd;">採用確定時は入力必須です。</p>
        </div>
        <div id="result-employment-kind-wrap" class="hired-wage-field-wrap is-visible" aria-hidden="false">
            <label for="result-employment-kind">採用区分</label>
            <select id="result-employment-kind" class="result-employment-kind-select">
                <option value="trial">新規入店</option>
                <option value="fulltime">本入店</option>
                <option value="help">ヘルプ</option>
            </select>
        </div>
        <div class="result-template-list" id="result-template-list"></div>
        <textarea id="result-message-textarea" class="result-message-textarea" placeholder="送信するメッセージを入力"></textarea>
        <div class="interview-modal-footer">
            <button type="button" class="btn-interview-cancel js-result-message-close">キャンセル</button>
            <button type="button" id="result-message-submit" class="btn-interview-submit">送信する</button>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
{{-- 入力エリア：定型文ポップアップ導線 / 面談候補日ボタン / composer 高さ・キーボード対応 --}}
(function () {
    'use strict';
    var form = document.getElementById('chat-form');
    if (!form) return;

    // 「定型文」ボタン（#open-quick-reply-popup）→ 定型文ポップアップ（#talk-template-menu-overlay）。
    // open 処理は talk-room.js 側で直接バインド。旧「＋メニュー」内の #open-template-send-menu も
    // フォールバックとして残している。

    // 面談候補日の送信（店舗のみ：入力欄左のカレンダーボタン → 既存モーダルを開く）
    var interviewInline = document.getElementById('open-interview-modal-inline');
    if (interviewInline) {
        interviewInline.addEventListener('click', function () {
            var trigger = document.getElementById('open-interview-modal');
            if (trigger) trigger.click();
        });
    }

    // 採用連絡カードの「種別・時給を修正」ボタン → hire-terms-edit モーダル（店舗のみ）
    var hireTermsOverlay = document.getElementById('hire-terms-edit-overlay');
    var hireTermsForm = document.getElementById('hire-terms-edit-form');
    if (hireTermsOverlay && hireTermsForm) {
        var hireTermsError = document.getElementById('hire-terms-edit-error');
        var hireTermsWageInput = document.getElementById('hire-terms-hourly-wage');
        var openHireTerms = function (btn) {
            // 現在値をフォームへ反映（案件ごとに再オープンした時のため）
            var kind = btn.getAttribute('data-employment-kind') || 'fulltime';
            var wage = btn.getAttribute('data-hourly-wage') || '';
            var radios = hireTermsForm.querySelectorAll('input[name="employment_kind"]');
            radios.forEach(function (r) { r.checked = (r.value === kind); });
            if (hireTermsWageInput) hireTermsWageInput.value = wage;
            if (hireTermsError) { hireTermsError.style.display = 'none'; hireTermsError.textContent = ''; }
            hireTermsOverlay.setAttribute('aria-hidden', 'false');
        };
        var closeHireTerms = function () {
            hireTermsOverlay.setAttribute('aria-hidden', 'true');
        };
        document.querySelectorAll('.js-open-hire-terms-edit').forEach(function (btn) {
            btn.addEventListener('click', function () { openHireTerms(btn); });
        });
        document.querySelectorAll('.js-hire-terms-close').forEach(function (el) {
            el.addEventListener('click', closeHireTerms);
        });
        hireTermsOverlay.addEventListener('click', function (e) {
            if (e.target === hireTermsOverlay) closeHireTerms();
        });
        hireTermsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var actionUrl = form.getAttribute('data-action-url');
            var csrfToken = (form.querySelector('input[name="_token"]') || {}).value || '';
            var partnerId = form.getAttribute('data-partner-id');
            var kindEl = hireTermsForm.querySelector('input[name="employment_kind"]:checked');
            var wageRaw = ((hireTermsWageInput && hireTermsWageInput.value) || '').replace(/[^\d]/g, '');
            if (!kindEl) {
                if (hireTermsError) { hireTermsError.textContent = '採用区分を選択してください。'; hireTermsError.style.display = 'block'; }
                return;
            }
            if (!wageRaw) {
                if (hireTermsError) { hireTermsError.textContent = '時給を入力してください。'; hireTermsError.style.display = 'block'; }
                return;
            }
            var submitBtn = document.getElementById('hire-terms-submit');
            if (submitBtn) submitBtn.disabled = true;
            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    partner_id: partnerId,
                    action_type: 'update_hire_terms',
                    employment_kind: kindEl.value,
                    hired_regular_hourly_wage: wageRaw
                })
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (submitBtn) submitBtn.disabled = false;
                if (!res.ok || !res.json.success) {
                    throw new Error((res.json && res.json.message) || '更新に失敗しました。');
                }
                closeHireTerms();
                window.location.reload();
            })
            .catch(function (err) {
                if (submitBtn) submitBtn.disabled = false;
                if (hireTermsError) { hireTermsError.textContent = err.message || '更新に失敗しました。'; hireTermsError.style.display = 'block'; }
            });
        });
    }

    // 入力エリアの実高さをメッセージ一覧の余白へ反映（cast/shop 両ロール共通）
    var inputArea = document.querySelector('#talk-room-container .chat-input-area');
    var messages = document.querySelector('#talk-room-container .chat-messages');
    if (inputArea && messages && !window.__talkComposerHBound) {
        window.__talkComposerHBound = true;
        var apply = function () {
            messages.style.setProperty('--talk-composer-h', inputArea.offsetHeight + 'px');
        };
        apply();
        if ('ResizeObserver' in window) new ResizeObserver(apply).observe(inputArea);
    }

    // ------------------------------------------------------------------
    // キーボード対応：
    //   - #talk-room-container は 100dvh でキーボード表示中は自動で縮む（レイアウト側）
    //   - JS では visualViewport の状態変化を .is-kbd-open クラスで反映し、
    //     padding など補助的な調整だけ行う（overlap を防ぐ）
    //
    //  BUG FIX: 以前は resize / scroll 両方で applyKbd を呼び、キーボード表示中は
    //  問答無用で messages.scrollTop = scrollHeight を実行していた。
    //  ・visualViewport.scroll は iOS Safari で pinch-zoom やアドレスバー変動、
    //    メッセージ列内のバウンススクロールでも発火する
    //  ・その度に「最下部にスナップ」してしまうため、ユーザーが履歴を遡ろうと
    //    しても即座に下へ引き戻され「スクロール不能」に見えていた
    //  対策:
    //    - scroll イベントの購読を撤去（キーボード開閉は resize だけで足りる）
    //    - スナップは「送信直後のように、ユーザーが元々最下部近くに居た時」
    //      に限定する（履歴を読んでいる時は現在位置を維持）
    // ------------------------------------------------------------------
    if (inputArea && 'visualViewport' in window) {
        var vv = window.visualViewport;
        var nearBottomForKbd = function () {
            if (!messages) return true;
            return messages.scrollHeight - messages.scrollTop - messages.clientHeight < 120;
        };
        var applyKbd = function () {
            var kbdH = Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
            // 40px 以下はアドレスバー変動等のノイズとして無視
            inputArea.classList.toggle('is-kbd-open', kbdH > 40);
            // メッセージエリアの余白を再計算（コンポーザ高さの変化を即反映）
            var wasNearBottom = nearBottomForKbd();
            if (messages && inputArea) {
                messages.style.setProperty('--talk-composer-h', inputArea.offsetHeight + 'px');
            }
            // キーボード出現時のみ「入力欄すぐ上」を維持。ただしユーザーが遡って
            // 履歴を見ている最中は追従しない（スクロールを奪わない）。
            if (kbdH > 40 && messages && wasNearBottom) {
                requestAnimationFrame(function () {
                    messages.scrollTop = messages.scrollHeight;
                });
            }
        };
        vv.addEventListener('resize', applyKbd);
        applyKbd();
    }
})();
</script>
@endpush


{{-- ===== User report modal (both cast and shop) =====
     Logic lives in public/assets/js/user-report.js (covered by frontend tests). --}}
@push('scripts')
<script src="{{ asset('assets/js/user-report.js') }}?v=20260924-report-fix"></script>
@endpush

@endsection
