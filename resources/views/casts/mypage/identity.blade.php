@extends('layouts.app-v2')

@section('title', '本人確認')
@section('body-class', 'page-cast-mypage page-cast-identity')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<style>
/* ============================================================
   本人確認ページ（ライトモード）
   共通デザイントークン（フォント2段階・色3段階に揃える）
   - ヘッダー文字   : #1e1a30 / 800
   - 本文           : #4a4560 / 500
   - 補助（灰紫）   : #8b84a1 / 500
   - アクセント     : #7c3aed
   - 面（カード）   : #ffffff / rgba紫 border / soft shadow
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

/* 外側ラッパー：mypage.css のダーク面を打ち消して薄紫背景を通す */
.cast-mypage-sub-page .mypage-detail-box,
.cast-mypage-sub-page .mypage-section {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
}
.cast-mypage-sub-page { color: var(--doc-body); }

/* ============================================================
   1. ヒーロー：全体ステータス
   ============================================================ */
.identity-hero {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 20px;
    margin-bottom: 20px;
    border-radius: var(--doc-radius);
    background: linear-gradient(180deg, rgba(168, 85, 247, 0.09), rgba(168, 85, 247, 0.02));
    border: 1px solid var(--doc-line-2);
    box-shadow: var(--doc-shadow);
}
.identity-hero__icon {
    flex: 0 0 auto;
    width: 44px; height: 44px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(124, 58, 237, 0.12);
    color: var(--doc-accent);
    font-size: 1.35rem;
}
.identity-hero__body { flex: 1; min-width: 0; }
.identity-hero__title {
    margin: 0 0 4px;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--doc-ink);
    letter-spacing: -0.005em;
    line-height: 1.3;
}
.identity-hero__desc {
    margin: 0;
    font-size: 0.82rem;
    color: var(--doc-body);
    line-height: 1.65;
}
.identity-hero.is-verified {
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.10), rgba(16, 185, 129, 0.02));
    border-color: rgba(16, 185, 129, 0.35);
}
.identity-hero.is-verified .identity-hero__icon { background: rgba(16, 185, 129, 0.12); color: #059669; }
.identity-hero.is-pending {
    background: linear-gradient(180deg, rgba(217, 119, 6, 0.09), rgba(217, 119, 6, 0.02));
    border-color: rgba(217, 119, 6, 0.35);
}
.identity-hero.is-pending .identity-hero__icon { background: rgba(217, 119, 6, 0.12); color: #b45309; }

/* フラッシュ */
.identity-flash {
    margin: 0 0 16px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(124, 58, 237, 0.08);
    border: 1px solid var(--doc-line-2);
    color: var(--doc-ink);
    font-size: 0.86rem;
    line-height: 1.6;
}

/* ============================================================
   2. 件数サマリー（KPI チップのみ・詳細は各書類カードに統合）
   ============================================================ */
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
   3. 承認催促
   ============================================================ */
.identity-remind {
    margin: 0 0 20px;
    display: flex; flex-direction: column; gap: 8px;
}
.identity-remind__btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    min-height: 48px;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid var(--doc-line-2);
    background: rgba(124, 58, 237, 0.06);
    color: var(--doc-accent);
    font-size: 0.90rem; font-weight: 800;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
}
.identity-remind__btn:active { transform: scale(0.98); }
.identity-remind__note {
    margin: 0; text-align: center;
    font-size: 0.74rem; color: var(--doc-muted);
}
.identity-remind__done {
    margin: 0; padding: 12px 14px; text-align: center;
    border-radius: 12px;
    border: 1px dashed var(--doc-line-2);
    color: var(--doc-body);
    font-size: 0.82rem;
}
.identity-remind__done i { color: var(--doc-accent); margin-right: 4px; }

/* ============================================================
   4. パターン切替タブ
   ============================================================ */
.identity-pattern-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: #ece7f7;
    border-radius: 12px;
    margin-bottom: 12px;
}
.identity-pattern-tab {
    flex: 1;
    padding: 12px 10px;
    border-radius: 9px;
    background: transparent; border: 0;
    color: var(--doc-body);
    font-size: 0.82rem; font-weight: 700;
    line-height: 1.35;
    cursor: pointer;
    text-align: center;
    transition: background 0.15s ease, color 0.15s ease;
}
.identity-pattern-tab.is-active {
    background: var(--doc-accent);
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(124, 58, 237, 0.30);
}
.identity-pattern-tab:not(.is-active):hover { background: rgba(124, 58, 237, 0.08); color: var(--doc-ink); }

