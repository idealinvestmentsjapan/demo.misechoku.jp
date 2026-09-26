{{-- 探索拠点（現在地 or パスポート）の表示＋切替トリガー
     使い方:
        @include('layouts.parts.location-pill')                            -- フル幅ピル（既定）
        @include('layouts.parts.location-pill', ['variant' => 'icon'])     -- ヘッダー用の小さいアイコンボタン
        @include('layouts.parts.location-pill', ['variant' => 'floating']) -- SWIPE カード左上のフローティングピル

     呼び出し元のレイアウト／コントローラから $userLocation 変数（UserLocationService::getActiveLocation の結果 or null）が渡されることを想定。
     渡されない場合は app() 経由でその場で解決する。
     - キャスト側: 現在地／エリア指定（パスポート）／プロフィール住所を保存できるモーダルを開く
     - 店舗側: 拠点は店舗住所固定（仕様）のため、説明＋検索半径のみのモーダルを開く

     同じページに複数のトリガー（検索画面のピル + SWIPE ヘッダーのアイコン等）が並ぶことを許容するため、
     トリガーは data 属性 [data-location-open] で識別し、モーダル HTML/CSS は @once で 1 回だけ描画する。
--}}
@php
    $locationService = app(\App\Services\UserLocationService::class);
    if (!isset($userLocation)) {
        $userLocation = $locationService->getActiveLocation();
    }
    $locationMaxKm = (int) ($locationService->getEffectiveMaxDistanceKm() ?? 0);
    $isCastSide = request()->is('cast/*');
    $modeLabel = match ($userLocation['mode'] ?? null) {
        'current' => '現在地',
        'passport' => '指定位置',
        'profile' => $isCastSide ? 'プロフィール住所' : '店舗住所',
        default => '未設定',
    };
    // profile モードは label 自体が「プロフィール住所／店舗住所」なのでチップと重複させない
    $showModeChip = in_array($userLocation['mode'] ?? null, ['current', 'passport'], true);

    // Cast side: profile address preview for the "use profile address" shortcut
    $locationProfileAddress = '';
    if ($isCastSide) {
        $locationProfileSettings = $locationService->loadProfileSettings();
        $locationProfileAddress = (string) ($locationProfileSettings['profile_address'] ?? '');
    }

    $locationDistanceOptions = \App\Services\UserLocationService::DISTANCE_OPTIONS_KM;
    // One-tap presets for well-known nightlife areas (geocoded server-side)
    $locationAreaPresets = ['新宿', '渋谷', '六本木', '銀座', '池袋', '中洲', 'すすきの'];

    $locationVariant = $variant ?? 'pill';
@endphp

@if($locationVariant === 'icon')
    {{-- Header-slot compact trigger. Fits alongside existing .header-icon-btn siblings. --}}
    <button type="button"
            class="header-icon-btn header-location-btn {{ $userLocation ? '' : 'is-unset has-badge' }}"
            data-location-open
            aria-haspopup="dialog"
            aria-controls="location-modal-overlay"
            aria-label="探索拠点の設定{{ $userLocation ? '（' . ($userLocation['label'] ?? $modeLabel) . '）' : '（未設定）' }}">
        <i class="fas fa-location-dot header-icon-btn__ico" aria-hidden="true"></i>
        @if(!$userLocation)
            <span class="header-badge is-accent" aria-hidden="true">!</span>
        @endif
    </button>
