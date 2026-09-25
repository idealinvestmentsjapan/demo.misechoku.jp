@extends('layouts.app-v2')

@section('title', 'プロフィール編集')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/form-enhance.css') }}">
<style>
    /* 店舗プロフィール編集：ライトテーマ（許可証・本人確認・キャスト編集と同じ doc トークン）
       2026-08-01 Phase 2 リニューアル */
    .shop-profile-edit {
        --spe-bg:            #f5f2fb;
        --spe-panel:         transparent;
        --spe-field:         #ffffff;
        --spe-border:        rgba(124, 58, 237, 0.30);
        --spe-border-focus:  #7c3aed;
        --spe-gold:          #7c3aed;
        --spe-muted:         #4a4560;
        --spe-hint:          #8b84a1;
        --spe-ink:           #1e1a30;
        --spe-line:          rgba(124, 58, 237, 0.18);
        --spe-subheader-h:   56px;
        background: var(--spe-bg);
        min-height: 100%;
        margin: 0 calc(-1 * var(--content-padding-x, 16px));
        /* --footer-height に safe-area 込み。二重加算しない。 */
        padding-bottom: calc(var(--footer-height, 75px) + 88px);
    }

    .shop-profile-edit__shell {
        max-width: 100%;
        margin: 0 auto;
        min-height: 100%;
        background: var(--spe-panel);
        box-shadow: none;
        padding-top: var(--spe-subheader-h, 56px);
    }

    .shop-profile-edit__top {
        position: fixed;
        top: var(--header-height, 60px);
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: var(--max-content-width);
        height: var(--spe-subheader-h, 56px);
        box-sizing: border-box;
        z-index: 1400;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 0 16px;
        background: rgba(245, 242, 251, 0.92);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--spe-line);
    }
    .shop-profile-edit__form section {
        scroll-margin-top: calc(var(--header-height, 60px) + var(--spe-subheader-h, 56px) + 12px);
    }

    .shop-profile-edit__back {
        color: var(--spe-muted);
        padding: 4px;
        margin-left: -4px;
        text-decoration: none;
        font-size: 1.25rem;
        line-height: 1;
        transition: color 0.15s ease;
    }
    .shop-profile-edit__back:hover { color: var(--spe-gold); }

    .shop-profile-edit__title-block {
        text-align: center;
        flex: 1;
        min-width: 0;
    }
    .shop-profile-edit__title-en {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 800;
        color: var(--spe-ink);
        letter-spacing: 0.2em;
        font-family: var(--font-sans);
    }
    .shop-profile-edit__title-sub {
        margin: 2px 0 0;
        font-size: 9px;
        font-weight: 700;
        color: var(--spe-gold);
        letter-spacing: 0.06em;
    }

    .shop-profile-edit__spacer {
        width: 2rem;
        flex-shrink: 0;
    }

    .shop-profile-edit__flash {
        margin: 0 16px 12px;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #bbf7d0;
        background: rgba(34, 197, 94, 0.12);
        border: 1px solid rgba(34, 197, 94, 0.25);
    }

    .shop-profile-edit__errors {
        margin: 0 16px 12px;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 0.75rem;
        color: #fecaca;
        background: rgba(127, 29, 29, 0.25);
        border: 1px solid rgba(248, 113, 113, 0.35);
    }
    .shop-profile-edit__errors ul {
        margin: 6px 0 0 1.1em;
        padding: 0;
    }

    /* セクションアンカーナビ：横スクロール chip 行 */
    .shop-profile-edit__anchor-nav {
        display: flex;
        gap: 8px;
        padding: 8px 16px 4px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        background: rgba(245, 242, 251, 0.85);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        border-bottom: 1px solid var(--spe-line);
        position: sticky;
        top: calc(var(--header-height, 60px) + var(--spe-subheader-h, 56px));
        z-index: 100;
    }
    .shop-profile-edit__anchor-nav::-webkit-scrollbar { display: none; }
    .shop-profile-edit__anchor-nav a {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid var(--spe-border);
        color: var(--spe-muted);
        font-size: 0.78rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
    }
    .shop-profile-edit__anchor-nav a:hover {
        background: rgba(124, 58, 237, 0.06);
        border-color: var(--spe-gold);
        color: var(--spe-gold);
    }
    .shop-profile-edit__anchor-nav a i { font-size: 0.72rem; color: var(--spe-gold); }

    .shop-profile-edit__form {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    /* 各セクションをカード化（許可証・本人確認・キャスト編集と同じ doc-card 仕様） */
    .shop-profile-edit__form > section {
        background: #ffffff;
        border: 1px solid var(--spe-line);
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 4px 14px rgba(76, 29, 149, 0.08);
    }

    /* セクション見出し：許可証・本人確認・キャスト編集と同じ「小さめ・muted・UPPER」パターン */
    .shop-profile-edit__section-title {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--spe-line);
        font-size: 0.78rem;
        font-family: var(--font-sans);
        font-weight: 800;
        color: var(--spe-hint);
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .shop-profile-edit__section-title i {
        font-size: 0.85rem;
        color: var(--spe-gold);
        font-style: normal;
    }

    .shop-profile-edit__field {
        margin-bottom: 22px;
    }
    .shop-profile-edit__field:last-child {
        margin-bottom: 0;
    }

    .shop-profile-edit__label {
        display: block;
        font-size: 0.80rem;
        font-weight: 700;
        color: var(--spe-ink);
        margin: 0 0 6px 2px;
        letter-spacing: 0.02em;
    }

    .shop-profile-edit__input,
    .shop-profile-edit__textarea,
    .shop-profile-edit__select {
        width: 100%;
        box-sizing: border-box;
        background: var(--spe-field);
        border: 1px solid var(--spe-border);
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 44px;
        font-size: 16px;   /* iOS ズーム回避 */
        color: var(--spe-ink);
        color-scheme: light;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .shop-profile-edit__input::placeholder,
    .shop-profile-edit__textarea::placeholder {
        color: #b9b3c7;
    }
    .shop-profile-edit__input:focus,
    .shop-profile-edit__textarea:focus,
    .shop-profile-edit__select:focus {
        outline: none;
        border-color: var(--spe-border-focus);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }
    .shop-profile-edit__textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.6;
    }

    .shop-profile-edit__input--mono {
        font-family: ui-monospace, monospace;
    }

    .shop-profile-edit__hint {
        margin: 6px 0 0 2px;
        font-size: 0.72rem;
        line-height: 1.5;
        color: var(--spe-hint);
    }

    .shop-profile-edit__grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .shop-profile-edit__shift-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        align-items: start;
    }
    .shop-profile-edit__shift-grid > .shop-profile-edit__field {
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
    }
    .shop-profile-edit__check-line {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        font-size: 0.82rem;
        color: var(--spe-muted);
        cursor: pointer;
    }
    .shop-profile-edit__check-line input { width: auto; accent-color: var(--spe-gold); }
    .shop-profile-edit__station-list { display: flex; flex-direction: column; gap: 10px; }
    .shop-profile-edit__station-row {
        display: flex;
        align-items: stretch;
        gap: 8px;
        padding: 10px;
        border: 1px solid var(--spe-line);
        border-radius: 12px;
        background: #faf7ff;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .shop-profile-edit__station-row:first-child {
        border-color: rgba(124, 58, 237, 0.55);
        background: rgba(124, 58, 237, 0.06);
    }
    .shop-profile-edit__station-row.is-dragging { opacity: 0.55; }
    .shop-profile-edit__station-row.is-ghost { background: rgba(124, 58, 237, 0.14); }
    .shop-profile-edit__station-drag {
        flex: 0 0 auto;
        width: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 0;
        color: var(--spe-hint);
        cursor: grab;
        touch-action: none;
        border-radius: 8px;
    }
    .shop-profile-edit__station-drag:hover { color: var(--spe-gold); background: rgba(124, 58, 237, 0.10); }
    .shop-profile-edit__station-drag:active { cursor: grabbing; }
    .shop-profile-edit__station-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .shop-profile-edit__station-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.70rem;
        font-weight: 700;
        color: var(--spe-hint);
        letter-spacing: 0.04em;
    }
    .shop-profile-edit__station-row:first-child .shop-profile-edit__station-badge { color: var(--spe-gold); }
    .shop-profile-edit__station-badge .is-main-pill {
        display: inline-flex;
        align-items: center;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 800;
        background: var(--spe-gold);
        color: #ffffff;
    }
    .shop-profile-edit__station-row:not(:first-child) .is-main-pill { display: none; }
    .shop-profile-edit__station-set-main {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        background: #ffffff;
        border: 1px solid var(--spe-border);
        border-radius: 999px;
        color: var(--spe-muted);
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }
    .shop-profile-edit__station-set-main:hover {
        background: rgba(124, 58, 237, 0.06);
        color: var(--spe-gold);
        border-color: var(--spe-gold);
    }
    .shop-profile-edit__station-set-main i { font-size: 0.66rem; }
    .shop-profile-edit__station-row:first-child .shop-profile-edit__station-set-main {
        background: var(--spe-gold);
        border-color: var(--spe-gold);
        color: #ffffff;
        cursor: default;
        pointer-events: none;
    }
    .shop-profile-edit__station-row:first-child .shop-profile-edit__station-set-main-label::before {
        content: '✓ ';
    }
    .shop-profile-edit__station-row:first-child .shop-profile-edit__station-set-main-label {
        font-weight: 800;
    }
    .shop-profile-edit__station-remove {
        flex: 0 0 auto;
        width: 36px;
        background: transparent;
        border: 0;
        color: var(--spe-hint);
        cursor: pointer;
        border-radius: 8px;
    }
    .shop-profile-edit__station-remove:hover { color: #dc2626; background: rgba(220, 38, 38, 0.08); }
    .shop-profile-edit__station-info {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        margin: 6px 0 10px;
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(124, 58, 237, 0.06);
        border: 1px solid var(--spe-line);
        font-size: 0.76rem;
        line-height: 1.55;
        color: var(--spe-muted);
    }
    .shop-profile-edit__station-info i { color: var(--spe-gold); margin-top: 2px; }
    .shop-profile-edit__station-add {
        align-self: flex-start;
        margin-top: 4px;
        padding: 8px 14px;
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--spe-gold);
        background: #ffffff;
        border: 1px dashed var(--spe-border);
        border-radius: 999px;
        cursor: pointer;
    }
    .shop-profile-edit__station-add:hover { background: rgba(124, 58, 237, 0.06); border-color: var(--spe-gold); }
    .shop-profile-edit__station-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .shop-profile-edit__section-title-actions {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
    }
    .shop-profile-edit__section-title-actions .shop-profile-edit__station-add {
        margin-top: 0;
        padding: 6px 10px;
        font-size: 0.66rem;
    }

    .shop-profile-edit__select-wrap {
        position: relative;
    }
    .shop-profile-edit__select-wrap select {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 2.25rem;
    }
    .shop-profile-edit__select-wrap::after {
        content: '▼';
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        font-size: 10px;
        color: var(--spe-muted);
    }

    .shop-profile-edit__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .shop-profile-edit__chip {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }
    .shop-profile-edit__chip input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .shop-profile-edit__chip span {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid var(--spe-border);
        background: #faf7ff;
        color: var(--spe-muted);
        font-size: 0.78rem;
        font-weight: 700;
        transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
    }
    .shop-profile-edit__chip:hover span {
        background: rgba(124, 58, 237, 0.06);
        border-color: var(--spe-gold);
    }
    .shop-profile-edit__chip input:checked + span {
        border-color: var(--spe-gold);
        background: rgba(124, 58, 237, 0.12);
        color: var(--spe-gold);
    }
    .shop-profile-edit__chip input:focus-visible + span {
        outline: 2px solid var(--spe-gold);
        outline-offset: 2px;
    }

    .shop-profile-edit__actions {
        position: fixed;
        left: 0;
        right: 0;
        bottom: var(--footer-height, 75px);
        z-index: 999;
        display: flex;
        justify-content: center;
        padding: 16px;
        padding-left: max(16px, env(safe-area-inset-left, 0px));
        padding-right: max(16px, env(safe-area-inset-right, 0px));
        padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
        background: linear-gradient(to top, rgba(245, 242, 251, 0.98) 55%, rgba(245, 242, 251, 0.90) 85%, rgba(245, 242, 251, 0));
        border-top: 1px solid var(--spe-line);
        box-sizing: border-box;
    }
    .shop-profile-edit__actions-inner {
        display: flex;
        align-items: stretch;
        gap: 12px;
        width: 100%;
        max-width: var(--max-content-width);
    }

    .shop-profile-edit__cancel {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--spe-muted);
        text-decoration: none;
        border: 1px solid var(--spe-line);
        background: #ffffff;
        cursor: pointer;
        transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
    }
    .shop-profile-edit__cancel:hover {
        color: var(--spe-ink);
        border-color: var(--spe-gold);
    }

    /* Primary CTA レシピ準拠（DESIGN.md §10） */
    .shop-profile-edit__submit {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 50px;
        padding: 12px 16px;
        border: 0;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 800;
        cursor: pointer;
        color: var(--on-accent-strong, #ffffff);
        background: linear-gradient(135deg, var(--accent-grad-from, #a78bfa), var(--accent-grad-to, #7c3aed));
        box-shadow:
            0 6px 14px rgba(0, 0, 0, 0.45),
            inset 0 1px 0 rgba(255, 255, 255, 0.20),
            inset 0 -1px 0 rgba(0, 0, 0, 0.18);
        transition: transform 0.12s ease;
    }
    .shop-profile-edit__submit:active {
        transform: scale(0.97);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.45), inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }
</style>
@endpush

@push('scripts')
<script src="https://yubinbango.github.io/yubinbango/yubinbango.js" charset="UTF-8"></script>
<script src="{{ asset('assets/js/form-enhance.js') }}?v=20260913-uiux"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var lastCb = document.querySelector('.js-biz-close-last');
    var closeInp = document.querySelector('.js-biz-close-time');
    if (lastCb && closeInp) {
        function syncBizClose() {
            closeInp.disabled = lastCb.checked;
            if (lastCb.checked) closeInp.value = '';
        }
        lastCb.addEventListener('change', syncBizClose);
        syncBizClose();
    }
    var form = document.getElementById('shop-profile-edit-form');
    if (form && closeInp) {
        form.addEventListener('submit', function () {
            if (closeInp.disabled) {
                closeInp.disabled = false;
                closeInp.value = '';
            }
        });
    }
    var stAdd = document.getElementById('shop-station-add');
    var stList = document.getElementById('shop-stations-list');
    var suggestUrl = @json(route('shop.profile.suggest-stations'));
    var prefInput = document.getElementById('pref');
    var cityInput = document.getElementById('city');
    var addrInput = document.getElementById('addr');
    var buildingInput = document.getElementById('building');
    var addr1Input = document.getElementById('addr1');
    var stationFetchBtn = document.getElementById('shop-station-fetch');
    var suggestSeq = 0;
    function collectAddressPayload() {
        return {
            pref: prefInput ? prefInput.value : '',
            city: cityInput ? cityInput.value : '',
            addr: addrInput ? addrInput.value : '',
            building: buildingInput ? buildingInput.value : '',
            addr1: addr1Input ? addr1Input.value : ''
        };
    }
    function buildStationRow(value, idx) {
        var row = document.createElement('div');
        row.className = 'shop-profile-edit__station-row';
        row.setAttribute('data-station-row', '');
        row.innerHTML =
            '<button type="button" class="shop-profile-edit__station-drag" aria-label="並び替え"><i class="fas fa-grip-vertical" aria-hidden="true"></i></button>' +
            '<div class="shop-profile-edit__station-body">' +
                '<span class="shop-profile-edit__station-badge">最寄り <span class="js-station-index">' + (idx + 1) + '</span> <span class="is-main-pill">MAIN</span></span>' +
                '<input type="text" name="stations[]" class="shop-profile-edit__input" placeholder="例：六本木駅 徒歩3分">' +
                '<button type="button" class="shop-profile-edit__station-set-main" data-station-set-main aria-label="この駅をメインに設定">' +
                    '<i class="fas fa-star" aria-hidden="true"></i>' +
                    '<span class="shop-profile-edit__station-set-main-label">メインに設定</span>' +
                '</button>' +
            '</div>' +
            '<button type="button" class="shop-profile-edit__station-remove" aria-label="削除"><i class="fas fa-times" aria-hidden="true"></i></button>';
        var input = row.querySelector('input[name="stations[]"]');
        if (input) input.value = value || '';
        return row;
    }
    function refreshStationIndexes() {
        if (!stList) return;
        var rows = stList.querySelectorAll('[data-station-row]');
        rows.forEach(function (r, i) {
            var idx = r.querySelector('.js-station-index');
            if (idx) idx.textContent = String(i + 1);
        });
    }
    function renderStationRows(lines) {
        if (!stList) return;
        var rows = Array.isArray(lines) ? lines.filter(function (v) { return (v || '').trim() !== ''; }) : [];
        if (rows.length === 0) return;
        stList.innerHTML = '';
        rows.forEach(function (line, i) {
            stList.appendChild(buildStationRow(line, i));
        });
        refreshStationIndexes();
    }
    function suggestStations() {
        if (!stList || !suggestUrl) return Promise.resolve();
        var payload = collectAddressPayload();
        var fullAddress = [payload.pref, payload.city, payload.addr, payload.building, payload.addr1].join('').trim();
        if (fullAddress === '') return Promise.resolve();
        var seq = ++suggestSeq;
        return fetch(suggestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token())
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (json) {
            if (!json || seq !== suggestSeq) return;
            renderStationRows(json.stations || []);
        })
        .catch(function () {});
    }
    if (stationFetchBtn) {
        stationFetchBtn.addEventListener('click', function () {
            if (stationFetchBtn.disabled) return;
            stationFetchBtn.disabled = true;
            stationFetchBtn.textContent = '取得中...';
            Promise.resolve()
                .then(suggestStations)
                .finally(function () {
                    stationFetchBtn.disabled = false;
                    stationFetchBtn.textContent = '住所から取得';
                });
        });
    }

    if (stAdd && stList) {
        stAdd.addEventListener('click', function () {
            var n = stList.querySelectorAll('input[name="stations[]"]').length;
            stList.appendChild(buildStationRow('', n));
            refreshStationIndexes();
        });
    }

    // 削除ボタン＋「メインに設定」ボタン（イベント委譲）
    if (stList) {
        stList.addEventListener('click', function (e) {
            // メインに設定：押下した行を最上段へ
            var mainBtn = e.target.closest && e.target.closest('[data-station-set-main]');
            if (mainBtn) {
                var row = mainBtn.closest('[data-station-row]');
                if (row && row.parentNode === stList && row !== stList.firstElementChild) {
                    stList.insertBefore(row, stList.firstElementChild);
                    refreshStationIndexes();
                }
                return;
            }

            // 削除
            var rmBtn = e.target.closest && e.target.closest('.shop-profile-edit__station-remove');
            if (!rmBtn) return;
            var rmRow = rmBtn.closest('[data-station-row]');
            if (!rmRow) return;
            // 最後の1行は中身を消すだけにする（フォーム送信時に行が消えるとレイアウトが崩れるため）
            var rows = stList.querySelectorAll('[data-station-row]');
            if (rows.length <= 1) {
                var input = rmRow.querySelector('input[name="stations[]"]');
                if (input) input.value = '';
            } else {
                rmRow.remove();
            }
            refreshStationIndexes();
        });
    }

    // SortableJS で並び替えを有効化（一番上が「メイン最寄り駅」として一覧に採用される）
    if (stList && window.Sortable) {
        new Sortable(stList, {
            handle: '.shop-profile-edit__station-drag',
            animation: 150,
            ghostClass: 'is-ghost',
            dragClass: 'is-dragging',
            onEnd: refreshStationIndexes,
        });
    }
    refreshStationIndexes();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
@endpush

@section('content')
<div class="shop-profile-edit animate-fadeIn">
    <div class="shop-profile-edit__shell">
        <header class="shop-profile-edit__top">
            <div class="shop-profile-edit__title-block">
                <h1 class="shop-profile-edit__title-en">EDIT PROFILE</h1>
                <p class="shop-profile-edit__title-sub">Shop Information</p>
            </div>
        </header>

        @if(session('message'))
            <p class="shop-profile-edit__flash" role="status" data-flash-toast="success">{{ session('message') }}</p>
        @endif

        @if($errors->any())
            <div class="shop-profile-edit__errors" role="alert">
                入力内容を確認してください。
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- セクションアンカーナビ（2026-08-01 Phase 3）：各セクションへ即ジャンプ --}}
        <nav class="shop-profile-edit__anchor-nav" aria-label="セクション">
            <a href="#spe-sec-basic" data-form-guard-bypass><i class="fas fa-info-circle"></i>基本</a>
            <a href="#spe-sec-loc"   data-form-guard-bypass><i class="fas fa-map-marker-alt"></i>位置</a>
            <a href="#spe-sec-hours" data-form-guard-bypass><i class="far fa-clock"></i>営業時間</a>
            <a href="#spe-sec-st"    data-form-guard-bypass><i class="fas fa-train"></i>最寄り駅</a>
            <a href="#spe-sec-tags"  data-form-guard-bypass><i class="fas fa-tags"></i>タグ</a>
        </nav>

        <form id="shop-profile-edit-form" action="{{ route('shop.profile.update') }}" method="POST" class="h-adr shop-profile-edit__form"
              data-form-guard data-completion-meter>
            @csrf
            <span class="p-country-name" style="display:none;">Japan</span>

            <section aria-labelledby="spe-sec-basic">
                <h2 id="spe-sec-basic" class="shop-profile-edit__section-title">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Basic Information
                </h2>

                <div class="shop-profile-edit__field">
                    <label class="shop-profile-edit__label" for="shop_name">店舗名</label>
                    <input
                        id="shop_name"
                        type="text"
                        name="shop_name"
                        class="shop-profile-edit__input"
                        value="{{ old('shop_name', $shopData['shop_name']) }}"
                        placeholder="店舗名を入力"
                        autocomplete="organization"
                        required
                    >
                </div>

                <p class="shop-profile-edit__hint" style="margin:0 0 16px;">店舗の「ひとこと」はマイページから編集できます。</p>

                <div class="shop-profile-edit__field">
                    <label class="shop-profile-edit__label" for="shop-industry-select">業種（1つ選択）</label>
                    @php
                        $selectedIndustryIds = collect(old('industry_ids', $shopData['industry_ids'] ?? []))
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $selectedIndustryId = $selectedIndustryIds[0] ?? null;
                    @endphp
                    {{-- 1つ選択はプルダウンに統一（入力コンポーネント規約）。name は既存API互換で industry_ids[] のまま --}}
                    <select id="shop-industry-select" name="industry_ids[]" class="shop-profile-edit__input">
                        <option value="">選択してください</option>
                        @foreach(($masters['industries'] ?? []) as $industry)
                            <option value="{{ $industry->id }}" {{ (int) $industry->id === (int) $selectedIndustryId ? 'selected' : '' }}>{{ $industry->name }}</option>
                        @endforeach
                    </select>
                    <p class="shop-profile-edit__hint">朝キャバとキャバクラ等、両方の営業形態を持つ店舗はメインの業種を 1 つ選んでください。</p>
                </div>

                <div class="shop-profile-edit__field">
                    <label class="shop-profile-edit__label" for="industry_label">業種名（表示用・自由入力）</label>
                    <input
                        id="industry_label"
                        type="text"
                        name="industry_label"
                        class="shop-profile-edit__input"
                        value="{{ old('industry_label', $shopData['industry_label'] ?? '') }}"
                        maxlength="60"
                        placeholder="例：高級ラウンジ／会員制クラブ／カジュアルキャバ等"
                    >
                    <p class="shop-profile-edit__hint">店舗ページや一覧で表示される業種名です。空欄の場合は上で選んだ業種名（例：キャバクラ）が表示されます。検索の絞り込みには上の業種カテゴリが使われます。</p>
                </div>
            </section>

            <section aria-labelledby="spe-sec-loc">
                <h2 id="spe-sec-loc" class="shop-profile-edit__section-title">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    Location
                </h2>

                <div class="shop-profile-edit__field">
                    <label class="shop-profile-edit__label" for="zip">郵便番号</label>
                    <input
                        id="zip"
                        type="text"
                        name="zip"
                        class="shop-profile-edit__input shop-profile-edit__input--mono p-postal-code"
                        data-postal-code
                        maxlength="8"
                        pattern="[0-9-]*"
                        value="{{ old('zip', $shopData['zip']) }}"
                        inputmode="numeric"
                        autocomplete="postal-code"
                        placeholder="例：106-0032"
                    >
                    <p class="shop-profile-edit__hint">※ハイフン有り・無しどちらでも入力でき、住所が自動補完されます。</p>
                </div>

                <div class="shop-profile-edit__grid-2">
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="pref">都道府県</label>
                        <div class="shop-profile-edit__select-wrap">
                            <select id="pref" name="pref" class="shop-profile-edit__select p-region" required>
                                <option value="">選択してください</option>
                                @foreach ($prefOptions as $pref)
                                    <option value="{{ $pref }}" {{ old('pref', $shopData['pref']) === $pref ? 'selected' : '' }}>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="city">市区町村</label>
                        <input
                            id="city"
                            type="text"
                            name="city"
                            class="shop-profile-edit__input p-locality"
                            value="{{ old('city', $shopData['city']) }}"
                            autocomplete="address-level2"
                            placeholder="例：港区六本木"
                        >
                    </div>
                </div>

                @if(!empty($hasProfileAddr))
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="addr">番地・丁目</label>
                        <input
                            id="addr"
                            type="text"
                            name="addr"
                            class="shop-profile-edit__input p-street-address"
                            value="{{ old('addr', $shopData['addr'] ?? '') }}"
                            autocomplete="street-address"
                            placeholder="例：7-12-34"
                        >
                    </div>
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="building">建物名・部屋番号</label>
                        <input
                            id="building"
                            type="text"
                            name="building"
                            class="shop-profile-edit__input"
                            value="{{ old('building', $shopData['building'] ?? '') }}"
                            placeholder="例：〇〇ビル 2F"
                        >
                    </div>
                @else
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="addr1">以降の住所・ビル名</label>
                        <input
                            id="addr1"
                            type="text"
                            name="addr1"
                            class="shop-profile-edit__input p-street-address"
                            value="{{ old('addr1', $shopData['addr1']) }}"
                            autocomplete="address-line1"
                            placeholder="例：7-12-34 〇〇ビル 2F"
                        >
                    </div>
                @endif

                @if(!empty($hasProfileTel))
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="tel">電話番号</label>
                        <input
                            id="tel"
                            type="tel"
                            name="tel"
                            class="shop-profile-edit__input shop-profile-edit__input--mono"
                            value="{{ old('tel', $shopData['tel'] ?? '') }}"
                            autocomplete="tel"
                            placeholder="例：03-1234-5678"
                        >
                    </div>
                @endif
            </section>

            @if(!empty($hasProfileBusinessHours))
            <section aria-labelledby="spe-sec-hours">
                <h2 id="spe-sec-hours" class="shop-profile-edit__section-title">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    Business Hours（店舗の営業時間）
                </h2>
                <p class="shop-profile-edit__hint" style="margin-top:0;">求人のシフト時間とは別に、店舗の営業時間を登録できます（未設定の場合は表示されません）。</p>

                @php
                    $bizOpen = old('business_open', $shopData['business_open'] ?? '');
                    $bizClose = old('business_close', $shopData['business_close'] ?? '');
                    $bizLastRaw = old('business_close_last', !empty($shopData['business_close_last']) ? '1' : '0');
                    $bizLast = $bizLastRaw === '1' || $bizLastRaw === 1 || $bizLastRaw === true;
                @endphp
                <div class="shop-profile-edit__shift-grid">
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="business_open">開店（open_time）</label>
                        <input type="time" step="60" name="business_open" id="business_open" class="shop-profile-edit__input js-biz-open"
                               value="{{ $bizOpen }}">
                    </div>
                    <div class="shop-profile-edit__field">
                        <label class="shop-profile-edit__label" for="business_close">閉店（close_time）</label>
                        <input type="time" step="60" name="business_close" id="business_close" class="shop-profile-edit__input js-biz-close-time"
                               value="{{ $bizClose }}" @if($bizLast) disabled @endif>
                        <label class="shop-profile-edit__check-line">
                            <input type="checkbox" name="business_close_last" value="1" class="js-biz-close-last" id="business_close_last" {{ $bizLast ? 'checked' : '' }}>
                            <span>LAST（終電まで）</span>
                        </label>
                    </div>
                </div>
                <p class="shop-profile-edit__hint">※閉店が翌日にまたがる場合は、翌日の時刻で入力してください（例：開店 20:00／閉店 04:00）。「LAST」のときは閉店時刻は保存されません。</p>
            </section>
            @endif

            @if(!empty($hasShopStationsTable))
            <section aria-labelledby="spe-sec-st">
                <h2 id="spe-sec-st" class="shop-profile-edit__section-title">
                    <i class="fas fa-train" aria-hidden="true"></i>
                    最寄り駅
                    <span class="shop-profile-edit__section-title-actions">
                        <button type="button" class="shop-profile-edit__station-add" id="shop-station-fetch" aria-label="住所から最寄り駅を取得">住所から取得</button>
                    </span>
                </h2>
                <p class="shop-profile-edit__hint" style="margin-top:0;">複数行で登録できます（例：六本木駅 徒歩3分）。</p>
                <p class="shop-profile-edit__station-info">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <span>各行の「★ メインに設定」を押すか、ハンドル <i class="fas fa-grip-vertical" aria-hidden="true"></i> をドラッグして一番上に持ってきた最寄り駅が、ホーム／検索の一覧に表示されます。</span>
                </p>
                <div class="shop-profile-edit__station-list" id="shop-stations-list">
                    @foreach(old('stations', $shopData['stations'] ?? ['']) as $i => $stLine)
                        <div class="shop-profile-edit__station-row" data-station-row>
                            <button type="button" class="shop-profile-edit__station-drag" aria-label="並び替え"><i class="fas fa-grip-vertical" aria-hidden="true"></i></button>
                            <div class="shop-profile-edit__station-body">
                                <span class="shop-profile-edit__station-badge">
                                    最寄り <span class="js-station-index">{{ $i + 1 }}</span>
                                    <span class="is-main-pill">MAIN</span>
                                </span>
                                <input id="station-{{ $i }}" type="text" name="stations[]" class="shop-profile-edit__input"
                                       value="{{ $stLine }}" placeholder="例：六本木駅 徒歩3分">
                                <button type="button" class="shop-profile-edit__station-set-main" data-station-set-main aria-label="この駅をメインに設定">
                                    <i class="fas fa-star" aria-hidden="true"></i>
                                    <span class="shop-profile-edit__station-set-main-label">メインに設定</span>
                                </button>
                            </div>
                            <button type="button" class="shop-profile-edit__station-remove" aria-label="削除"><i class="fas fa-times" aria-hidden="true"></i></button>
                        </div>
                    @endforeach
                </div>
                <div class="shop-profile-edit__station-actions">
                    <button type="button" class="shop-profile-edit__station-add" id="shop-station-add" aria-label="最寄り駅の行を追加">＋ 行を追加</button>
                </div>
            </section>
            @endif


            <section aria-labelledby="spe-sec-tags">
                <h2 id="spe-sec-tags" class="shop-profile-edit__section-title">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    Shop Tags
                </h2>

                <div class="shop-profile-edit__field">
                    <span class="shop-profile-edit__label">店内の雰囲気・客層</span>
                    <div class="shop-profile-edit__chips">
                        @foreach(($masters['atmosphere'] ?? []) as $tag)
                            <label class="shop-profile-edit__chip">
                                <input type="checkbox" name="atmosphere_tag_ids[]" value="{{ $tag->id }}"
                                    {{ in_array((int) $tag->id, old('atmosphere_tag_ids', $shopData['atmosphere_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                <span>{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="shop-profile-edit__hint">来店客やお店のムードを表すタグを選択してください。</p>
                </div>

                <div class="shop-profile-edit__field">
                    <span class="shop-profile-edit__label">設備・アクセス</span>
                    <div class="shop-profile-edit__chips">
                        @foreach(($masters['facility'] ?? []) as $tag)
                            <label class="shop-profile-edit__chip">
                                <input type="checkbox" name="facility_tag_ids[]" value="{{ $tag->id }}"
                                    {{ in_array((int) $tag->id, old('facility_tag_ids', $shopData['facility_tag_ids'] ?? []), true) ? 'checked' : '' }}>
                                <span>{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="shop-profile-edit__hint">店舗設備や駅からのアクセス等を表すタグを選択してください。</p>
                </div>
            </section>
        </form>

        <div class="shop-profile-edit__actions">
            <div class="shop-profile-edit__actions-inner">
                <a href="{{ route('shop.mypage.index') }}" class="shop-profile-edit__cancel">キャンセル</a>
                <button type="submit" form="shop-profile-edit-form" class="shop-profile-edit__submit">
                    <i class="fas fa-check" aria-hidden="true"></i>
                    プロフィールを更新する
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
