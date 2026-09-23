@extends('layouts.app-v2')

@section('title', '許可証の提出・管理')
@section('body-class', 'page-shop-documents')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/shop-license-documents.css') }}?v=20260801-merge">
@endpush

@section('content')
@php
    $docList = collect($documents ?? []);
    $docTotal = $docList->count();
    $docApproved = $docList->where('status', 'approved')->count();
    $docPending  = $docList->where('status', 'pending')->count();
    $docRejected = $docList->where('status', 'rejected')->count();
    $docDraft    = $docList->where('status', 'draft')->count();
    $docNotSubmitted = $docTotal - $docApproved - $docPending - $docRejected - $docDraft;

    $overallVerified = $docTotal > 0 && $docApproved === $docTotal;
    $overallPending = !$overallVerified && $docPending > 0;
@endphp
<div class="license-page">
    {{-- 1. ヒーロー：全体ステータス --}}
    <div class="license-hero {{ $overallVerified ? 'is-verified' : ($overallPending ? 'is-pending' : '') }}">
        <span class="license-hero__icon" aria-hidden="true">
            <i class="fas {{ $overallVerified ? 'fa-circle-check' : ($overallPending ? 'fa-hourglass-half' : 'fa-file-signature') }}"></i>
        </span>
        <div class="license-hero__body">
            @if($overallVerified)
                <p class="license-hero__title">許可証すべて承認済み</p>
                <p class="license-hero__desc">求人票の公開に必要な書類はすべて揃っています。</p>
            @elseif($overallPending)
                <p class="license-hero__title">審査中の書類があります</p>
                <p class="license-hero__desc">運営が内容を確認しています（通常 1〜2 営業日）。承認まで一部機能が制限されます。</p>
            @else
                <p class="license-hero__title">許可証の提出が必要です</p>
                <p class="license-hero__desc">すべて承認されると求人票を公開できます。</p>
            @endif
        </div>
    </div>

    {{-- 2. 件数サマリー（KPI チップのみ。詳細は下の各書類カードに集約） --}}
    <div class="doc-counts" aria-label="提出状況">
        <span class="doc-counts__chip is-approved"><i class="fas fa-circle-check"></i>承認 {{ $docApproved }}</span>
        <span class="doc-counts__chip is-pending"><i class="fas fa-hourglass-half"></i>審査中 {{ $docPending }}</span>
        @if($docDraft > 0)
            <span class="doc-counts__chip is-pending"><i class="fas fa-paper-plane"></i>提出待ち {{ $docDraft }}</span>
        @endif
        @if($docRejected > 0)
            <span class="doc-counts__chip is-rejected"><i class="fas fa-circle-exclamation"></i>差戻し {{ $docRejected }}</span>
        @endif
        <span class="doc-counts__chip"><i class="fas fa-minus"></i>未提出 {{ $docNotSubmitted }}</span>
    </div>

    {{-- 3. 各書類：提出状況と操作を1カードに統合（アコーディオン部品） --}}
    @include('shops.mypage.partials.shop-license-documents', ['documents' => $documents ?? []])

    <p class="license-page__back">
        <a href="{{ route('shop.mypage.index') }}"><i class="fas fa-arrow-left"></i> マイページへ戻る</a>
    </p>
</div>
@endsection

@push('styles')
<style>
/* ============================================================
   許可証ページ（ライトモード）— 本人確認ページと同一デザイントークン
   ============================================================ */
:root {
    --doc-ink:      #1e1a30;
    --doc-body:     #4a4560;
    --doc-muted:    #8b84a1;
    --doc-accent:   #7c3aed;
    --doc-accent-2: #a78bfa;
    --doc-line:     rgba(124, 58, 237, 0.18);
    --doc-line-2:   rgba(124, 58, 237, 0.30);
    --doc-surface:  #ffffff;
    --doc-shadow:   0 4px 14px rgba(76, 29, 149, 0.08);
    --doc-radius:   16px;
}

