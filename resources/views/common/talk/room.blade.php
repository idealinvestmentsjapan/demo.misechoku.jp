@extends('layouts.app-v2')

@php
    $isCast = request()->is('cast/*');
@endphp

@section('title', $partnerName ?? 'トーク')
@section('header_title', $partnerName)
@section('header_avatar', $partnerAvatar ?? asset('assets/images/common/no-image.png'))
@section('body-class', 'page-talk page-talk-room')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/talk.css') }}?v=20260926-footer-safearea">
<link rel="stylesheet" href="{{ asset('assets/css/talk-light.css') }}?v=20260823-template-popup">
@if($isCast)
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/review-modal.css') }}">
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
<script src="{{ asset('assets/js/talk-room.js') }}?v=20260922-msg-action-menu"></script>
@endpush

@section('content')
@php
    $isCast = request()->is('cast/*');
    $sendUrl = $isCast ? route('cast.talk.send') : route('shop.talk.send');
    $deleteUrl = $isCast ? route('cast.talk.delete') : route('shop.talk.delete');
    $actionUrl = $actionUrl ?? ($isCast ? route('cast.talk.action') : route('shop.talk.action'));
    $blockUrl = $blockUrl ?? ($isCast ? route('cast.talk.block') : route('shop.talk.block'));
    $partnerAvatar = $partnerAvatar ?? asset('assets/images/common/no-image.png');
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
                    {{-- キャスト側 or 本入店ロック済み：読み取り専用チップ --}}
                    <span class="talk-job-kind-chip {{ $isJobKindFulltimeLocked ? 'talk-job-kind-chip--locked' : '' }}">
                        <span class="talk-job-kind-chip__caption">種別</span>
                        <span id="talk-job-kind-current" class="talk-job-kind-chip__value" data-job-kind-current="{{ $currentTalkJobKindValue ?? '' }}">{{ $currentTalkJobKindLabel }}</span>
                        @if($isJobKindFulltimeLocked)
                            <i class="fas fa-lock" aria-hidden="true"></i>
                        @endif
                    </span>
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
                            @if($isCast && !empty($reviewApplicationId) && in_array(($currentStatusCode ?? ''), ['hired'], true))
                                <p class="interview-body-copy" style="margin-top:10px;">
                                    勤務が完了したら、以下から{{ $currentTalkJobKindValue === 'fulltime' ? 'ボーナス達成' : '勤務完了' }}を報告してください。
                                </p>
                                <button
                                    type="button"
                                    class="talk-bonus-cta js-work-complete-trigger"
                                    data-application-id="{{ $reviewApplicationId }}"
                                >
                                    <i class="fas fa-yen-sign" aria-hidden="true"></i>
                                    {{ $currentTalkJobKindValue === 'fulltime' ? 'ボーナス達成報告をする' : '勤務完了報告をする' }}
                                </button>
                            @endif
                            @if($isMineForLayout)
                                <span class="message-bubble-tail" aria-hidden="true">
                                    <svg viewBox="0 0 8 12" fill="currentColor"><path d="M0 0V12C3 12 8 8 8 0H0Z"/></svg>
                                </span>
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
                        <div class="message-bubble message-bubble-interview message-bubble-auto">
                            <div class="interview-card-head">
                                <div class="interview-title">
                                    <span class="auto-msg-chip"><i class="fas fa-robot" aria-hidden="true"></i>自動送信</span>
                                    <span>{{ $msg->billing_title ?? '入金手続きの進捗' }}</span>
                                </div>
                                <span class="interview-badge">採用ボーナス</span>
                            </div>
                            <p class="interview-body-copy">{!! nl2br(e($msg->content)) !!}</p>
                            <p class="interview-change-schedule-wrap">
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

    @if($isCast && !empty($canRequestFulltime))
        <div class="talk-result-panel">
            <div class="talk-result-panel-copy">
                <span class="talk-result-panel-title">本入店リクエスト</span>
                <p>体験採用後に、本入店希望を店舗へ送信できます。</p>
            </div>
            <div class="talk-result-panel-actions">
                <button type="button" id="send-fulltime-request" class="btn-interview btn-interview-result">
                    <i class="fas fa-paper-plane"></i>
                    <span>本入店をリクエスト</span>
                </button>
            </div>
        </div>
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
            @if($isCast && !empty($reviewApplicationId))
                <button type="button" id="open-work-complete-report-menu" class="talk-action-item js-work-complete-trigger" data-application-id="{{ $reviewApplicationId }}">
                    <span class="talk-action-icon"><i class="fas fa-circle-check"></i></span>
                    <span>{{ $currentTalkJobKindValue === 'fulltime' ? 'ボーナス達成報告' : '勤務完了報告' }}</span>
                </button>
            @endif
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
                <option value="trial">新規入店</option>
                <option value="fulltime">本入店</option>
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
            面談の候補日時を最大3件まで送れます。キャストはこの中から1つ選んで確定します。
        </p>
        <form id="interview-form">
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">1</span>候補1 <em class="interview-option-req">必須</em></label>
                <input type="date" name="option1_date" aria-label="候補1の日付" required>
                <input type="time" name="option1_time" aria-label="候補1の時刻" required>
            </div>
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">2</span>候補2（任意）</label>
                <input type="date" name="option2_date" aria-label="候補2の日付">
                <input type="time" name="option2_time" aria-label="候補2の時刻">
            </div>
            <div class="interview-option-group interview-option-group-grid">
                <label><span class="interview-option-no">3</span>候補3（任意）</label>
                <input type="date" name="option3_date" aria-label="候補3の日付">
                <input type="time" name="option3_time" aria-label="候補3の時刻">
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
<div id="work-complete-confirm-overlay" role="dialog" aria-modal="true" aria-label="勤務完了の確認" class="interview-modal-overlay" aria-hidden="true">
    <div class="interview-modal interview-confirm-modal">
        <div class="interview-modal-header">
            <h2 id="work-complete-confirm-title">勤務完了報告</h2>
            <button type="button" class="interview-modal-close js-work-complete-close" aria-label="閉じる">&times;</button>
        </div>
        <p id="work-complete-confirm-desc" class="interview-modal-desc">完了しますか？</p>
        <div class="interview-modal-footer">
            <button type="button" class="btn-interview-cancel js-work-complete-close">いいえ</button>
            <button type="button" id="work-complete-confirm-submit" class="btn-interview-submit">はい</button>
        </div>
    </div>