.identity-pattern-help {
    margin: 0 0 16px;
    padding: 12px 14px;
    font-size: 0.82rem;
    line-height: 1.7;
    color: var(--doc-body);
    background: rgba(124, 58, 237, 0.05);
    border-left: 3px solid var(--doc-accent-2);
    border-radius: 0 10px 10px 0;
}
.identity-pattern-help strong { color: var(--doc-ink); font-weight: 700; }

/* ============================================================
   5. 書類カード（doc-card / doc-form）— 各書類の入力領域
   ============================================================ */
.doc-card {
    background: var(--doc-surface);
    border: 1px solid var(--doc-line);
    border-radius: var(--doc-radius);
    padding: 18px;
    margin-bottom: 14px;
    box-shadow: var(--doc-shadow);
}
.doc-card__head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    margin-bottom: 14px;
}
.doc-card__head-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.doc-card__title {
    margin: 0;
    font-size: 1rem; font-weight: 800;
    color: var(--doc-ink);
    letter-spacing: -0.005em;
    line-height: 1.35;
}
/* 統合後：ヘッダーに「提出書類・更新日」を1行で集約（旧 doc-card__current を廃止） */
.doc-card__meta {
    margin: 0;
    font-size: 0.76rem;
    color: var(--doc-muted);
    line-height: 1.5;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.doc-card__meta strong { color: var(--doc-body); font-weight: 700; }
.doc-card__pill {
    flex: 0 0 auto;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.02em;
    background: #f4f0fb;
    color: var(--doc-muted);
    border: 1px solid var(--doc-line);
    margin-top: 1px;
    white-space: nowrap;
}
.doc-card__pill.is-approved { background: rgba(5, 150, 105, 0.10); color: #059669; border-color: rgba(5, 150, 105, 0.30); }
.doc-card__pill.is-pending  { background: rgba(180, 83, 9, 0.10); color: #b45309; border-color: rgba(180, 83, 9, 0.30); }
.doc-card__pill.is-rejected { background: rgba(220, 38, 38, 0.08); color: #dc2626; border-color: rgba(220, 38, 38, 0.32); }

.doc-card__ng {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(220, 38, 38, 0.06);
    border: 1px solid rgba(220, 38, 38, 0.30);
    color: #b91c1c;
    font-size: 0.82rem;
    line-height: 1.55;
    display: flex; align-items: flex-start; gap: 6px;
}
.doc-card__ng i { margin-top: 2px; }

/* ---------- フォーム内部（縦積み・統一） ---------- */
.doc-form { display: flex; flex-direction: column; gap: 14px; }
.doc-form__field { display: flex; flex-direction: column; gap: 6px; }
.doc-form__label {
    font-size: 0.80rem; font-weight: 700; color: var(--doc-ink);
    letter-spacing: 0.02em;
    display: inline-flex; align-items: center; gap: 6px;
}
.doc-form__req {
    display: inline-flex; align-items: center;
    padding: 1px 7px;
    border-radius: 999px;
    background: rgba(220, 38, 38, 0.10);
    color: #dc2626;
    font-size: 0.64rem; font-weight: 800;
    letter-spacing: 0.04em;
}
.doc-form__req.is-optional {
    background: #f4f0fb;
    color: var(--doc-muted);
}

.doc-form__select,
.doc-form__input {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid var(--doc-line-2);
    background: #ffffff;
    color: var(--doc-ink);
    font-size: 0.90rem;
    font-family: inherit;
    box-sizing: border-box;
    color-scheme: light;
}
.doc-form__select:focus,
.doc-form__input:focus {
    outline: none;
    border-color: var(--doc-accent);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}

/* ドロップボタン：全幅・大きく・選択時は色替え */
.doc-form__drop {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 16px;
    border: 1.5px dashed var(--doc-line-2);
    border-radius: 12px;
    background: #faf7ff;
    color: var(--doc-body);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.doc-form__drop:hover {
    background: rgba(124, 58, 237, 0.06);
    border-color: var(--doc-accent-2);
}
.doc-form__drop-icon {
    flex: 0 0 auto;
    width: 40px; height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background: rgba(124, 58, 237, 0.10);
    color: var(--doc-accent);
    font-size: 1.05rem;
}
.doc-form__drop-text {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column; gap: 2px;
    text-align: left;
    line-height: 1.4;
    overflow: hidden;
}
.doc-form__drop-name {
    font-size: 0.88rem; font-weight: 700;
    color: var(--doc-ink);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.doc-form__drop-text small {
    font-size: 0.70rem; font-weight: 500; color: var(--doc-muted);
}
/* 選択済み状態 */
.doc-form__drop.is-selected {
    border-style: solid;
    border-color: rgba(16, 185, 129, 0.45);
    background: rgba(16, 185, 129, 0.06);
}
.doc-form__drop.is-selected .doc-form__drop-icon {
    background: rgba(16, 185, 129, 0.14);
    color: #059669;
}
.doc-form__drop.is-selected .doc-form__drop-name { color: #065f46; }

.doc-form__preview {
    display: block;
    max-width: 100%;
    max-height: 200px;
    margin-top: 6px;
    border-radius: 10px;
    border: 1px solid var(--doc-line);
    background: #ffffff;
    object-fit: contain;
    box-shadow: 0 2px 8px rgba(76, 29, 149, 0.08);
}
.doc-form__preview[hidden] { display: none; }
.doc-form__pdf-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 10px;
    margin-top: 4px;
    border-radius: 8px;
    background: rgba(220, 38, 38, 0.06);
    border: 1px solid rgba(220, 38, 38, 0.30);
    color: #b91c1c;
    font-size: 0.76rem; font-weight: 700;
    max-width: 100%; overflow: hidden;
}
.doc-form__pdf-chip[hidden] { display: none; }
.doc-form__pdf-chip span {
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* 送信ボタン（本ページ内の Primary CTA レシピ） */
.doc-form__submit {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    min-height: 50px;
    margin-top: 4px;
    padding: 12px 16px;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--doc-accent-2), var(--doc-accent));
    color: #ffffff;
    font-size: 0.95rem; font-weight: 800;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow:
        0 6px 14px rgba(124, 58, 237, 0.30),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    transition: transform 0.12s ease;
}
.doc-form__submit:active { transform: scale(0.98); }
.doc-form__submit:disabled { opacity: 0.65; cursor: progress; }

/* インラインメッセージ */
.cast-identity-error {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(220, 38, 38, 0.35);
    background: rgba(220, 38, 38, 0.05);
    color: #b91c1c;
    font-size: 0.82rem;
    line-height: 1.5;
    display: flex; align-items: flex-start; gap: 6px;
}
.cast-identity-error::before { content: "⚠"; flex-shrink: 0; }
.cast-identity-error[hidden] { display: none; }
.cast-identity-success {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(16, 185, 129, 0.35);
    background: rgba(16, 185, 129, 0.06);
    color: #047857;
    font-size: 0.82rem;
    line-height: 1.5;
}
.cast-identity-success[hidden] { display: none; }

/* ===== アップロード完了バナー（2段階フロー） ===== */
.cast-identity-upload-status {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(16, 185, 129, 0.10);
    color: #047857;
    border: 1px solid rgba(16, 185, 129, 0.35);
    font-size: 0.82rem;
    line-height: 1.55;
    display: flex; align-items: flex-start; gap: 6px;
}
.cast-identity-upload-status[hidden] { display: none; }
.cast-identity-upload-status i { margin-top: 2px; }
.cast-identity-upload-status.is-uploading {
    background: rgba(124, 58, 237, 0.08);
    color: var(--doc-accent);
    border-color: rgba(124, 58, 237, 0.32);
}
.cast-identity-upload-status.is-error {
    background: rgba(220, 38, 38, 0.06);
    color: #b91c1c;
    border-color: rgba(220, 38, 38, 0.32);
}

.cast-identity-submit-hint {
    margin: 6px 0 0;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(180, 83, 9, 0.06);
    color: #b45309;
    border: 1px solid rgba(180, 83, 9, 0.22);
    font-size: 0.74rem;
    line-height: 1.5;
    display: flex; align-items: flex-start; gap: 6px;
}
.cast-identity-submit-hint[hidden] { display: none; }
.cast-identity-submit-hint i { margin-top: 2px; color: #b45309; }

/* ============================================================
   提出済みプレビュー（承認待ち / 承認済み / 差し戻し で表示）
   ============================================================ */
.doc-submitted {
    margin: 0 0 14px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(124, 58, 237, 0.04);
    border: 1px solid var(--doc-line);
}
.doc-submitted__label {
    margin: 0 0 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--doc-body);
    letter-spacing: 0.02em;
    display: inline-flex; align-items: center; gap: 6px;
}
.doc-submitted__label i { color: var(--doc-accent); }
.doc-submitted__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}
.doc-submitted__item { margin: 0; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.doc-submitted__cap {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--doc-muted);
    letter-spacing: 0.04em;
}
.doc-submitted__img {
    display: block;
    width: 100%;
    max-height: 220px;
    object-fit: contain;
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid var(--doc-line);
    box-shadow: 0 2px 8px rgba(76, 29, 149, 0.08);
    transition: transform 0.15s ease;
}
.doc-submitted__img:hover { transform: scale(1.01); }
.doc-submitted__pdf {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(220, 38, 38, 0.06);
    border: 1px solid rgba(220, 38, 38, 0.30);
    color: #b91c1c;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
}
.doc-submitted__pdf:hover { background: rgba(220, 38, 38, 0.10); }
.doc-submitted__pdf i { font-size: 1.1rem; }

/* ============================================================
   ロック状態（承認待ち・承認済み）: 取り下げゾーン
   ============================================================ */
.doc-withdraw {
    padding: 14px;
    border-radius: 12px;
    background: rgba(180, 83, 9, 0.05);
    border: 1px solid rgba(180, 83, 9, 0.28);
    display: flex; flex-direction: column; gap: 10px;
}
.doc-withdraw__note {
    margin: 0;
    font-size: 0.84rem;
    line-height: 1.65;
    color: var(--doc-body);
    display: flex; align-items: flex-start; gap: 6px;
}
.doc-withdraw__note i {
    margin-top: 3px;
    color: #b45309;
    font-size: 0.85rem;
    flex: 0 0 auto;
}
.doc-withdraw__note strong { color: #b45309; font-weight: 800; }
.doc-withdraw__form { margin: 0; }
.doc-withdraw__btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    min-height: 46px;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid rgba(180, 83, 9, 0.35);
    background: #ffffff;
    color: #b45309;
    font-size: 0.90rem; font-weight: 800;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
}
.doc-withdraw__btn:hover { background: rgba(180, 83, 9, 0.06); border-color: rgba(180, 83, 9, 0.55); }
.doc-withdraw__btn:active { transform: scale(0.98); }
.doc-withdraw__btn:disabled { opacity: 0.6; cursor: progress; }

/* ===== 提出完了モーダル ===== */
.cast-identity-submitted-modal {
    position: fixed; inset: 0; z-index: 2600;
    display: none;
    align-items: center; justify-content: center;
    padding: 24px 16px;
}
.cast-identity-submitted-modal:not([hidden]) { display: flex; }
.cast-identity-submitted-modal__overlay {
    position: absolute; inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
}
.cast-identity-submitted-modal__panel {
    position: relative;
    width: min(420px, 100%);
    background: linear-gradient(180deg, #ffffff, #faf7ff);
    border: 1px solid var(--doc-line-2);
    border-radius: 18px;
    padding: 24px 20px 20px;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
}
.cast-identity-submitted-modal__icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    font-size: 1.5rem;
}
.cast-identity-submitted-modal__title {
    margin: 0 0 10px;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--doc-ink);
    letter-spacing: -0.005em;
}
.cast-identity-submitted-modal__text {
    margin: 0 0 18px;
    font-size: 0.84rem;
    color: var(--doc-body);
    line-height: 1.65;
}
.cast-identity-submitted-modal__btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    min-height: 48px;
    padding: 12px 16px;
    border-radius: 12px;
    border: 0;
    background: linear-gradient(135deg, var(--doc-accent-2), var(--doc-accent));
    color: #ffffff;
    font-size: 0.95rem; font-weight: 800;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(124, 58, 237, 0.30);
}
</style>
@endpush

@section('content')
@php
    $allowedTypes = $allowedTypes ?? [
        'photo_id' => ['driver_license', 'passport', 'mynumber_card', 'residence_card'],
        'non_photo_id' => ['health_insurance', 'pension_book'],
        'address_proof' => ['residence_certificate', 'utility_bill'],
    ];
    $typeLabels = $typeLabels ?? [];
    $categoryDocs = $categoryDocuments ?? ['photo_id' => null, 'non_photo_id' => null, 'address_proof' => null];
    $detectedPattern = $detectedPattern ?? 'photo';
    $isVerified = $isVerified ?? false;
    $isPendingReview = !$isVerified && ($identityStatus ?? '') === 'pending';

    $summaryRows = [
        ['label' => '顔写真付き身分証', 'pattern' => 'A', 'doc' => $categoryDocs['photo_id'] ?? null],
        ['label' => '顔写真なし身分証', 'pattern' => 'B', 'doc' => $categoryDocs['non_photo_id'] ?? null],
        ['label' => '住所確認書類',       'pattern' => 'B', 'doc' => $categoryDocs['address_proof'] ?? null],
    ];
    $idApproved = 0; $idPending = 0; $idRejected = 0; $idDraft = 0; $idNone = 0;
    foreach ($summaryRows as $r) {
        $sk = $r['doc']['status_key'] ?? null;
        if (!$r['doc']) { $idNone++; }
        elseif ($sk === 'approved') { $idApproved++; }
        elseif ($sk === 'rejected') { $idRejected++; }
        elseif ($sk === 'draft')    { $idDraft++; }
        else { $idPending++; }
    }
@endphp
<div class="content-wrapper animate-fadeIn">
    <div class="cast-mypage-sub-page">
        <section class="mypage-area">
            <div class="mypage-detail-box">
                <div class="mypage-section">

                    @if(session('status'))
                        <p class="identity-flash" role="status">{{ session('status') }}</p>
                    @endif

                    {{-- 1. ヒーロー：全体状況 --}}
                    <div class="identity-hero {{ $isVerified ? 'is-verified' : ($isPendingReview ? 'is-pending' : '') }}">
                        <span class="identity-hero__icon" aria-hidden="true">
                            <i class="fas {{ $isVerified ? 'fa-circle-check' : ($isPendingReview ? 'fa-hourglass-half' : 'fa-clock') }}"></i>
                        </span>
                        <div class="identity-hero__body">
                            @if($isVerified)
                                <p class="identity-hero__title">本人確認 完了</p>
                                <p class="identity-hero__desc">すべての書類が承認されています。</p>
                            @elseif($isPendingReview)
                                <p class="identity-hero__title">審査中です</p>
                                <p class="identity-hero__desc">運営が内容を確認しています（通常 1〜2 営業日）。承認まで一部機能が制限されます。</p>
                            @else
                                <p class="identity-hero__title">本人確認 未完了</p>
                                <p class="identity-hero__desc">下記のいずれかのパターンで書類を提出してください。</p>
                            @endif
                        </div>
                    </div>

                    {{-- 2. 件数サマリー（KPI チップのみ。詳細は下の各書類カードに集約） --}}
                    <div class="doc-counts" aria-label="提出状況">
                        <span class="doc-counts__chip is-approved"><i class="fas fa-circle-check"></i>承認 {{ $idApproved }}</span>
                        <span class="doc-counts__chip is-pending"><i class="fas fa-hourglass-half"></i>審査中 {{ $idPending }}</span>
                        @if($idDraft > 0)
                            <span class="doc-counts__chip is-pending"><i class="fas fa-paper-plane"></i>提出待ち {{ $idDraft }}</span>
                        @endif
                        @if($idRejected > 0)
                            <span class="doc-counts__chip is-rejected"><i class="fas fa-circle-exclamation"></i>差戻し {{ $idRejected }}</span>
                        @endif
                        <span class="doc-counts__chip"><i class="fas fa-minus"></i>未提出 {{ $idNone }}</span>
                    </div>

                    {{-- 3. 審査催促 --}}
                    @if($isPendingReview)
                        <div class="identity-remind">
                            @if(!empty($identityRemindSentRecently))
                                <p class="identity-remind__done">
                                    <i class="fas fa-paper-plane"></i> 承認の催促を送信済みです（24時間に1回まで）
                                </p>
                            @else
                                <form method="POST" action="{{ route('cast.mypage.identity.remind') }}"
                                      onsubmit="return confirm('運営へ本人確認の承認催促を送信します。よろしいですか？');">
                                    @csrf
                                    <button type="submit" class="identity-remind__btn">
                                        <i class="fas fa-paper-plane"></i> 運営に承認を催促する
                                    </button>
                                </form>
                                <p class="identity-remind__note">審査が長引いている場合、運営へ確認の連絡を送れます。</p>
                            @endif
                        </div>
                    @endif

                    {{-- 4. パターン切替タブ --}}
                    <div class="identity-pattern-tabs" role="tablist">
                        <button type="button" class="identity-pattern-tab {{ $detectedPattern === 'photo' ? 'is-active' : '' }}" data-pattern="photo">
                            パターンA<br>顔写真付き身分証 1枚
                        </button>
                        <button type="button" class="identity-pattern-tab {{ $detectedPattern === 'non_photo' ? 'is-active' : '' }}" data-pattern="non_photo">
                            パターンB<br>顔写真なし＋住所確認
                        </button>
                    </div>

                    {{-- 5. パターンA --}}
                    <div class="identity-pattern-pane" data-pattern-pane="photo" @if($detectedPattern !== 'photo') hidden @endif>
                        <p class="identity-pattern-help">
                            <strong>運転免許証 / パスポート / マイナンバーカード / 在留カード</strong> のいずれか 1 点をアップロードしてください。両面ある書類は表・裏の両方を提出してください。
                        </p>

                        @include('casts.mypage._identity_form', [
                            'category'    => 'photo_id',
                            'sectionTitle'=> '顔写真付き身分証',
                            'currentDoc'  => $categoryDocs['photo_id'] ?? null,
                            'allowedTypes'=> $allowedTypes['photo_id'],
                            'typeLabels'  => $typeLabels,
                            'showExpiry'  => true,
                            'requireBack' => false,
                        ])
                    </div>

                    {{-- 6. パターンB --}}
                    <div class="identity-pattern-pane" data-pattern-pane="non_photo" @if($detectedPattern !== 'non_photo') hidden @endif>
                        <p class="identity-pattern-help">
                            <strong>顔写真なし身分証（健康保険証 など）</strong>と<strong>住所確認書類（住民票・公共料金領収書 など）</strong>の <strong>両方</strong> をアップロードしてください。両方が承認されて本人確認完了となります。
                        </p>

                        @include('casts.mypage._identity_form', [
                            'category'    => 'non_photo_id',
                            'sectionTitle'=> '① 顔写真なし身分証',
                            'currentDoc'  => $categoryDocs['non_photo_id'] ?? null,
                            'allowedTypes'=> $allowedTypes['non_photo_id'],
                            'typeLabels'  => $typeLabels,
                            'showExpiry'  => false,
                            'requireBack' => false,
                        ])

                        @include('casts.mypage._identity_form', [
                            'category'    => 'address_proof',
                            'sectionTitle'=> '② 住所確認書類',
                            'currentDoc'  => $categoryDocs['address_proof'] ?? null,
                            'allowedTypes'=> $allowedTypes['address_proof'],
                            'typeLabels'  => $typeLabels,
                            'showExpiry'  => false,
                            'requireBack' => false,
                        ])
                    </div>

                    <p id="cast-identity-message" style="display:none; margin-top:8px;"></p>

                    {{-- 提出完了モーダル：DRAFT→PENDING に成功したときに表示 --}}
                    <div class="cast-identity-submitted-modal" data-cast-submitted-modal hidden role="dialog" aria-modal="true" aria-labelledby="cast-submitted-title">
                        <div class="cast-identity-submitted-modal__overlay" data-cast-submitted-close></div>
                        <div class="cast-identity-submitted-modal__panel">
                            <span class="cast-identity-submitted-modal__icon" aria-hidden="true"><i class="fas fa-paper-plane"></i></span>
                            <h3 id="cast-submitted-title" class="cast-identity-submitted-modal__title">運営に提出しました</h3>
                            <p class="cast-identity-submitted-modal__text">運営による承認をお待ちください（通常 1〜2 営業日）。承認まで一部機能が制限されます。</p>
                            <button type="button" class="cast-identity-submitted-modal__btn" data-cast-submitted-close>
                                <i class="fas fa-check"></i> OK
                            </button>
                        </div>
                    </div>

                    {{-- 取り下げ確認モーダル：PENDING/APPROVED → DRAFT に戻す明示的アクション --}}
                    <div class="cast-identity-submitted-modal" data-cast-withdraw-modal hidden role="dialog" aria-modal="true" aria-labelledby="cast-withdraw-title">
                        <div class="cast-identity-submitted-modal__overlay" data-cast-withdraw-cancel></div>
                        <div class="cast-identity-submitted-modal__panel">
                            <span class="cast-identity-submitted-modal__icon" aria-hidden="true" style="background: rgba(180, 83, 9, 0.15); color: #b45309;">
                                <i class="fas fa-rotate-left"></i>
                            </span>
                            <h3 id="cast-withdraw-title" class="cast-identity-submitted-modal__title">提出を取り下げますか？</h3>
                            <p class="cast-identity-submitted-modal__text">
                                取り下げると審査が中断され、書類を差し替えることができます。<br>
                                再度提出するまで本人確認は完了しません。
                            </p>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="cast-identity-submitted-modal__btn" data-cast-withdraw-cancel
                                        style="background: transparent; color: var(--doc-body); border: 1px solid var(--doc-line-2); box-shadow: none;">
                                    キャンセル
                                </button>
                                <button type="button" class="cast-identity-submitted-modal__btn" data-cast-withdraw-confirm
                                        style="background: linear-gradient(135deg, #f59e0b, #b45309);">
                                    <i class="fas fa-rotate-left"></i> 取り下げる
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // パターン切替
    var tabs = document.querySelectorAll('.identity-pattern-tab');
    var panes = document.querySelectorAll('[data-pattern-pane]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-pattern');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            panes.forEach(function (p) {
                p.hidden = p.getAttribute('data-pattern-pane') !== key;
            });
        });
    });

    // ===== 提出完了モーダル =====
    var submittedModal = document.querySelector('[data-cast-submitted-modal]');
    function openSubmittedModal() {
        if (!submittedModal) return;
        submittedModal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function closeSubmittedModalAndReload() {
        if (!submittedModal) return;
        submittedModal.hidden = true;
        document.body.style.overflow = '';
        window.location.reload();
    }
    if (submittedModal) {
        submittedModal.querySelectorAll('[data-cast-submitted-close]').forEach(function (el) {
            el.addEventListener('click', closeSubmittedModalAndReload);
        });
    }

    // ===== 各カテゴリのフォーム（2段階フロー: ファイル選択で即アップロード, 提出ボタンで審査依頼） =====
    var csrfToken = '{{ csrf_token() }}';
    var uploadUrl = '{{ route("cast.mypage.identity.upload") }}';
    var submitUrl = '{{ route("cast.mypage.identity.submit") }}';
    var withdrawUrl = '{{ route("cast.mypage.identity.withdraw") }}';

    // ===== 取り下げモーダル + フォーム送信 =====
    var withdrawModal = document.querySelector('[data-cast-withdraw-modal]');
    var withdrawConfirmBtn = document.querySelector('[data-cast-withdraw-confirm]');
    var pendingWithdrawCategory = null;
    var pendingWithdrawForm = null;

    function openWithdrawModal(category, form) {
        pendingWithdrawCategory = category;
        pendingWithdrawForm = form;
        if (!withdrawModal) return;
        withdrawModal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function closeWithdrawModal() {
        pendingWithdrawCategory = null;
        pendingWithdrawForm = null;
        if (!withdrawModal) return;
        withdrawModal.hidden = true;
        document.body.style.overflow = '';
    }
    if (withdrawModal) {
        withdrawModal.querySelectorAll('[data-cast-withdraw-cancel]').forEach(function (el) {
            el.addEventListener('click', closeWithdrawModal);
        });
    }

    document.querySelectorAll('form[data-cast-withdraw-form]').forEach(function (form) {
        var categoryInput = form.querySelector('input[name="category"]');
        var errorEl = form.querySelector('[data-cast-withdraw-error]');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (errorEl) errorEl.hidden = true;
            openWithdrawModal(categoryInput ? categoryInput.value : '', form);
        });
    });

    if (withdrawConfirmBtn) {
        withdrawConfirmBtn.addEventListener('click', function () {
            var category = pendingWithdrawCategory;
            var form = pendingWithdrawForm;
            if (!category) { closeWithdrawModal(); return; }
            var errorEl = form ? form.querySelector('[data-cast-withdraw-error]') : null;
            var btn = form ? form.querySelector('.doc-withdraw__btn') : null;
            withdrawConfirmBtn.disabled = true;
            if (btn) btn.disabled = true;

            fetch(withdrawUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ category: category }),
            })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
            .then(function () {
                closeWithdrawModal();
                window.location.reload();
            })
            .catch(function (err) {
                withdrawConfirmBtn.disabled = false;
                if (btn) btn.disabled = false;
                closeWithdrawModal();
                if (errorEl) {
                    var msgs = err && err.errors ? Object.values(err.errors).flat() : [];
                    errorEl.textContent = msgs[0] || (err && err.message) || '取り下げに失敗しました。時間をおいて再度お試しください。';
                    errorEl.hidden = false;
                }
            });
        });
    }

    document.querySelectorAll('form.cast-identity-form').forEach(function (form) {
        var errorEl = form.querySelector('.cast-identity-error');
        var uploadStatus = form.querySelector('[data-cast-upload-status]');
        var uploadStatusText = form.querySelector('[data-cast-upload-status-text]');
        var submitBtn = form.querySelector('[data-cast-submit-btn]');
        var submitHint = form.querySelector('[data-cast-submit-hint]');
        var frontInput = form.querySelector('input[name="front_file"]');
        var backInput = form.querySelector('input[name="back_file"]');
        var typeSelect = form.querySelector('select[name="type"]');
        var expiryInput = form.querySelector('input[name="expired_at"]');
        var categoryInput = form.querySelector('input[name="category"]');
        var category = categoryInput ? categoryInput.value : '';

        // draft 済みか（初期表示時：submitBtn が disabled でないなら既に draft がある）
        var isUploaded = submitBtn ? !submitBtn.disabled : false;

        function showError(text) {
            if (!errorEl) return;
            errorEl.textContent = text;
            errorEl.hidden = false;
        }
        function clearError() {
            if (errorEl) errorEl.hidden = true;
        }
        function setUploadStatus(state, message) {
            if (!uploadStatus) return;
            uploadStatus.classList.remove('is-uploading', 'is-error');
            if (!state) { uploadStatus.hidden = true; return; }
            uploadStatus.hidden = false;
            if (state !== 'success') uploadStatus.classList.add('is-' + state);
            if (uploadStatusText) uploadStatusText.textContent = message || '';
            var icon = uploadStatus.querySelector('i');
            if (icon) {
                icon.className = state === 'uploading' ? 'fas fa-spinner fa-spin'
                    : state === 'error' ? 'fas fa-triangle-exclamation'
                    : 'fas fa-circle-check';
            }
        }
        function syncSubmitBtn() {
            if (!submitBtn) return;
            submitBtn.disabled = !isUploaded;
        }

        // ---- 自動アップロード（表面 or 裏面選択時、両方揃ってから発火するとは限らないため常時実行） ----
        function autoUpload() {
            if (!frontInput || !frontInput.files || !frontInput.files.length) {
                // 表面がまだない：submit は無効のまま
                return;
            }
            clearError();
            setUploadStatus('uploading', 'アップロードしています…');
            if (submitBtn) submitBtn.disabled = true;

            var fd = new FormData();
            fd.append('_token', csrfToken);
            fd.append('category', category);
            if (typeSelect) fd.append('type', typeSelect.value);
            fd.append('front_file', frontInput.files[0]);
            if (backInput && backInput.files && backInput.files[0]) {
                fd.append('back_file', backInput.files[0]);
            }
            if (expiryInput && expiryInput.value) fd.append('expired_at', expiryInput.value);

            fetch(uploadUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
            .then(function () {
                isUploaded = true;
                setUploadStatus('success', 'アップロード完了。下の「運営に提出する」ボタンで審査依頼できます。');
                syncSubmitBtn();
            })
            .catch(function (err) {
                isUploaded = false;
                var messages = err && err.errors ? Object.values(err.errors).flat() : [];
                setUploadStatus('error', messages[0] || (err && err.message) || 'アップロードに失敗しました。ファイルを選び直してください。');
                syncSubmitBtn();
            });
        }
        if (frontInput) frontInput.addEventListener('change', autoUpload);
        if (backInput)  backInput.addEventListener('change', autoUpload);
        // 書類種別・有効期限の変更でも draft を上書きしたいので再アップロード
        if (typeSelect) typeSelect.addEventListener('change', function () { if (isUploaded) autoUpload(); });
        if (expiryInput) expiryInput.addEventListener('change', function () { if (isUploaded) autoUpload(); });

        // ---- 提出（DRAFT → PENDING の明示的アクション） ----
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!isUploaded) {
                showError('まずファイルをアップロードしてください。');
                return;
            }
            clearError();
            if (submitBtn) submitBtn.disabled = true;

            fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ category: category })
            })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
            .then(function () {
                if (submitHint) submitHint.hidden = true;
                openSubmittedModal();
            })
            .catch(function (err) {
                var messages = err && err.errors ? Object.values(err.errors).flat() : [];
                showError(messages[0] || (err && err.message) || '提出に失敗しました。');
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    });

    /* ファイル選択：ドロップボタンにファイル名を反映 + プレビュー / PDF チップ */
    document.querySelectorAll('input[type="file"].bank-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var nameEl  = document.getElementById(input.id + '_name');
            var preview = document.getElementById(input.id + '_preview');
            var pdfChip = document.getElementById(input.id + '_pdf');
            // ドロップボタン（input の直前の label）
            var drop = document.querySelector('label[for="' + input.id + '"]');
            var file = input.files && input.files[0];

            // プレビュー
            if (preview) {
                if (preview.dataset.blobUrl) {
                    URL.revokeObjectURL(preview.dataset.blobUrl);
                    delete preview.dataset.blobUrl;
                }
                if (file && /^image\//.test(file.type)) {
                    var blobUrl = URL.createObjectURL(file);
                    preview.src = blobUrl;
                    preview.dataset.blobUrl = blobUrl;
                    preview.hidden = false;
                } else {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
            }
            if (pdfChip) {
                var isPdf = file && (file.type === 'application/pdf' || /\.pdf$/i.test(file.name));
                pdfChip.hidden = !isPdf;
                var nameSpan = pdfChip.querySelector('span');
                if (nameSpan) nameSpan.textContent = isPdf ? file.name : '';
            }

            if (file) {
                if (nameEl) nameEl.textContent = file.name;
                if (drop) drop.classList.add('is-selected');
            } else {
                if (nameEl) nameEl.textContent = 'タップしてファイルを選択';
                if (drop) drop.classList.remove('is-selected');
            }
        });
    });
});
</script>
@endpush