.license-page { padding: 12px 0 32px; color: var(--doc-body); }
.license-page__back { margin: 22px 0 0; text-align: center; font-size: 0.84rem; }
.license-page__back a { color: var(--doc-accent); text-decoration: none; font-weight: 700; }

/* ヒーロー */
.license-hero {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 20px;
    margin-bottom: 20px;
    border-radius: var(--doc-radius);
    background: linear-gradient(180deg, rgba(168, 85, 247, 0.09), rgba(168, 85, 247, 0.02));
    border: 1px solid var(--doc-line-2);
    box-shadow: var(--doc-shadow);
}
.license-hero.is-verified {
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.10), rgba(16, 185, 129, 0.02));
    border-color: rgba(16, 185, 129, 0.35);
}
.license-hero.is-pending {
    background: linear-gradient(180deg, rgba(217, 119, 6, 0.09), rgba(217, 119, 6, 0.02));
    border-color: rgba(217, 119, 6, 0.35);
}
.license-hero__icon {
    flex: 0 0 auto;
    width: 44px; height: 44px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(124, 58, 237, 0.12);
    color: var(--doc-accent);
    font-size: 1.35rem;
}
.license-hero.is-verified .license-hero__icon { background: rgba(16, 185, 129, 0.12); color: #059669; }
.license-hero.is-pending  .license-hero__icon { background: rgba(217, 119, 6, 0.12); color: #b45309; }
.license-hero__body { flex: 1; min-width: 0; }
.license-hero__title {
    margin: 0 0 4px;
    font-size: 1.05rem; font-weight: 800;
    color: var(--doc-ink);
    letter-spacing: -0.005em;
    line-height: 1.3;
}
.license-hero__desc {
    margin: 0;
    font-size: 0.82rem;
    color: var(--doc-body);
    line-height: 1.65;
}

/* 件数サマリー（KPI チップ行のみ・詳細は各書類カードに統合） */
.doc-counts {
    display: flex; flex-wrap: wrap; gap: 6px;
    margin-bottom: 16px;
}
.doc-counts__chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem; font-weight: 700;
    background: #f4f0fb;
    color: var(--doc-body);
    border: 1px solid var(--doc-line);
}
.doc-counts__chip i { font-size: 0.7rem; }
.doc-counts__chip.is-approved { background: rgba(5, 150, 105, 0.08); color: #059669; border-color: rgba(5, 150, 105, 0.28); }
.doc-counts__chip.is-pending  { background: rgba(180, 83, 9, 0.08); color: #b45309; border-color: rgba(180, 83, 9, 0.28); }
.doc-counts__chip.is-rejected { background: rgba(220, 38, 38, 0.06); color: #dc2626; border-color: rgba(220, 38, 38, 0.30); }

/* ============================================================
   アコーディオン（partial の shop-license-documents.blade.php）を
   同じライトトークンに揃える上書き
   ============================================================ */
.license-section { margin: 0; }
.license-section__head { margin-bottom: 14px; }
.license-section__title {
    display: flex; align-items: center; gap: 8px;
    margin: 0 0 6px;
    font-size: 0.78rem; font-weight: 800;
    color: var(--doc-muted);
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.license-section__title i { color: var(--doc-accent); }
.license-section__title-en { display: none; }
.license-section__lead {
    margin: 0;
    font-size: 0.80rem;
    line-height: 1.65;
    color: var(--doc-body);
}
.license-accordion-list { display: flex; flex-direction: column; gap: 12px; }

.license-accordion {
    background: var(--doc-surface) !important;
    border: 1px solid var(--doc-line) !important;
    border-radius: var(--doc-radius) !important;
    box-shadow: var(--doc-shadow);
    overflow: hidden;
}
.license-accordion.is-approved { border-color: rgba(5, 150, 105, 0.32) !important; }
.license-accordion.is-pending  { border-color: rgba(180, 83, 9, 0.32) !important; }
.license-accordion.is-rejected { border-color: rgba(220, 38, 38, 0.32) !important; }

.license-accordion__head {
    display: flex; align-items: flex-start; gap: 12px;
    width: 100%;
    padding: 14px 16px !important;
    background: transparent;
    border: 0; cursor: pointer;
    text-align: left;
    color: var(--doc-ink) !important;
    transition: background 0.15s ease;
}
.license-accordion__head:hover { background: rgba(124, 58, 237, 0.04) !important; }
.license-accordion__title-block { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.license-accordion__title {
    font-size: 0.95rem !important;
    font-weight: 800 !important;
    color: var(--doc-ink) !important;
    line-height: 1.35;
    word-break: break-word;
}
.license-accordion__issuer {
    font-size: 0.72rem !important;
    color: var(--doc-muted) !important;
    font-weight: 600 !important;
    letter-spacing: 0.02em;
}
/* 統合後：ヘッダー内に「発行元・ファイル名・更新日」を1行に集約
   （旧 doc-summary__list の情報をここに移設） */
.license-accordion__meta {
    font-size: 0.74rem;
    color: var(--doc-muted);
    line-height: 1.5;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.license-accordion__meta strong {
    color: var(--doc-body);
    font-weight: 700;
}
.license-accordion__status-col {
    flex: 0 0 auto;
    display: inline-flex; flex-direction: column; align-items: flex-end; gap: 4px;
    padding-top: 1px;
}

.license-accordion__status {
    display: inline-flex; align-items: center;
    padding: 4px 10px !important;
    border-radius: 999px !important;
    font-size: 0.70rem !important; font-weight: 700 !important;
    letter-spacing: 0.02em;
    border: 1px solid transparent;
    flex: 0 0 auto;
    white-space: nowrap;
}
.license-accordion__status--not-submitted {
    background: #f4f0fb !important;
    color: var(--doc-muted) !important;
    border-color: var(--doc-line) !important;
}
.license-accordion__status--pending,
.license-accordion__status--draft {
    background: rgba(180, 83, 9, 0.10) !important;
    color: #b45309 !important;
    border-color: rgba(180, 83, 9, 0.30) !important;
}
.license-accordion__status--approved {
    background: rgba(5, 150, 105, 0.10) !important;
    color: #059669 !important;
    border-color: rgba(5, 150, 105, 0.30) !important;
}
.license-accordion__status--rejected {
    background: rgba(220, 38, 38, 0.08) !important;
    color: #dc2626 !important;
    border-color: rgba(220, 38, 38, 0.32) !important;
}
.license-accordion__chip {
    font-size: 0.66rem !important;
    padding: 2px 8px !important;
    border-radius: 999px !important;
    background: rgba(220, 38, 38, 0.10) !important;
    color: #dc2626 !important;
    border: 1px solid rgba(220, 38, 38, 0.30) !important;
    flex: 0 0 auto;
}
.license-accordion__chevron { flex: 0 0 auto; color: var(--doc-accent) !important; font-size: 0.82rem; }

.license-accordion__body {
    padding: 0 16px 16px !important;
    border-top: 1px solid var(--doc-line) !important;
    background: #faf7ff !important;
}

/* 折りたたみヒント */
.license-accordion__hint {
    margin: 12px 0 4px;
    border: 1px solid var(--doc-line);
    border-radius: 10px;
    background: #ffffff;
}
.license-accordion__hint summary {
    padding: 10px 12px;
    font-size: 0.80rem; font-weight: 700; color: var(--doc-accent);
    cursor: pointer;
    list-style: none;
    display: flex; align-items: center; gap: 6px;
}
.license-accordion__hint summary::-webkit-details-marker { display: none; }
.license-accordion__hint-body { padding: 0 12px 12px; font-size: 0.80rem; color: var(--doc-body); line-height: 1.7; }
.license-accordion__hint-body p { margin: 0 0 6px; }

/* 差し戻し理由 */
.license-accordion__ng {
    margin: 12px 0 0 !important;
    padding: 10px 12px !important;
    border-radius: 10px !important;
    background: rgba(220, 38, 38, 0.06) !important;
    border: 1px solid rgba(220, 38, 38, 0.30) !important;
    color: #b91c1c !important;
    font-size: 0.82rem !important;
    line-height: 1.55 !important;
}

/* パネル */
.license-accordion__panel {
    display: flex; flex-direction: column; gap: 12px;
    padding-top: 14px;
}

/* 既存ファイル表示 */
.license-accordion__file-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px !important;
    background: #ffffff !important;
    border: 1px solid var(--doc-line) !important;
    border-radius: 10px !important;
}
.license-accordion__file-icon {
    flex: 0 0 auto; width: 32px; height: 32px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px;
    background: rgba(124, 58, 237, 0.10) !important;
    color: var(--doc-accent) !important;
    font-size: 0.95rem;
}
.license-accordion__file-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.license-accordion__file-link {
    color: var(--doc-accent) !important;
    text-decoration: underline;
    text-underline-offset: 2px;
    font-size: 0.86rem; font-weight: 700;
    word-break: break-all;
}
.license-accordion__file-meta { font-size: 0.72rem !important; color: var(--doc-muted) !important; }

/* ラベル・入力 */
.license-accordion__field { display: flex; flex-direction: column; gap: 6px; }
.license-accordion__label {
    font-size: 0.80rem !important;
    font-weight: 700 !important;
    color: var(--doc-ink) !important;
    letter-spacing: 0.02em;
    display: inline-flex; align-items: center; gap: 6px;
}
.license-accordion__required {
    margin-left: 6px !important;
    padding: 1px 7px !important;
    border-radius: 999px !important;
    background: rgba(220, 38, 38, 0.10) !important;
    color: #dc2626 !important;
    font-size: 0.64rem !important; font-weight: 800 !important;
    letter-spacing: 0.04em;
}
.license-accordion__optional {
    margin-left: 6px !important;
    padding: 1px 7px !important;
    border-radius: 999px !important;
    background: #f4f0fb !important;
    color: var(--doc-muted) !important;
    font-size: 0.64rem !important; font-weight: 800 !important;
    letter-spacing: 0.04em;
}
.license-accordion__input {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px !important;
    border-radius: 10px !important;
    border: 1px solid var(--doc-line-2) !important;
    background: #ffffff !important;
    color: var(--doc-ink) !important;
    font-size: 0.90rem !important;
    font-family: inherit;
    box-sizing: border-box;
    color-scheme: light;
}
.license-accordion__input:focus {
    outline: none;
    border-color: var(--doc-accent) !important;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15) !important;
}
.license-accordion__field-note {
    margin: 0;
    font-size: 0.72rem; color: var(--doc-muted); line-height: 1.5;
}

/* アップロードエリア */
.license-accordion__dropzone {
    background: #ffffff !important;
    border: 1.5px dashed var(--doc-line-2) !important;
    border-radius: 12px !important;
    padding: 14px !important;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.license-accordion__dropzone.is-dragover {
    background: rgba(124, 58, 237, 0.06) !important;
    border-color: var(--doc-accent) !important;
}
.license-accordion__dropzone-inner {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 8px 0;
    text-align: center;
}
.license-accordion__dropzone-icon {
    font-size: 1.6rem !important;
    color: var(--doc-accent) !important;
}
.license-accordion__dropzone-text {
    margin: 0 !important;
    font-size: 0.82rem !important;
    line-height: 1.55;
    color: var(--doc-body) !important;
}
.license-accordion__file-selected {
    margin: 4px 0 0 !important;
    font-size: 0.80rem !important;
    font-weight: 700 !important;
    color: #047857 !important;
    word-break: break-all;
}
.license-accordion__preview {
    display: block;
    margin: 10px auto 0;
    max-width: 100%;
    max-height: 200px;
    border-radius: 10px;
    border: 1px solid var(--doc-line);
    box-shadow: 0 2px 8px rgba(76, 29, 149, 0.08);
    object-fit: contain;
    background: #ffffff;
}
.license-accordion__preview[hidden] { display: none; }

/* ボタン群 */
.license-accordion__btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    min-height: 44px;
    padding: 10px 18px !important;
    border-radius: 10px !important;
    font-size: 0.88rem !important; font-weight: 700 !important;
    font-family: inherit;
    letter-spacing: 0.02em;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.12s ease, border-color 0.12s ease, transform 0.12s ease;
}
.license-accordion__btn:active { transform: scale(0.98); }
.license-accordion__btn--secondary {
    background: #ffffff !important;
    border-color: var(--doc-line-2) !important;
    color: var(--doc-accent) !important;
}
.license-accordion__btn--secondary:hover {
    background: rgba(124, 58, 237, 0.06) !important;
    border-color: var(--doc-accent) !important;
}
.license-accordion__btn--primary,
.license-accordion__btn--full {
    background: linear-gradient(135deg, var(--doc-accent-2), var(--doc-accent)) !important;
    color: #ffffff !important;
    border: 0 !important;
    box-shadow:
        0 6px 14px rgba(124, 58, 237, 0.30),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    width: 100%;
    min-height: 50px;
    font-size: 0.95rem !important;
    font-weight: 800 !important;
}
.license-accordion__btn--ghost {
    background: transparent !important;
    color: var(--doc-body) !important;
    border-color: var(--doc-line) !important;
}
.license-accordion__btn--danger {
    background: #dc2626 !important;
    color: #ffffff !important;
    border: 0 !important;
    box-shadow: 0 6px 14px rgba(220, 38, 38, 0.30);
}

/* 取り下げゾーン */
.license-accordion__withdraw-zone {
    display: flex; flex-direction: column; gap: 10px;
    padding: 12px !important;
    border-radius: 10px !important;
    background: #faf7ff !important;
    border: 1px dashed var(--doc-line-2) !important;
}
.license-accordion__withdraw-warning {
    margin: 0 !important;
    font-size: 0.80rem !important;
    color: var(--doc-body) !important;
    line-height: 1.6;
    display: flex; align-items: flex-start; gap: 6px;
}
.license-accordion__withdraw-warning i { color: var(--doc-accent); margin-top: 2px; }

/* 送信フィードバック */
.license-accordion__submit-feedback {
    margin: 0 !important;
    padding: 10px 12px !important;
    border-radius: 10px !important;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.35);
    color: #047857;
    font-size: 0.82rem;
    line-height: 1.5;
}

/* サポート窓口 */
.license-accordion__support {
    padding: 12px !important;
    border-radius: 10px !important;
    background: #faf7ff !important;
    border: 1px solid var(--doc-line) !important;
    display: flex; flex-direction: column; gap: 6px;
}
.license-accordion__support-text {
    margin: 0 !important;
    font-size: 0.78rem !important;
    color: var(--doc-body) !important;
    line-height: 1.6;
}
.license-accordion__support-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: var(--doc-accent) !important;
    text-decoration: none;
    font-size: 0.82rem; font-weight: 700;
}
.license-accordion__support-link:hover { text-decoration: underline; }

/* 取り下げモーダル */
.license-withdraw-modal__panel {
    background: #ffffff !important;
    border: 1px solid var(--doc-line-2) !important;
    box-shadow: 0 20px 40px rgba(76, 29, 149, 0.25);
}

/* エラー・成功 */
.license-section__flash {
    margin: 0 0 12px !important;
    padding: 11px 14px !important;
    border-radius: 12px !important;
    background: rgba(16, 185, 129, 0.08) !important;
    color: #047857 !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
    font-size: 0.84rem !important;
    line-height: 1.6;
}
.license-section__errors {
    margin: 0 0 12px !important;
    padding: 11px 14px !important;
    border-radius: 12px !important;
    background: rgba(220, 38, 38, 0.06) !important;
    color: #b91c1c !important;
    border: 1px solid rgba(220, 38, 38, 0.32) !important;
    font-size: 0.84rem !important;
    line-height: 1.6;
}
.license-section__errors p { margin: 0; }
</style>
@endpush