</div>

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

@if($isCast)
{{-- レビュー投稿モーダル（スターレーティング＋コメント） --}}
<div id="review-post-modal" class="payment-bank-modal" role="dialog" aria-labelledby="review-post-modal-title" aria-modal="true" hidden>
    <div class="payment-bank-modal-backdrop" data-close-review-modal></div>
    <div class="payment-bank-modal-panel review-modal-wrap">
        <header class="review-modal-header">
            <h3 id="review-post-modal-title" class="review-modal-title">レビュー投稿</h3>
            <button type="button" class="review-modal-close-btn" data-close-review-modal aria-label="閉じる"><i class="fas fa-times"></i></button>
        </header>
        <div class="payment-bank-modal-body review-modal-body">
            <p id="review-modal-loading" class="review-modal-loading">読み込み中...</p>
            <div id="review-modal-form-wrap" style="display:none;">
                <p class="review-modal-intro">勤務完了後、お店の雰囲気や働きやすさをレビューしてください。</p>
                <form id="review-post-form">
                    <input type="hidden" name="application_id" id="review-form-application-id" value="">
                    @csrf
                    <div class="review-rating-list" id="review-modal-scores"></div>
                    <div class="review-comment-card">
                        <label class="review-comment-label" for="review-modal-comment">レビューコメント</label>
                        <textarea id="review-modal-comment" name="review_comment" class="review-comment-textarea" rows="4" placeholder="働いてみた感想、雰囲気、条件の印象などを入力してください。" required></textarea>
                    </div>
                    <p id="review-modal-error" class="review-modal-error"></p>
                </form>
                <div class="review-modal-footer">
                    <button type="submit" form="review-post-form" class="review-submit-btn" id="review-submit-btn" disabled>
                        <i class="fas fa-paper-plane"></i> 投稿する
                    </button>
                    <p class="review-footer-hint" id="review-footer-hint">すべての項目を評価すると送信できます</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="bonus-confirm-modal" class="payment-bank-modal" role="dialog" aria-labelledby="bonus-confirm-modal-title" aria-modal="true" hidden>
    <div class="payment-bank-modal-backdrop" data-close-bonus-modal></div>
    <div class="payment-bank-modal-panel">
        <div class="payment-bank-modal-header">
            <h3 id="bonus-confirm-modal-title" class="payment-bank-modal-title">ボーナス条件達成確認</h3>
            <button type="button" class="payment-bank-modal-close" data-close-bonus-modal aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="payment-bank-modal-body">
            <p class="deposit-precheck-note">採用された時点のボーナス金・達成条件です。勤務日数・時間などの条件を確認してから申請してください。申請後は店舗の入金確認と運営の振込手続きに進みます。</p>
            <div class="deposit-precheck-card">
                <div class="deposit-precheck-title">
                    <span id="bonus-confirm-shop-name">—</span>
                    <span class="doc-status status-pending">採用済み案件</span>
                </div>
                <div class="deposit-precheck-meta">ボーナス金額: ¥<span id="bonus-confirm-amount">0</span></div>
                <div class="deposit-precheck-note" id="bonus-confirm-condition">—</div>
            </div>
            <form id="bonus-confirm-form">
                <input type="hidden" name="application_id" id="bonus-confirm-application-id" value="">
                @csrf
                <input type="hidden" name="confirm_bonus_condition" value="1">
                <label class="deposit-check-row">
                    <input type="checkbox" name="confirm_checked" value="1" required>
                    <span>上記のボーナス達成条件を確認し、申請内容に相違がないことを確認しました。</span>
                </label>
                <p id="bonus-confirm-error" class="deposit-precheck-note" style="color:#fca5a5; display:none;"></p>
                <div class="text-right mt-3">
                    <button type="submit" class="btn-action manage" id="bonus-confirm-submit-btn">この内容でボーナスを申請する</button>
                </div>
            </form>
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

