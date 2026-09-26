<!DOCTYPE html>
{{-- ポイント色（ボタン/アイコン/アクティブ状態）をアメジスト紫に統一（2026-07-12） --}}
<html lang="ja" data-theme="amethyst">
<head>
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $metaTitle = trim($__env->yieldContent('meta_title'));
        $metaDescription = trim($__env->yieldContent('meta_description')) ?: 'ミセチョクのデモサイトです。';
        $metaImage = trim($__env->yieldContent('meta_image')) ?: asset('assets/images/pwa/icon-512.png');
        $canonicalUrl = trim($__env->yieldContent('canonical')) ?: url()->current();
        $assetVersion = '20260926-footer-flat';
        $resolvedTitle = $metaTitle !== ''
            ? $metaTitle
            : ($pageTitle !== '' ? $pageTitle . ' | ' . config('app.name', 'ミセチョク') : config('app.name', 'ミセチョク'));

        // ===== ヘッダー：タイトル / バックボタンの決定（旧 layouts.parts.header と同一ロジック） =====
        $routeName = Route::currentRouteName();
        $mainRouteNames = [
            'cast.home', 'shop.home',
            'cast.search.index', 'shop.search.index',
            'cast.interaction.index', 'shop.interaction.index',
            'cast.talk.index', 'shop.talk.index',
            'cast.mypage.index', 'shop.mypage.index',
        ];
        $isMainPage = in_array($routeName, $mainRouteNames, true);
        $isLoginPage = request()->routeIs('login.demo', 'cast.login', 'shop.login', 'admin.login');
        $showBackButton = !$isMainPage && !$isLoginPage;
        $isTalkRoomPage = request()->routeIs('cast.talk.room', 'shop.talk.room');

        // ===== Back link (2026-07-20 / 2026-08-15 route-based) =====
        // Never use history.back(); resolve the parent screen from the route name so the
        // back button always lands on an existing route. The previous fallback that
        // trimmed the last URL segment could synthesise non-existent URLs such as
        // /shop/htu -> /shop or /shop/recruits/edit -> /shop/recruits/status and 404.
        // Rules below match by route name only and fall back to the current role's home.
        $backRoleTop = auth()->guard('member')->check() ? 'cast' : 'shop';
        $backUrl = null;
        if ($showBackButton) {
            // 1) Talk room -> talk list (same role)
            if ($isTalkRoomPage) {
                $backUrl = route($backRoleTop . '.talk.index');
            }
            // 2) Profile detail -> the search list they came from
            elseif (request()->routeIs('cast.shopprofile.show', 'cast.shopprofileview.show')) {
                $backUrl = route('cast.search.index', ['tab' => 'list']);
            } elseif (request()->routeIs('shop.castprofileview.show')) {
                $backUrl = route('shop.search.index');
            }
            // 3) Shop recruit editor -> shop management (owner-only screen family)
            elseif (request()->routeIs('shop.recruits.edit')) {
                $backUrl = route('shop.mypage.management');
            }
            // 4) MyPage sub-pages -> MyPage top (per role)
            elseif (request()->routeIs('cast.mypage.*')) {
                $backUrl = route('cast.mypage.index');
            } elseif (request()->routeIs('shop.mypage.*')) {
                $backUrl = route('shop.mypage.index');
            }
            // 5) Profile editors -> MyPage top (per role)
            elseif (request()->routeIs('cast.profile.*')) {
                $backUrl = route('cast.mypage.index');
            } elseif (request()->routeIs('shop.profile.*')) {
                $backUrl = route('shop.mypage.index');
            }
            // 6) Interaction (KEEP) sub-pages -> interaction top
            elseif (request()->routeIs('cast.interaction.*')) {
                $backUrl = route('cast.interaction.index');
            } elseif (request()->routeIs('shop.interaction.*')) {
                $backUrl = route('shop.interaction.index');
            }
            // 7) Settings (notification/account/talk-templates/location/line) -> current role MyPage
            //    Subscription (Premium plan) is a shop-owner flow launched from MyPage.
            elseif (request()->routeIs('setting.*')
                 || request()->routeIs('subscription', 'subscription.*')) {
                $backUrl = route($backRoleTop . '.mypage.index');
            }
            // 8) Column article detail -> column index (role-aware)
            elseif (request()->routeIs('cast.column.show')) {
                $backUrl = route('cast.column.index');
            } elseif (request()->routeIs('shop.column.show')) {
                $backUrl = route('shop.column.index');
            } elseif (request()->routeIs('pages.support.column.show')) {
                $backUrl = route('pages.support.column');
            }
            // 9) Notice detail -> notice list
            elseif (request()->routeIs('pages.support.notices.show')) {
                $backUrl = route('pages.support.notices');
            }
            // 10) Standalone pages opened from the side menu -> current role's home
            //     Column list / How To Use / Contact form / Notices /
            //     About / Terms / Privacy
            elseif (request()->routeIs(
                'cast.column.index', 'shop.column.index', 'pages.support.column',
                'cast.htu', 'shop.htu',
                'pages.support.form', 'pages.support.notices',
                'pages.official.about', 'pages.official.terms', 'pages.official.privacy'
            )) {
                $backUrl = route($backRoleTop . '.home');
            }
            // 11) Tutorial and any other page without a defined parent -> role home.
            //     URL-segment slicing is intentionally not used here: it produced
            //     non-existent URLs (e.g. /shop/htu -> /shop, /shop/recruits/edit ->
            //     /shop/recruits/status) that returned 404. Safe fallback only.
            else {
                $backUrl = route($backRoleTop . '.home');
            }
        }

        $segments = explode('.', $routeName ?: '');
        $lastSegment = end($segments) ?: '';
        $secondSegment = $segments[1] ?? '';

        $engByLast = [
            'about' => 'ABOUT', 'terms' => 'TERMS', 'privacy' => 'PRIVACY', 'htu' => 'HOW TO USE',
            'column' => 'COLUMN', 'form' => 'CONTACT', 'notices' => 'NOTICES', 'subscription' => 'SUBSCRIPTION',
            'account' => 'ACCOUNT', 'notification' => 'NOTIFICATION', 'identity' => 'IDENTITY',
            'reviews' => 'REVIEWS', 'review' => 'REVIEW', 'management' => 'MANAGEMENT',
            'employment' => 'EMPLOYMENT', 'documents' => 'DOCUMENTS', 'jobdescription' => 'JOB',
            'castprofileview' => 'CAST PROFILE',
        ];
        $engBySecond = [
            'home' => 'SWIPE', 'login' => 'LOGIN', 'search' => 'SEARCH', 'mypage' => 'MY PAGE',
            'talk' => 'TALK', 'interaction' => 'KEEPS', 'manage' => 'MANAGEMENT',
            'register' => 'ENTRY', 'profile' => 'PROFILE', 'recruit' => 'RECRUIT', 'recruits' => 'RECRUIT',
            'setting' => 'SETTING', 'support' => 'SUPPORT', 'official' => 'OFFICIAL',
            'share' => 'SHARE', 'column' => 'COLUMN',
        ];
        $currentEngTitle = $engByLast[$lastSegment] ?? ($engBySecond[$secondSegment] ?? '');

        $headerTitleCustom = trim((string) ($__env->yieldContent('header_title') ?? ''));
        if ($isTalkRoomPage) {
            $displayTitle = $headerTitleCustom !== '' ? $headerTitleCustom : 'TALK';
        } elseif ($currentEngTitle !== '') {
            $displayTitle = $currentEngTitle;
        } else {
            $displayTitle = $headerTitleCustom !== '' ? $headerTitleCustom : $pageTitle;
        }

        // ===== ライトモード判定 =====
        // SWIPE（home）と MyPage、認証系フルスクリーン以外はライトモード（薄ラベンダー基調）。
        // ただし採用・入金管理（*/mypage/management*）は業務ページのためライトにする。
        // ヘッダー／フッター等のクロームは light-theme.css 側で除外して紫ダークを維持する。
        $bodyClassAttr = trim($__env->yieldContent('body-class'));
        // ライトモードを標準とする（2026-07-20）。ダークのまま残すのは
        // SWIPE（home。白基調は崩れるため常時ダーク）と公開共有ページのみ。
        $isDarkPage = request()->is('*/home*')
            || request()->is('share/*');
        // ===== プレミアムホワイト（試験導入 2026-07-19）=====
        // MyPage / プロフィール詳細を「白基調 + 高級感」テーマで表示するためのフラグ。
        // premium-white.css がここでのみ発動し、既存のダークスタイルを上書きする。
        // これらは Tailwind トークンベースの画面のため、トークン反転で一括ライト化できる。
        // ※ SWIPE (home) は白基調で崩れたため対象から除外（元のダーク表示を維持）
        $naturalPremiumWhite = request()->routeIs(
            'cast.mypage.index',
            'shop.mypage.index',
            'cast.shopprofile.show',      // キャスト → 店舗プロフィール
            'shop.castprofileview.show',  // 店舗 → キャストプロフィール
        );

        // premium-white 対象ページは theme-light と競合しないよう除外（premium-white が優先）
        // ログイン画面（demo/auth）もライトモード対象（2026-07-20。トークン反転で追従）
        $naturalLightTheme = !$isDarkPage
            && !$naturalPremiumWhite;

        // ページ標準テーマをそのまま適用する（ユーザーによるライト/ダーク切替は廃止）。
        $isPremiumWhite = $naturalPremiumWhite;
        $isLightTheme = $naturalLightTheme;

        // ===== ボトムナビ：アクティブ判定（旧 layouts.parts.footer と同一ロジック） =====
        $navPrefix       = request()->is('shop/*') ? 'shop' : (auth()->guard('member')->check() ? 'cast'
            : (auth()->guard('shop')->check() ? 'shop' : (request()->is('cast/*') ? 'cast' : 'shop')));
        $navIsHome        = request()->is("*/home*");
        $navIsSearch      = request()->is("*/search*");
        $navIsTalk        = request()->is("*/talk*");
        $navIsMypage      = request()->is("*/mypage*");
        $searchHref = $navPrefix === 'cast'
            ? route('cast.search.index', ['tab' => 'list'])
            : route('shop.search.index');
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ config('app.name', 'ミセチョク') }}">
    <meta property="og:locale" content="ja_JP">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    {{-- PWA --}}
    <meta name="theme-color" content="#050505">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ミセチョク">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/pwa/icon-192.png') }}">
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" fill="#050505"/><text x="16" y="22" font-size="18" text-anchor="middle" fill="#a855f7">店</text></svg>') }}" type="image/svg+xml">
    <title>{{ $resolvedTitle }}</title>

    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}?v=20260913-uiux">
    {{-- 共通: Font Awesome（sidebar partial が依存） / Swiper / Noto Sans JP --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- layout-sidebar.css が依存する CSS 変数（旧 app.css 由来）の最小フォールバック。
         サイドメニューはビューポート右端から出すため --max-content-width を 100vw にして
         layout-sidebar.css の `right: max(0px, calc(50vw - var(--max-content-width)/2))` を 0 に解決させる。 --}}
    <style>
        :root {
            /* メインコンテンツの最大幅。ヘッダー・フッターと同様に全画面で横幅いっぱいを使う。
               header / sub-header / main / content-wrapper / bottom-nav すべてがこの値を共有する。
               （固定要素の calc(50vw - w/2) 系は 0 に解決され、自然に全幅へ展開される） */
            --max-content-width: 100%;
            --content-padding-x: 16px;
            /* --footer-height: ボトムナビの "総高さ"（ノッチ端末では safe-area を含む）。
               全ページの `padding-bottom: calc(var(--footer-height) + N)` が
               自動的に home-indicator を避けるよう、変数側で env() を折り込む。
               2026-09-26: 前回の nav-height 修正で nav が 75+safe に育ったのに
               各ページの余白計算が "nav は 75px" の前提のままだったため、
               iPhone で 34px 分コンテンツが nav に食い込んでいた症状を根本解消する。 */
            --footer-height: calc(75px + env(safe-area-inset-bottom, 0px));
            --header-height: 60px;
            --sub-header-height: 46px;

            /* layout-header.css / layout-sidebar.css 等が var(--gold) を参照する。
               アンビエント紫はオリジナルのまま保持（=> ヘッダータイトル・サイドメニュー・アイコンの hover が紫）。
               ボタン／バッジ／FAB はそれ自体が bg-accent / text-accent-text を使うので、
               --accent 側のモーブピンクで描画される（このブロックでは触らない）。 */
            --gold:               #a78bfa;
            --gold-light:         #c4b5fd;
            --gold-deep:          #7c3aed;
            --color-text-header:  #f5f5f5;
            --color-text-muted:   #a0a0a0;
            --color-text:         #f5f5f5;
            --color-border:        rgba(168, 85, 247, 0.20);
            --color-border-strong: rgba(168, 85, 247, 0.45);
            --color-card-strong:  #1a1a1a;
        }
        /* タブレット〜デスクトップ：幅は 100% のまま、左右の余白だけ少し広げる */
        @media (min-width: 768px) {
            :root { --content-padding-x: 24px; }
        }
        @media (min-width: 1024px) {
            :root { --content-padding-x: 28px; }
        }

        /* --- 認証/ログイン画面：ヘッダー・フッター・サイドメニュー・キャラガイドを非表示にして
              ロゴ＋フォームだけの全画面表示に。デザイン側は共通の紫アクセント（global override で適用済み） --- */
        body.page-demo-login #global-header,
        body.page-demo-login nav[data-bottom-nav],
        body.page-demo-login #character-guide,
        body.page-demo-login #side-menu,
        body.page-demo-login #menu-overlay,
        body.page-demo-login #header-task-popup,
        body.page-demo-login #header-notification-popup,
        body.page-auth-login #global-header,
        body.page-auth-login nav[data-bottom-nav],
        body.page-auth-login #character-guide,
        body.page-auth-login #side-menu,
        body.page-auth-login #menu-overlay,
        body.page-auth-login #header-task-popup,
        body.page-auth-login #header-notification-popup,
        body.page-auth-register #global-header,
        body.page-auth-register nav[data-bottom-nav],
        body.page-auth-register #character-guide,
        body.page-auth-register #side-menu,
        body.page-auth-register #menu-overlay,
        body.page-auth-register #header-task-popup,
        body.page-auth-register #header-notification-popup {
            display: none !important;
        }
        body.page-demo-login main#main-content,
        body.page-auth-login main#main-content,
        body.page-auth-register main#main-content {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            min-height: 100vh !important;
            min-height: 100dvh !important;
            max-width: 100% !important;
        }

        /* ============================================================
           レイアウト崩れ修正バッチ
           ============================================================ */

        /* Generic accessibility helper (still referenced across the app) */
        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            clip: rect(0,0,0,0) !important;
            white-space: nowrap !important;
        }

        /* Fix 5: /cast/talk の店舗名が 2 行に折り返すのを 1 行省略へ */
        .talk-name {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: 100% !important;
        }

        /* Fix 6: /cast/mypage/management の下部ボーナス申請バーで店舗名が切れる
           Option 2 採用：info を flex:1 + min-width:0 で縮小許可、内側で省略 */
        .deposit-cta-bar__inner {
            gap: 8px !important;
        }
        .deposit-cta-bar__info {
            flex: 1 !important;
            min-width: 0 !important;
            overflow: hidden !important;
        }
        .deposit-cta-bar__amount {
            overflow: hidden !important;
        }
        .deposit-cta-bar__amount strong {
            display: block !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* Fix 9: SWIPE の求人カード店舗名が 2 行に折り返すのを 1 行省略へ */
        .rc-shop-name {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* --- TALK ROOM 専用：ボトムナビを隠し、メッセージ入力欄をルームコンテナ最下部に固定。
              さらに main / body のスクロールを抑止して二重スクロールバーを解消する。
              chat-input-area は position: fixed ではなく、position: relative の #talk-room-container を
              基準に position: absolute で貼り付けることで、祖先要素の transform / filter / backdrop-filter
              による「fixed が absolute 化する」問題を避け、スクロール中もブレずに最下部に貼り付く。 --- */
        body.page-talk-room { overflow: hidden; }
        body.page-talk-room nav[data-bottom-nav] { display: none !important; }
        /* TALK（一覧・ルーム）は行・吹き出しが内側余白を持つため、
           content-wrapper の左右ガターを外して画面横幅いっぱいを使う */
        body.page-talk .content-wrapper {
            padding-left: 0 !important;
            padding-right: 0 !important;
            max-width: 100% !important;
        }
        body.page-talk-room main#main-content {
            /* 100vh はモバイルブラウザだと URL バー込みの高さになり、下端の入力欄が画面外へ落ちる。
               可視領域に追従する dvh を優先し、非対応ブラウザのみ vh フォールバック。 */
            height: 100vh !important;
            height: 100dvh !important;
            min-height: 0 !important;
            padding-bottom: 0 !important;      /* ボトムナビが無いので確保不要 */
            overflow: hidden !important;
        }
        body.page-talk-room #talk-room-container {
            /* Height source of truth, in fallback order:
               1) 100vh — legacy fallback (browsers without dvh)
               2) 100dvh — modern browsers; excludes URL bar but NOT keyboard
               3) --talk-room-visible-h — JS-set from visualViewport.height,
                  which excludes BOTH URL bar AND keyboard. Set by talk-room.js
                  on visualViewport `resize`/`scroll` so the container shrinks
                  when the keyboard opens and the input stays above it. */
            height: calc(100vh - var(--header-height, 60px)) !important;
            height: calc(100dvh - var(--header-height, 60px)) !important;
            height: calc(var(--talk-room-visible-h, 100dvh) - var(--header-height, 60px)) !important;
            min-height: 0 !important;
            position: relative !important;     /* chat-input-area の absolute 基準 */
        }
        body.page-talk-room #talk-room-container .chat-input-area {
            position: absolute !important;     /* 祖先 transform/filter で fixed が壊れる問題を回避 */
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            top: auto !important;
            width: 100% !important;
            max-width: 100% !important;
            transform: none !important;        /* talk.css の translateX(-50%) を無効化（left:0/right:0 で全幅化） */
        }

        /* --- サブヘッダー（TALK / LIKES 等のタブ）をヘッダーと同じく
              全幅展開＋中身は --max-content-width センターに揃え、ヘッダー直下に "1本のバー" として密着させる。
              左右ガターは #global-header と同じ計算式（16px or 中央寄せ）でブレなく揃える。
              背景・blur をヘッダーと完全に揃え、間の継ぎ目を消す。 --- */
        .sub-header-wrapper {
            top: var(--header-height, 60px) !important;
            left: 0 !important;
            transform: none !important;
            width: 100% !important;
            max-width: 100% !important;
            padding-left:  max(var(--content-padding-x, 16px), calc(50vw - var(--max-content-width, 430px) / 2)) !important;
            padding-right: max(var(--content-padding-x, 16px), calc(50vw - var(--max-content-width, 430px) / 2)) !important;
            z-index: 1600 !important;
            /* ヘッダーと同じニュートラルなすりガラス面（色なし） */
            background: rgba(18, 15, 26, 0.55) !important;
            backdrop-filter: blur(20px) saturate(150%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(150%) !important;
            margin-top: 0 !important;
            border-top: 0 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.14) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.20) !important;
            padding-top: 6px !important;
            padding-bottom: 6px !important;
        }
        body.theme-light .sub-header-wrapper,
        body.theme-premium-white .sub-header-wrapper {
            background: linear-gradient(180deg,
                rgba(255, 255, 255, 0.74) 0%,
                rgba(255, 255, 255, 0.55) 100%) !important;
            backdrop-filter: blur(20px) saturate(170%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(170%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.80) !important;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.90),
                0 6px 18px rgba(30, 20, 60, 0.10) !important;
        }
        /* ============================================================
           サブヘッダーのタブ切替：お知らせポップアップの
           「すべて / 未読」タブ（.notif-popup__tab）と同一デザイン
           ・透明タブ + 角丸8px / アクティブ = 薄紫フィル + 紫枠 + 強調文字
           ============================================================ */
        .sub-header-wrapper .sub-header-tabs {
            position: relative;
            display: flex !important;
            width: 100%;
            gap: 4px !important;
            padding: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        .sub-header-wrapper .sub-header-tabs::before,
        .sub-header-wrapper .sub-header-tabs::after {
            display: none !important;
        }
        .sub-header-wrapper .sub-header-tabs .tab-item {
            flex: 1 1 0 !important;
            min-width: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center !important;
            padding: 6px 10px !important;
            border-radius: 8px !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            box-shadow: none !important;
            color: rgba(196, 181, 253, 0.7) !important;
            font-size: 0.76rem !important;
            font-weight: 700 !important;
            text-shadow: none !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease !important;
        }
        .sub-header-wrapper .sub-header-tabs .tab-item:hover {
            background: rgba(168, 85, 247, 0.08) !important;
            color: #f5f5f5 !important;
        }
        .sub-header-wrapper .sub-header-tabs .tab-item:active {
            transform: scale(0.97);
        }
        .sub-header-wrapper .sub-header-tabs .tab-item.active {
            background: rgba(168, 85, 247, 0.18) !important;
            border-color: rgba(168, 85, 247, 0.45) !important;
            color: #ffffff !important;
        }
        .sub-header-wrapper .sub-header-tabs .tab-item.active::after {
            display: none !important;
        }
        /* ライトテーマ：お知らせタブの §18 ライト配色と同一トーン */
        body.theme-light .sub-header-wrapper .sub-header-tabs .tab-item,
        body.theme-premium-white .sub-header-wrapper .sub-header-tabs .tab-item {
            color: #574d6f !important;
        }
        body.theme-light .sub-header-wrapper .sub-header-tabs .tab-item:hover,
        body.theme-premium-white .sub-header-wrapper .sub-header-tabs .tab-item:hover {
            background: rgba(124, 58, 237, 0.06) !important;
            color: #241f33 !important;
        }
        body.theme-light .sub-header-wrapper .sub-header-tabs .tab-item.active,
        body.theme-premium-white .sub-header-wrapper .sub-header-tabs .tab-item.active {
            background: rgba(124, 58, 237, 0.12) !important;
            border-color: rgba(124, 58, 237, 0.40) !important;
            color: #5b21b6 !important;
        }
        .sub-header-tabs {
            background-color: transparent !important;
        }

        /* --- ボトムナビ：ニュートラルなすりガラス（グラスモーフィズム 2026-07-20）。
              色味（アメジストグラデ）は撤去し、blur + 彩度ブーストのフロスト面のみ。
              ダーク面 = 白文字 / ライトテーマ = 濃色文字 で可読性を確保する。
              Safe-area fix (2026-09-24): Tailwind の h-[75px] + pb-[env(safe-area-inset-bottom)]
              + box-border では、ノッチ端末の safe-area (~34px) が 75px の内側に食い込み、
              タブアイコン+ラベルの実表示領域が 41px まで潰れていた。総高さを
              「コンテンツ 75px + 安全域」に切り替え、タブ本体が常に 75px 分の
              余裕を持てるようにする。main の padding-bottom（75+safe-area）とも一致し、
              コンテンツ末尾とナビ上端の間の無駄な空白も消える。 --- */
        nav[data-bottom-nav] {
            /* --footer-height に safe-area が含まれているのでそのまま使う（二重加算しない）。
               Tailwind の `pb-[env(safe-area-inset-bottom)]` は nav ボックス自体の
               padding-bottom を設定するが、内側の `<div class="flex h-full">` は
               `height: 100%` で親の border-box.height（=109px on iPhone）に張られ、
               親の padding-bottom を "越えて" 塗り拡がる。結果、内側 flex の中央
               (align/justify) にあるアイコン列が nav の下側 34px（safe-area zone）
               まで下がり、iPhone のホームインジケータ帯に食い込んで見えなくなる。
               fix: 親の padding-bottom はゼロにして、safe-area は内側 flex 側で
               持たせる。これで内側 flex の "コンテンツ領域" が 75px に確定し、
               アイコンは safe-area の上に正しく載る。 */
            padding-bottom: 0 !important;
            height: var(--footer-height) !important;
            box-sizing: border-box !important;
            /* Glassmorphism removed (2026-09-26): flat opaque surface so icons stay legible. */
            background: rgba(18, 15, 26, 0.90) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border-top: 1px solid rgba(255, 255, 255, 0.14) !important;
            box-shadow: 0 -8px 28px rgba(0, 0, 0, 0.28) !important;
        }
        /* Inner flex row inside the nav: HTML 側で `h-[75px]` を明示している
           ので padding-bottom は不要（コンテンツ 75px + 下 safe-area empty で
           構造的に安全域を確保する）。box-sizing だけ念のため明示。 */
        nav[data-bottom-nav] > div {
            box-sizing: border-box !important;
        }
        /* ナビの文字・アイコン：フラットな紫（影・ネオンなしのシンプル表示）
           2026-09-26: グラスモーフィズム解除に合わせてアイコンの視認性を優先。 */
        nav[data-bottom-nav] .nav-item {
            color: rgba(196, 181, 253, 0.92) !important;
            text-shadow: none !important;
        }
        nav[data-bottom-nav] .nav-item.is-active {
            color: #c4b5fd !important;
            text-shadow: none !important;
        }
        /* ライトテーマ：ヘッダーと上下対称の艶ガラス（下端ハイライト + 上方向の浮遊影） */
        /* さがす・トーク一覧：main の下余白を外し、リストがフッターの
           すりガラス越しに透けて見えるようにする（最下部の到達余白はリスト側で確保） */
        body.page-search main#main-content,
        body.page-talk.page-talk-list main#main-content {
            padding-bottom: 0 !important;
        }
        body.page-search .tab-page-body,
        body.page-search .search-page-body,
        body.page-talk.page-talk-list .talk-list-container {
            /* --footer-height に safe-area 込み。二重加算しない。 */
            padding-bottom: calc(var(--footer-height) + 24px) !important;
        }

        body.theme-light nav[data-bottom-nav],
        body.theme-premium-white nav[data-bottom-nav] {
            /* Glassmorphism removed (2026-09-26): 90%-opaque dark chrome to keep icons crisp on light pages. */
            background: rgba(18, 15, 26, 0.90) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border-top: 1px solid rgba(255, 255, 255, 0.14) !important;
            box-shadow: 0 -8px 28px rgba(0, 0, 0, 0.24) !important;
        }

        /* --- TALK ROOM：他画面と同じ --max-content-width に揃えつつ、内側コンテナはフル幅で背景を敷く --- */
        body.page-talk-room #talk-room-container {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            background:
                radial-gradient(circle at top, rgba(168, 85, 247, 0.10), transparent 40%),
                linear-gradient(180deg, var(--color-surface-from, #1a1a1a) 0%, var(--color-base, #050505) 100%) !important;
        }

        /* --- ヘッダー本体：背景は全幅、中身は --max-content-width センター（sub-header / bottom-nav と幅が揃う）。
              下端の border / 強い影は撤去。さらに左右上端から落とす radial-gradient（紫グロー）は
              下端まで色が残ってしまい、無地のサブヘッダーと角で段差が出るので、上から下へ向けて
              透明にフェードする linear-gradient に置き換える。これでヘッダー下端の色 = サブヘッダーの色
              （rgba(10,10,10,0.92)）になり、継ぎ目が消える。 --- */
        #global-header {
            width: 100% !important;
            max-width: 100% !important;
            left: 0 !important;
            transform: none !important;
            /* 左右パディングは sub-header と同じ計算式で完全一致させる（モバイルでは 16px ガター） */
            padding-left:  max(var(--content-padding-x, 16px), calc(50vw - var(--max-content-width, 430px) / 2)) !important;
            padding-right: max(var(--content-padding-x, 16px), calc(50vw - var(--max-content-width, 430px) / 2)) !important;
            /* ニュートラルなすりガラス：透過を強め blur を深めた本格グラスモーフィズム */
            background: linear-gradient(180deg,
                rgba(24, 20, 34, 0.48) 0%,
                rgba(18, 15, 26, 0.36) 100%) !important;
            backdrop-filter: blur(28px) saturate(190%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(190%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.14),
                0 8px 28px rgba(0, 0, 0, 0.28) !important;
        }
        /* タイトル・アイコン：全画面共通のフラットな紫（影・ネオンなしのシンプル表示） */
        #global-header .header-title-main,
        #global-header .header-icon-btn,
        #global-header .btn-back,
        #global-header .header-talk-name {
            color: #8b5cf6 !important;
            text-shadow: none !important;
        }
        .header-icon-btn:hover,
        .header-icon-btn:focus-visible {
            background-color: rgba(139, 92, 246, 0.12) !important;
        }
        .header-icon-btn:active {
            background-color: rgba(139, 92, 246, 0.20) !important;
        }
        /* タイトル：中央絶対配置を廃止して左寄せ（戻るボタンの隣から始まる） */
        #global-header .header-center-title {
            position: static !important;
            left: auto !important;
            top: auto !important;
            transform: none !important;
            flex: 1 1 auto !important;
            justify-content: flex-start !important;
            text-align: left !important;
            min-width: 0 !important;
            max-width: none !important;
            margin: 0 8px 0 2px !important;
        }
        /* アイコン群：右詰め。ただし誤タップ防止に 8px の間隔を確保 */
        #global-header .header-right {
            gap: 8px !important;
            margin-right: -6px !important;
        }
        /* ライトテーマ：立体＋艶のある白ガラス（上端ハイライト + 下方向の浮遊影） */
        body.theme-light #global-header,
        body.theme-premium-white #global-header {
            background: linear-gradient(180deg,
                rgba(255, 255, 255, 0.58) 0%,
                rgba(255, 255, 255, 0.32) 100%) !important;
            backdrop-filter: blur(30px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(30px) saturate(200%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.80) !important;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                inset 0 -1px 0 rgba(124, 58, 237, 0.10),
                0 10px 30px rgba(30, 20, 60, 0.16) !important;
        }

        /* --- サイドメニュー本体：明るい薄ラベンダー（全モード共通 2026-07-20）。
              半透明だと背後の暗い画面が透けて暗く見えるため、ほぼ不透明の明色面にする --- */
        #side-menu {
            right: 0 !important;
            background: linear-gradient(160deg, #faf8ff 0%, #efe9fa 100%) !important;
            backdrop-filter: blur(20px) saturate(160%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(160%) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.90) !important;
            box-shadow:
                inset 1px 0 0 rgba(255, 255, 255, 0.90),
                -16px 0 40px rgba(30, 20, 60, 0.25) !important;
        }
        .sidebar-header {
            border-bottom-color: rgba(36, 31, 51, 0.10) !important;
        }
        .sidebar-footer {
            border-top-color: rgba(36, 31, 51, 0.10) !important;
        }
        #side-menu,
        #side-menu .sidebar-sub-menu a,
        #side-menu .menu-summary,
        #side-menu .btn-sidebar-close {
            color: #4b465c !important;
            text-shadow: none;
        }
        #side-menu .sidebar-sub-menu a i,
        #side-menu .menu-summary i:first-child {
            color: #6d28d9 !important;
        }
        #side-menu .menu-label-header {
            color: #857ca0 !important;
            text-shadow: none;
        }
        .sidebar-sub-menu a:hover,
        .btn-sidebar-close:hover {
            background: rgba(124, 58, 237, 0.07) !important;
        }
        #side-menu .btn-logout {
            background: rgba(220, 38, 38, 0.06) !important;
            color: #dc2626 !important;
            border: 1px solid rgba(220, 38, 38, 0.35) !important;
        }
        #side-menu .sidebar-badge-pending {
            background: #dc2626 !important;
            color: #ffffff !important;
        }
    </style>

    {{-- サイドバー partial の位置決めCSS（右端からスライド） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/layout-sidebar.css') }}?v=20260720-full-height">

    {{-- ヘッダーのポップアップ（通知 / タスク）専用CSS：app.js が #btn-header-* で togglePopup する --}}
    <link rel="stylesheet" href="{{ asset('assets/css/layout-header.css') }}?v=20260720-ink-policy">

    {{-- 通知 / やることリスト ポップアップ：SWIPE 等のダーク画面でも
         常にライトデザイン（白パネル）で統一（2026-07-20） --}}
    <style>
        #header-task-popup,
        #header-notification-popup {
            background: #ffffff !important;
            border: 1px solid rgba(124, 58, 237, 0.30) !important;
            box-shadow: 0 12px 32px rgba(76, 29, 149, 0.18) !important;
            border-radius: var(--radius-card) !important;
            opacity: 1 !important;
        }
        .task-popup-header {
            background: rgba(168, 85, 247, 0.12) !important;
            border-bottom: 1px solid rgba(168, 85, 247, 0.30) !important;
        }
        .task-popup-header h4 {
            color: var(--color-accent-text) !important;
            font-family: var(--font-display) !important;
            letter-spacing: 0.10em !important;
        }
        .notif-popup-section-label {
            background: rgba(168, 85, 247, 0.08) !important;
            color: var(--color-accent-text) !important;
            border-bottom-color: rgba(168, 85, 247, 0.20) !important;
        }
        .notif-popup-item {
            border-bottom-color: rgba(168, 85, 247, 0.20) !important;
            color: var(--color-text-main) !important;
        }
        a.notif-popup-item:hover {
            background: rgba(168, 85, 247, 0.10) !important;
        }
        .notif-popup-empty {
            color: var(--color-text-sub) !important;
            border-bottom-color: rgba(168, 85, 247, 0.20) !important;
        }

        /* --- 旧 layouts.app の main / content-wrapper / animate-fadeIn を v2 でも再現 ---
             ヘッダー/フッターが fixed で被さるため、旧 app.css と同じく main に
             padding-top: --header-height、padding-bottom: --footer-height を確保する。 --}}
        */
        /* Mobile-viewport fix (2026-09-24):
           `100vh` on iOS Safari / Android Chrome is the *large* viewport (URL bar hidden),
           so a page first opened with the URL bar visible then navigated-to reads
           `100vh` as taller than the actually visible area. Fixed footer at `bottom:0`
           tracks the visible bottom, so short pages end up with their bottom
           padding + last elements clipped by the URL bar / off-screen. Switch to
           `100dvh` which tracks the currently visible viewport. Keep `100vh` first
           as a fallback for browsers without dvh support. */
        main#main-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
            min-height: 100vh;
            min-height: 100dvh;
            padding-top: var(--header-height, 60px);
            /* --footer-height に safe-area が含まれているのでそのまま使う（二重加算しない）。 */
            padding-bottom: var(--footer-height);
            box-sizing: border-box;
        }
        .content-wrapper {
            width: 100%;
            max-width: var(--max-content-width, 430px);
            min-width: 0;
            margin-left: auto;
            margin-right: auto;
            padding: 0 var(--content-padding-x, 16px);
            box-sizing: border-box;
            overflow-x: hidden;
        }
        .animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* --- スクロールバー非表示（全要素） : 機能は維持して見た目だけ消す --- */
        * {
            -ms-overflow-style: none;   /* IE / Edge */
            scrollbar-width: none;      /* Firefox */
        }
        *::-webkit-scrollbar {
            display: none;              /* Chrome / Safari / Webkit */
            width: 0;
            height: 0;
        }

        /* --- フォント統一：全要素を Noto Sans JP に。<i> は Font Awesome / Phosphor のアイコンフォントを保つため除外 --- */
        :root {
            --font-display: "Noto Sans JP", sans-serif;
            --font-sans:    "Noto Sans JP", sans-serif;
            --font-serif:   "Noto Sans JP", sans-serif;
        }
        html, body, body *:not(i):not(svg):not(svg *) {
            font-family: "Noto Sans JP", sans-serif !important;
        }
        /* serif-font / .header-title-serif 等の旧クラスも上書き */
        .serif-font,
        .header-title-serif,
        .header-title-main.header-title-serif,
        .mypage-page-title.serif-font,
        .mypage-shop-name.serif-font {
            font-family: "Noto Sans JP", sans-serif !important;
        }
        /* app-title (Montserrat 想定) も Noto Sans JP に */
        .app-title {
            font-family: "Noto Sans JP", sans-serif !important;
        }
    </style>

    {{-- キャラクターガイド（オコジョ）専用CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/character-guide.css') }}?v={{ $assetVersion }}">
    {{-- 操作モーダルの視認性一括改善（各モーダル CSS の後に読み込んで override する） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/modal-readability.css') }}?v={{ $assetVersion }}">

    {{-- アプリ既存 JS（CSRFやサイドメニュー開閉などはここに集約） --}}
    <script src="{{ asset('assets/js/app.js') }}" defer></script>
    {{-- グローバル トースト：alert() の置き換えに使う window.appToast(msg, variant) --}}
    <script src="{{ asset('assets/js/app-toast.js') }}?v=20260913-uiux" defer></script>
    <script src="{{ asset('assets/js/ux-accessibility.js') }}?v=20260913" defer></script>

    {{-- 新デザインシステム（tailwind.css / Phosphor / Montserrat / behaviors.js）
         x-ui.assets は @push 経由なので、必ず @stack('head-styles') より前に呼ぶ（後だとスタックが空のまま head が確定する）。 --}}
    <x-ui.assets />
    @stack('styles')
    @stack('head-styles')

    {{-- ボタン/CTA の役割ベース統一（全ページCSSの後に読み込んで上書き。DESIGN.md §10） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/ui-consistency.css') }}?v={{ $assetVersion }}">
    {{-- 入力コンポーネントの全画面統一（文字列/文章/数値/日付/選択） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/form-controls.css') }}?v={{ $assetVersion }}">
    {{-- モーション基盤（タブ/モーダル/画像/リビールのなめらか化。Step1） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/motion.css') }}?v={{ $assetVersion }}">
    <script src="{{ asset('assets/js/motion.js') }}?v={{ $assetVersion }}" defer></script>
    {{-- ライトモード（薄ラベンダー基調）。全ルールが body.theme-light スコープのため常時読み込みで安全。
         テーマトグル（ライト/ダーク）のライブ切替を可能にするため @if を外して常時ロードする --}}
    <link rel="stylesheet" href="{{ asset('assets/css/light-theme.css') }}?v=20260926-keep-rename">
    {{-- プレミアムホワイト（MyPage）: 全ルールが body.theme-premium-white スコープ。同上で常時ロード --}}
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;500;600;700;900&family=Cinzel:wght@600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/premium-white.css') }}?v=20260720-pwhite-09">
    {{-- フラット化：プロフィール／設定／管理系の丸角カード枠を廃し、区切り線ベースの
         モダンなセクション表示に統一する（2026-09-24）。ページ固有の inline <style> / light-theme /
         premium-white の全てより後に読み込んで確実に上書きするため、最後尾に置く。 --}}
    <link rel="stylesheet" href="{{ asset('assets/css/flat-sections.css') }}?v={{ $assetVersion }}">
</head>
<body class="@yield('body-class') {{ $isLightTheme ? 'theme-light' : '' }} {{ $isPremiumWhite ? 'theme-premium-white' : '' }} bg-base text-text-main"
      data-navigation-scope="{{ $navPrefix }}:{{ auth()->guard($navPrefix === 'cast' ? 'member' : 'shop')->id() }}"
      data-notification-badge="{{ isset($unreadNewsCount) ? (int) $unreadNewsCount : 0 }}">
    @if(in_array(session('registration_completed'), ['cast', 'shop'], true))
    <script>
        try { sessionStorage.removeItem('register-form-draft-v2:' + @json(session('registration_completed'))); } catch (_) {}
    </script>
    @endif

    {{-- サイドメニュー開閉用オーバーレイ（app.js が #menu-overlay を操作） --}}
    <div id="menu-overlay" class="menu-overlay"></div>

    <div id="app">

        {{-- ヘッダー：旧画面とサイズ・フォント・配色を完全に揃えるため、
             layouts/parts/header を流用する（layout-header.css と Font Awesome に依存）。 --}}
        @include('layouts.parts.header', ['headerTitle' => $pageTitle])

        {{-- オコジョガイド：表示／文言は運営管理画面で設定 --}}
        @include('layouts.parts.character-guide')

        {{-- メイン：--max-content-width に追従して中央寄せ（モバイル430 → タブレット600 → デスクトップ720）。
             旧 layouts.app と同じ条件で content-wrapper を被せ、移行画面のレイアウトを大きく崩さないようにする。 --}}
        <main id="main-content" class="max-w-[var(--max-content-width)] mx-auto">
            {{-- Email verification is now surfaced via the header task popup (UserTaskService),
                 not as a full-width banner — the banner used to overlap the swipe-home layout. --}}
            {{-- 面談リマインダー（24h 以内の面談確定案件があれば表示） --}}
            @include('layouts.parts.interview-reminder-banner')

            @if(request()->routeIs('cast.shopprofile.show', 'shop.castprofileview.show', 'cast.mypage.index', 'shop.mypage.index', 'share.cast.show', 'share.recruit.show'))
                @yield('content')
            @else
                <div class="content-wrapper animate-fadeIn">
                    @yield('content')
                </div>
            @endif
        </main>

        {{-- ============================================================
             新ボトムナビ（behaviors.js が data-bottom-nav を見る）
             active 判定は URL 階層から（旧 footer と同等）。href は実ルート。

             2026-09-26 Plan B fix:
             - `h-[75px]` と `pb-[env(safe-area-inset-bottom)]` は撤去。総高さは
               インライン CSS 側の `height: var(--footer-height)` に一本化する
               （`--footer-height` に safe-area 込み）
             - 内側 flex は `h-full` ではなく `h-[75px]` に固定。これで
               「flex コンテナが親の padding-bottom を貫通して 109px に張り、
                 items-center がアイコンを下方に押し込む」ホームインジケータ
               食い込み問題を CSS 上書きに依存せず HTML 側で断つ。
             - `pb-[env(safe-area-inset-bottom)]` はコンテナ外側（<nav>）に付け直し、
               総高さ = 75 + safe になるよう構造で担保する。
               インライン CSS の override はフォールバック層として残る。
             ============================================================ --}}
        <nav data-bottom-nav data-nav-style="flat"
             class="fixed bottom-0 left-0 w-full z-50 box-border bg-deep-purple/30 backdrop-blur-md border-t border-line-accent/40 shadow-footer"
             style="height: calc(75px + env(safe-area-inset-bottom, 0px));">
            <div class="flex justify-around items-center h-[75px] px-2 max-w-[var(--max-content-width)] mx-auto">
                <a href="{{ route($navPrefix . '.home') }}"
                   class="nav-item flex flex-col items-center justify-center transition-all duration-300 {{ $navIsHome ? 'is-active' : '' }}">
                    <span class="nav-icon-wrap flex items-center justify-center mb-1 transition-all">
                        <x-ui.icon name="swipe" class="nav-icon text-[22px] transition-all" />
                    </span>
                    <span class="text-[10px] font-bold">スワイプ</span>
                </a>
                <a href="{{ $searchHref }}"
                   class="nav-item flex flex-col items-center justify-center transition-all duration-300 {{ $navIsSearch ? 'is-active' : '' }}">
                    <span class="nav-icon-wrap flex items-center justify-center mb-1 transition-all">
                        <x-ui.icon name="search" class="nav-icon text-[22px] transition-all" />
                    </span>
                    <span class="text-[10px] font-bold">さがす</span>
                </a>
                <a href="{{ route($navPrefix . '.talk.index') }}"
                   class="nav-item flex flex-col items-center justify-center transition-all duration-300 {{ $navIsTalk ? 'is-active' : '' }}">
                    <span class="nav-icon-wrap flex items-center justify-center mb-1 transition-all">
                        <x-ui.icon name="talk" class="nav-icon text-[22px] transition-all" />
                    </span>
                    <span class="text-[10px] font-bold">トーク</span>
                </a>
                <a href="{{ route($navPrefix . '.mypage.index') }}"
                   class="nav-item flex flex-col items-center justify-center transition-all duration-300 {{ $navIsMypage ? 'is-active' : '' }}">
                    <span class="nav-icon-wrap flex items-center justify-center mb-1 transition-all">
                        <x-ui.icon name="mypage" class="nav-icon text-[22px] transition-all" />
                    </span>
                    <span class="text-[10px] font-bold">マイページ</span>
                </a>
            </div>
        </nav>

        {{-- サイドバー（既存 partial をそのまま利用：#side-menu 内に inline style 同梱） --}}
        @include('layouts.parts.sidebar')
    </div>

    {{-- 通知 / タスク用ポップオーバー（旧 footer の最下部で読み込まれていたものを継承） --}}
    @include('layouts.parts.header_popover.notification')
    @include('layouts.parts.header_popover.task')

    {{-- 共通ライトボックス（プロフィール画像クリック等）。
         hidden 属性で常時 display:none にしておく。外部 CSS（app.css / *_profile.css）を
         読み込まないページで、中の「閉じる」ボタン（position:absolute; top:20px; right:20px）
         がグローバルヘッダーに被って常駐してしまう問題を防ぐため、JS 側でも開閉時に
         hidden 属性を切り替える。 --}}
    <div id="global-lightbox-overlay" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="画像の拡大表示" aria-hidden="true" hidden onclick="window._closeGlobalLightbox && window._closeGlobalLightbox(event)">
        <button type="button" class="btn-secondary-cta" style="position:absolute;top:20px;right:20px" onclick="event.stopPropagation(); window._closeGlobalLightbox()">閉じる</button>
        <img id="global-lightbox-image" src="" alt="拡大したプロフィール画像" class="lightbox-image">
    </div>

    {{-- 既存共通 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="{{ asset('assets/js/character-guide.js') }}?v={{ $assetVersion }}"></script>
    <script src="{{ asset('assets/js/push-notification.js') }}"></script>
    <script src="{{ asset('assets/js/location-modal.js') }}?v=20260926-multi-trigger" defer></script>
    <script src="{{ asset('assets/js/share-menu.js') }}" defer></script>

    @stack('scripts')
    @stack('foot-scripts')

    <script>
    (function () {
        var overlay = document.getElementById('global-lightbox-overlay');
        var img = document.getElementById('global-lightbox-image');
        if (!overlay || !img) return;
        window.openImageLightbox = function (src) {
            if (!src) return;
            img.src = src;
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
        };
        window._closeGlobalLightbox = function (e) {
            if (e) {
                if (e.target && !e.target.classList.contains('lightbox-overlay')) return;
                e.stopPropagation();
            }
            overlay.classList.remove('is-open');
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            img.src = '';
        };
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('is-open')) window._closeGlobalLightbox();
        });
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-lightbox-target').forEach(function (el) {
                el.style.cursor = 'zoom-in';
                el.addEventListener('click', function () {
                    window.openImageLightbox(el.currentSrc || el.src);
                });
            });
        });
    })();
    </script>

    {{-- PWA: Service Worker 登録 --}}
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/sw.js?v={{ $assetVersion }}', { scope: '/' })
            .then(function () {}).catch(function () {});
        });
      }
    </script>
</body>
</html>