@elseif($locationVariant === 'floating')
    {{-- SWIPE カード左上のフローティングピル。現在のエリア名と半径をラベル表示し、
         タップで拠点変更モーダルを開く。ヘッダーアイコンでは「何のアイコンか」「今どの
         エリアで検索しているか」が一目で分からなかったため、テキスト付きで置き換え。 --}}
    <button type="button"
            class="home-location-fab {{ $userLocation ? 'is-set' : 'is-unset' }}"
            data-location-open
            aria-haspopup="dialog"
            aria-controls="location-modal-overlay"
            aria-label="探索拠点の設定{{ $userLocation ? '（' . ($userLocation['label'] ?? $modeLabel) . '）' : '（未設定）' }}">
        <i class="fas fa-location-dot home-location-fab__ico" aria-hidden="true"></i>
        @if($userLocation)
            <span class="home-location-fab__label">{{ !empty($userLocation['label']) ? $userLocation['label'] : $modeLabel }}</span>
            @if($locationMaxKm > 0)
                <span class="home-location-fab__radius">{{ $locationMaxKm }}km</span>
            @endif
        @else
            <span class="home-location-fab__label home-location-fab__label--unset">エリア未設定</span>
        @endif
        <i class="fas fa-chevron-down home-location-fab__chev" aria-hidden="true"></i>
    </button>
@else
    <div class="location-pill-wrap">
        <button type="button"
                class="location-pill {{ $userLocation ? 'is-set' : 'is-unset' }}"
                data-location-open
                aria-haspopup="dialog"
                aria-controls="location-modal-overlay">
            @if($userLocation)
                <i class="fas fa-location-dot location-pill__icon location-pill__icon--set" aria-hidden="true"></i>
                @if($showModeChip)
                    <span class="location-pill__mode">{{ $modeLabel }}</span>
                @endif
                <span class="location-pill__label">{{ !empty($userLocation['label']) ? $userLocation['label'] : $modeLabel }}</span>
                @if($locationMaxKm > 0)
                    <span class="location-pill__radius">半径{{ $locationMaxKm }}km</span>
                @endif
                <span class="location-pill__cta">{{ $isCastSide ? '変更' : '詳細' }}</span>
            @else
                <i class="fas fa-location-dot location-pill__icon location-pill__icon--unset" aria-hidden="true"></i>
                <span class="location-pill__label location-pill__label--unset">位置情報が未設定です（距離の表示・並び替えが無効）</span>
                <span class="location-pill__cta location-pill__cta--unset">設定する</span>
            @endif
            <i class="fas fa-chevron-right location-pill__chev" aria-hidden="true"></i>
        </button>
    </div>
@endif

