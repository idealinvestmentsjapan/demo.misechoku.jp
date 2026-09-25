@extends('layouts.app-v2')

@section('title', '求人票の編集')
@section('body-class', 'page-recruit-edit')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/recruitment.css') }}?v=20260801-btn-rules">
<link rel="stylesheet" href="{{ asset('assets/css/form-enhance.css') }}">
<style>
    .job-edit-v2 {
        --je-bg: transparent;
        --je-panel: rgba(18, 18, 18, 0.55); /* 旧ワイン色 rgba(20,12,18) → ニュートラルな暗色 */
        --je-field: rgba(255, 255, 255, 0.05);
        --je-border: rgba(168, 85, 247, 0.22);
        --je-gold: #a78bfa;
        background: var(--je-bg);
        margin: 0 calc(-1 * var(--content-padding-x, 16px));
        /* --footer-height に safe-area 込み。二重加算しない。 */
        padding-bottom: calc(var(--footer-height, 75px) + 96px);
    }
    /* サブヘッダーは共通 sub-header.css と同じ「fixed + viewport 基準」方式。
       sticky は祖先 .content-wrapper の overflow-x: hidden がスクロールコンテキストを
       作るため viewport にピン留めされず下にズレる（既知の落とし穴）。 */
    .job-edit-v2 {
        --je-subheader-h: 44px; /* 1行構成の細型サブヘッダー */
    }
    .job-edit-v2__shell {
        max-width: 100%;
        margin: 0 auto;
        min-height: 100%;
        background: var(--je-panel);
        /* NOTE: backdrop-filter は fixed 子要素（サブヘッダー・保存帯）の
           containing block を作ってしまい、viewport 基準でなくなる（＝帯が
           画面途中に浮く・ヘッダーが被る）ため使用禁止 */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        /* fixed 化したサブヘッダーの高さぶんコンテンツを下げる */
        padding-top: var(--je-subheader-h);
    }
    .job-edit-v2__top {
        position: fixed;
        top: var(--header-height, 60px);
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: var(--max-content-width);
        height: var(--je-subheader-h, 44px);
        box-sizing: border-box;
        z-index: 1400; /* グローバルヘッダー(1500)より下、コンテンツより上 */
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 0 16px;
        background: rgba(10, 10, 10, 0.95); /* EDIT PROFILE のサブヘッダーと同トーン */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--color-line, #2a2a2a);
        box-shadow: 0 6px 14px -8px rgba(0, 0, 0, 0.6);
    }
    /* アンカースクロール時、fixed ヘッダー2段ぶんのマージンを確保 */
    .job-edit-v2__notice,
    .job-edit-v2__form section {
        scroll-margin-top: calc(var(--header-height, 60px) + var(--je-subheader-h, 44px) + 12px);
    }
    .job-edit-v2__back {
        color: #a1a1aa;
        padding: 4px;
        margin-left: -4px;
        font-size: 1.25rem;
        text-decoration: none;
        line-height: 1;
    }
    .job-edit-v2__back:hover { color: var(--je-gold); }
    /* タイトル：2段積み → 1行（英字は小さな添えラベル）にして細型化 */
    .job-edit-v2__title-wrap {
        display: flex;
        align-items: baseline;
        gap: 8px;
        text-align: left;
        flex: 1;
        min-width: 0;
        overflow: hidden;
    }
    .job-edit-v2__title-en {
        margin: 0;
        font-size: 0.6rem;
        font-weight: 800;
        color: rgba(var(--accent-rgb, 139, 92, 246), 0.75);
        letter-spacing: 0.18em;
        font-family: var(--font-sans);
        flex-shrink: 0;
    }
    .job-edit-v2__title-sub {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: 0.04em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .job-edit-v2__spacer { width: 2rem; flex-shrink: 0; }

    .job-edit-v2__form {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 32px;  /* 36 → 32 でセクション間を少し詰める */
        counter-reset: jobedit-section;  /* CSS counter で section 番号を自動採番 */
    }

    /* セクション見出し — STEP 番号 + 細い区切り線で "今どこ" を可視化 */
    .job-edit-v2__sec-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 18px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(168, 85, 247, 0.18);
        font-size: 1rem;
        font-family: var(--font-sans);
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.02em;
        counter-increment: jobedit-section;
        /* 上から sticky header (60px) + sub-header(46px) で被るぶんを補う */
        scroll-margin-top: 120px;
    }
    /* 番号バッジ：STEP のような視覚タグ */
    .job-edit-v2__sec-title::before {
        content: counter(jobedit-section, decimal-leading-zero);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 22px;
        padding: 0 7px;
        border-radius: 6px;
        background: rgba(168, 85, 247, 0.18);
        border: 1px solid rgba(168, 85, 247, 0.40);
        color: var(--accent-text, #f0a6c4);
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        font-family: var(--font-sans);
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }
    .job-edit-v2__sec-title i { font-size: 0.9rem; color: var(--accent-text, #f0a6c4); }
    /* セクション見出し直後の field group は subtle in-card 感覚 */
    .job-edit-v2__sec-title + * { animation: jobedit-section-enter 0.4s ease-out both; }
    @keyframes jobedit-section-enter {
        from { opacity: 0.6; transform: translateY(3px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .job-edit-v2__sec-title + * { animation: none; }
    }

    .job-edit-v2__field { margin-bottom: 22px; }
    .job-edit-v2__field:last-child { margin-bottom: 0; }
    .job-edit-v2__label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.78rem;
        font-weight: 800;
        color: #e6dffc;
        margin: 0 0 8px 2px;
        letter-spacing: 0.02em;
    }
    .job-edit-v2__label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin: 0 0 8px 0;
    }
    .job-edit-v2__label-row .job-edit-v2__label { margin: 0; }
    .job-edit-v2__suggest-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        border: 1px solid rgba(168, 85, 247, 0.45);
        background: rgba(168, 85, 247, 0.10);
        color: var(--je-gold, #a78bfa);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
    }
    .job-edit-v2__suggest-btn:hover {
        background: rgba(168, 85, 247, 0.18);
        border-color: rgba(168, 85, 247, 0.75);
    }
    .job-edit-v2__suggest-btn:active { transform: scale(0.96); }
    .job-edit-v2__suggest-btn:disabled { opacity: 0.6; cursor: wait; }
    .job-edit-v2__suggest-btn i { font-size: 0.78rem; }
    .job-edit-v2__req {
        font-size: 8px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 4px;
        background: rgba(168, 85, 247, 0.15);
        border: 1px solid rgba(168, 85, 247, 0.35);
        color: var(--je-gold);
        line-height: 1.2;
    }
    .job-edit-v2__opt {
        font-size: 8px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        background: rgba(113, 113, 122, 0.2);
        border: 1px solid rgba(113, 113, 122, 0.45);
        color: #a1a1aa;
        line-height: 1.2;
    }
    .job-edit-v2__input,
    .job-edit-v2__textarea {
        width: 100%;
        box-sizing: border-box;
        background: var(--je-field);
        border: 1px solid var(--je-border);
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.92rem;
        color: #fafafa;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }
    .job-edit-v2__input::placeholder,
    .job-edit-v2__textarea::placeholder { color: rgba(196, 181, 253, 0.35); }
    .job-edit-v2__textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
    .job-edit-v2__input:focus,
    .job-edit-v2__textarea:focus {
        outline: none;
        border-color: rgba(168, 85, 247, 0.6);
        background: rgba(255, 255, 255, 0.07);
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
    }
    .job-edit-v2__hint { margin: 8px 0 0 2px; font-size: 0.72rem; line-height: 1.6; color: rgba(196, 181, 253, 0.5); }

    .job-edit-v2__grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .job-edit-v2__unit-wrap { position: relative; }
    .job-edit-v2__unit-wrap .job-edit-v2__input { padding-right: 2.25rem; }
    .job-edit-v2__unit-suffix {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: #71717a;
        pointer-events: none;
    }

    .job-edit-v2__card {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--je-border);
        border-radius: 12px;
        padding: 16px;
    }
    .job-edit-v2__card--accent {
        border-color: #1f1a14;
        position: relative;
        overflow: hidden;
    }
    .job-edit-v2__card--accent::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: rgba(168, 85, 247, 0.45);
    }

    .job-edit-v2__chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .job-edit-v2__chip {
        position: relative;
        display: inline-flex;
        cursor: pointer;
    }
    .job-edit-v2__chip input { position: absolute; opacity: 0; pointer-events: none; }
    .job-edit-v2__chip span {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid var(--je-border);
        background: rgba(255, 255, 255, 0.04);
        color: #d4d4d8;
        font-size: 0.74rem;
        font-weight: 600;
        transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    .job-edit-v2__chip input:checked + span {
        border-color: var(--je-gold);
        background: rgba(168, 85, 247, 0.18);
        color: #c4b5fd;
        box-shadow: 0 0 10px rgba(168, 85, 247, 0.18);
    }
    .job-edit-v2__tag-cat { margin: 0 0 8px; font-size: 0.78rem; font-weight: 800; color: var(--je-gold); }

    .job-edit-v2__shop-note {
        margin-top: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px dashed rgba(255, 255, 255, 0.12);
        font-size: 0.68rem;
        line-height: 1.6;
        color: #9ca3af;
    }

    /* ── 本入・体験・ヘルプ（開閉メニュー） ─ */
    .job-edit-v2__kind-details {
        margin: 24px 16px 0;
        border: 1px solid var(--je-border);
        border-radius: 12px;
        background: rgba(20, 12, 18, 0.42);
        overflow: hidden;
    }
    .job-edit-v2__kind-summary {
        list-style: none;
        padding: 14px 16px;
        cursor: pointer;
        font-size: 0.8125rem;
        font-weight: 800;
        color: #e4e4e7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
    }
    .job-edit-v2__kind-summary::-webkit-details-marker { display: none; }
    .job-edit-v2__kind-summary::after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        font-size: 0.65rem;
        color: #71717a;
        transition: transform 0.2s ease;
    }
    .job-edit-v2__kind-details[open] .job-edit-v2__kind-summary::after {
        transform: rotate(180deg);
    }
    .job-edit-v2__kind-body {
        padding: 0 20px 20px;
        display: flex;
        flex-direction: column;
        gap: 32px;
        border-top: 1px solid #1f1a14;
    }
    .job-edit-v2__kind-subtitle {
        margin: 0 0 10px;
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--je-gold);
        letter-spacing: 0.08em;
    }
    /* Nav card that replaces the inline help wage section — points to /shop/help-recruitment */
    .job-edit-v2__nav-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 14px;
        margin-bottom: 20px;
        background: rgba(168, 85, 247, 0.06);
        border: 1px solid rgba(168, 85, 247, 0.22);
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: background 0.12s ease, border-color 0.12s ease;
    }
    .job-edit-v2__nav-card:hover {
        background: rgba(168, 85, 247, 0.10);
        border-color: rgba(168, 85, 247, 0.36);
    }
    .job-edit-v2__nav-card-icon {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: rgba(168, 85, 247, 0.16);
        color: #a78bfa;
    }
    .job-edit-v2__nav-card-body {
        display: flex;
        flex-direction: column;
        gap: 2px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .job-edit-v2__nav-card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #f5f5f5;
        line-height: 1.4;
    }
    .job-edit-v2__nav-card-sub {
        font-size: 0.72rem;
        color: #a1a1aa;
        line-height: 1.4;
    }
    .job-edit-v2__nav-card-chev {
        flex-shrink: 0;
        color: #a1a1aa;
        font-size: 0.85rem;
    }
    .job-edit-v2__shift-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        align-items: end;
    }
    @media (max-width: 360px) {
        .job-edit-v2__shift-grid { grid-template-columns: 1fr; }
    }
    .job-edit-v2__shift-last {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        font-size: 0.75rem;
        color: #a1a1aa;
        cursor: pointer;
    }
    .job-edit-v2__shift-last input { width: auto; accent-color: var(--je-gold); }

    /* ── タブパネル相当（旧クラス互換） ───── */
    .job-edit-v2__tab-panel {
        padding: 0 20px;
        display: flex;
        flex-direction: column;
        gap: 32px;
        margin-top: 0;
    }

    /* ── タブ内ステータス行 ────────────────── */
    .job-edit-v2__status {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 0;
        border-bottom: 1px solid var(--je-border);
    }
    .job-edit-v2__status-label { margin: 0 0 2px; font-size: 0.75rem; font-weight: 800; color: #fafafa; }
    .job-edit-v2__status-hint { margin: 0; font-size: 9px; color: #71717a; }
    .job-edit-v2__status-right { display: flex; align-items: center; gap: 10px; }
    .job-edit-v2__pub-label { font-size: 10px; font-weight: 800; color: #71717a; }
    .job-edit-v2__pub-label.is-on { color: var(--je-gold); }

    .job-edit-v2__switch {
        position: relative;
        width: 48px;
        height: 26px;
        flex-shrink: 0;
        cursor: pointer;
    }
    .job-edit-v2__switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .job-edit-v2__switch-track {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #52525b;
        transition: background 0.25s ease;
    }
    .job-edit-v2__switch input:checked + .job-edit-v2__switch-track { background: var(--je-gold); }
    .job-edit-v2__switch-knob {
        position: absolute;
        top: 3px;
        left: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        transition: transform 0.25s ease;
    }
    .job-edit-v2__switch input:checked + .job-edit-v2__switch-track .job-edit-v2__switch-knob { transform: translateX(22px); }

    /* ── プレビューリンク ─────────────────── */
    .job-edit-v2__preview-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--je-gold);
        text-decoration: none;
        border: 1px solid rgba(168, 85, 247, 0.35);
        padding: 6px 12px;
        border-radius: 999px;
        transition: background 0.15s ease;
    }
    .job-edit-v2__preview-link:hover { background: rgba(168, 85, 247, 0.08); }
    .job-edit-v2__preview-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 10px 0 4px;
    }

    /* ── コピーボタン（ヘルプ専用） ───────── */
    .job-edit-v2__copy-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        color: #a1a1aa;
        background: #161311;
        border: 1px solid #2a2015;
        border-radius: 8px;
        padding: 8px 14px;
        cursor: pointer;
        font-family: inherit;
        margin-bottom: 16px;
        transition: border-color 0.15s ease, color 0.15s ease;
    }
    .job-edit-v2__copy-btn:hover {
        border-color: rgba(168, 85, 247, 0.4);
        color: var(--je-gold);
    }

    /* ── フッター（ヘッダー・ボトムナビと同じく #app 幅に揃える） ─────────────────────── */
    .job-edit-v2__footer {
        position: fixed;
        left: 50%;
        transform: translateX(-50%);
        /* ボトムナビ(75px)の直上に隙間なく貼り付ける */
        bottom: var(--footer-height, 75px);
        width: 100%;
        max-width: var(--max-content-width);
        z-index: 35;
        display: flex;
        justify-content: center;
        padding: 12px var(--content-padding-x, 16px) 12px;
        /* ボトムナビに合流して見えるニュートラルな暗色（旧ワイン色 #1a0e18 を廃止） */
        background: rgba(10, 10, 10, 0.97);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-top: 1px solid rgba(168, 85, 247, 0.30);
        box-shadow: 0 -8px 20px -8px rgba(0, 0, 0, 0.75);
        box-sizing: border-box;
    }
    .job-edit-v2__footer-inner {
        display: flex;
        gap: 12px;
        width: 100%;
    }
    .job-edit-v2__btn-cancel {
        flex: 0 0 auto;
        padding: 12px 18px;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 700;
        color: #a1a1aa;
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: transparent;
        cursor: pointer;
        font-family: inherit;
        transition: color 0.15s ease, border-color 0.15s ease;
    }
    .job-edit-v2__btn-cancel:hover { color: #fff; border-color: rgba(255, 255, 255, 0.35); }
    /* 保存 = Primary CTA（DESIGN.md §10：アクセントグラデ + 立体。混色グラデ・hover明暗は廃止） */
    .job-edit-v2__btn-save {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 16px;
        border: none;
        border-radius: 999px;
        font-size: 0.9rem;
        font-weight: 800;
        cursor: pointer;
        color: var(--on-accent-strong, #fff);
        background: linear-gradient(135deg, var(--accent-grad-from), var(--accent-grad-to));
        box-shadow:
            0 6px 14px rgba(0, 0, 0, .45),
            inset 0 1px 0 rgba(255, 255, 255, .20),
            inset 0 -1px 0 rgba(0, 0, 0, .18);
        font-family: inherit;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    .job-edit-v2__btn-save:active {
        transform: scale(0.98);
        box-shadow: 0 2px 5px rgba(0, 0, 0, .45), inset 0 2px 4px rgba(0, 0, 0, .2);
    }

    /* 上部に表示する案内ノーティス（読みやすさ向上のためボックス＋アイコン） */
    .job-edit-v2__notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 14px 16px 0;
        padding: 12px 14px;
        border-radius: 12px;
        background: rgba(168, 85, 247, 0.10);
        border: 1px solid rgba(168, 85, 247, 0.45);
        color: #c4b5fd;
        font-size: 0.82rem;
        line-height: 1.6;
    }
    .job-edit-v2__notice i { flex-shrink: 0; margin-top: 3px; color: #c4b5fd; }
    .job-edit-v2__notice a {
        color: #c4b5fd;
        font-weight: 800;
        text-decoration: underline;
    }
    .job-edit-v2__notice--success {
        background: rgba(16, 185, 129, 0.10);
        border-color: rgba(16, 185, 129, 0.45);
        color: #6ee7b7;
    }
    .job-edit-v2__notice--success i { color: #6ee7b7; }

    .profile-edit-flash,
    .recruit-error-summary { margin: 12px 16px 0; }
</style>
@endpush

@section('content')
@php
    $horizontal = !empty($horizontalShopJobs);
    $isTrialActive = (($recruitTrial ?? [])['status'] ?? 'active') === 'active';
    $isHelpActive  = (($recruitHelp ?? [])['status']  ?? 'active') === 'active';
    if ($horizontal) {
        $pubReg = ((int) ($recruit['regular_status'] ?? 0)) === 1;
        $pubTrial = ((int) ($recruit['trial_status'] ?? 0)) === 1;
        $pubHelp = ((int) ($recruit['help_status'] ?? 0)) === 1;
    } else {
        $pubReg = (($recruit['status'] ?? 'inactive') === 'active');
        $pubTrial = $isTrialActive;
        $pubHelp = $isHelpActive;
    }
    $trialWorkStyleIds = array_map('intval', ($recruitTrial ?? [])['work_style_tag_ids'] ?? []);
    $trialWelcomeIds   = array_map('intval', ($recruitTrial ?? [])['welcome_tag_ids'] ?? []);
    $trialBenefitIds   = array_map('intval', ($recruitTrial ?? [])['benefit_tag_ids'] ?? []);
@endphp

<div class="job-edit-v2 animate-fadeIn">
    <div class="job-edit-v2__shell">

        {{-- ヘッダー --}}
        <header class="job-edit-v2__top">
            <div class="job-edit-v2__title-wrap">
                <h1 class="job-edit-v2__title-en">EDIT JOB</h1>
                <p class="job-edit-v2__title-sub">求人票の編集</p>
            </div>
        </header>

        @if(session('message'))
            <div class="job-edit-v2__notice job-edit-v2__notice--success" data-flash-toast="success">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @php $canPublishJobs = $canPublishJobs ?? true; @endphp
        @if(!$canPublishJobs)
            <div class="job-edit-v2__notice">
                <i class="fas fa-exclamation-triangle"></i>
                <span>
                    求人を公開するには、営業許可証と風営許可証の両方を提出し、運営の承認が必要です。審査が完了するまで「公開」にできません。
                    <a href="{{ route('shop.mypage.documents.index') }}">許可証提出ページへ</a>
                </span>
            </div>
        @endif

        @if($errors->any())
            <div class="recruit-error-summary" style="margin-top:12px;">
                <p class="recruit-error-summary-title">入力内容を確認してください</p>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="job-edit-v2__preview-row" style="padding-inline:16px;">
            <a href="{{ route('shop.recruits.preview') }}" class="job-edit-v2__preview-link">
                <i class="fas fa-eye" aria-hidden="true"></i> キャストからの見え方を確認
            </a>
        </div>

        {{-- 入力完成度メーター（form-enhance.js が挿入する） --}}
        <div id="recruit-meter-host" style="padding: 12px 16px 0;"></div>

        <form id="recruit-form" action="{{ route('shop.recruits.update') }}" method="POST"
              data-form-guard data-completion-meter data-completion-target="#recruit-meter-host">
            @csrf
            @method('PUT')
            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 Basic Information（新規入店・ヘルプ共通）
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="job-edit-v2__form" style="padding-bottom:0; gap:0;">
                <section aria-labelledby="job-sec-basic">
                    <h2 id="job-sec-basic" class="job-edit-v2__sec-title"><i class="fas fa-file-alt"></i> 店舗からのメッセージ</h2>
                    <div class="job-edit-v2__field">
                        <div class="job-edit-v2__label-row">
                            <label class="job-edit-v2__label" for="catch_copy">キャッチコピー <span class="job-edit-v2__req">必須</span></label>
                            <button type="button" class="job-edit-v2__suggest-btn" data-suggest-target="#catch_copy" data-suggest-category="catch_copy">
                                <i class="fas fa-dice" aria-hidden="true"></i> ランダム
                            </button>
                        </div>
                        <input type="text" id="catch_copy" name="catch_copy" class="job-edit-v2__input recruit-input"
                               value="{{ old('catch_copy', $recruit['catch_copy']) }}"
                               placeholder="例: 未経験でも時給5000円スタート！" maxlength="100">
                        <p class="job-edit-v2__hint">一覧・求人票の冒頭で目立つ短い一文です。「ランダム」で候補を差し込めます。</p>
                    </div>
                    <div class="job-edit-v2__field">
                        <div class="job-edit-v2__label-row">
                            <label class="job-edit-v2__label" for="message">店長からのメッセージ <span class="job-edit-v2__req">必須</span></label>
                            <button type="button" class="job-edit-v2__suggest-btn" data-suggest-target="#message" data-suggest-category="manager_message">
                                <i class="fas fa-dice" aria-hidden="true"></i> ランダム
                            </button>
                        </div>
                        <textarea id="message" name="message" rows="5" class="job-edit-v2__textarea recruit-textarea"
                                  placeholder="未経験歓迎、サポート体制など">{{ old('message', $recruit['message']) }}</textarea>
                    </div>
                    <div class="job-edit-v2__field">
                        <label class="job-edit-v2__label" for="job_content">お仕事内容について補足 <span class="job-edit-v2__opt">任意</span></label>
                        <textarea id="job_content" name="job_content" rows="4" class="job-edit-v2__textarea recruit-textarea"
                                  placeholder="業務内容の補足・備考があれば入力（未入力でも保存できます）">{{ old('job_content', $recruit['job_content'] ?? '') }}</textarea>
                    </div>
                </section>
            </div>

            @if(!empty($usesJobTypes) && $usesJobTypes)

                <details class="job-edit-v2__kind-details" @if($errors->any()) open @endif open>
                    <summary class="job-edit-v2__kind-summary">本入店・新規入店・ヘルプの設定</summary>
                    <div class="job-edit-v2__kind-body" style="padding-top:12px;">

                        <section aria-labelledby="job-sec-salary">
                            <h2 id="job-sec-salary" class="job-edit-v2__sec-title"><i class="fas fa-wallet"></i> 給与・ボーナス</h2>

                            @if($horizontal)
                                <p class="job-edit-v2__kind-subtitle">本入店</p>
                                <div class="job-edit-v2__status">
                                    <div>
                                        <p class="job-edit-v2__status-label">本入店を公開</p>
                                        <p class="job-edit-v2__status-hint">オフにすると非公開になります</p>
                                    </div>
                                    <div class="job-edit-v2__status-right">
                                        <span class="job-edit-v2__pub-label {{ $pubReg ? 'is-on' : '' }}" id="published-reg-label">{{ $pubReg ? '公開中' : '非公開' }}</span>
                                        <label class="job-edit-v2__switch">
                                            <input type="checkbox" name="published_regular" value="1" class="js-kind-pub" data-label-id="published-reg-label" @checked($pubReg)>
                                            <span class="job-edit-v2__switch-track"><span class="job-edit-v2__switch-knob"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="job-edit-v2__grid2">
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="regular_hourly_wage">時給（下限） <span class="job-edit-v2__req">必須</span></label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="regular_hourly_wage" name="regular_hourly_wage" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('regular_hourly_wage', number_format((float)($recruit['regular_hourly_wage'] ?? $recruit['hourly_wage_regular'] ?? 0))) }}"
                                                   placeholder="5,000" inputmode="numeric" data-type="currency">
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="regular_hourly_wage_max">時給（上限）</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="regular_hourly_wage_max" name="regular_hourly_wage_max" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('regular_hourly_wage_max', isset($recruit['regular_hourly_wage_max']) && $recruit['regular_hourly_wage_max'] !== null ? number_format((float) $recruit['regular_hourly_wage_max']) : '') }}"
                                                   placeholder="任意" inputmode="numeric" data-type="currency" data-optional-currency>
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="job-edit-v2__kind-subtitle">新規入店</p>
                                <div class="job-edit-v2__status">
                                    <div>
                                        <p class="job-edit-v2__status-label">新規入店を公開</p>
                                        <p class="job-edit-v2__status-hint">オフにすると非公開になります</p>
                                    </div>
                                    <div class="job-edit-v2__status-right">
                                        <span class="job-edit-v2__pub-label {{ $pubTrial ? 'is-on' : '' }}" id="published-trial-label">{{ $pubTrial ? '公開中' : '非公開' }}</span>
                                        <label class="job-edit-v2__switch">
                                            <input type="checkbox" name="published_trial" value="1" class="js-kind-pub" data-label-id="published-trial-label" @checked($pubTrial)>
                                            <span class="job-edit-v2__switch-track"><span class="job-edit-v2__switch-knob"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="job-edit-v2__grid2">
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="trial_hourly_wage">時給（下限）</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="trial_hourly_wage" name="trial_hourly_wage" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('trial_hourly_wage', !empty($recruit['trial_hourly_wage']) ? number_format((float) $recruit['trial_hourly_wage']) : '') }}"
                                                   placeholder="5,000" inputmode="numeric" data-type="currency">
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="trial_hourly_wage_max">時給（上限）</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="trial_hourly_wage_max" name="trial_hourly_wage_max" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('trial_hourly_wage_max', isset($recruit['trial_hourly_wage_max']) && $recruit['trial_hourly_wage_max'] !== null ? number_format((float) $recruit['trial_hourly_wage_max']) : '') }}"
                                                   placeholder="任意" inputmode="numeric" data-type="currency" data-optional-currency>
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Help recruitment moved to dedicated page (/shop/help-recruitment).
                                     Hidden inputs preserve current values on save; users edit them on the new page. --}}
                                <p class="job-edit-v2__kind-subtitle">ヘルプ</p>
                                <a href="{{ route('shop.help-recruitment.index') }}" class="job-edit-v2__nav-card">
                                    <span class="job-edit-v2__nav-card-icon"><i class="fas fa-bolt"></i></span>
                                    <span class="job-edit-v2__nav-card-body">
                                        <span class="job-edit-v2__nav-card-title">ヘルプ募集は専用画面で管理します</span>
                                        <span class="job-edit-v2__nav-card-sub">募集日・時給・応募状況を1画面で編集</span>
                                    </span>
                                    <span class="job-edit-v2__nav-card-chev"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                @if($pubHelp)
                                    <input type="hidden" name="published_help" value="1">
                                @endif
                                <input type="hidden" name="has_help" value="{{ $pubHelp ? 1 : 0 }}">
                                <input type="hidden" name="help_hourly_wage" value="{{ old('help_hourly_wage', !empty($recruit['help_hourly_wage']) ? (int) $recruit['help_hourly_wage'] : '') }}">
                                <input type="hidden" name="help_hourly_wage_max" value="{{ old('help_hourly_wage_max', isset($recruit['help_hourly_wage_max']) && $recruit['help_hourly_wage_max'] !== null ? (int) $recruit['help_hourly_wage_max'] : '') }}">
                            @else
                                <p class="job-edit-v2__kind-subtitle">新規入店</p>
                                <div class="job-edit-v2__status">
                                    <div>
                                        <p class="job-edit-v2__status-label">新規入店を公開</p>
                                        <p class="job-edit-v2__status-hint">オフにすると非公開になります</p>
                                    </div>
                                    <div class="job-edit-v2__status-right">
                                        <span class="job-edit-v2__pub-label {{ $pubTrial ? 'is-on' : '' }}" id="published-trial-label">{{ $pubTrial ? '公開中' : '非公開' }}</span>
                                        <label class="job-edit-v2__switch">
                                            <input type="checkbox" name="published_trial" value="1" class="js-kind-pub" data-label-id="published-trial-label" @checked($pubTrial)>
                                            <span class="job-edit-v2__switch-track"><span class="job-edit-v2__switch-knob"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="job-edit-v2__grid2">
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="trial_hourly_wage">時給（下限） <span class="job-edit-v2__req">必須</span></label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="trial_hourly_wage" name="trial_hourly_wage" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('trial_hourly_wage', !empty($recruit['trial_hourly_wage']) ? number_format((float) $recruit['trial_hourly_wage']) : '') }}"
                                                   placeholder="5,000" inputmode="numeric" data-type="currency">
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="trial_hourly_wage_max">時給（上限）</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="text" id="trial_hourly_wage_max" name="trial_hourly_wage_max" class="job-edit-v2__input recruit-input"
                                                   value="{{ old('trial_hourly_wage_max', isset($recruit['trial_hourly_wage_max']) && $recruit['trial_hourly_wage_max'] !== null ? number_format((float) $recruit['trial_hourly_wage_max']) : '') }}"
                                                   placeholder="任意" inputmode="numeric" data-type="currency" data-optional-currency>
                                            <span class="job-edit-v2__unit-suffix">円</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Help recruitment moved to dedicated page (/shop/help-recruitment).
                                     Hidden inputs preserve current values on save; users edit them on the new page. --}}
                                <p class="job-edit-v2__kind-subtitle">ヘルプ</p>
                                <a href="{{ route('shop.help-recruitment.index') }}" class="job-edit-v2__nav-card">
                                    <span class="job-edit-v2__nav-card-icon"><i class="fas fa-bolt"></i></span>
                                    <span class="job-edit-v2__nav-card-body">
                                        <span class="job-edit-v2__nav-card-title">ヘルプ募集は専用画面で管理します</span>
                                        <span class="job-edit-v2__nav-card-sub">募集日・時給・応募状況を1画面で編集</span>
                                    </span>
                                    <span class="job-edit-v2__nav-card-chev"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                @if($pubHelp)
                                    <input type="hidden" name="published_help" value="1">
                                @endif
                                <input type="hidden" name="has_help" value="1">
                                <input type="hidden" name="help_hourly_wage" value="{{ old('help_hourly_wage', !empty($recruit['help_hourly_wage']) ? (int) $recruit['help_hourly_wage'] : '') }}">
                                <input type="hidden" name="help_hourly_wage_max" value="{{ old('help_hourly_wage_max', isset($recruit['help_hourly_wage_max']) && $recruit['help_hourly_wage_max'] !== null ? (int) $recruit['help_hourly_wage_max'] : '') }}">
                            @endif

                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="salary_text">給与備考</label>
                                <textarea id="salary_text" name="salary_text" rows="3" class="job-edit-v2__textarea recruit-textarea"
                                          placeholder="バック詳細・日払い・昇給など">{{ old('salary_text', $recruit['salary_text']) }}</textarea>
                            </div>

                            <div class="job-edit-v2__card job-edit-v2__card--accent" style="padding-left:18px;">
                                <p style="margin:0 0 14px;font-size:0.75rem;font-weight:800;color:#e4e4e7;">入店ボーナス設定</p>
                                <div class="job-edit-v2__field">
                                    <label class="job-edit-v2__label" for="bonus_reward">ボーナス金額</label>
                                    <div class="job-edit-v2__unit-wrap">
                                        <input type="text" id="bonus_reward" name="bonus_reward" class="job-edit-v2__input recruit-input"
                                               value="{{ old('bonus_reward', old('noruma_reward', number_format((float)($recruit['bonus_reward'] ?? $recruit['noruma_reward'] ?? 0)))) }}"
                                               placeholder="50,000" inputmode="numeric" data-type="currency">
                                        <span class="job-edit-v2__unit-suffix">円</span>
                                    </div>
                                </div>
                                @if($horizontal)
                                <div class="job-edit-v2__field">
                                    <label class="job-edit-v2__label" for="bonus_remarks">ボーナス補足</label>
                                    <input type="text" id="bonus_remarks" name="bonus_remarks" class="job-edit-v2__input"
                                           value="{{ old('bonus_remarks', $recruit['bonus_remarks'] ?? '') }}"
                                           placeholder="補足があれば入力">
                                </div>
                                @endif
                                <div class="job-edit-v2__grid2">
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="bonus_total_working_days">合計勤務回数</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="number" id="bonus_total_working_days" name="bonus_total_working_days" class="job-edit-v2__input"
                                                   value="{{ old('bonus_total_working_days', $recruit['bonus_total_working_days'] ?? '') }}"
                                                   placeholder="10" min="0">
                                            <span class="job-edit-v2__unit-suffix">回</span>
                                        </div>
                                    </div>
                                    <div class="job-edit-v2__field">
                                        <label class="job-edit-v2__label" for="bonus_total_working_hours">合計勤務時間</label>
                                        <div class="job-edit-v2__unit-wrap">
                                            <input type="number" id="bonus_total_working_hours" name="bonus_total_working_hours" class="job-edit-v2__input"
                                                   value="{{ old('bonus_total_working_hours', $recruit['bonus_total_working_hours'] ?? '') }}"
                                                   placeholder="40" min="0">
                                            <span class="job-edit-v2__unit-suffix">h</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="job-edit-v2__field">
                                    <label class="job-edit-v2__label" for="bonus_other_conditions">その他条件</label>
                                    <input type="text" id="bonus_other_conditions" name="bonus_other_conditions" class="job-edit-v2__input"
                                           value="{{ old('bonus_other_conditions', $recruit['bonus_other_conditions'] ?? '') }}"
                                           placeholder="例: 無遅刻無欠勤">
                                </div>
                            </div>
                        </section>
                    </div>
                </details>

                <div class="job-edit-v2__tab-panel" style="padding-top:4px;">
                    <section aria-labelledby="job-sec-work">
                        <h2 id="job-sec-work" class="job-edit-v2__sec-title"><i class="fas fa-clock"></i> 勤務条件</h2>
                        @include('shops.recruit.parts.shift-time-fields')
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="working_days">勤務日数・シフト <span class="job-edit-v2__req">必須</span></label>
                            <input type="text" id="working_days" name="working_days" class="job-edit-v2__input"
                                   value="{{ old('working_days', $recruit['working_days']) }}"
                                   placeholder="週1日からOK">
                        </div>
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="regular_holiday">定休日・休みの書き方</label>
                            <input type="text" id="regular_holiday" name="regular_holiday" class="job-edit-v2__input"
                                   value="{{ old('regular_holiday', $recruit['regular_holiday']) }}"
                                   placeholder="不定休">
                        </div>
                    </section>

                    <section aria-labelledby="job-sec-detail">
                        <h2 id="job-sec-detail" class="job-edit-v2__sec-title"><i class="fas fa-briefcase"></i> 募集要項</h2>
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="qualification">応募資格 <span class="job-edit-v2__req">必須</span></label>
                            <input type="text" id="qualification" name="qualification" class="job-edit-v2__input"
                                   value="{{ old('qualification', $recruit['qualification']) }}"
                                   placeholder="18歳以上（高校生不可）">
                        </div>
                    </section>

                    <section aria-labelledby="job-sec-tags">
                        <h2 id="job-sec-tags" class="job-edit-v2__sec-title"><i class="fas fa-check-circle"></i> タグ・アピールポイント</h2>
                        <div style="margin-bottom:22px;">
                            <p class="job-edit-v2__tag-cat">働き方・給与</p>
                            <div class="job-edit-v2__chips" id="chips-work-style">
                                @foreach(($masters['work_style'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="work_style_tag_ids[]" value="{{ $tag->id }}"
                                               data-tag-id="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('work_style_tag_ids', $recruit['work_style_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div style="margin-bottom:22px;">
                            <p class="job-edit-v2__tag-cat">歓迎条件</p>
                            <div class="job-edit-v2__chips" id="chips-welcome">
                                @foreach(($masters['welcome'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="welcome_tag_ids[]" value="{{ $tag->id }}"
                                               data-tag-id="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('welcome_tag_ids', $recruit['welcome_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="job-edit-v2__tag-cat">待遇・サポート</p>
                            <div class="job-edit-v2__chips" id="chips-benefit">
                                @foreach(($masters['benefit'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="benefit_tag_ids[]" value="{{ $tag->id }}"
                                               data-tag-id="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('benefit_tag_ids', $recruit['benefit_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

            @else
                {{-- usesJobTypes = false の場合（シンプルモード）--}}
                <div class="job-edit-v2__form" style="padding-top:0;">
                    <div class="job-edit-v2__status">
                        @php $isActive = ($recruit['status'] ?? 'active') === 'active'; @endphp
                        <div>
                            <p class="job-edit-v2__status-label">公開ステータス</p>
                            <p class="job-edit-v2__status-hint">オフにすると非公開になります</p>
                        </div>
                        <div class="job-edit-v2__status-right">
                            <span class="job-edit-v2__pub-label {{ $isActive ? 'is-on' : '' }}" id="published-label">{{ $isActive ? '公開中' : '非公開' }}</span>
                            <label class="job-edit-v2__switch">
                                <input type="checkbox" name="published" value="1" {{ $isActive ? 'checked' : '' }} id="published-toggle">
                                <span class="job-edit-v2__switch-track"><span class="job-edit-v2__switch-knob"></span></span>
                            </label>
                        </div>
                    </div>

                    <section aria-labelledby="job-sec-salary-s">
                        <h2 id="job-sec-salary-s" class="job-edit-v2__sec-title"><i class="fas fa-wallet"></i> 給与・ボーナス</h2>
                        <input type="hidden" name="recruit_job_kind" value="fulltime">
                        <div class="job-edit-v2__grid2">
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="trial_hourly_wage">体入時給（下限） <span class="job-edit-v2__req">必須</span></label>
                                <div class="job-edit-v2__unit-wrap">
                                    <input type="text" id="trial_hourly_wage" name="trial_hourly_wage" class="job-edit-v2__input recruit-input"
                                           value="{{ old('trial_hourly_wage', $recruit['trial_hourly_wage'] ? number_format((float) $recruit['trial_hourly_wage']) : '') }}"
                                           placeholder="5,000" inputmode="numeric" data-type="currency">
                                    <span class="job-edit-v2__unit-suffix">円</span>
                                </div>
                            </div>
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="trial_hourly_wage_max">体入時給（上限）</label>
                                <div class="job-edit-v2__unit-wrap">
                                    <input type="text" id="trial_hourly_wage_max" name="trial_hourly_wage_max" class="job-edit-v2__input recruit-input"
                                           value="{{ old('trial_hourly_wage_max', isset($recruit['trial_hourly_wage_max']) && $recruit['trial_hourly_wage_max'] !== null ? number_format((float) $recruit['trial_hourly_wage_max']) : '') }}"
                                           placeholder="任意" inputmode="numeric" data-type="currency" data-optional-currency>
                                    <span class="job-edit-v2__unit-suffix">円</span>
                                </div>
                            </div>
                        </div>
                        <div class="job-edit-v2__grid2">
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="hourly_wage_regular">本入時給（下限） <span class="job-edit-v2__req">必須</span></label>
                                <div class="job-edit-v2__unit-wrap">
                                    <input type="text" id="hourly_wage_regular" name="hourly_wage_regular" class="job-edit-v2__input recruit-input"
                                           value="{{ old('hourly_wage_regular', number_format((float) ($recruit['hourly_wage_regular'] ?? 0))) }}"
                                           placeholder="5,000" inputmode="numeric" data-type="currency">
                                    <span class="job-edit-v2__unit-suffix">円</span>
                                </div>
                            </div>
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="regular_hourly_wage_max">本入時給（上限）</label>
                                <div class="job-edit-v2__unit-wrap">
                                    <input type="text" id="regular_hourly_wage_max" name="regular_hourly_wage_max" class="job-edit-v2__input recruit-input"
                                           value="{{ old('regular_hourly_wage_max', isset($recruit['regular_hourly_wage_max']) && $recruit['regular_hourly_wage_max'] !== null ? number_format((float) $recruit['regular_hourly_wage_max']) : '') }}"
                                           placeholder="任意" inputmode="numeric" data-type="currency" data-optional-currency>
                                    <span class="job-edit-v2__unit-suffix">円</span>
                                </div>
                            </div>
                        </div>
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="salary_text">給与備考</label>
                            <textarea id="salary_text" name="salary_text" rows="3" class="job-edit-v2__textarea recruit-textarea"
                                      placeholder="バック詳細・日払い・昇給など">{{ old('salary_text', $recruit['salary_text']) }}</textarea>
                        </div>
                        <div class="job-edit-v2__card job-edit-v2__card--accent" style="padding-left:18px;">
                            <p style="margin:0 0 14px;font-size:0.75rem;font-weight:800;color:#e4e4e7;">入店ボーナス設定</p>
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="noruma_reward">ボーナス金額</label>
                                <div class="job-edit-v2__unit-wrap">
                                    <input type="text" id="noruma_reward" name="noruma_reward" class="job-edit-v2__input recruit-input"
                                           value="{{ old('noruma_reward', number_format((float) ($recruit['noruma_reward'] ?? 0))) }}"
                                           placeholder="50,000" inputmode="numeric" data-type="currency">
                                    <span class="job-edit-v2__unit-suffix">円</span>
                                </div>
                            </div>
                            <div class="job-edit-v2__grid2">
                                <div class="job-edit-v2__field">
                                    <label class="job-edit-v2__label" for="bonus_total_working_days">合計勤務回数</label>
                                    <div class="job-edit-v2__unit-wrap">
                                        <input type="number" id="bonus_total_working_days" name="bonus_total_working_days" class="job-edit-v2__input"
                                               value="{{ old('bonus_total_working_days', $recruit['bonus_total_working_days'] ?? '') }}" placeholder="10" min="0">
                                        <span class="job-edit-v2__unit-suffix">回</span>
                                    </div>
                                </div>
                                <div class="job-edit-v2__field">
                                    <label class="job-edit-v2__label" for="bonus_total_working_hours">合計勤務時間</label>
                                    <div class="job-edit-v2__unit-wrap">
                                        <input type="number" id="bonus_total_working_hours" name="bonus_total_working_hours" class="job-edit-v2__input"
                                               value="{{ old('bonus_total_working_hours', $recruit['bonus_total_working_hours'] ?? '') }}" placeholder="40" min="0">
                                        <span class="job-edit-v2__unit-suffix">h</span>
                                    </div>
                                </div>
                            </div>
                            <div class="job-edit-v2__field">
                                <label class="job-edit-v2__label" for="bonus_other_conditions">その他条件</label>
                                <input type="text" id="bonus_other_conditions" name="bonus_other_conditions" class="job-edit-v2__input"
                                       value="{{ old('bonus_other_conditions', $recruit['bonus_other_conditions'] ?? '') }}"
                                       placeholder="例: 無遅刻無欠勤">
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="job-sec-work-s">
                        <h2 id="job-sec-work-s" class="job-edit-v2__sec-title"><i class="fas fa-clock"></i> 勤務条件</h2>
                        @include('shops.recruit.parts.shift-time-fields')
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="working_days">勤務日数・シフト <span class="job-edit-v2__req">必須</span></label>
                            <input type="text" id="working_days" name="working_days" class="job-edit-v2__input"
                                   value="{{ old('working_days', $recruit['working_days']) }}" placeholder="週1日からOK">
                        </div>
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="regular_holiday">定休日・休みの書き方</label>
                            <input type="text" id="regular_holiday" name="regular_holiday" class="job-edit-v2__input"
                                   value="{{ old('regular_holiday', $recruit['regular_holiday']) }}" placeholder="不定休">
                        </div>
                    </section>

                    <section aria-labelledby="job-sec-detail-s">
                        <h2 id="job-sec-detail-s" class="job-edit-v2__sec-title"><i class="fas fa-briefcase"></i> 募集要項</h2>
                        <div class="job-edit-v2__field">
                            <label class="job-edit-v2__label" for="qualification">応募資格 <span class="job-edit-v2__req">必須</span></label>
                            <input type="text" id="qualification" name="qualification" class="job-edit-v2__input"
                                   value="{{ old('qualification', $recruit['qualification']) }}" placeholder="18歳以上（高校生不可）">
                        </div>
                    </section>

                    <section aria-labelledby="job-sec-tags-s">
                        <h2 id="job-sec-tags-s" class="job-edit-v2__sec-title"><i class="fas fa-check-circle"></i> タグ・アピールポイント</h2>
                        <div style="margin-bottom:22px;">
                            <p class="job-edit-v2__tag-cat">働き方・給与</p>
                            <div class="job-edit-v2__chips">
                                @foreach(($masters['work_style'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="work_style_tag_ids[]" value="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('work_style_tag_ids', $recruit['work_style_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div style="margin-bottom:22px;">
                            <p class="job-edit-v2__tag-cat">歓迎条件</p>
                            <div class="job-edit-v2__chips">
                                @foreach(($masters['welcome'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="welcome_tag_ids[]" value="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('welcome_tag_ids', $recruit['welcome_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="job-edit-v2__tag-cat">待遇・サポート</p>
                            <div class="job-edit-v2__chips">
                                @foreach(($masters['benefit'] ?? []) as $tag)
                                    <label class="job-edit-v2__chip">
                                        <input type="checkbox" name="benefit_tag_ids[]" value="{{ $tag->id }}"
                                               {{ in_array((int) $tag->id, old('benefit_tag_ids', $recruit['benefit_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>
            @endif

        </form>

        <div class="job-edit-v2__footer">
            <div class="job-edit-v2__footer-inner">
                <a href="{{ route('shop.mypage.index') }}" class="job-edit-v2__btn-cancel">キャンセル</a>
                <button type="submit" form="recruit-form" class="job-edit-v2__btn-save">
                    <i class="fas fa-check"></i> 保存する
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/form-enhance.js') }}?v=20260913-uiux"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('recruit-form');

    // 公開ステータストグル（シンプルモード or 単一）
    var pubToggle = document.getElementById('published-toggle');
    var pubLabel  = document.getElementById('published-label');
    if (pubToggle && pubLabel) {
        pubToggle.addEventListener('change', function () {
            pubLabel.textContent = pubToggle.checked ? '保存後に公開（未保存）' : '保存後に非公開（未保存）';
            pubLabel.classList.toggle('is-on', pubToggle.checked);
        });
    }
    document.querySelectorAll('.js-kind-pub').forEach(function (cb) {
        var lid = cb.getAttribute('data-label-id');
        var lbl = lid ? document.getElementById(lid) : null;
        if (!lbl) return;
        function sync() {
            lbl.textContent = cb.checked ? '保存後に公開（未保存）' : '保存後に非公開（未保存）';
            lbl.classList.toggle('is-on', cb.checked);
        }
        cb.addEventListener('change', sync);
        sync();
    });

    var lastCb = document.querySelector('.js-shift-end-last');
    var endInp = document.querySelector('.js-shift-end-time');
    if (lastCb && endInp) {
        function syncEnd() {
            endInp.disabled = lastCb.checked;
            if (lastCb.checked) endInp.value = '';
        }
        lastCb.addEventListener('change', syncEnd);
        syncEnd();
    }

    // 通貨フォーマット（送信時にカンマ除去・任意上限は空なら送信しない）
    if (form) {
        form.addEventListener('submit', function () {
            if (endInp && endInp.disabled) {
                endInp.disabled = false;
                endInp.value = '';
            }
            form.querySelectorAll('[data-optional-currency]').forEach(function (el) {
                el.value = String(el.value).replace(/[^\d]/g, '');
                if (el.value === '') el.disabled = true;
            });
            form.querySelectorAll('[data-type="currency"]').forEach(function (el) {
                if (el.disabled) return;
                el.value = String(el.value).replace(/[^\d]/g, '');
            });
        });
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 「新規入店と同じタグをコピー」ボタン（ヘルプタブ専用）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    var copyBtn = document.getElementById('copy-trial-tags-btn');
    if (copyBtn) {
        // PHP から新規入店のタグIDを JS 変数として埋め込む
        var trialWorkStyleIds = @json($trialWorkStyleIds);
        var trialWelcomeIds   = @json($trialWelcomeIds);
        var trialBenefitIds   = @json($trialBenefitIds);

        function applyTagIds(containerId, ids) {
            var container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                cb.checked = ids.indexOf(parseInt(cb.getAttribute('data-tag-id'), 10)) !== -1;
            });
        }

        copyBtn.addEventListener('click', function () {
            applyTagIds('chips-work-style', trialWorkStyleIds);
            applyTagIds('chips-welcome',    trialWelcomeIds);
            applyTagIds('chips-benefit',    trialBenefitIds);

            copyBtn.textContent = '✓ コピーしました';
            copyBtn.style.color = '#a78bfa';
            copyBtn.style.borderColor = 'rgba(168, 85, 247, 0.5)';
            setTimeout(function () {
                copyBtn.innerHTML = '<i class="fas fa-copy"></i> 新規入店と同じタグをコピー';
                copyBtn.style.color = '';
                copyBtn.style.borderColor = '';
            }, 2000);
        });
    }

    // Random auto-fill for catchcopy / manager message
    var suggestEndpoint = @json(route('shop.recruits.suggest', ['category' => '__CATEGORY__']));
    document.querySelectorAll('[data-suggest-target][data-suggest-category]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.querySelector(btn.getAttribute('data-suggest-target'));
            var category = btn.getAttribute('data-suggest-category');
            if (!target || !category) return;
            if (target.value && target.value.trim() !== '') {
                if (!window.confirm('入力内容を候補で置き換えますか？（現在の内容は失われます）')) return;
            }
            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 取得中...';
            fetch(suggestEndpoint.replace('__CATEGORY__', encodeURIComponent(category)), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (json) {
                    if (json && json.ok && json.body) {
                        target.value = json.body;
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                        target.dispatchEvent(new Event('change', { bubbles: true }));
                        if (window.appToast) window.appToast('候補を挿入しました', 'success');
                    } else {
                        if (window.appToast) window.appToast('候補が見つかりませんでした', 'info');
                    }
                })
                .catch(function () {
                    if (window.appToast) window.appToast('取得に失敗しました', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                });
        });
    });
});
</script>
@endpush
