@extends('layouts.app-v2')

@section('title', ($recruit['store_name'] ?? ($shop['name'] ?? '店舗')) . 'の求人情報')
@section('meta_description', trim((string) (($recruit['catch_copy'] ?? '') ?: ($recruit['message'] ?? 'ミセチョクの求人情報です。'))))
@section('meta_image', $shop['main_img'] ?? ($recruit['hero_image'] ?? asset('assets/images/common/no-image.png')))
@section('canonical', $shareUrl ?? url()->current())

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/recruitment.css') }}?v=20260801-btn-rules">
<link rel="stylesheet" href="{{ asset('assets/css/fav-actions.css') }}">
<style>
    /* アプリの max-content-width に追従させ、追加の幅制限・余分なネストを掛けない */
    .recruit-ref-shell { width: 100%; max-width: 100%; margin: 0; min-height: auto; background: transparent; box-shadow: none; position: relative; }
    .recruit-ref-wrap { padding-bottom: calc(var(--footer-height, 75px) + 16px); background: transparent; }
    /* タイトル類だけセリフ。それ以外の本文・項目・ラベルは Noto Sans JP（メイリオ系）に統一 */
    .recruit-ref-shell,
    .recruit-ref-shell p,
    .recruit-ref-shell span,
    .recruit-ref-shell li,
    .recruit-ref-shell a,
    .recruit-ref-shell button,
    .recruit-ref-shell .recruit-ref-inforow,
    .recruit-ref-shell .recruit-ref-h2 { font-family: 'Noto Sans JP', 'Hiragino Sans', 'メイリオ', Meiryo, sans-serif; }
    .recruit-ref-shell h1.recruit-ref-title,
    .recruit-ref-shell .recruit-ref-h2-lg { font-family: var(--font-sans); }

    /* プレビューバー（sticky） */
    .recruit-ref-preview-sticky {
        position: sticky;
        top: var(--header-height, 60px);
        z-index: 50;
        background: #110f0d;
        border-bottom: 1px solid var(--color-line, #2a2a2a);
        padding: 12px 16px;
    }
    .recruit-ref-preview-sticky > p {
        margin: 0 0 12px;
        font-size: 11px;
        color: #a78bfa;
        font-weight: 800;
    }
    .recruit-ref-preview-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .recruit-ref-publish-form { margin: 0; }
    .recruit-ref-switch {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        background: transparent;
        padding: 0;
        cursor: pointer;
        font: inherit;
    }
    .recruit-ref-switch-track {
        width: 44px;
        height: 24px;
        border-radius: 999px;
        padding: 4px;
        box-sizing: border-box;
        background: #52525b;
        transition: background 0.25s ease;
        flex-shrink: 0;
    }
    .recruit-ref-switch-track.is-on { background: var(--accent, #d670a2); }
    .recruit-ref-switch-knob {
        display: block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,.35);
        transition: transform 0.25s ease;
        transform: translateX(0);
    }
    .recruit-ref-switch-track.is-on .recruit-ref-switch-knob { transform: translateX(20px); }
    .recruit-ref-switch-label {
        font-size: 12px;
        font-weight: 800;
        color: #71717a;
    }
    .recruit-ref-switch-label.is-on { color: #a78bfa; }
    .recruit-ref-preview-edit {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border: 1px solid #a78bfa;
        border-radius: 999px;
        color: #a78bfa;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .recruit-ref-preview-edit:hover { background: rgba(168, 85, 247, .1); }
    .recruit-ref-flash { font-size: 11px; color: #86efac; margin-top: 10px; margin-bottom: 0; }

    .recruit-ref-hero-wrap { position: relative; }
    .recruit-ref-hero { position: relative; margin: 0; height: 16rem; overflow: hidden; background: #18181b; }
    .recruit-ref-hero img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
    .recruit-ref-hero-overlay { position: absolute; inset: 0; background: linear-gradient(to top, #0a0a0a 0%, rgba(10,10,10,.4) 50%, transparent 100%); pointer-events: none; z-index: 2; }

    /* メイン画像上：キャッチコピー（ホーム求人カードと同系統） */
    .recruit-ref-catch-hero {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        z-index: 5;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 36px 12px 0;
        pointer-events: none;
        box-sizing: border-box;
    }
    .recruit-ref-catch-hero__backdrop {
        display: block;
        max-width: 96%;
        padding: 10px 14px 12px;
        border-radius: 12px;
        text-align: center;
        background: linear-gradient(180deg, rgba(0,0,0,.92) 0%, rgba(0,0,0,.78) 45%, rgba(0,0,0,.52) 100%);
        box-shadow: 0 4px 20px rgba(0,0,0,.35);
    }
    .recruit-ref-catch-hero__line1,
    .recruit-ref-catch-hero__line2 {
        margin: 0;
        font-weight: 800;
        color: #fff;
        letter-spacing: 0.02em;
        line-height: 1.35;
        text-shadow: 0 1px 2px rgba(0,0,0,.45);
    }
    .recruit-ref-catch-hero__line1 { font-size: clamp(0.82rem, 3.6vw, 1.05rem); }
    .recruit-ref-catch-hero__line2 { margin-top: 6px; font-size: clamp(0.72rem, 3.1vw, 0.92rem); font-weight: 700; opacity: 0.98; }
    .recruit-ref-catch-hero .rc-msg-em { color: #f5e042; font-weight: 900; }
    .recruit-ref-catch-hero__badge {
        display: inline-block;
        margin: 8px 0 0;
        padding: 5px 12px;
        font-size: 0.58rem;
        font-weight: 700;
        color: rgba(255,255,255,.96);
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 9999px;
        line-height: 1.35;
        max-width: 100%;
        box-sizing: border-box;
        word-break: break-word;
    }
    .recruit-ref-job-supplement { margin-top: 8px; }
    .recruit-ref-msg--pre { white-space: normal; }
    .recruit-ref-hero-carousel {
        display: flex;
        flex-flow: row nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        height: 100%;
        touch-action: pan-y pinch-zoom;
    }
    .recruit-ref-hero-carousel::-webkit-scrollbar { display: none; }
    .recruit-ref-hero-slide {
        flex: 0 0 100%;
        width: 100%;
        height: 100%;
        scroll-snap-align: start;
        scroll-snap-stop: always;
        position: relative;
    }
    .recruit-ref-hero-slide img { display: block; }
    .recruit-ref-dots {
        position: absolute;
        left: 0; right: 0; bottom: 10px;
        display: flex;
        justify-content: center;
        gap: 6px;
        z-index: 14;
        pointer-events: none;
    }
    .recruit-ref-dot {
        pointer-events: auto;
        width: 6px; height: 6px;
        border-radius: 50%;
        border: none;
        padding: 0;
        background: rgba(255,255,255,.35);
        cursor: pointer;
        transition: background 0.2s, transform 0.2s;
    }
    .recruit-ref-dot.is-active { background: #a78bfa; transform: scale(1.15); }
    .recruit-ref-thumbs--carousel {
        position: absolute;
        right: 12px;
        bottom: 12px;
        left: 12px;
        justify-content: flex-end;
        flex-wrap: wrap;
        max-width: none;
    }
    .recruit-ref-thumbs--carousel button {
        border: none;
        padding: 0;
        background: transparent;
        cursor: pointer;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
    }
    .recruit-ref-thumbs--carousel button.is-active img { border-color: #a78bfa; box-shadow: 0 0 0 1px #a78bfa; }
    .recruit-ref-thumbs--carousel img { width: 2.5rem; height: 2.5rem; border-radius: 8px; border: 1px solid rgba(168, 85, 247, .5); object-fit: cover; display: block; }

    .recruit-ref-thumbs { position: absolute; right: 12px; bottom: 12px; display: flex; gap: 6px; z-index: 12; align-items: center; }
    .recruit-ref-thumbs img { width: 2.5rem; height: 2.5rem; border-radius: 8px; border: 1px solid rgba(168, 85, 247, .5); object-fit: cover; }
    .recruit-ref-thumb-more { width: 2.5rem; height: 2.5rem; border-radius: 8px; border: 1px solid #2a2015; background: rgba(0,0,0,.55); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; color: #fff; }

    .recruit-ref-head { padding: 16px var(--content-padding-x, 12px) 20px; border-bottom: 1px solid rgba(168, 85, 247, 0.16); }
    .recruit-ref-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .recruit-ref-chip { font-size: 10px; padding: 2px 8px; border-radius: 2px; font-weight: 700; background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; }
    .recruit-ref-chip.gold-outline { background: transparent; color: #a78bfa; border: 1px solid #a78bfa; }

    .recruit-ref-title { margin: 0 0 8px; font-size: 1.5rem; line-height: 1.25; font-weight: 800; color: #fff; letter-spacing: .02em; font-family: var(--font-sans); }
    .recruit-ref-catch { margin: 0 0 20px; font-size: 0.875rem; font-weight: 800; color: #a78bfa; letter-spacing: 0.02em; }

    .recruit-job-toggle { background: #110f0d; padding: 4px; border-radius: 8px; display: flex; border: 1px solid #2a2015; margin-bottom: 16px; }
    .recruit-job-toggle button { flex: 1; border: none; background: transparent; color: #71717a; padding: 10px 4px; font-size: 12px; font-weight: 800; border-radius: 6px; cursor: pointer; transition: color .15s, background .15s; }
    .recruit-job-toggle button.is-active { background: #2a2210; color: #a78bfa; box-shadow: 0 1px 2px rgba(0,0,0,.2); }

    /* ヒーロー直下の単一時給カード（時給・ボーナスは目立たせる） */
    .recruit-ref-pay-highlight {
        background: linear-gradient(135deg, rgba(168, 85, 247, 0.18), rgba(168, 85, 247, 0.15));
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid rgba(168, 85, 247, 0.55);
        margin-bottom: 18px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 226, 163, 0.18);
        position: relative;
        overflow: hidden;
    }
    .recruit-ref-pay-highlight::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at top right, rgba(255, 226, 163, 0.18), transparent 60%);
        pointer-events: none;
    }
    .recruit-ref-pay-highlight .label {
        font-size: 0.7rem; font-weight: 800; color: #c4b5fd; margin-bottom: 6px; display: block;
        letter-spacing: 0.08em; text-transform: uppercase;
    }
    .recruit-ref-pay-highlight .line { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
    .recruit-ref-pay-highlight .yen { color: #c4b5fd; font-weight: 900; font-size: 1.05rem; }
    .recruit-ref-pay-highlight .num {
        font-size: 2.4rem; font-weight: 900; color: #fff; letter-spacing: -0.02em;
        text-shadow: 0 2px 6px rgba(0, 0, 0, 0.45);
    }
    .recruit-ref-pay-highlight .tilde { font-size: 1rem; color: #c4b5fd; font-weight: 700; }

    .recruit-ref-tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .recruit-ref-tags span { font-size: 10px; padding: 4px 10px; border-radius: 999px; font-weight: 700; }
    .recruit-ref-tags span.gold { background: rgba(168, 85, 247, .1); border: 1px solid rgba(168, 85, 247, .3); color: #a78bfa; }
    .recruit-ref-tags span.dim { background: #1a1714; border: 1px solid #3a2a18; color: #d4d4d8; font-weight: 600; }

    .recruit-ref-body { padding: 16px var(--content-padding-x, 12px) 32px; display: flex; flex-direction: column; gap: 32px; }

    .recruit-ref-h2 { margin: 0 0 12px; font-size: 0.875rem; font-weight: 800; color: #a78bfa; display: flex; align-items: center; gap: 8px; }
    .recruit-ref-h2-lg { margin: 0 0 16px; font-size: 1.125rem; font-weight: 800; color: #fff; display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
    .recruit-ref-h2-lg .bar { width: 4px; height: 1.25rem; background: #a78bfa; border-radius: 1px; flex-shrink: 0; margin-top: 2px; }
    .recruit-ref-subtle { font-size: 11px; font-weight: 600; color: #71717a; }

    .recruit-ref-msg { font-size: 0.875rem; color: #d4d4d8; line-height: 1.75; font-weight: 500; background: #110f0d; padding: 20px; border-radius: 12px; border: 1px solid #1f1a14; white-space: pre-wrap; margin-bottom: 16px; }

    .recruit-ref-share-row { display: flex; gap: 10px; }
    .recruit-ref-share-row .recruit-ref-share-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        transition: background 0.15s ease;
        cursor: pointer;
        border: 1px solid #71717a;
        background: transparent;
        color: #d4d4d8;
        font-family: inherit;
    }
    .recruit-ref-share-row .recruit-ref-share-btn--gold { border-color: #a78bfa; color: #a78bfa; }
    .recruit-ref-share-row .recruit-ref-share-btn--gold:hover { background: rgba(168, 85, 247, .1); }
    .recruit-ref-share-row .recruit-ref-share-btn--line { border-color: rgba(6,199,85,.5); color: #06C755; }
    .recruit-ref-share-row .recruit-ref-share-btn--line:hover { background: rgba(6,199,85,.1); }
    .recruit-ref-share-row .recruit-ref-share-btn--muted:hover { background: #27272a; }

    /* 入店ボーナス（募集要項内）— 採用ボーナスを目立たせる */
    .recruit-ref-bonus-card {
        border-radius: 14px;
        padding: 18px 20px;
        margin-bottom: 18px;
        background: linear-gradient(135deg, rgba(168, 85, 247, 0.22), rgba(168, 85, 247, 0.18));
        border: 1px solid rgba(168, 85, 247, 0.6);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 226, 163, 0.2);
    }
    .recruit-ref-bonus-card__head { display: flex; align-items: center; gap: 8px; color: #c4b5fd; margin-bottom: 10px; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .recruit-ref-bonus-card__amount { display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px; }
    .recruit-ref-bonus-card__amount .num {
        font-size: 2.0rem; font-weight: 900; color: #fff; letter-spacing: -0.02em;
        text-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
    }
    .recruit-ref-bonus-card__amount .suffix { font-size: 1rem; font-weight: 800; color: #c4b5fd; }
    .recruit-ref-bonus-card__cond {
        font-size: 10px;
        color: #a1a1aa;
        background: rgba(0,0,0,.3);
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid #3a2a18;
        line-height: 1.55;
    }
    .recruit-ref-bonus-card__cond strong { color: #a78bfa; font-weight: 800; }

    .recruit-ref-inforow { display: flex; padding: 14px 0; border-bottom: 1px solid #1f1a14; font-size: 0.875rem; }
    .recruit-ref-inforow:last-child { border-bottom: none; }
    .recruit-ref-inforow .k { width: 6rem; flex-shrink: 0; font-size: 11px; font-weight: 800; color: #71717a; padding-top: 2px; }
    .recruit-ref-inforow .v { flex: 1; color: #e4e4e7; font-weight: 600; line-height: 1.6; }

    .recruit-ref-tag-matrix { margin-top: 24px; background: #110f0d; border-radius: 12px; border: 1px solid #1f1a14; padding: 16px; }
    .recruit-ref-tag-matrix > p { margin: 0 0 12px; font-size: 12px; font-weight: 800; color: #a78bfa; }
    .recruit-ref-tag-matrix-row { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    @media (min-width: 480px) {
        .recruit-ref-tag-matrix-row { flex-direction: row; align-items: flex-start; gap: 12px; }
        .recruit-ref-tag-matrix-row .cat { width: 6rem; flex-shrink: 0; padding-top: 4px; }
    }
    .recruit-ref-tag-matrix-row:last-child { margin-bottom: 0; }
    .recruit-ref-tag-matrix-row .cat { font-size: 10px; font-weight: 800; color: #71717a; }
    .recruit-ref-tag-matrix-pills { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }
    .recruit-ref-tag-matrix-pills span {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        color: #d4d4d8;
        background: #1a1714;
        border: 1px solid #3a2a18;
    }

    .recruit-ref-card { background: #110f0d; border-radius: 12px; border: 1px solid #1f1a14; padding: 16px; margin-bottom: 16px; }

    .recruit-ref-concept .label { font-size: 11px; font-weight: 800; color: #a1a1aa; margin-bottom: 8px; }
    .recruit-ref-concept .body { font-size: 0.875rem; color: #d4d4d8; line-height: 1.75; }

    .recruit-ref-map-placeholder {
        width: 100%; height: 10rem; border-radius: 8px; background: #18181b; border: 1px solid #2a2015;
        display: flex; align-items: center; justify-content: center; margin-bottom: 12px;
    }
    .recruit-ref-map-placeholder i { font-size: 2rem; color: #a78bfa; }

    .recruit-ref-map-link {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px;
        border: 1px solid #a78bfa;
        border-radius: 8px;
        color: #a78bfa;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .recruit-ref-map-link:hover { background: rgba(168, 85, 247, .1); }

    /* =================================================================
       Brushup overrides — 暗さ軽減・ブランド色（ボルドー×シャンパンゴールド）統一
       ================================================================= */

    /* 1. ベース：純黒からボルドー寄り暗色へ */
    .recruit-ref-shell {
        background: linear-gradient(180deg, #141414 0%, #0a0a0a 100%);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(168, 85, 247, 0.06);
    }
    .recruit-ref-wrap { background: transparent; }

    /* 2. プレビューバー */
    .recruit-ref-preview-sticky {
        background: rgba(42, 13, 24, 0.92);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-bottom-color: rgba(168, 85, 247, 0.22);
    }
    .recruit-ref-preview-sticky > p { color: #a78bfa; }
    .recruit-ref-switch-track { background: #2a2a2a; }
    .recruit-ref-switch-track.is-on { background: linear-gradient(135deg, #a78bfa, #7c3aed); }
    .recruit-ref-switch-label { color: #a0a0a0; }
    .recruit-ref-switch-label.is-on { color: #a78bfa; }
    .recruit-ref-preview-edit { border-color: #a78bfa; color: #a78bfa; }
    .recruit-ref-preview-edit:hover { background: rgba(168, 85, 247, 0.12); }

    /* 3. ヒーロー */
    .recruit-ref-hero { background: #0a0a0a; }
    .recruit-ref-dot.is-active { background: #a78bfa; }
    .recruit-ref-thumbs--carousel button.is-active img { border-color: #a78bfa; box-shadow: 0 0 0 1px #a78bfa; }
    .recruit-ref-thumbs--carousel img,
    .recruit-ref-thumbs img { border-color: rgba(168, 85, 247, 0.55); }
    .recruit-ref-thumb-more { background: rgba(26, 10, 14, 0.7); border-color: rgba(168, 85, 247, 0.28); }

    /* 4. ヘッダ部 */
    .recruit-ref-head { border-bottom-color: rgba(168, 85, 247, 0.16); }
    .recruit-ref-chip {
        background: rgba(168, 85, 247, 0.08);
        color: #f5f5f5;
        border-color: rgba(168, 85, 247, 0.22);
    }
    .recruit-ref-chip.gold-outline { color: #a78bfa; border-color: #a78bfa; }
    .recruit-ref-catch { color: #a78bfa; }

    /* 5. ジョブタイプ切替 */
    .recruit-job-toggle {
        background: rgba(0, 0, 0, 0.35);
        border-color: rgba(168, 85, 247, 0.22);
    }
    .recruit-job-toggle button { color: #a0a0a0; }
    .recruit-job-toggle button.is-active {
        background: rgba(168, 85, 247, 0.16);
        color: #a78bfa;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    /* 6. 時給ハイライト */
    .recruit-ref-pay-highlight {
        background: linear-gradient(135deg, #2d1018 0%, #1f0810 100%);
        border-color: rgba(168, 85, 247, 0.55);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
    }
    .recruit-ref-pay-highlight .label,
    .recruit-ref-pay-highlight .yen { color: #a78bfa; }
    .recruit-ref-pay-highlight .tilde { color: #c8b8b0; }

    /* 7. タグピル */
    .recruit-ref-tags span.gold { background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.32); color: #a78bfa; }
    .recruit-ref-tags span.dim { background: rgba(255, 255, 255, 0.04); border-color: rgba(168, 85, 247, 0.18); color: #f5f5f5; font-weight: 600; }

    /* 8. セクション見出し・本文 */
    .recruit-ref-h2 { color: #a78bfa; }
    .recruit-ref-h2-lg .bar { background: #a78bfa; }
    .recruit-ref-subtle { color: #a0a0a0; }
    .recruit-ref-msg {
        background: #141414;
        border-color: rgba(168, 85, 247, 0.22);
        color: #f5f5f5;
        box-shadow: inset 0 1px 0 rgba(168, 85, 247, 0.04);
    }

    /* 9. シェアボタン */
    .recruit-ref-share-row .recruit-ref-share-btn { color: #f5f5f5; border-color: rgba(168, 85, 247, 0.32); }
    .recruit-ref-share-row .recruit-ref-share-btn--gold { border-color: #a78bfa; color: #a78bfa; }
    .recruit-ref-share-row .recruit-ref-share-btn--gold:hover { background: rgba(168, 85, 247, 0.12); }

    /* 10. 入店ボーナスカード */
    .recruit-ref-bonus-card {
        background: linear-gradient(135deg, #2a2a2a 0%, #141414 100%);
        border-color: rgba(168, 85, 247, 0.45);
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(168, 85, 247, 0.1);
    }
    .recruit-ref-bonus-card__head { color: #a78bfa; }
    .recruit-ref-bonus-card__cond {
        background: rgba(0, 0, 0, 0.3);
        border-color: rgba(168, 85, 247, 0.22);
        color: #c8b8b0;
    }
    .recruit-ref-bonus-card__cond strong { color: #a78bfa; }

    /* 11. 情報行（k/v） */
    .recruit-ref-inforow { border-bottom-color: rgba(168, 85, 247, 0.14); }
    .recruit-ref-inforow .k { color: #a0a0a0; }
    .recruit-ref-inforow .v { color: #f5f5f5; }

    /* 12. タグマトリクス */
    .recruit-ref-tag-matrix {
        background: #141414;
        border-color: rgba(168, 85, 247, 0.18);
    }
    .recruit-ref-tag-matrix > p { color: #a78bfa; }
    .recruit-ref-tag-matrix-row .cat { color: #a0a0a0; }

    /* 13. マップリンク */
    .recruit-ref-map-link { color: #a78bfa; border-color: rgba(168, 85, 247, 0.5); }
    .recruit-ref-map-link:hover { background: rgba(168, 85, 247, 0.12); }

    /* 14. キャッチコピー強調 */
    .recruit-ref-catch-hero .rc-msg-em { color: #c4b5fd; }

    /* 15. CTA / 応募ボタンの色味（必要なら別途上書き） */

    /* === 既存のローカル lightbox は削除済み。万が一の保険として念のため非表示。=== */
    .recruit-ref-shell ~ #lightbox-overlay { display: none !important; }

    /* =================================================================
       New Components — A1-A4 / B5-B7 / C8-C9 / E14
       ================================================================= */

    /* ヒーロー：前/次矢印 (B6) */
    .recruit-ref-hero-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 18;
        width: 40px;
        height: 40px;
        border-radius: 999px;
        border: 0;
        background: rgba(0, 0, 0, 0.55);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.92rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.32);
        transition: background 0.15s ease, transform 0.12s ease;
    }
    .recruit-ref-hero-arrow:hover { background: rgba(168, 85, 247, 0.65); color: #0a0a0a; }
    .recruit-ref-hero-arrow:active { transform: translateY(-50%) scale(0.94); }
    .recruit-ref-hero-arrow--prev { left: 12px; }
    .recruit-ref-hero-arrow--next { right: 12px; }
    .recruit-ref-hero-fallback {
        width: 100%; height: 100%;
        background: linear-gradient(135deg, #2a2a2a 0%, #141414 50%, #0a0a0a 100%);
    }

    /* 時給ハイライト：RANGE バッジ (A1) */
    .recruit-ref-pay-highlight__head {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }
    .recruit-ref-pay-range-badge {
        display: inline-block;
        padding: 1px 8px;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.18em;
        color: #1a0814;
        background: linear-gradient(135deg, #c4b5fd, #a78bfa);
        border-radius: 999px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    .recruit-ref-pay-empty {
        font-size: 0.9rem;
        color: #a0a0a0;
        font-weight: 700;
    }

    /* ボーナス進捗バー (A2) */
    .recruit-ref-bonus-progress {
        margin: 14px 0 4px;
    }
    .recruit-ref-bonus-progress-track {
        position: relative;
        height: 28px;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(168, 85, 247, 0.25);
        overflow: visible;
    }
    .recruit-ref-bonus-progress-fill {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 14%;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(168, 85, 247, 0.85) 0%, rgba(255, 226, 163, 0.5) 100%);
        box-shadow: 0 0 12px rgba(168, 85, 247, 0.5);
    }
    .recruit-ref-bonus-progress-marker {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: #f5e9c8;
        white-space: nowrap;
    }
    .recruit-ref-bonus-progress-marker small { font-size: 8px; opacity: 0.72; margin-left: 4px; }
    .recruit-ref-bonus-progress-marker--start { left: 10px; }
    .recruit-ref-bonus-progress-marker--end { right: 10px; color: #c4b5fd; }

    /* インフォ行レイアウト改善 (A3) */
    .recruit-ref-inforow .k {
        width: auto;
        min-width: 6rem;
        max-width: 8rem;
        flex-shrink: 0;
        word-break: keep-all;
    }
    .recruit-ref-inforow .v { flex: 1; min-width: 0; }

    /* タグマトリクスのアイコン (A4) */
    .recruit-ref-tag-matrix > p {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .recruit-ref-tag-matrix-row .cat {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .recruit-ref-tag-matrix-row .cat i {
        color: #a78bfa;
        font-size: 11px;
    }
    /* タグ折りたたみ (C9) */
    .recruit-ref-tag-more {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px dashed rgba(168, 85, 247, 0.4);
        background: transparent;
        color: #a78bfa;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
        font-family: inherit;
        letter-spacing: 0.04em;
    }
    .recruit-ref-tag-more:hover { background: rgba(168, 85, 247, 0.1); }
    .recruit-ref-tag-more .less-text { display: none; }
    .recruit-ref-tag-more[aria-expanded="true"] .more-text { display: none; }
    .recruit-ref-tag-more[aria-expanded="true"] .less-text { display: inline; }

    /* 長文メッセージ折りたたみ (C8) */
    .recruit-ref-msg.is-clipped {
        max-height: 8rem;
        overflow: hidden;
        position: relative;
        margin-bottom: 0;
    }
    .recruit-ref-msg.is-clipped::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 3.2rem;
        background: linear-gradient(to bottom, rgba(42, 13, 24, 0) 0%, rgba(42, 13, 24, 0.95) 78%);
        pointer-events: none;
    }
    .recruit-ref-msg.is-clipped.is-expanded {
        max-height: none;
    }
    .recruit-ref-msg.is-clipped.is-expanded::after { display: none; }
    .recruit-ref-msg-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 12px 0 16px;
        padding: 7px 14px;
        border: 1px solid rgba(168, 85, 247, 0.4);
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.25);
        color: #a78bfa;
        font-size: 11px;
        font-weight: 800;
        cursor: pointer;
        font-family: inherit;
    }
    .recruit-ref-msg-toggle:hover { background: rgba(168, 85, 247, 0.12); }
    .recruit-ref-msg-toggle .recruit-ref-msg-toggle__less { display: none; }
    .recruit-ref-msg-toggle[aria-expanded="true"] .recruit-ref-msg-toggle__more { display: none; }
    .recruit-ref-msg-toggle[aria-expanded="true"] .recruit-ref-msg-toggle__less { display: inline-flex; align-items: center; gap: 6px; }

    /* 共有ボタン強化 (B7) */
    .recruit-ref-share-row {
        gap: 8px;
        margin-top: 14px;
    }
    .recruit-ref-share-row .recruit-ref-share-btn {
        gap: 6px;
        padding: 11px 10px;
        font-size: 12px;
        min-height: 42px;
    }
    .recruit-ref-share-row .recruit-ref-share-btn i,
    .recruit-ref-share-row .recruit-ref-share-icon {
        font-size: 1.05rem;
    }

    /* CTA sticky (B5) — キャストビューのみ */
    body[class*="page-recruit"] .recruit-footer-cta,
    .recruit-footer-cta {
        position: sticky;
        bottom: calc(var(--footer-height, 75px) + 8px);
        z-index: 30;
        margin: 28px -20px 0;
        padding: 16px 20px 12px;
        background: linear-gradient(to top, rgba(26, 10, 14, 0.98) 0%, rgba(26, 10, 14, 0.86) 70%, rgba(26, 10, 14, 0) 100%);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-top: 1px solid rgba(168, 85, 247, 0.18);
    }
    .recruit-cta-row {
        display: flex;
        align-items: stretch;
        gap: 10px;
    }
    .recruit-cta-actions {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    .recruit-cta-heart {
        flex: 0 0 auto;
        align-self: stretch;
        width: 48px;
        border-radius: 14px;
        border: 1px solid rgba(168, 85, 247, 0.4);
        background: rgba(0, 0, 0, 0.4);
        color: #c4b5fd;
        font-size: 1.05rem;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .recruit-cta-heart:hover {
        background: rgba(168, 85, 247, 0.12);
        border-color: rgba(168, 85, 247, 0.65);
    }
    .recruit-cta-heart.is-active {
        background: rgba(168, 85, 247, 0.22);
        border-color: rgba(168, 85, 247, 0.85);
        color: #fff1cc;
    }
    .recruit-cta-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px 14px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.18s ease, background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        min-height: 46px;
    }
    .recruit-cta-btn:hover {
        transform: translateY(-1px);
        text-decoration: none;
    }
    .recruit-cta-btn i { font-size: 0.95rem; }
    .recruit-cta-btn--primary {
        background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 100%);
        color: #1a0814;
        box-shadow: 0 6px 18px rgba(168, 85, 247, 0.32);
    }
    .recruit-cta-btn--primary:hover { box-shadow: 0 8px 22px rgba(168, 85, 247, 0.42); color: #1a0814; }
    .recruit-cta-btn--help {
        background: rgba(168, 85, 247, 0.16);
        border-color: rgba(168, 85, 247, 0.7);
        color: #f5e1a8;
    }
    .recruit-cta-btn--help:hover { background: rgba(168, 85, 247, 0.26); color: #fff1cc; }
    .recruit-cta-btn--ghost {
        background: rgba(255, 255, 255, 0.04);
        border-color: rgba(168, 85, 247, 0.28);
        color: #c0c0c0;
    }
    .recruit-cta-btn--ghost:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(168, 85, 247, 0.5);
        color: #c4b5fd;
    }

    /* 「本日すぐ入れます」タグ（プロフィール／検索一覧／スワイプ共通） */
    .shop-avail-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.02em;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #1a1a1a;
        box-shadow: 0 2px 6px rgba(251, 191, 36, 0.35);
        line-height: 1.4;
        white-space: nowrap;
    }
    .shop-avail-tag i { font-size: 0.72rem; }
</style>
@endpush

@push('head-styles')
<style>
    /* === forCast の Instagram 風プロフィール用：ギャラリーグリッド + ライトボックス === */
    #profile-gallery-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2px;
        padding: 0;
        margin: 0;
        list-style: none;
    }
    .profile-gallery-item {
        aspect-ratio: 1 / 1;
        padding: 0;
        margin: 0;
        overflow: hidden;
        position: relative;
    }
    .profile-gallery-slot {
        position: relative;
        width: 100%;
        height: 100%;
        padding: 0;
        border: 0;
        border-radius: 0;
        overflow: hidden;
        cursor: pointer;
        box-sizing: border-box;
        background: transparent;
    }
    .profile-gallery-slot:not(.has-img) {
        border: 2px dashed rgba(255, 255, 255, 0.22);
    }
    .profile-gallery-slot > img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .profile-gallery-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        opacity: 0.45;
    }
    .profile-gallery-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        font-size: 9px;
        font-weight: 700;
        color: #111;
        background: linear-gradient(to right, #fbcfe8, #f472b6);
        padding: 2px 6px;
        border-radius: 4px;
        line-height: 1;
    }
    .lightbox-overlay {
        position: fixed;
        inset: 0;
        z-index: 2000;
        background: rgba(0, 0, 0, 0.92);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .lightbox-overlay.is-open { display: flex; }
    .lightbox-image { max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 12px; }
    /* Swiper container inside the lightbox overlay (multi-image gallery) */
    .lightbox-swiper { width: 100%; max-width: 900px; height: 90vh; }
    .lightbox-swiper .swiper-slide { display: flex; align-items: center; justify-content: center; }
    .lightbox-swiper .swiper-slide img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px; }
    .lightbox-swiper .swiper-button-prev,
    .lightbox-swiper .swiper-button-next { color: #fff; }
    .lightbox-swiper .swiper-pagination-bullet { background: rgba(255,255,255,0.55); opacity: 1; }
    .lightbox-swiper .swiper-pagination-bullet-active { background: #fff; }
    .lightbox-close {
        position: fixed; top: 20px; right: 20px;
        background: rgba(0, 0, 0, 0.5); color: #fff;
        border: 0; width: 40px; height: 40px; border-radius: 50%;
        cursor: pointer; font-size: 18px;
    }
</style>
@endpush

@section('content')
@php
    $usesJobTypes = $usesJobTypes ?? false;
    $recruit_trial = $recruit_trial ?? $recruit;
    $recruit_help = $recruit_help ?? $recruit;

    $hasHelp = !empty($recruit['help_hourly_wage']);
    $hasTrial = !empty($recruit['trial_hourly_wage']);
    $regularWage = (int) ($recruit['regular_hourly_wage'] ?? $recruit['hourly_wage_regular'] ?? 0);
    $regularWageMax = isset($recruit['regular_hourly_wage_max']) && $recruit['regular_hourly_wage_max'] !== null && (int) $recruit['regular_hourly_wage_max'] > 0
        ? (int) $recruit['regular_hourly_wage_max'] : null;
    $trialWageMax = isset($recruit['trial_hourly_wage_max']) && $recruit['trial_hourly_wage_max'] !== null && (int) $recruit['trial_hourly_wage_max'] > 0
        ? (int) $recruit['trial_hourly_wage_max'] : null;
    $helpWageMax = isset($recruit['help_hourly_wage_max']) && $recruit['help_hourly_wage_max'] !== null && (int) $recruit['help_hourly_wage_max'] > 0
        ? (int) $recruit['help_hourly_wage_max'] : null;
    $noruma = (int) ($recruit['bonus_reward'] ?? $recruit['noruma_reward'] ?? 0);
    $bonusDays = trim((string) ($recruit['bonus_total_working_days'] ?? $recruit['bonus_working_days'] ?? ''));
    $bonusHours = trim((string) ($recruit['bonus_total_working_hours'] ?? $recruit['bonus_working_hours'] ?? ''));
    $bonusExtra = trim((string) ($recruit['bonus_other_conditions'] ?? $recruit['bonus_condition'] ?? ''));
    $bonusCondParts = array_filter([
        $bonusDays !== '' ? '累計勤務日数: ' . $bonusDays . '日以上' : null,
        $bonusHours !== '' ? '累計勤務時間: ' . $bonusHours . '時間以上' : null,
        $bonusExtra !== '' ? $bonusExtra : null,
    ]);
    $bonusConditionsText = implode('、', $bonusCondParts);
    $showBonusMain = $noruma > 0 || $bonusConditionsText !== '';

    $workStyleTags = collect($recruit['store_features']['働き方・給与'] ?? [])->values();
    $otherTags = collect($recruit['store_features'] ?? [])->except('働き方・給与')->flatten()->filter()->unique()->values();
    $pillTags = $workStyleTags->merge($otherTags)->unique()->values();

    $subImages = $shop['sub_images'] ?? [];
    $thumbMore = max(0, count($subImages) - 2);
    $galleryImages = array_values(array_filter($shop['gallery_images'] ?? []));
    if (empty($galleryImages)) {
        $galleryImages = array_values(array_filter(array_merge(
            !empty($shop['main_img']) ? [$shop['main_img']] : [],
            is_array($subImages) ? $subImages : []
        )));
    }
    $addressLine = trim((string) ($recruit['address'] ?? ''));
    if ($addressLine === '') {
        $addressLine = trim(($shop['pref'] ?? '') . ($shop['city'] ?? '') . ($shop['addr1'] ?? ''));
    }
    $pref = trim((string) ($shop['pref'] ?? ''));
    $city = trim((string) ($shop['city'] ?? ''));
    $areaChip = ($pref !== '' && $city !== '') ? $pref . '・' . $city : ($pref !== '' ? $pref : $city);

    $isPublishActive = (($recruit['status'] ?? 'active') === 'active');
    $tagGroups = $shop['tag_groups'] ?? [];

    $shareUrlResolved = $shareUrl ?? url()->current();
    $shareTitleResolved = ($shareTitle ?? (($recruit['store_name'] ?? ($shop['name'] ?? '店舗')) . 'の求人情報'));
    $shareTextResolved = $shareText ?? (trim((string) ($recruit['catch_copy'] ?? '')) !== ''
        ? trim((string) $recruit['catch_copy'])
        : trim((string) ($recruit['message'] ?? '')));
    $xShareUrl = 'https://twitter.com/intent/tweet?url=' . rawurlencode($shareUrlResolved) . '&text=' . rawurlencode(trim($shareTitleResolved . ' ' . $shareTextResolved));
    $lineShareUrl = 'https://social-plugins.line.me/lineit/share?url=' . rawurlencode($shareUrlResolved);

    $storeFeatures = $recruit['store_features'] ?? [];
    $matrixLabels = [
        '働き方・給与'   => '働き方・給与',
        '歓迎条件'       => '歓迎条件',
        '待遇・サポート' => '待遇・サポート',
        '店内の雰囲気・客層' => '店内の雰囲気・客層',
        '設備・アクセス' => '設備・アクセス',
    ];
    $messageBody = trim((string) ($recruit['message'] ?? ''));
    $jobSupplementMain = trim((string) ($recruit['job_content'] ?? ''));

    $salaryNotesMain = trim((string) ($recruit['salary_text'] ?? ''));
    $jobNotesHelp = trim((string) ($recruit['help_job_content'] ?? ''));

    $hasFeatureMatrix = false;
    foreach ($matrixLabels as $key => $_lbl) {
        if (!empty($storeFeatures[$key]) && count((array) $storeFeatures[$key]) > 0) {
            $hasFeatureMatrix = true;
            break;
        }
    }

    // タグマトリクス：カテゴリ別アイコン
    $matrixIcons = [
        '働き方・給与'       => 'fa-yen-sign',
        '歓迎条件'           => 'fa-handshake',
        '待遇・サポート'     => 'fa-gift',
        '店内の雰囲気・客層' => 'fa-store',
        '設備・アクセス'     => 'fa-location-dot',
    ];

    // メッセージの長文判定（200字以上で折りたたみ対象）
    $messageNeedsClip = mb_strlen($messageBody) > 200;
    $jobSupplementNeedsClip = mb_strlen($jobSupplementMain) > 200;

    // JSON-LD JobPosting 構築
    $ldHiringName = $shop['name'] ?? ($recruit['store_name'] ?? '');
    $ldDescription = $messageBody !== '' ? $messageBody : (string) ($recruit['catch_copy'] ?? '');
    $ldHourly = [];
    if ($regularWage > 0) {
        $ldHourly['min'] = $regularWage;
        $ldHourly['max'] = $regularWageMax ?: $regularWage;
    } elseif ($hasTrial) {
        $tw = (int) $recruit['trial_hourly_wage'];
        $ldHourly['min'] = $tw;
        $ldHourly['max'] = $trialWageMax ?: $tw;
    }
    $ldJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $ldHiringName !== '' ? ($ldHiringName . '【' . ($recruit['catch_copy'] ?? '求人') . '】') : '求人',
        'description' => $ldDescription !== '' ? mb_strimwidth($ldDescription, 0, 500, '…') : 'ミセチョクの求人情報',
        'datePosted' => now()->toDateString(),
        'validThrough' => now()->addDays(60)->toDateString(),
        'employmentType' => $hasHelp ? 'CONTRACTOR' : 'PART_TIME',
        'hiringOrganization' => [
            '@type' => 'Organization',
            'name' => $ldHiringName ?: 'ミセチョク',
            'logo' => $shop['main_img'] ?? '',
        ],
        'jobLocation' => [
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'addressCountry' => 'JP',
                'addressRegion' => $pref,
                'addressLocality' => $city,
                'streetAddress' => trim((string) ($shop['addr1'] ?? '')),
            ],
        ],
    ];
    if (!empty($ldHourly)) {
        $ldJsonLd['baseSalary'] = [
            '@type' => 'MonetaryAmount',
            'currency' => 'JPY',
            'value' => [
                '@type' => 'QuantitativeValue',
                'minValue' => $ldHourly['min'],
                'maxValue' => $ldHourly['max'],
                'unitText' => 'HOUR',
            ],
        ];
    }
    // 空キーを取り除く
    $ldJsonLd['hiringOrganization'] = array_filter($ldJsonLd['hiringOrganization']);
    $ldJsonLd['jobLocation']['address'] = array_filter($ldJsonLd['jobLocation']['address']);
@endphp

@push('scripts')
<script type="application/ld+json">{!! json_encode($ldJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@php
    // 店舗側プレビューでもキャストと同じレイアウトを使うため、常に共通の @php 変数を用いて
    // cast-show パーシャルを include する。店舗プレビュー時はプレビュー用のバナーを先頭に付ける。
    $isShopPreview = empty($forCast);
@endphp

@if($isShopPreview)
    {{-- 店舗側：求人プレビュー（キャストが実際に見る画面と同一の見た目） --}}
    <div class="recruit-ref-preview-sticky">
        <p>店舗プロフィールプレビュー（求職者からの見え方）</p>
        <div class="recruit-ref-preview-row">
            <form method="post" action="{{ route('shop.recruits.toggle-status') }}" class="recruit-ref-publish-form">
                @csrf
                @if(!empty($horizontalShopJobs))
                    <input type="hidden" name="job_type" value="1">
                @endif
                <button type="submit" class="recruit-ref-switch" title="タップで公開／非公開を切り替えます" aria-label="{{ $isPublishActive ? '公開中。クリックで非公開にします' : '非公開。クリックで公開します' }}">
                    <span class="recruit-ref-switch-track {{ $isPublishActive ? 'is-on' : '' }}">
                        <span class="recruit-ref-switch-knob"></span>
                    </span>
                    <span class="recruit-ref-switch-label {{ $isPublishActive ? 'is-on' : '' }}">{{ $isPublishActive ? '公開中' : '非公開' }}</span>
                </button>
            </form>
            <a href="{{ route('shop.recruits.edit') }}" class="recruit-ref-preview-edit"><i class="fas fa-pen"></i> 編集</a>
        </div>
        @if(session('message'))
            <p class="recruit-ref-flash" role="status">{{ session('message') }}</p>
        @endif
    </div>
@endif

{{-- キャスト側／店舗プレビュー共通：Instagram 風のプロフィール表示 --}}
@include('shops.recruit.parts.cast-show')

@push('scripts')
<script>
(function () {
    'use strict';
    // ライトボックスは Swiper 化：クリックしたサムネの index を initial slide として開く。
    var overlay = document.getElementById('lightbox-overlay');
    if (!overlay) return;
    var swiperEl = overlay.querySelector('.lightbox-swiper');
    var slideCount = swiperEl ? swiperEl.querySelectorAll('.swiper-slide').length : 0;
    var swiperInstance = null;
    function ensureSwiper() {
        if (swiperInstance || !swiperEl || typeof window.Swiper !== 'function') return;
        swiperInstance = new window.Swiper(swiperEl, {
            loop: false,
            navigation: slideCount > 1 ? {
                nextEl: swiperEl.querySelector('.swiper-button-next'),
                prevEl: swiperEl.querySelector('.swiper-button-prev')
            } : false,
            pagination: slideCount > 1 ? {
                el: swiperEl.querySelector('.swiper-pagination'),
                clickable: true
            } : false,
            keyboard: { enabled: true }
        });
    }
    function openLightbox(index) {
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        ensureSwiper();
        if (swiperInstance) {
            swiperInstance.update();
            swiperInstance.slideTo(index, 0);
        }
    }
    window.closeLightbox = function (e) {
        if (e && e.target && !e.target.classList.contains('lightbox-overlay') && !e.target.closest('.lightbox-close')) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#profile-gallery-list .js-lightbox-target').forEach(function (el, i) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                openLightbox(i);
            });
        });
    });
})();
</script>
@endpush

@endsection

@push('scripts')
{{-- KEEP アイコンボタン（data-fav-toggle）のトグル + トースト --}}
<script src="{{ asset('assets/js/favorite-quick.js') }}?v=20260924-coalesce"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var usesJobTypes = @json($usesJobTypes ?? false);
    var initialJobPanel = @json($initial_job_panel ?? '');
    var heroCarousel = document.getElementById('recruit-hero-carousel');
    if (heroCarousel && heroCarousel.children.length > 1) {
        var heroSlides = heroCarousel.querySelectorAll('.recruit-ref-hero-slide');
        var dots = document.querySelectorAll('.recruit-ref-dot[data-hero-goto]');
        var thumbBtns = document.querySelectorAll('#recruit-hero-thumbs button[data-hero-goto]');
        function setHeroIndex(idx) {
            var i = Math.max(0, Math.min(idx, heroSlides.length - 1));
            var slide = heroSlides[i];
            if (slide) heroCarousel.scrollTo({ left: slide.offsetLeft, behavior: 'smooth' });
            dots.forEach(function(d) { d.classList.toggle('is-active', parseInt(d.getAttribute('data-hero-goto'), 10) === i); });
            thumbBtns.forEach(function(b) { b.classList.toggle('is-active', parseInt(b.getAttribute('data-hero-goto'), 10) === i); });
        }
        function currentHeroIndex() {
            var w = heroCarousel.clientWidth || 1;
            return Math.round(heroCarousel.scrollLeft / w);
        }
        document.querySelectorAll('[data-hero-goto]').forEach(function(el) {
            el.addEventListener('click', function() {
                var g = parseInt(el.getAttribute('data-hero-goto'), 10);
                if (!isNaN(g)) setHeroIndex(g);
            });
        });
        var scrollEndTimer;
        heroCarousel.addEventListener('scroll', function() {
            clearTimeout(scrollEndTimer);
            scrollEndTimer = setTimeout(function() {
                var ci = currentHeroIndex();
                dots.forEach(function(d) { d.classList.toggle('is-active', parseInt(d.getAttribute('data-hero-goto'), 10) === ci); });
                thumbBtns.forEach(function(b) { b.classList.toggle('is-active', parseInt(b.getAttribute('data-hero-goto'), 10) === ci); });
            }, 60);
        }, { passive: true });
    }

    var jobToggle = document.getElementById('recruit-job-toggle');
    if (jobToggle) {
        var reqSub = document.getElementById('recruit-req-sub');
        jobToggle.querySelectorAll('button[data-job-type]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var t = btn.getAttribute('data-job-type');
                jobToggle.querySelectorAll('button').forEach(function(b) { b.classList.toggle('is-active', b === btn); });
                if (usesJobTypes) {
                    document.querySelectorAll('[data-variant-head]').forEach(function(el) {
                        el.hidden = el.getAttribute('data-variant-head') !== t;
                    });
                    document.querySelectorAll('[data-variant-body]').forEach(function(el) {
                        el.hidden = el.getAttribute('data-variant-body') !== t;
                    });
                    document.querySelectorAll('[data-recruit-catch-hero]').forEach(function(el) {
                        el.hidden = el.getAttribute('data-recruit-catch-hero') !== t;
                    });
                } else {
                    document.querySelectorAll('[data-job-panel]').forEach(function(panel) {
                        panel.hidden = panel.getAttribute('data-job-panel') !== t;
                    });
                    var reqMain = document.getElementById('recruit-req-main');
                    var reqHelp = document.getElementById('recruit-req-help');
                    if (reqMain && reqHelp) {
                        reqMain.hidden = (t === 'help');
                        reqHelp.hidden = (t !== 'help');
                    }
                    if (reqSub) {
                        reqSub.textContent = t === 'help' ? '（ヘルプ）' : '（新規入店）';
                    }
                }
            });
        });
        if (initialJobPanel === 'fulltime' || initialJobPanel === 'help') {
            var targetType = usesJobTypes
                ? (initialJobPanel === 'help' ? 'help' : 'trial')
                : (initialJobPanel === 'help' ? 'help' : 'main');
            var autoBtn = jobToggle.querySelector('button[data-job-type="' + targetType + '"]');
            if (autoBtn) {
                autoBtn.click();
            }
        }
    }

    var shareUrl = @json($shareUrlResolved ?? '');
    var shareTitle = @json($shareTitleResolved ?? '');
    var shareText = @json($shareTextResolved ?? '');
    document.querySelectorAll('.js-recruit-native-share').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (navigator.share) {
                navigator.share({ title: shareTitle, text: shareText, url: shareUrl }).catch(function() {});
            } else if (shareUrl) {
                window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle), '_blank', 'noopener,noreferrer');
            }
        });
    });

    var keepBtn = document.querySelector('.recruit-cta-heart[data-action="keep"]');
    if (keepBtn) {
        var csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfTokenEl ? csrfTokenEl.getAttribute('content') : null;
        keepBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var itemId = keepBtn.getAttribute('data-item-id');
            var itemType = keepBtn.getAttribute('data-item-type');
            var action = keepBtn.getAttribute('data-action');
            if (!itemId || !itemType || !action) {
                return;
            }

            fetch('/api/favorites/toggle', {
                method: 'POST',
                headers: Object.assign({
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }, csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                body: JSON.stringify({
                    action: action,
                    item_id: itemId,
                    item_type: itemType
                }),
                credentials: 'same-origin'
            })
                .then(function (res) {
                    return res.ok ? res.json() : Promise.reject(res);
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }
                    keepBtn.classList.toggle('is-active', !!data.is_active);
                })
                .catch(function () {
                    // noop
                });
        });
    }

    // 画像のフルスクリーン表示は layouts/app.blade.php の global lightbox に委譲
    // （window.openImageLightbox が .js-lightbox-target を自動でハンドリング）

    // ============== ヒーロー前/次矢印 (B6) ==============
    var heroPrev = document.getElementById('recruit-hero-prev');
    var heroNext = document.getElementById('recruit-hero-next');
    function getHeroIndex() {
        var slides = heroCarousel ? heroCarousel.querySelectorAll('.recruit-ref-hero-slide') : [];
        if (!heroCarousel || !slides.length) return 0;
        var sw = heroCarousel.scrollLeft;
        var w = heroCarousel.clientWidth;
        return Math.round(sw / Math.max(w, 1));
    }
    if (heroPrev && heroNext && heroCarousel) {
        var slidesAll = heroCarousel.querySelectorAll('.recruit-ref-hero-slide');
        heroPrev.addEventListener('click', function () {
            var i = Math.max(0, getHeroIndex() - 1);
            if (typeof setHeroIndex === 'function') setHeroIndex(i);
        });
        heroNext.addEventListener('click', function () {
            var i = Math.min(slidesAll.length - 1, getHeroIndex() + 1);
            if (typeof setHeroIndex === 'function') setHeroIndex(i);
        });
    }

    // ============== 長文メッセージ折りたたみ (C8) ==============
    document.querySelectorAll('[data-recruit-clip-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var section = btn.parentElement || btn.closest('section');
            var clip = section ? section.querySelector('[data-recruit-clip]') : null;
            if (!clip) return;
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                clip.classList.remove('is-expanded');
                btn.setAttribute('aria-expanded', 'false');
            } else {
                clip.classList.add('is-expanded');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // ============== タグ折りたたみ (C9) ==============
    document.querySelectorAll('[data-recruit-tag-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var container = btn.parentElement;
            if (!container) return;
            var extras = container.querySelectorAll('[data-tag-extra]');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            extras.forEach(function (el) {
                if (expanded) el.setAttribute('hidden', '');
                else el.removeAttribute('hidden');
            });
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        });
    });

    // ============== 共有ボタン Web Share API (B7 既存機能の補強) ==============
    document.querySelectorAll('.js-recruit-native-share').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var shareUrl = @json($shareUrlResolved ?? '');
            var shareTitle = @json($shareTitleResolved ?? '');
            var shareText = @json($shareTextResolved ?? '');
            if (navigator.share) {
                navigator.share({ title: shareTitle, text: shareText, url: shareUrl })
                    .catch(function () { /* user canceled */ });
            } else if (navigator.clipboard && shareUrl) {
                navigator.clipboard.writeText(shareUrl).then(function () {
                    btn.classList.add('is-copied');
                    var label = btn.querySelector('span');
                    var orig = label ? label.textContent : '';
                    if (label) label.textContent = 'コピーしました';
                    setTimeout(function () { if (label) label.textContent = orig; btn.classList.remove('is-copied'); }, 1500);
                });
            }
        });
    });
});
</script>
@endpush