@once
{{-- モーダル（同じページに1つだけ） --}}
<div id="location-modal-overlay" class="location-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="探索拠点の設定">
    <div class="location-modal">
        <button type="button" class="location-modal__close js-location-close" aria-label="閉じる">
            <i class="fas fa-xmark"></i>
        </button>
        <h2 class="location-modal__title">{{ $isCastSide ? '探索拠点を設定' : '探索拠点について' }}</h2>

        {{-- Current origin summary shown first so users always know the active state --}}
        <div class="location-modal__current">
            @if($userLocation)
                <i class="fas fa-location-dot" aria-hidden="true"></i>
                <span>いまの拠点：<strong>{{ $modeLabel }}</strong></span>
                @if(!empty($userLocation['label']) && $userLocation['label'] !== $modeLabel)
                    <span class="location-modal__current-label">{{ $userLocation['label'] }}</span>
                @endif
                <span class="location-modal__current-radius">{{ $locationMaxKm > 0 ? '半径' . $locationMaxKm . 'km' : '距離制限なし' }}</span>
            @else
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <span>拠点が未設定です。距離の表示・並び替えが無効になっています。</span>
            @endif
        </div>

        @if($isCastSide)
            <div class="location-modal__section">
                <h3 class="location-modal__section-title"><i class="fas fa-crosshairs" aria-hidden="true"></i> 現在地から探す</h3>
                <button type="button" id="location-use-current" class="location-modal__btn-primary">
                    <i class="fas fa-location-crosshairs"></i> いまいる場所を拠点にする
                </button>
                <p class="location-modal__hint">ブラウザの位置情報の許可が必要です。</p>
            </div>

            <div class="location-modal__divider"><span>または</span></div>

            <div class="location-modal__section">
                <h3 class="location-modal__section-title"><i class="fas fa-map-location-dot" aria-hidden="true"></i> エリア・駅名で探す（パスポートモード）</h3>
                <p class="location-modal__hint location-modal__hint--top">住みたい街や働きたいエリアを拠点にできます。人気エリアはワンタップで設定。</p>
                <div class="location-modal__chips" id="location-area-presets">
                    @foreach($locationAreaPresets as $area)
                        <button type="button" class="location-modal__chip" data-area="{{ $area }}">{{ $area }}</button>
                    @endforeach
                </div>
                <form id="location-passport-form" class="location-modal__form" autocomplete="off">
                    <div class="location-modal__suggest-wrap">
                        <input type="text" name="address" id="location-passport-input" class="location-modal__input"
                               placeholder="住所・駅名を入力（例: 港区六本木 / 新宿駅）"
                               autocomplete="off" required
                               role="combobox" aria-expanded="false" aria-controls="location-suggest-list" aria-autocomplete="list">
                        <ul id="location-suggest-list" class="location-modal__suggest" role="listbox" hidden></ul>
                    </div>
                    <button type="submit" class="location-modal__btn-secondary">
                        <i class="fas fa-map-pin"></i> この場所を拠点にする
                    </button>
                </form>
            </div>

            @if($locationProfileAddress !== '')
                <div class="location-modal__section">
                    <button type="button" id="location-use-profile" class="location-modal__btn-ghost-wide">
                        <i class="fas fa-house"></i> プロフィール住所（{{ \Illuminate\Support\Str::limit($locationProfileAddress, 20) }}）を拠点にする
                    </button>
                </div>
            @endif
        @else
            {{-- 店舗側：拠点は店舗住所に固定（キャスト側のようなモード切替は無い仕様） --}}
            <p class="location-modal__lead">
                店舗の探索拠点は<strong>登録済みの店舗住所</strong>に固定されています。
                キャストとの距離はこの住所を起点に表示されます。
            </p>
            @if(!$userLocation)
                <p class="location-modal__lead">
                    店舗住所（緯度経度）が未登録のため、距離の表示・並び替えが無効になっています。
                    プロフィール編集から住所を登録してください。
                </p>
                @if(Route::has('shop.profile.edit'))
                    <a href="{{ route('shop.profile.edit') }}" class="location-modal__btn-primary" style="text-decoration:none;">
                        <i class="fas fa-pen"></i> プロフィール編集で住所を登録
                    </a>
                @endif
            @endif
        @endif

        @if($userLocation || $isCastSide)
            <div class="location-modal__section location-modal__section--radius">
                <h3 class="location-modal__section-title"><i class="fas fa-circle-dot" aria-hidden="true"></i> 検索半径（タップで即反映）</h3>
                <div class="location-modal__radius" id="location-radius-chips">
                    @foreach($locationDistanceOptions as $km)
                        <button type="button"
                                class="location-modal__radius-chip {{ $locationMaxKm === (int) $km ? 'is-active' : '' }}"
                                data-km="{{ $km }}">
                            {{ $km === 0 ? '制限なし' : $km . 'km' }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if($isCastSide && in_array($userLocation['mode'] ?? null, ['current', 'passport'], true))
            <div class="location-modal__footer">
                <button type="button" id="location-clear" class="location-modal__btn-ghost">
                    <i class="fas fa-rotate-left"></i> 解除してプロフィール住所に戻す
                </button>
            </div>
        @endif

        <p id="location-modal-message" class="location-modal__message" hidden></p>
    </div>
</div>


{{-- モーダルのスタイル同梱（2026-07-20）：
     従来は旧 app.css / search.css に定義があり、検索ページ以外（サイドメニュー経由）で
     開くと未スタイルで崩れていた。コンポーネントに CSS を同梱して全画面で成立させる。
     ライトモード補正は light-theme.css（body.theme-light .location-modal*）が上書きする。 --}}
<style id="location-modal-css-bundled">
.location-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.78);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    /* Anchor to the top so the modal never spills below the viewport when content grows.
       Vertical scroll happens inside the modal via its own overflow-y:auto. */
    align-items: flex-start;
    justify-content: center;
    /* Respect iOS safe areas so the modal's top/bottom aren't cut by notch / home indicator */
    padding:
        max(12px, env(safe-area-inset-top, 0px))
        12px
        max(12px, env(safe-area-inset-bottom, 0px));
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s ease;
    overscroll-behavior: contain;
    box-sizing: border-box;
}
.location-modal-overlay[aria-hidden="false"] {
    opacity: 1;
    pointer-events: auto;
}
.location-modal {
    position: relative;
    width: min(420px, 100%);
    /* Constrain to the overlay's flex content area. `100%` refers to the flex container
       (the overlay), which already deducts safe-area insets via its padding. This is more
       robust than a manual dvh calc because it always tracks the overlay's actual size. */
    max-height: 100%;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    background: linear-gradient(180deg, var(--color-sub), var(--dark-bg));
    border: 1px solid var(--color-border-strong);
    border-radius: 18px;
    padding: 18px 20px 16px;
    color: var(--color-text-header);
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.7);
    box-sizing: border-box;
    /* Guarantee the last button remains reachable above iOS bottom safe area even when the
       modal's own scroll ends flush at the container bottom. */
    scroll-padding-bottom: 24px;
}
/* Short viewports (landscape phones, small tablets in split view): tighten paddings so
   the primary sections stay reachable without excessive scrolling. */
