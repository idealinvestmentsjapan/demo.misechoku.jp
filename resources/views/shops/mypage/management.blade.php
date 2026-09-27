@extends('layouts.app-v2')

@section('title', '採用・入金管理')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/recruitment.css') }}?v=20260801-btn-rules">
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/management.css') }}?v=20260508">
<link rel="stylesheet" href="{{ asset('assets/css/case-flow.css') }}?v=20260927-accordion">
<style>
    /* ========================================================
       採用・入金 統合タイムライン（店舗側）
       ======================================================== */
    .shop-management-shell { padding: 0 16px 16px; }

    /* 固定ヘッダー分のアンカー余白 */
    .case-group, .case-card { scroll-margin-top: calc(var(--header-height, 60px) + 12px); }

    .case-card {
        background: #ffffff;
        border: 1px solid var(--color-border);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 10px;
        position: relative;
        box-shadow: 0 2px 10px rgba(76, 29, 149, 0.08);
    }
    .case-card.is-actionable {
        border-color: var(--color-border-strong);
        background: linear-gradient(180deg, rgba(168, 85, 247, 0.06), #ffffff 40%);
        box-shadow: 0 2px 14px rgba(124, 58, 237, 0.18);
    }
    .case-card.is-completed { opacity: 0.82; }

    .case-card__head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .case-card__icon {
        width: 32px; height: 32px; flex: 0 0 auto;
        border-radius: 8px; background: rgba(168, 85, 247, 0.12); color: var(--gold);
        display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;
    }
    .case-card__avatar {
        width: 32px; height: 32px; flex: 0 0 auto;
        border-radius: 8px; object-fit: cover;
        border: 1px solid var(--color-border);
    }
    .case-card.is-completed .case-card__icon { background: var(--color-success-bg); color: var(--color-success); }
    .case-card__main { flex: 1; min-width: 0; }
    .case-card__shop-name { font-size: 0.94rem; font-weight: 800; color: var(--color-text-header); line-height: 1.3; word-break: break-word; margin: 0 0 2px; }
    .case-card__meta { font-size: 0.7rem; color: var(--color-text-muted); display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; }
    .case-card__meta i { color: var(--gold); font-size: 0.62rem; margin-right: 2px; }
    .case-card__meta strong { color: var(--color-text-header); font-weight: 800; }

    /* 横長 7 ステップパイプライン：横スライドで閲覧・大きめの文字 */
    .case-pipeline {
        list-style: none; margin: 0 0 10px; padding: 4px 0 8px;
        display: flex; gap: 0; position: relative;
        overflow-x: auto; -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .case-pipeline::-webkit-scrollbar { display: none; }
    .case-pipeline__step {
        position: relative; flex: 0 0 auto; min-width: 86px;
        text-align: center; padding-top: 32px; font-size: 0.74rem;
    }
    .case-pipeline__step::after {
        content: ''; position: absolute; top: 12px; left: 50%; right: -50%;
        height: 2px; background: rgba(168, 85, 247, 0.16); z-index: 0;
    }
    .case-pipeline__step:last-child::after { display: none; }
    .case-pipeline__step.is-done::after,
    .case-pipeline__step.is-current::after { background: var(--gold); }

    .case-pipeline__bullet {
        position: absolute; top: 0; left: 50%; transform: translateX(-50%);
        width: 24px; height: 24px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.72rem; font-weight: 800;
        background: #ffffff; border: 2px solid rgba(124, 58, 237, 0.28);
        color: var(--color-text-muted); z-index: 1;
    }
    .case-pipeline__step.is-done .case-pipeline__bullet {
        background: linear-gradient(135deg, var(--gold), var(--gold-deep)); color: #ffffff; border-color: var(--gold);
    }
    .case-pipeline__step.is-current .case-pipeline__bullet {
        background: #ffffff; color: var(--gold-deep, #6d28d9); border-color: var(--gold);
        animation: case-pulse 1.6s ease-in-out infinite;
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
    }
    @keyframes case-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0.45); }
        50% { box-shadow: 0 0 0 5px rgba(168, 85, 247, 0); }
    }
    .case-pipeline__label { display: block; font-size: 0.74rem; color: var(--color-text-muted); line-height: 1.3; padding: 0 4px; }
    .case-pipeline__step.is-done .case-pipeline__label,
    .case-pipeline__step.is-current .case-pipeline__label { color: var(--color-text-header); font-weight: 700; }

    .case-card__highlights {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px;
        margin: 8px 0 2px;
    }
    .case-card__highlight {
        background: rgba(124, 58, 237, 0.06); border-radius: 8px; padding: 6px 8px;
        font-size: 0.68rem; color: var(--color-text-muted);
    }
    .case-card__highlight strong {
        display: block; margin-top: 1px; font-size: 0.88rem; color: var(--color-text-header);
        font-variant-numeric: tabular-nums; font-weight: 800;
    }
    .case-card__highlight i { color: var(--gold); margin-right: 4px; font-size: 0.66rem; }

    /* 進行中／不採用の応募リスト（mini） */
    .mypage-mini-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .mypage-mini-row {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 12px; border-radius: 12px;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        text-decoration: none; color: inherit;
    }
    .mypage-mini-row:hover { border-color: rgba(168, 85, 247, 0.3); background: rgba(168, 85, 247, 0.04); }
    .mypage-mini-row__avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex: 0 0 auto; }
    .mypage-mini-row__avatar-fallback {
        width: 32px; height: 32px; border-radius: 50%; background: rgba(168, 85, 247, 0.14); color: #a78bfa;
        display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
    }
    .mypage-mini-row__name { flex: 1; font-size: 0.88rem; font-weight: 700; color: #f0a6c4; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mypage-mini-row__sub { font-size: 0.7rem; color: rgba(201,184,184,0.6); }
    .mypage-mini-row__status { flex: 0 0 auto; font-size: 0.7rem; padding: 3px 8px; border-radius: 999px; background: rgba(168, 85, 247, 0.1); color: #a78bfa; }
    .mypage-mini-row__status.is-rejected { background: rgba(220,38,38,0.12); color: #fca5a5; }
    .mypage-mini-row__status.is-overdue { background: rgba(220,38,38,0.12); color: #fca5a5; }
    .mypage-mini-row__chev { color: rgba(196, 181, 253, 0.4); font-size: 0.72rem; }

    /* 空状態 */
    .shop-management-empty {
        padding: 40px 12px; text-align: center;
        font-size: 0.86rem; color: rgba(201,184,184,0.6);
        border: 1px dashed rgba(255,255,255,0.08);
        border-radius: 14px; background: rgba(255,255,255,0.02);
    }

    /* セッション通知 */
    .management-summary-note {
        margin: 12px 0; padding: 10px 14px;
        border-radius: 10px;
        background: rgba(124, 58, 237, 0.08);
        border: 1px solid rgba(124, 58, 237, 0.30);
        color: #6d28d9; font-size: 0.82rem; line-height: 1.5;
    }

    /* モーダル（承認・入金処理共通）：ライト画面に追従して白パネル
       - z-index: グローバルフッターより前面（`!important` で親の stacking-context 事故を防ぐ）
       - モバイル時は panel が bottom-nav（--footer-height ≒ 75px + safe-area）に隠れないよう
         panel を padding-bottom 分だけ物理的に押し上げる（z-index が効かない場合の belt-and-suspenders） */
    .shop-action-modal {
        position: fixed !important;
        inset: 0;
        z-index: 3000 !important;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        background: rgba(20, 10, 35, 0.55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        padding: 0 0 calc(var(--footer-height, 75px) + 8px);
    }
    .shop-action-modal[hidden] { display: none; }
    @media (min-width: 640px) { .shop-action-modal { justify-content: center; padding-bottom: 0; } }
    .shop-action-modal-backdrop { position: absolute; inset: 0; cursor: pointer; }
    .shop-action-modal-panel { position: relative; width: 100%; max-width: min(28rem, calc(100vw - 2rem)); max-height: 90vh; background: #ffffff; border-top-left-radius: 1.5rem; border-top-right-radius: 1.5rem; border: 1px solid rgba(124, 58, 237, 0.30); display: flex; flex-direction: column; box-shadow: 0 25px 60px -12px rgba(76, 29, 149, 0.35); overflow: hidden; box-sizing: border-box; }
    .shop-action-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(124, 58, 237, 0.20); background: #f7f4fc; }
    .shop-action-modal-title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #241f33; letter-spacing: 0.04em; }
    .shop-action-modal-close { width: 2.5rem; height: 2.5rem; border: none; background: transparent; color: #6d6685; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .shop-action-modal-body { overflow-y: auto; overflow-x: hidden; padding: 1.5rem; min-width: 0; flex: 1 1 auto; box-sizing: border-box; }
    .shop-action-modal-note { font-size: 0.78rem; line-height: 1.7; color: #5f5876; margin: 0 0 1.2rem; padding: 0.9rem; background: #f7f4fc; border-radius: 0.75rem; border: 1px solid rgba(124, 58, 237, 0.16); }
    .shop-action-modal-checklist { display: grid; gap: 10px; margin-bottom: 1rem; }
    .shop-action-modal-check {
        display: flex; align-items: flex-start; gap: 10px;
        font-size: 0.86rem; color: #241f33;
        cursor: pointer; padding: 10px 12px; border-radius: 10px;
        background: #ffffff; border: 1px solid rgba(124, 58, 237, 0.16);
        line-height: 1.5;
    }
    .shop-action-modal-check:hover { background: rgba(124, 58, 237, 0.05); border-color: rgba(124, 58, 237, 0.30); }
    .shop-action-modal-check input[type="checkbox"] { flex: 0 0 auto; margin-top: 2px; accent-color: #7c3aed; width: 18px; height: 18px; cursor: pointer; }
    .shop-action-modal-check span { flex: 1; cursor: pointer; }
    .shop-action-modal-check:has(input:checked) { background: rgba(124, 58, 237, 0.08); border-color: rgba(124, 58, 237, 0.50); }
    .shop-action-modal-field { margin-bottom: 1rem; }
    .shop-action-modal-label { display: block; font-size: 0.74rem; font-weight: 600; color: #5f5876; margin-bottom: 6px; }
    .shop-action-modal-input { width: 100%; padding: 12px 14px; border-radius: 0.75rem; border: 1px solid rgba(124, 58, 237, 0.30); background: #ffffff; color: #241f33; font-size: 0.92rem; box-sizing: border-box; }
    .shop-action-modal-input:focus { outline: none; border-color: rgba(124, 58, 237, 0.60); }
    .shop-action-modal-error { color: #dc2626; font-size: 0.82rem; line-height: 1.5; display: none; margin: 0 0 0.6rem; }
    .shop-action-modal-error.show { display: block; }
    .shop-action-modal-footer { display: flex; gap: 0.75rem; padding: 1.1rem 1.5rem; background: #f7f4fc; border-top: 1px solid rgba(124, 58, 237, 0.20); }
    .shop-action-modal-btn { flex: 1; padding: 13px 1rem; border-radius: 0.75rem; font-size: 0.9rem; font-weight: 700; cursor: pointer; border: 0; }
    .shop-action-modal-btn-cancel { background: transparent; border: 1px solid rgba(124, 58, 237, 0.25); color: #5f5876; }
    .shop-action-modal-btn-submit { background: linear-gradient(135deg, #c4b5fd, #a78bfa 48%, #7c3aed); color: #1a0814; box-shadow: 0 4px 14px rgba(168, 85, 247, 0.35); }
    .shop-action-modal-btn-submit:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }

    /* レビュー確認への導線（承認モーダル本文の先頭に置く） */
    .shop-action-modal-review-link {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 14px; margin: 0 0 1rem;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(196, 181, 253, 0.14));
        border: 1px solid rgba(124, 58, 237, 0.30);
        text-decoration: none; color: #241f33;
        transition: background 0.15s ease, border-color 0.15s ease, transform 0.1s ease;
    }
    .shop-action-modal-review-link:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.14), rgba(196, 181, 253, 0.22));
        border-color: rgba(124, 58, 237, 0.55);
    }
    .shop-action-modal-review-link:active { transform: scale(0.99); }
    .shop-action-modal-review-link__icon {
        width: 34px; height: 34px; flex: 0 0 auto;
        border-radius: 8px; background: rgba(124, 58, 237, 0.16);
        color: #6d28d9;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.92rem;
    }
    .shop-action-modal-review-link__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .shop-action-modal-review-link__title { font-size: 0.9rem; font-weight: 700; color: #241f33; line-height: 1.3; }
    .shop-action-modal-review-link__sub { font-size: 0.72rem; color: #6d6685; line-height: 1.4; }
    .shop-action-modal-review-link__chev { color: #7c3aed; font-size: 0.8rem; flex: 0 0 auto; }

    /* 承認直後のハイライト。?highlight_app=XX で遷移してきた案件を強調して
       「どこにいるか」を明示する（waiting アコーディオンに移動しても迷子にならない）。 */
    .case-card.case-card--just-updated {
        animation: case-card-just-updated 3.5s ease-out;
        box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.55), 0 12px 32px rgba(124, 58, 237, 0.22);
    }
    @keyframes case-card-just-updated {
        0%, 55% { background-color: rgba(196, 181, 253, 0.25); }
        100%    { background-color: #ffffff; }
    }
</style>
@endpush

@section('content')
<div class="shop-management-shell animate-fadeIn">
    {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}

    @php
        $hiredCases = $hiredCases ?? [];
        $ongoingApplications = $ongoingApplications ?? [];
        $rejectedApplications = $rejectedApplications ?? [];
        // Split active cases into actionable (need your action) and waiting-on-others.
        $actionCases = collect($hiredCases)
            ->filter(fn ($c) => empty($c['is_completed']) && !empty($c['actionable']))
            ->values();
        $waitingCases = collect($hiredCases)
            ->filter(fn ($c) => empty($c['is_completed']) && empty($c['actionable']))
            ->values();
        $completedCases = collect($hiredCases)->filter(fn ($c) => !empty($c['is_completed']))->values();
    @endphp

    @if(session('status'))
        <p class="management-summary-note">{{ session('status') }}</p>
    @endif
    @if(session('error'))
        <p class="management-summary-note" style="color:#fca5a5; background: rgba(220,38,38,0.12); border-color: rgba(220,38,38,0.4);">{{ session('error') }}</p>
    @endif

    @if($actionCases->isNotEmpty())
        <details class="case-group case-group--action" data-case-group="action" open>
            <summary class="case-group__summary">
                <span class="case-group__label"><i class="fas fa-bolt"></i>要対応</span>
                <span class="case-group__count">{{ $actionCases->count() }}</span>
                <span class="case-group__chev"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="case-group__body">
                @foreach($actionCases as $case)
                    @include('shops.mypage._case_card', ['case' => $case])
                @endforeach
            </div>
        </details>
    @endif

    @if($waitingCases->isNotEmpty())
        <details class="case-group case-group--waiting" data-case-group="waiting" {{ $actionCases->isEmpty() ? 'open' : '' }}>
            <summary class="case-group__summary">
                <span class="case-group__label"><i class="fas fa-hourglass-half"></i>進行中（相手の対応待ち）</span>
                <span class="case-group__count">{{ $waitingCases->count() }}</span>
                <span class="case-group__chev"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="case-group__body">
                @foreach($waitingCases as $case)
                    @include('shops.mypage._case_card', ['case' => $case])
                @endforeach
            </div>
        </details>
    @endif

    @if(!empty($ongoingApplications))
        <details class="case-group case-group--ongoing" data-case-group="ongoing">
            <summary class="case-group__summary">
                <span class="case-group__label"><i class="fas fa-comments"></i>選考中・やり取り中</span>
                <span class="case-group__count">{{ count($ongoingApplications) }}</span>
                <span class="case-group__chev"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="case-group__body">
                <ul class="mypage-mini-list">
                    @foreach($ongoingApplications as $app)
                        <a href="{{ !empty($app['cast_id']) ? route('shop.talk.room', ['id' => $app['cast_id'], 'talk_topic' => 'other', 'initiate' => 1]) : '#' }}" class="mypage-mini-row">
                            @if(!empty($app['cast_avatar_url']))
                                <img src="{{ $app['cast_avatar_url'] }}" alt="" class="mypage-mini-row__avatar">
                            @else
                                <span class="mypage-mini-row__avatar-fallback"><i class="fas fa-user"></i></span>
                            @endif
                            <span class="mypage-mini-row__name">
                                {{ $app['cast_name'] }}
                                @if(!empty($app['job_kind_label']))
                                    <span class="mypage-mini-row__sub">／{{ $app['job_kind_label'] }}</span>
                                @endif
                            </span>
                            <i class="fas fa-chevron-right mypage-mini-row__chev"></i>
                        </a>
                    @endforeach
                </ul>
            </div>
        </details>
    @endif

    @if($completedCases->isNotEmpty())
        <details class="case-group case-group--done" data-case-group="done">
            <summary class="case-group__summary">
                <span class="case-group__label"><i class="fas fa-check-circle"></i>完了した案件</span>
                <span class="case-group__count">{{ $completedCases->count() }}</span>
                <span class="case-group__chev"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="case-group__body">
                @foreach($completedCases as $case)
                    @include('shops.mypage._case_card', ['case' => $case])
                @endforeach
            </div>
        </details>
    @endif

    @if(!empty($rejectedApplications))
        <details class="case-group case-group--rejected" data-case-group="rejected">
            <summary class="case-group__summary">
                <span class="case-group__label"><i class="fas fa-times-circle"></i>不採用となった応募</span>
                <span class="case-group__count">{{ count($rejectedApplications) }}</span>
                <span class="case-group__chev"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="case-group__body">
                <ul class="mypage-mini-list">
                    @foreach($rejectedApplications as $app)
                        <a href="{{ !empty($app['cast_id']) ? route('shop.talk.room', ['id' => $app['cast_id'], 'talk_topic' => 'other', 'initiate' => 1]) : '#' }}" class="mypage-mini-row">
                            @if(!empty($app['cast_avatar_url']))
                                <img src="{{ $app['cast_avatar_url'] }}" alt="" class="mypage-mini-row__avatar">
                            @else
                                <span class="mypage-mini-row__avatar-fallback"><i class="fas fa-user"></i></span>
                            @endif
                            <span class="mypage-mini-row__name">
                                {{ $app['cast_name'] }}
                                @if(!empty($app['job_kind_label']))
                                    <span class="mypage-mini-row__sub">／{{ $app['job_kind_label'] }}</span>
                                @endif
                            </span>
                            <i class="fas fa-chevron-right mypage-mini-row__chev"></i>
                        </a>
                    @endforeach
                </ul>
            </div>
        </details>
    @endif

    @if(empty($hiredCases) && empty($ongoingApplications) && empty($rejectedApplications))
        <div class="shop-management-empty">
            応募・採用案件がまだありません。<br>
            求人票を公開してキャストからの応募を受け付けましょう。
        </div>
    @endif
</div>

{{-- 承認モーダル --}}
<div id="shop-approve-modal" class="shop-action-modal" role="dialog" aria-labelledby="shop-approve-modal-title" aria-modal="true" hidden>
    <div class="shop-action-modal-backdrop" data-close-approve-modal></div>
    <div class="shop-action-modal-panel">
        <div class="shop-action-modal-header">
            <h3 id="shop-approve-modal-title" class="shop-action-modal-title">勤務完了報告の承認</h3>
            {{-- title/labels are swapped by JS for help-kind deposits --}}
            <button type="button" class="shop-action-modal-close" data-close-approve-modal aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="shop-action-modal-body">
            <p class="shop-action-modal-note" id="shop-approve-note">
                キャストから提出されたレビューと達成条件を確認のうえ、承認を行ってください。<br>
                承認後は、運営から請求書が発行されます。
            </p>
            {{-- レビュー確認への導線：承認前にキャスト投稿のレビュー本文を確認する --}}
            <a id="shop-approve-review-link" class="shop-action-modal-review-link"
               href="{{ route('shop.mypage.review.index') }}"
               target="_blank" rel="noopener">
                <span class="shop-action-modal-review-link__icon" aria-hidden="true"><i class="fas fa-comment-dots"></i></span>
                <span class="shop-action-modal-review-link__body">
                    <span class="shop-action-modal-review-link__title">レビュー内容を確認する</span>
                    <span class="shop-action-modal-review-link__sub" id="shop-approve-review-link-sub">別タブでレビュー一覧を開きます</span>
                </span>
                <i class="fas fa-external-link-alt shop-action-modal-review-link__chev" aria-hidden="true"></i>
            </a>
            <form id="shop-approve-form" action="{{ route('shop.mypage.deposit.approve') }}" method="POST">
                @csrf
                {{-- 対象 deposit の特定に使う。未指定だと service 側が「店舗最新」を対象にしてしまい、
                     複数の承認待ち案件があるときにクリックした案件と別のものが更新される。 --}}
                <input type="hidden" name="deposit_id" id="shop-approve-deposit-id" value="">
                <input type="hidden" name="application_id" id="shop-approve-application-id" value="">
                <div class="shop-action-modal-checklist">
                    <label class="shop-action-modal-check">
                        <input type="checkbox" name="confirm_review_checked" value="1" required>
                        <span>キャストが投稿したレビュー内容を確認しました</span>
                    </label>
                    <label class="shop-action-modal-check">
                        <input type="checkbox" name="confirm_bonus_condition" value="1" required>
                        <span id="shop-approve-condition-label">求人票に登録したボーナス達成条件を満たしていることを確認しました</span>
                    </label>
                </div>
                <p class="shop-action-modal-error" id="shop-approve-error"></p>
            </form>
        </div>
        <div class="shop-action-modal-footer">
            <button type="button" class="shop-action-modal-btn shop-action-modal-btn-cancel" data-close-approve-modal>キャンセル</button>
            <button type="submit" form="shop-approve-form" class="shop-action-modal-btn shop-action-modal-btn-submit" id="shop-approve-submit" disabled>
                承認する
            </button>
        </div>
    </div>
</div>

{{-- 入金処理モーダル --}}
<div id="shop-pay-modal" class="shop-action-modal" role="dialog" aria-labelledby="shop-pay-modal-title" aria-modal="true" hidden>
    <div class="shop-action-modal-backdrop" data-close-pay-modal></div>
    <div class="shop-action-modal-panel">
        <div class="shop-action-modal-header">
            <h3 id="shop-pay-modal-title" class="shop-action-modal-title">入金処理の報告</h3>
            <button type="button" class="shop-action-modal-close" data-close-pay-modal aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="shop-action-modal-body">
            <p class="shop-action-modal-note">
                請求書のお支払いが完了したら、振込日と金額を入力して報告してください。<br>
                報告内容は運営側で確認後、キャストへの振込が実行されます。
            </p>
            <form id="shop-pay-form" action="{{ route('shop.mypage.deposit.pay') }}" method="POST">
                @csrf
                {{-- 対象 deposit を明示（未指定だと store 側が店舗最新を対象にしてしまう） --}}
                <input type="hidden" name="deposit_id" id="shop-pay-deposit-id" value="">
                <div class="shop-action-modal-field">
                    <label class="shop-action-modal-label" for="shop-pay-amount">振込金額（円）<span style="color:#a78bfa;">*</span></label>
                    <input id="shop-pay-amount" type="number" name="reported_amount" min="1" step="1" required class="shop-action-modal-input" inputmode="numeric" placeholder="例: 50000">
                </div>
                <div class="shop-action-modal-field">
                    <label class="shop-action-modal-label" for="shop-pay-at">振込日時<span style="color:#a78bfa;">*</span></label>
                    <input id="shop-pay-at" type="datetime-local" name="reported_at" required class="shop-action-modal-input">
                </div>
                <div class="shop-action-modal-field">
                    <label class="shop-action-modal-label" for="shop-pay-ref">振込番号・参考情報（任意）</label>
                    <input id="shop-pay-ref" type="text" name="reference" maxlength="255" class="shop-action-modal-input" placeholder="振込番号、メモ等">
                </div>
                <p class="shop-action-modal-error" id="shop-pay-error"></p>
            </form>
        </div>
        <div class="shop-action-modal-footer">
            <button type="button" class="shop-action-modal-btn shop-action-modal-btn-cancel" data-close-pay-modal>キャンセル</button>
            <button type="submit" form="shop-pay-form" class="shop-action-modal-btn shop-action-modal-btn-submit" id="shop-pay-submit">
                報告する
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var approveModal = document.getElementById('shop-approve-modal');
    var payModal = document.getElementById('shop-pay-modal');

    // Pipeline: center the current step on load. Runs on details[open] change
    // so newly-opened accordions get the scroll adjustment too.
    function centerPipelines(root) {
        (root || document).querySelectorAll('.case-pipeline').forEach(function (p) {
            var cur = p.querySelector('.is-current') || p.querySelector('.case-pipeline__step.is-done:last-of-type');
            if (cur) {
                p.scrollLeft = Math.max(0, cur.offsetLeft - (p.clientWidth / 2) + (cur.clientWidth / 2));
            }
        });
    }
    centerPipelines(document);
    document.querySelectorAll('.case-group').forEach(function (g) {
        g.addEventListener('toggle', function () { if (g.open) centerPipelines(g); });
    });

    // ---- 承認 / 入金完了直後のハイライト ----
    // approveDeposit / payToPlatform が成功時に `?highlight_app=XX` を付けて
    // 戻ってくるので、該当 application の case-card を親アコーディオンごと開いて
    // スクロール + 一時的ハイライトを付ける。「押しても変わらない」感覚を排除するため。
    (function () {
        var params = new URLSearchParams(window.location.search);
        var appId = params.get('highlight_app');
        if (!appId) return;
        var card = document.querySelector('.case-card[data-application-id="' + CSS.escape(appId) + '"]');
        if (!card) return;
        // 親アコーディオンを強制展開（進行中や完了へ移動しているケースで閉じっぱなしを回避）
        var group = card.closest('details.case-group');
        if (group && !group.open) group.open = true;
        // スクロール + ハイライト
        var headerH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height'), 10) || 60;
        var y = card.getBoundingClientRect().top + window.scrollY - headerH - 16;
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
        card.classList.add('case-card--just-updated');
        setTimeout(function () { card.classList.remove('case-card--just-updated'); }, 3600);
    })();

    function openModal(el) { if (el) { el.removeAttribute('hidden'); document.body.style.overflow = 'hidden'; } }
    function closeModal(el) { if (el) { el.setAttribute('hidden', ''); document.body.style.overflow = ''; } }

    // 承認モーダル
    if (approveModal) {
        var approveForm = document.getElementById('shop-approve-form');
        var approveSubmit = document.getElementById('shop-approve-submit');
        function syncApproveReady() {
            var ok = true;
            approveForm.querySelectorAll('input[type="checkbox"][required]').forEach(function (c) { if (!c.checked) ok = false; });
            if (approveSubmit) approveSubmit.disabled = !ok;
        }
        approveForm.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.addEventListener('change', syncApproveReady); });
        document.querySelectorAll('[data-close-approve-modal]').forEach(function (b) { b.addEventListener('click', function () { closeModal(approveModal); }); });
        approveModal.addEventListener('click', function (e) { if (e.target === approveModal) closeModal(approveModal); });
    }

    // 入金処理モーダル
    if (payModal) {
        var payForm = document.getElementById('shop-pay-form');
        document.querySelectorAll('[data-close-pay-modal]').forEach(function (b) { b.addEventListener('click', function () { closeModal(payModal); }); });
        payModal.addEventListener('click', function (e) { if (e.target === payModal) closeModal(payModal); });
        // 振込日時のデフォルトを「いま」にしておく
        var payAt = document.getElementById('shop-pay-at');
        if (payAt && !payAt.value) {
            var now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            payAt.value = now.toISOString().slice(0, 16);
        }
    }

    var reviewIndexUrl = @json(route('shop.mypage.review.index'));
    function openApproveForCase(applicationId, jobKind, castId, castName, depositId) {
        if (!approveModal) return;
        // Hidden input に対象 deposit / application を必ずセット。
        // これが無いと service 側で店舗の最新 deposit を対象にしてしまい、
        // 押した案件と別の案件のステータスが更新される（または対象が既に別ステータスで失敗）。
        var depIdInput = document.getElementById('shop-approve-deposit-id');
        var appIdInput = document.getElementById('shop-approve-application-id');
        if (depIdInput) depIdInput.value = depositId || '';
        if (appIdInput) appIdInput.value = applicationId || '';
        var isHelp = (jobKind === 'help');
        var title = document.getElementById('shop-approve-modal-title');
        if (title) title.textContent = '勤務完了報告の承認';
        var note = document.getElementById('shop-approve-note');
        if (note) note.innerHTML = isHelp
            ? 'キャストからヘルプ勤務の完了報告が届いています。レビューと勤務内容を確認のうえ、承認を行ってください。<br>承認後は、運営からヘルプ時給の135%分の請求書が発行されます（うち50%がキャストへ振り込まれます）。'
            : 'キャストから提出されたレビューと達成条件を確認のうえ、承認を行ってください。<br>承認後は、運営から請求書が発行されます。';
        var condLabel = document.getElementById('shop-approve-condition-label');
        if (condLabel) condLabel.textContent = isHelp
            ? 'ヘルプ勤務が完了していることを確認しました（請求額はヘルプ時給の135%です）'
            : '求人票に登録したボーナス達成条件を満たしていることを確認しました';

        // レビュー確認リンクの URL・サブテキストをキャストごとに切り替える。
        // Reviews page（`/shop/mypage/reviews`）は cast_id クエリで対象キャストの
        // 直近レビューへ自動スクロール／ハイライトするよう拡張してある。
        var reviewLink = document.getElementById('shop-approve-review-link');
        var reviewSub  = document.getElementById('shop-approve-review-link-sub');
        if (reviewLink) {
            var url = reviewIndexUrl;
            if (castId) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'cast=' + encodeURIComponent(castId) + '&highlight=1';
            }
            reviewLink.setAttribute('href', url);
        }
        if (reviewSub) {
            reviewSub.textContent = castName
                ? castName + ' さんの直近レビューを別タブで開きます'
                : '別タブでレビュー一覧を開きます';
        }

        approveModal.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
        var btn = document.getElementById('shop-approve-submit'); if (btn) btn.disabled = true;
        var err = document.getElementById('shop-approve-error'); if (err) { err.textContent = ''; err.classList.remove('show'); }
        openModal(approveModal);
    }
    function openPayForCase(invoiceAmount, depositId) {
        if (!payModal) return;
        var amt = document.getElementById('shop-pay-amount');
        if (amt && invoiceAmount) amt.value = invoiceAmount;
        // 対象 deposit を明示（未指定だと store 側が店舗最新を対象にしてしまう）
        var depIdInput = document.getElementById('shop-pay-deposit-id');
        if (depIdInput) depIdInput.value = depositId || '';
        var err = document.getElementById('shop-pay-error'); if (err) { err.textContent = ''; err.classList.remove('show'); }
        openModal(payModal);
    }

    document.querySelectorAll('[data-case-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-case-action');
            var appId = btn.getAttribute('data-application-id');
            if (action === 'approve') {
                openApproveForCase(
                    appId,
                    btn.getAttribute('data-job-kind') || '',
                    btn.getAttribute('data-cast-id') || '',
                    btn.getAttribute('data-cast-name') || '',
                    btn.getAttribute('data-deposit-id') || ''
                );
            } else if (action === 'pay') {
                var card = btn.closest('.case-card');
                var amountText = card ? (card.querySelector('.case-card__highlight strong') || {}).textContent || '' : '';
                var digits = amountText.replace(/[^0-9]/g, '');
                openPayForCase(
                    digits ? parseInt(digits, 10) : '',
                    btn.getAttribute('data-deposit-id') || ''
                );
            }
        });
    });

});
</script>
@endpush
