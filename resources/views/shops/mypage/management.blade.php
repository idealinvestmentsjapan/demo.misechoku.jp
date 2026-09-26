@extends('layouts.app-v2')

@section('title', '採用・入金管理')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/recruitment.css') }}?v=20260801-btn-rules">
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/management.css') }}?v=20260508">
<link rel="stylesheet" href="{{ asset('assets/css/case-flow.css') }}?v=20260720-talk-open">
<style>
    /* ========================================================
       採用・入金 統合タイムライン（店舗側）
       ======================================================== */
    .shop-management-shell { padding: 0 16px 16px; }

    /* 固定ヘッダー分のアンカー余白 */
    .mypage-stage-heading, .case-card { scroll-margin-top: calc(var(--header-height, 60px) + 12px); }

    /* 絞り込みチップ（ダッシュボード数値カードは廃止）
       2026-09-26: 大きすぎたため縮小 + アクティブ塗りを控えめに */
    .case-filter { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; }
    .case-filter__chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 999px;
        font-size: 0.74rem; font-weight: 700; font-family: inherit;
        background: transparent; border: 1px solid rgba(124, 58, 237, 0.30);
        color: #5b21b6; cursor: pointer; line-height: 1.3;
        transition: background .15s, color .15s, border-color .15s;
    }
    .case-filter__chip:hover { background: rgba(124, 58, 237, 0.05); }
    .case-filter__chip.is-active {
        background: rgba(124, 58, 237, 0.10);
        border-color: rgba(124, 58, 237, 0.55);
        color: #4c1d95;
    }
    .case-filter__num {
        min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px;
        background: #ef4444; color: #ffffff;
        font-size: 0.62rem; font-weight: 800; line-height: 16px; text-align: center;
        font-variant-numeric: tabular-nums;
    }

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

    .case-card__invoice-link-row {
        margin: 8px 0 0; padding-top: 8px;
        border-top: 1px dashed var(--color-border);
    }
    .case-card__invoice-link {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 0.74rem; padding: 6px 12px; border-radius: 999px;
        background: rgba(168, 85, 247, 0.08); border: 1px solid var(--color-border-strong);
        color: var(--gold-light); text-decoration: none;
    }
    .case-card__invoice-link:hover { background: rgba(168, 85, 247, 0.16); color: var(--color-text-header); }

    .case-card__action-row {
        display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        margin-top: 10px; padding-top: 10px;
        border-top: 1px dashed var(--color-border);
    }
    .case-card__waiting {
        font-size: 0.74rem; color: var(--color-text-muted);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .case-card__waiting i { color: var(--gold); }
    /* 主要アクション：ひと回り大きく・グラデ＋アクセントグローで最優先の操作として目立たせる */
    .case-card__action-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 12px 20px; border-radius: 999px; min-height: 46px;
        background: linear-gradient(135deg, var(--accent-grad-from, #a78bfa), var(--accent-grad-to, #7c3aed));
        color: var(--on-accent-strong, #ffffff); border: 0; font-weight: 800; font-size: 0.92rem; cursor: pointer;
        box-shadow: 0 8px 20px rgba(var(--accent-rgb, 139, 92, 246), 0.38), inset 0 1px 0 rgba(255,255,255,.28), inset 0 -1px 0 rgba(0,0,0,.15);
        margin-left: auto;
        transition: filter .15s, transform .12s, box-shadow .15s;
    }
    .case-card__action-btn:hover { filter: brightness(1.07); box-shadow: 0 10px 26px rgba(var(--accent-rgb, 139, 92, 246), 0.50), inset 0 1px 0 rgba(255,255,255,.28); }
    .case-card__action-btn:active { transform: scale(.97); box-shadow: 0 3px 8px rgba(var(--accent-rgb, 139, 92, 246), .35), inset 0 2px 4px rgba(0,0,0,.2); }
    .case-card__view-talk {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 0.74rem; color: var(--color-text-muted); text-decoration: none;
    }
    .case-card__view-talk:hover { color: var(--color-text-header); text-decoration: underline; }
    .case-card__waiting--done { color: var(--color-success); }
    .case-card__waiting--done i { color: var(--color-success); }

    /* セクション見出し：ラベル＋ホライズンの構成（cast 側と同じ） */
    .mypage-stage-heading {
        margin: 28px 0 12px;
        font-size: 0.72rem; font-weight: 800;
        color: var(--accent-text, #f0a6c4); letter-spacing: 0.14em; text-transform: uppercase;
        display: flex; align-items: center; gap: 10px;
    }
    .mypage-stage-heading i {
        color: var(--accent-text, #f0a6c4);
        font-size: 0.7rem;
        flex-shrink: 0;
        opacity: 0.85;
    }
    .mypage-stage-heading::after {
        content: '';
        flex: 1 1 auto;
        height: 1px;
        background: linear-gradient(to right, rgba(var(--accent-rgb, 214, 112, 162), 0.35), transparent);
        opacity: 0.6;
    }

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

    /* モーダル（承認・入金処理共通）：ライト画面に追従して白パネル */
    .shop-action-modal { position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; background: rgba(20, 10, 35, 0.55); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); padding: 0; }
    .shop-action-modal[hidden] { display: none; }
    @media (min-width: 640px) { .shop-action-modal { justify-content: center; } }
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
</style>
@endpush

@section('content')
<div class="shop-management-shell animate-fadeIn">
    {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}

    @php
        $hiredCases = $hiredCases ?? [];
        $ongoingApplications = $ongoingApplications ?? [];
        $rejectedApplications = $rejectedApplications ?? [];
        // Actionable cases first, preserve original relative order otherwise (stable sort via sortBy).
        $activeCases = collect($hiredCases)
            ->filter(fn ($c) => empty($c['is_completed']))
            ->sortBy(fn ($c) => empty($c['actionable']) ? 1 : 0)
            ->values();
        $completedCases = collect($hiredCases)->filter(fn ($c) => !empty($c['is_completed']))->values();
        $actionableCount = $activeCases->filter(fn ($c) => !empty($c['actionable']))->count();
    @endphp

    {{-- 絞り込みチップ（要対応/待ち/完了） --}}
    <div class="case-filter" role="group" aria-label="案件の絞り込み">
        <button type="button" class="case-filter__chip is-active" data-case-filter="all">すべて</button>
        <button type="button" class="case-filter__chip" data-case-filter="action">
            要対応@if($actionableCount > 0)<span class="case-filter__num">{{ $actionableCount }}</span>@endif
        </button>

    </div>

    @if(session('status'))
        <p class="management-summary-note">{{ session('status') }}</p>
    @endif
    @if(session('error'))
        <p class="management-summary-note" style="color:#fca5a5; background: rgba(220,38,38,0.12); border-color: rgba(220,38,38,0.4);">{{ session('error') }}</p>
    @endif

    @if($activeCases->isNotEmpty())
        <section data-case-section>
            <h2 class="mypage-stage-heading" id="section-active-cases"><i class="fas fa-fire"></i> 進行中の案件</h2>
            @foreach($activeCases as $case)
                @include('shops.mypage._case_card', ['case' => $case])
            @endforeach
        </section>
    @endif

    @if($completedCases->isNotEmpty())
        <section data-case-section>
            <h2 class="mypage-stage-heading" id="section-completed-cases"><i class="fas fa-check-circle"></i> 完了した案件</h2>
            @foreach($completedCases as $case)
                @include('shops.mypage._case_card', ['case' => $case])
            @endforeach
        </section>
    @endif

    @if(!empty($ongoingApplications))
        <h2 class="mypage-stage-heading"><i class="fas fa-comments"></i> 選考中・やり取り中</h2>
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
                    <span class="mypage-mini-row__status {{ !empty($app['is_decision_overdue']) ? 'is-overdue' : '' }}">
                        {{ $app['status_display_label'] ?? $app['status_label'] ?? '' }}
                    </span>
                    <i class="fas fa-chevron-right mypage-mini-row__chev"></i>
                </a>
            @endforeach
        </ul>
    @endif

    @if(!empty($rejectedApplications))
        <h2 class="mypage-stage-heading"><i class="fas fa-times-circle"></i> 不採用となった応募</h2>
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
                    <span class="mypage-mini-row__status is-rejected">{{ $app['status_display_label'] ?? $app['status_label'] ?? '' }}</span>
                    <i class="fas fa-chevron-right mypage-mini-row__chev"></i>
                </a>
            @endforeach
        </ul>
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
            <h3 id="shop-approve-modal-title" class="shop-action-modal-title">ボーナス申請の承認</h3>
            {{-- title/labels are swapped by JS for help-kind deposits --}}
            <button type="button" class="shop-action-modal-close" data-close-approve-modal aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="shop-action-modal-body">
            <p class="shop-action-modal-note" id="shop-approve-note">
                キャストから提出されたレビューと達成条件を確認のうえ、承認を行ってください。<br>
                承認後は、運営から請求書が発行されます。
            </p>
            <form id="shop-approve-form" action="{{ route('shop.mypage.deposit.approve') }}" method="POST">
                @csrf
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

    // 絞り込みチップ：要対応 / 待ち / 完了 でケースカードをフィルタ
    document.querySelectorAll('[data-case-filter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var mode = chip.getAttribute('data-case-filter');
            document.querySelectorAll('[data-case-filter]').forEach(function (c) {
                c.classList.toggle('is-active', c === chip);
            });
            document.querySelectorAll('.case-card[data-case-state]').forEach(function (card) {
                var st = card.getAttribute('data-case-state');
                card.style.display = (mode === 'all' || st === mode) ? '' : 'none';
            });
            // 表示中カードが1枚もないセクションは見出しごと隠す
            document.querySelectorAll('[data-case-section]').forEach(function (sec) {
                var visible = Array.prototype.some.call(
                    sec.querySelectorAll('.case-card[data-case-state]'),
                    function (c) { return c.style.display !== 'none'; }
                );
                sec.style.display = visible ? '' : 'none';
            });
        });
    });

    // 進行ステータスバー：現在ステップが中央に来るよう初期スクロール
    document.querySelectorAll('.case-pipeline').forEach(function (p) {
        var cur = p.querySelector('.is-current') || p.querySelector('.case-pipeline__step.is-done:last-of-type');
        if (cur) {
            p.scrollLeft = Math.max(0, cur.offsetLeft - (p.clientWidth / 2) + (cur.clientWidth / 2));
        }
    });

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

    function openApproveForCase(applicationId, jobKind) {
        if (!approveModal) return;
        var isHelp = (jobKind === 'help');
        var title = document.getElementById('shop-approve-modal-title');
        if (title) title.textContent = isHelp ? 'ヘルプ勤務完了の承認' : 'ボーナス申請の承認';
        var note = document.getElementById('shop-approve-note');
        if (note) note.innerHTML = isHelp
            ? 'キャストからヘルプ勤務の完了申請が届いています。レビューと勤務内容を確認のうえ、承認を行ってください。<br>承認後は、運営からヘルプ時給の135%分の請求書が発行されます（うち50%がキャストへ振り込まれます）。'
            : 'キャストから提出されたレビューと達成条件を確認のうえ、承認を行ってください。<br>承認後は、運営から請求書が発行されます。';
        var condLabel = document.getElementById('shop-approve-condition-label');
        if (condLabel) condLabel.textContent = isHelp
            ? 'ヘルプ勤務が完了していることを確認しました（請求額はヘルプ時給の135%です）'
            : '求人票に登録したボーナス達成条件を満たしていることを確認しました';
        approveModal.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
        var btn = document.getElementById('shop-approve-submit'); if (btn) btn.disabled = true;
        var err = document.getElementById('shop-approve-error'); if (err) { err.textContent = ''; err.classList.remove('show'); }
        openModal(approveModal);
    }
    function openPayForCase(invoiceAmount) {
        if (!payModal) return;
        var amt = document.getElementById('shop-pay-amount');
        if (amt && invoiceAmount) amt.value = invoiceAmount;
        var err = document.getElementById('shop-pay-error'); if (err) { err.textContent = ''; err.classList.remove('show'); }
        openModal(payModal);
    }

    document.querySelectorAll('[data-case-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-case-action');
            var appId = btn.getAttribute('data-application-id');
            if (action === 'approve') {
                openApproveForCase(appId, btn.getAttribute('data-job-kind') || '');
            } else if (action === 'pay') {
                var card = btn.closest('.case-card');
                var amountText = card ? (card.querySelector('.case-card__highlight strong') || {}).textContent || '' : '';
                var digits = amountText.replace(/[^0-9]/g, '');
                openPayForCase(digits ? parseInt(digits, 10) : '');
            }
        });
    });

});
</script>
@endpush