@media (max-height: 640px) {
    .location-modal { padding: 14px 16px 12px; }
    .location-modal__title { font-size: 0.98rem; margin-bottom: 4px; }
    .location-modal__lead { margin-bottom: 10px; }
    .location-modal__current { margin-bottom: 8px; padding: 7px 10px; font-size: 0.78rem; }
    .location-modal__section { margin-bottom: 8px; }
    .location-modal__section-title { margin-bottom: 6px; }
    .location-modal__divider { margin: 6px 0; }
    .location-modal__hint { font-size: 0.66rem; }
    .location-modal__btn-primary,
    .location-modal__btn-secondary { padding: 9px 12px; font-size: 0.86rem; }
    .location-modal__input { height: 38px; font-size: 0.88rem; }
    .location-modal__chip { padding: 5px 10px; font-size: 0.76rem; }
    .location-modal__radius-chip { padding: 5px 10px; font-size: 0.74rem; }
}
.location-modal__close {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 0;
    background: rgba(255, 255, 255, 0.08);
    color: var(--color-text-muted);
    cursor: pointer;
}
.location-modal__close:hover { background: rgba(255, 255, 255, 0.16); color: var(--color-text-header); }
.location-modal__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text-header);
    margin: 0 0 8px;
    font-family: var(--font-serif);
    letter-spacing: 0.04em;
}
.location-modal__lead {
    font-size: 0.82rem;
    color: var(--color-text-muted);
    line-height: 1.7;
    margin: 0 0 16px;
}
.location-modal__section { margin-bottom: 14px; }
.location-modal__section--radius {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px dashed var(--color-border);
}
.location-modal__section-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--gold);
    margin: 0 0 8px;
    letter-spacing: 0.04em;
}
.location-modal__section-title i { margin-right: 2px; }
.location-modal__btn-primary,
.location-modal__btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.92rem;
    cursor: pointer;
    border: 0;
}
.location-modal__btn-primary {
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    color: #1a1206;
}
.location-modal__btn-primary:hover { filter: brightness(1.05); }
.location-modal__btn-secondary {
    background: transparent;
    border: 1px solid var(--color-border-strong);
    color: var(--color-text-header);
}
.location-modal__btn-secondary:hover { background: rgba(168, 85, 247, 0.08); }
.location-modal__btn-ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 6px 12px;
    color: var(--color-text-muted);
    font-size: 0.78rem;
    cursor: pointer;
}
.location-modal__btn-ghost:hover { color: var(--color-text-header); border-color: rgba(255, 255, 255, 0.35); }
.location-modal__btn-ghost-wide {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid var(--color-border);
    color: var(--color-text-header);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
}
.location-modal__btn-ghost-wide:hover { background: rgba(168, 85, 247, 0.1); border-color: var(--color-border-strong); }
.location-modal__hint {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    margin: 6px 0 0;
}
.location-modal__hint--top { margin: 0 0 8px; }
.location-modal__divider {
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.74rem;
    margin: 12px 0;
    position: relative;
}
.location-modal__divider::before,
.location-modal__divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 38%;
    height: 1px;
    background: var(--color-border);
}
.location-modal__divider::before { left: 0; }
.location-modal__divider::after { right: 0; }
.location-modal__form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
}
.location-modal__input {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid var(--color-border-strong);
    background: rgba(255, 255, 255, 0.06);
    color: var(--color-text-header);
    font-size: 0.92rem;
}
.location-modal__input:focus {
    outline: 2px solid rgba(168, 85, 247, 0.45);
    outline-offset: 1px;
    background: rgba(255, 255, 255, 0.1);
}
/* Area preset chips (one-tap passport) */
.location-modal__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.location-modal__chip {
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--color-border-strong);
    background: rgba(255, 255, 255, 0.05);
    color: var(--color-text-header);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.location-modal__chip:hover { background: rgba(168, 85, 247, 0.14); border-color: var(--gold); }