@if($isCast)
@push('scripts')
<script>
{{-- 入力欄の実高さをメッセージ一覧の padding-bottom に反映（新着が入力欄に隠れない） --}}
(function () {
    'use strict';
    var inputArea = document.querySelector('#talk-room-container .chat-input-area');
    var messages = document.querySelector('#talk-room-container .chat-messages');
    if (!inputArea || !messages) return;

    function nearBottom() {
        return messages.scrollHeight - messages.scrollTop - messages.clientHeight < 120;
    }
    function applyComposerHeight() {
        var wasNearBottom = nearBottom();
        messages.style.setProperty('--talk-composer-h', inputArea.offsetHeight + 'px');
        // 末尾を見ている間は、入力欄が伸びても最後のメッセージに追従する
        if (wasNearBottom) messages.scrollTop = messages.scrollHeight;
    }
    applyComposerHeight();
    if ('ResizeObserver' in window) {
        new ResizeObserver(applyComposerHeight).observe(inputArea);
    }
    // メッセージ追加（送信/受信）でも末尾へ追従
    if ('MutationObserver' in window) {
        new MutationObserver(function () {
            if (nearBottom()) messages.scrollTop = messages.scrollHeight;
        }).observe(messages, { childList: true });
    }
})();
</script>
<script>
(function () {
    var reviewModal = document.getElementById('review-post-modal');
    var bonusModal = document.getElementById('bonus-confirm-modal');
    if (!reviewModal || !bonusModal) return;
    var requestTargetUrl = '{{ route("cast.mypage.deposit.request-target") }}';
    var reviewPostUrl = '{{ route("cast.mypage.deposit.review") }}';
    var depositRequestUrl = '{{ route("cast.mypage.deposit.request") }}';
    var chatForm = document.getElementById('chat-form');
    var actionUrl = chatForm ? chatForm.getAttribute('data-action-url') : '';
    var csrfToken = chatForm ? chatForm.querySelector('input[name="_token"]').value : '';
    var selectedTalkJobKind = window.selectedTalkJobKind || '';
    var workCompleteOverlay = document.getElementById('work-complete-confirm-overlay');
    var workCompleteTitle = document.getElementById('work-complete-confirm-title');
    var workCompleteDesc = document.getElementById('work-complete-confirm-desc');
    var workCompleteSubmitBtn = document.getElementById('work-complete-confirm-submit');

    var pendingReviewApplicationId = null;
    var pendingReviewTarget = null;
    var bonusFlowMode = 'review';

    function showReviewModalWithTarget(applicationId, target) {
        document.getElementById('review-form-application-id').value = applicationId;
        document.getElementById('review-modal-loading').style.display = 'block';
        document.getElementById('review-modal-form-wrap').style.display = 'none';
        var errEl = document.getElementById('review-modal-error');
        if (errEl) { errEl.textContent = ''; errEl.classList.remove('show'); }
        reviewModal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
        buildReviewRatingCards(target.review_contents || []);
        var cmtEl = document.getElementById('review-modal-comment');
        if (cmtEl) cmtEl.value = '';
        document.getElementById('review-modal-loading').style.display = 'none';
        document.getElementById('review-modal-form-wrap').style.display = 'block';
        checkReviewFormReady();
    }

    function openWorkCompleteFlow(applicationId) {
        if (selectedTalkJobKind === 'trial' || selectedTalkJobKind === 'help') {
            bonusFlowMode = 'work_complete';
            if (workCompleteTitle) {
                workCompleteTitle.textContent = '勤務完了報告';
            }
            if (workCompleteDesc) {
                workCompleteDesc.textContent = '完了しますか？';
            }
            if (workCompleteOverlay) {
                workCompleteOverlay.setAttribute('aria-hidden', 'false');
            }
            pendingReviewApplicationId = applicationId;
            return;
        }
        fetch(requestTargetUrl + '?application_id=' + encodeURIComponent(applicationId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.request_target) {
                (window.appToast || window.alert)(data.message || 'データの取得に失敗しました。', 'error');
                return;
            }
            pendingReviewApplicationId = applicationId;
            pendingReviewTarget = data.request_target;
            bonusFlowMode = 'bonus_then_review';
            showBonusConfirmModal(applicationId, pendingReviewTarget);
        })
        .catch(function () {
            (window.appToast || window.alert)('読み込みに失敗しました。', 'error');
        });
    }

    function buildReviewRatingCards(contents) {
        var scoresWrap = document.getElementById('review-modal-scores');
        scoresWrap.innerHTML = '';
        contents.forEach(function (c) {
            var card = document.createElement('div');
            card.className = 'review-rating-card';
            card.setAttribute('data-content-id', c.id);
            var question = document.createElement('p');
            question.className = 'review-rating-question';
            question.textContent = c.name || '';
            var row = document.createElement('div');
            row.className = 'review-rating-row';
            var stars = document.createElement('div');
            stars.className = 'review-rating-stars';
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'review_scores[' + c.id + ']';
            hidden.value = '0';
            hidden.setAttribute('data-rating-input', '1');
            for (var s = 1; s <= 5; s++) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'review-star-btn';
                btn.setAttribute('data-value', s);
                btn.innerHTML = '<i class="far fa-star"></i>';
                (function (starVal, button) {
                    button.addEventListener('click', function () {
                        hidden.value = starVal;
                        updateStarButtons(card, starVal);
                        updateRatingValueSpan(card, starVal);
                        checkReviewFormReady();
                    });
                    button.addEventListener('mouseenter', function () { setStarHover(card, starVal); });
                    button.addEventListener('mouseleave', function () { clearStarHover(card); });
                })(s, btn);
                stars.appendChild(btn);
            }
            var valueSpan = document.createElement('span');
            valueSpan.className = 'review-rating-value';
            valueSpan.textContent = '- / 5';
            row.appendChild(stars);
            row.appendChild(valueSpan);
            card.appendChild(question);
            card.appendChild(hidden);
            card.appendChild(row);
            scoresWrap.appendChild(card);
        });
    }
    function updateStarButtons(card, value) {
        var btns = card.querySelectorAll('.review-star-btn');
        btns.forEach(function (btn) {
            var v = parseInt(btn.getAttribute('data-value'), 10);
            btn.classList.toggle('active', v <= value);
            btn.querySelector('.fa-star').className = v <= value ? 'fas fa-star' : 'far fa-star';
        });
    }
    function setStarHover(card, value) {
        card.querySelectorAll('.review-rating-stars .review-star-btn').forEach(function (btn) {
            var v = parseInt(btn.getAttribute('data-value'), 10);
            btn.classList.toggle('hover', v <= value);
            if (btn.querySelector('.fa-star')) btn.querySelector('.fa-star').className = v <= value ? 'fas fa-star' : 'far fa-star';
        });
    }
    function clearStarHover(card) {
        card.querySelectorAll('.review-star-btn').forEach(function (btn) { btn.classList.remove('hover'); });
        var input = card.querySelector('input[name^="review_scores"]');
        if (input && input.value !== '0') { updateStarButtons(card, parseInt(input.value, 10)); }
    }
    function updateRatingValueSpan(card, value) {
        var span = card.querySelector('.review-rating-value');
        if (span) {
            span.textContent = value > 0 ? value + ' / 5' : '- / 5';
            span.classList.toggle('has-value', value > 0);
        }
    }
    function checkReviewFormReady() {
        var form = document.getElementById('review-post-form');
        if (!form) return;
        var allRated = true;
        form.querySelectorAll('input[data-rating-input="1"]').forEach(function (inp) {
            if (!inp.value || inp.value === '0') allRated = false;
        });
        var comment = (form.querySelector('#review-modal-comment') && form.querySelector('#review-modal-comment').value) || '';
        var ready = allRated && comment.trim().length > 0;
        var btn = document.getElementById('review-submit-btn');
        var hint = document.getElementById('review-footer-hint');
        if (btn) btn.disabled = !ready;
        if (hint) hint.style.display = ready ? 'none' : 'block';
    }
    var commentEl = document.getElementById('review-modal-comment');
    if (commentEl) { commentEl.addEventListener('input', checkReviewFormReady); commentEl.addEventListener('change', checkReviewFormReady); }
    function closeReviewModal() {
        reviewModal.setAttribute('hidden', '');
        document.body.style.overflow = '';
    }
    function showBonusConfirmModal(applicationId, target) {
        document.getElementById('bonus-confirm-application-id').value = applicationId;
        document.getElementById('bonus-confirm-shop-name').textContent = target.shop_name || '—';
        document.getElementById('bonus-confirm-amount').textContent = (target.bonus_amount || 0).toLocaleString();
        document.getElementById('bonus-confirm-condition').innerHTML = (target.bonus_condition || '（条件の記載なし）').replace(/\n/g, '<br>');
        document.getElementById('bonus-confirm-form').querySelector('input[name="confirm_checked"]').checked = false;
        document.getElementById('bonus-confirm-error').style.display = 'none';
        bonusModal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeBonusModal() {
        bonusModal.setAttribute('hidden', '');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.btn-review-post, .js-work-complete-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-application-id');
            if (id) openWorkCompleteFlow(id);
        });
    });
    document.querySelectorAll('.js-work-complete-close').forEach(function (el) {
        el.addEventListener('click', function () {
            if (workCompleteOverlay) workCompleteOverlay.setAttribute('aria-hidden', 'true');
        });
    });
    if (workCompleteOverlay) {
        workCompleteOverlay.addEventListener('click', function (e) {
            if (e.target === workCompleteOverlay) {
                workCompleteOverlay.setAttribute('aria-hidden', 'true');
            }
        });
    }
    if (workCompleteSubmitBtn) {
        workCompleteSubmitBtn.addEventListener('click', function () {
            if (!actionUrl || !csrfToken || !chatForm) return;
            if (workCompleteOverlay) workCompleteOverlay.setAttribute('aria-hidden', 'true');
            var partnerId = chatForm.getAttribute('data-partner-id');
            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    partner_id: partnerId,
                    action_type: 'work_complete_report'
                })
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.json.success) {
                    throw new Error((res.json && res.json.message) || '勤務完了報告に失敗しました。');
                }
                window.location.reload();
            })
            .catch(function (err) {
                (window.appToast || window.alert)(err.message || '勤務完了報告に失敗しました。', 'error');
            });
        });
    }
    document.querySelectorAll('[data-close-review-modal]').forEach(function (el) { el.addEventListener('click', closeReviewModal); });
    document.querySelectorAll('[data-close-bonus-modal]').forEach(function (el) { el.addEventListener('click', closeBonusModal); });
    reviewModal.addEventListener('click', function (e) { if (e.target === reviewModal) closeReviewModal(); });
    bonusModal.addEventListener('click', function (e) { if (e.target === bonusModal) closeBonusModal(); });
    document.getElementById('review-post-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        var fd = new FormData(form);
        var btn = document.getElementById('review-submit-btn');
        if (btn) btn.disabled = true;
        fetch(reviewPostUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (btn) btn.disabled = false;
            if (res.success) {
                closeReviewModal();
                window.location.reload();
            } else {
                var re = document.getElementById('review-modal-error');
                if (re) { re.textContent = res.message || '投稿に失敗しました。'; re.classList.add('show'); }
            }
        })
        .catch(function () {
            if (btn) btn.disabled = false;
            var re = document.getElementById('review-modal-error');
            if (re) { re.textContent = '送信に失敗しました。'; re.classList.add('show'); }
        });
    });
    document.getElementById('bonus-confirm-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        var confirmChecked = form.querySelector('input[name="confirm_checked"]');
        var errEl = document.getElementById('bonus-confirm-error');
        if (!confirmChecked || !confirmChecked.checked) {
            errEl.textContent = 'チェックを入れてください。';
            errEl.style.display = 'block';
            return;
        }
        if (bonusFlowMode === 'bonus_then_review') {
            if (!actionUrl || !csrfToken || !chatForm) {
                errEl.textContent = '送信先の設定が不足しています。';
                errEl.style.display = 'block';
                return;
            }
            var partnerIdForBonus = chatForm.getAttribute('data-partner-id');
            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    partner_id: partnerIdForBonus,
                    action_type: 'bonus_achievement_report'
                })
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (reportRes) {
                if (!reportRes.ok || !reportRes.json.success) {
                    throw new Error((reportRes.json && reportRes.json.message) || 'ボーナス達成報告に失敗しました。');
                }
                closeBonusModal();
                if (pendingReviewApplicationId && pendingReviewTarget) {
                    if (pendingReviewTarget.review_exists) {
                        (window.appToast || window.alert)('レビュー投稿は完了しています。', 'info');
                        return;
                    }
                    showReviewModalWithTarget(pendingReviewApplicationId, pendingReviewTarget);
                }
            })
            .catch(function (reportErr) {
                errEl.textContent = reportErr.message || 'ボーナス達成報告に失敗しました。';
                errEl.style.display = 'block';
            });
            return;
        }
        var fd = new FormData(form);
        fd.set('confirm_bonus_condition', '1');
        var btn = document.getElementById('bonus-confirm-submit-btn');
        if (btn) btn.disabled = true;
        fetch(depositRequestUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (btn) btn.disabled = false;
            if (res.success) {
                closeBonusModal();
                window.location.href = '{{ route("cast.mypage.management") }}';
            } else {
                errEl.textContent = res.message || '申請に失敗しました。';
                errEl.style.display = 'block';
            }
        })
        .catch(function () {
            if (btn) btn.disabled = false;
            errEl.textContent = '送信に失敗しました。';
            errEl.style.display = 'block';
        });
    });
})();
</script>

@endpush
@endif

{{-- ===== User report modal (both cast and shop) =====
     Logic lives in public/assets/js/user-report.js (covered by frontend tests).
     Keep this OUTSIDE the @if($isCast) push above: it once lived inside that
     block, which made the report button dead for shop users. --}}
@push('scripts')
<script src="{{ asset('assets/js/user-report.js') }}?v=20260924-report-fix"></script>
@endpush

@endsection