.location-modal__chip:disabled { opacity: 0.5; cursor: wait; }
/* Address autosuggest dropdown */
.location-modal__suggest-wrap { position: relative; }
.location-modal__suggest {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 20;
    margin: 0;
    padding: 4px;
    list-style: none;
    background: var(--dark-bg, #17131f);
    border: 1px solid var(--color-border-strong);
    border-radius: 10px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55);
    max-height: 220px;
    overflow-y: auto;
}
.location-modal__suggest-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 9px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--color-text-header);
    font-size: 0.84rem;
    text-align: left;
    cursor: pointer;
}
.location-modal__suggest-item i { color: var(--gold); font-size: 0.76rem; flex-shrink: 0; }
.location-modal__suggest-item:hover,
.location-modal__suggest-item.is-focused { background: rgba(168, 85, 247, 0.14); }
.location-modal__suggest-empty {
    padding: 9px 10px;
    color: var(--color-text-muted);
    font-size: 0.8rem;
}
/* Radius chips (tap to apply instantly) */
.location-modal__radius {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.location-modal__radius-chip {
    padding: 6px 11px;
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.location-modal__radius-chip:hover { color: var(--color-text-header); border-color: var(--color-border-strong); }
.location-modal__radius-chip.is-active {
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    border-color: transparent;
    color: #1a1206;
}
.location-modal__radius-chip:disabled { opacity: 0.5; cursor: wait; }
.location-modal__current {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(168, 85, 247, 0.06);
    border: 1px solid var(--color-border);
    font-size: 0.82rem;
}
.location-modal__current i { color: var(--gold); }
.location-modal__current-label { color: var(--color-text-header); font-weight: 700; }
.location-modal__current-radius {
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--color-text-muted);
    white-space: nowrap;
}
.location-modal__footer {
    margin-top: 12px;
    text-align: center;
}
.location-modal__message {
    margin-top: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.82rem;
    background: var(--color-danger-bg);
    border: 1px solid rgba(248, 113, 113, 0.4);
    color: var(--color-danger);
}
.location-modal__message.is-success {
    background: var(--color-success-bg);
    border-color: rgba(74, 222, 128, 0.4);
    color: var(--color-success);
}
/* Header-slot compact trigger (variant=icon). Displayed inline with .header-icon-btn siblings.
   Adds a small dot when the location is unset so users notice the setup is pending. */
.header-location-btn { position: relative; }
.header-location-btn .header-badge.is-accent {
    position: absolute;
    top: 4px;
    right: 4px;
    min-width: 6px;
    height: 6px;
    padding: 0;
    border-radius: 50%;
    background: #e15c5c;
    color: transparent;
    font-size: 0;
    line-height: 0;
    border: 1.5px solid var(--dark-bg, #17131f);
    box-shadow: 0 0 0 1px rgba(225, 92, 92, 0.35);
}
</style>
@endonce
