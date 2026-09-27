@extends('layouts.app-v2')

@section('title', $title)
@section('body-class', $bodyClass)

@section('content')
    @php
        // ロール別ラベル。admin は cast/shop と違って新規登録・クロスリンクなし。
        $isAdminPage = ($role === 'admin');
        $isCastPage  = ($role === 'cast');
        $isShopPage  = ($role === 'shop');
        $roleLabel   = match ($role) {
            'admin' => '運営',
            'shop'  => '店舗',
            default => 'キャスト',
        };
        $crossLinks = match ($role) {
            'cast' => [['route' => 'shop.login', 'label' => '店舗のログイン画面へ', 'icon' => 'store']],
            'shop' => [['route' => 'cast.login', 'label' => 'キャストのログイン画面へ', 'icon' => 'user']],
            // admin は他ロールへのクロスリンクを持たない（URL 露出を避ける）
            default => [],
        };
        $showRegister = !$isAdminPage; // 運営は自己登録なし
    @endphp
    <div class="role-login-shell">
        <div class="role-login-page">
            <div class="role-login-brand">
                <img src="{{ asset('assets/images/common/logo-yoko.png') }}" alt="ミセチョク" class="role-login-logo">
                <p class="role-login-sub">{{ $roleLabel }} ログイン</p>
            </div>

            @if (session('message'))
                <div class="role-login-alert role-login-alert-info">
                    {{ session('message') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="role-login-alert role-login-alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="role-login-card">
                <form method="POST" action="{{ $formAction }}" class="role-login-form">
                    @csrf

                    <label class="role-login-field">
                        <span>メールアドレス</span>
                        <div class="role-login-input-wrap">
                            <i class="fas fa-envelope role-login-input-icon" aria-hidden="true"></i>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="example@misechoku.jp" autocomplete="email">
                        </div>
                    </label>

                    <label class="role-login-field">
                        <span>パスワード</span>
                        <div class="role-login-input-wrap">
                            <i class="fas fa-lock role-login-input-icon" aria-hidden="true"></i>
                            <input type="password" name="password" placeholder="••••••••" autocomplete="current-password">
                        </div>
                    </label>

                    <button type="submit" class="role-login-submit">
                        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                        <span>ログイン</span>
                    </button>

                    @if (!$isAdminPage)
                        <div class="role-login-forgot">
                            <a href="{{ route('password.forgot.show') }}">パスワードをお忘れの方はこちら</a>
                        </div>
                    @endif
                </form>
            </div>

            {{-- 下段：クロスリンク → 新規登録 → デモログインへ --}}
            <div class="role-login-links">
                @foreach ($crossLinks as $link)
                    <a href="{{ route($link['route']) }}" class="role-login-crosslink">
                        <i class="fas fa-{{ $link['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach

                @if ($showRegister)
                    <div class="role-login-signup-block">
                        <p class="role-login-signup-lead">アカウントをお持ちでない方</p>
                        <a href="{{ $registerUrl }}" class="role-login-signup">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            <span>{{ $roleLabel }} 新規登録</span>
                        </a>
                    </div>
                @endif

                <a href="{{ route('login.demo') }}" class="role-login-back">デモ用ログイン画面へ</a>
            </div>
        </div>
    </div>

    <style>
        /* 認証ページのグローバル UI 非表示（クロームを完全に消す） */
        body.page-auth-login #global-header,
        body.page-auth-login nav[data-bottom-nav],
        body.page-auth-login #side-menu,
        body.page-auth-login #character-guide,
        body.page-auth-login #menu-overlay,
        body.page-auth-login #header-task-popup,
        body.page-auth-login #header-notification-popup,
        body.page-auth-login .header-right {
            display: none !important;
        }

        body.page-auth-login,
        body.page-auth-login #app,
        body.page-auth-login .main-layout-container,
        body.page-auth-login main {
            min-height: 100vh;
        }

        body.page-auth-login main {
            padding: 0 !important;
        }

        body.page-auth-login .content-wrapper {
            width: 100%;
            max-width: 100%;
            padding: 0;
            background: transparent;
            box-shadow: none;
            overflow-x: hidden;
        }

        body.page-auth-login #app {
            max-width: 100%;
        }

        /* ==== Light tone base ==================================================
           2026-08-09: ダーク基調から反転してライトトーンへ統一。
           トークンではなく色を直書きにすることで、theme-light / mode-dark 等の
           上位トグルに影響されず「ログイン画面だけは常にライト」を保証する。 */
        body.page-auth-login {
            background:
                radial-gradient(circle at 15% 12%, rgba(196, 181, 253, 0.35), transparent 42%),
                radial-gradient(circle at 85% 88%, rgba(251, 207, 232, 0.28), transparent 46%),
                linear-gradient(180deg, #faf7ff 0%, #ffffff 60%, #f6f2ff 100%) !important;
            color: #241f33 !important;
            font-family: "Noto Sans JP", "Hiragino Sans", "Meiryo", sans-serif;
        }

        .role-login-shell {
            position: relative;
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
        }

        .role-login-page {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: min(460px, calc(100vw - 2 * var(--content-padding-x, 16px)));
            margin: 0 auto;
            padding: 44px max(16px, var(--content-padding-x)) 32px;
            box-sizing: border-box;
        }

        .role-login-brand {
            text-align: center;
            margin-bottom: 26px;
        }

        .role-login-logo {
            width: 200px;
            max-width: 72%;
            margin-bottom: 8px;
            filter: drop-shadow(0 6px 18px rgba(124, 58, 237, 0.20));
        }

        .role-login-sub {
            margin: 8px 0 0;
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            color: #7c3aed;
        }

        .role-login-alert {
            margin-bottom: 14px;
            padding: 13px 16px;
            border-radius: 16px;
            font-size: 0.85rem;
            line-height: 1.7;
            box-shadow: 0 6px 18px rgba(30, 20, 60, 0.08);
        }

        .role-login-alert-info {
            border: 1px solid rgba(168, 85, 247, 0.35);
            background: linear-gradient(90deg, rgba(196, 181, 253, 0.18), rgba(251, 207, 232, 0.18));
            color: #6b21a8;
        }

        .role-login-alert-error {
            border: 1px solid rgba(220, 38, 38, 0.35);
            background: rgba(254, 226, 226, 0.85);
            color: #991b1b;
        }

        .role-login-card {
            border: 1px solid rgba(168, 85, 247, 0.22);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(14px) saturate(160%);
            -webkit-backdrop-filter: blur(14px) saturate(160%);
            box-shadow: 0 18px 44px rgba(76, 29, 149, 0.12);
            padding: 24px;
            max-width: 100%;
            box-sizing: border-box;
        }

        .role-login-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .role-login-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .role-login-field > span {
            margin-left: 2px;
            color: #7c3aed;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.14em;
        }

        .role-login-input-wrap {
            position: relative;
        }

        .role-login-input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.88rem;
            color: rgba(124, 58, 237, 0.55);
            pointer-events: none;
            transition: color 0.2s;
        }

        .role-login-input-wrap:focus-within .role-login-input-icon {
            color: #7c3aed;
        }

        .role-login-input-wrap input {
            width: 100%;
            min-height: 52px;
            padding: 0 16px 0 42px;
            border-radius: 14px;
            border: 1px solid rgba(124, 58, 237, 0.22);
            background: #ffffff;
            color: #241f33;
            font-size: 0.94rem;
            box-sizing: border-box;
            max-width: 100%;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .role-login-input-wrap input::placeholder {
            color: rgba(36, 31, 51, 0.35);
        }

        .role-login-input-wrap input:focus,
        .role-login-input-wrap input:focus-visible {
            outline: none;
            border-color: rgba(124, 58, 237, 0.55);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
        }

        .role-login-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 52px;
            padding: 14px 18px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 45%, #7c3aed 100%);
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            cursor: pointer;
            box-shadow:
                0 8px 20px rgba(124, 58, 237, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.35),
                inset 0 -1px 0 rgba(0, 0, 0, 0.12);
            transition: filter 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
        }
        .role-login-submit:hover { filter: brightness(1.03); }
        .role-login-submit:active {
            transform: scale(0.98);
            box-shadow: 0 3px 8px rgba(124, 58, 237, 0.30), inset 0 2px 4px rgba(0, 0, 0, 0.18);
        }

        .role-login-forgot {
            margin-top: 6px;
            text-align: center;
        }
        .role-login-forgot a {
            font-size: 0.82rem;
            color: rgba(124, 58, 237, 0.80);
            text-decoration: underline;
        }
        .role-login-forgot a:hover { color: #7c3aed; }

        /* ==== Links block (下段：クロスリンク → 新規登録 → デモに戻る) ==== */
        .role-login-links {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-top: 22px;
        }

        .role-login-crosslink {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 14px;
            border: 1px solid rgba(124, 58, 237, 0.30);
            background: rgba(255, 255, 255, 0.60);
            color: #6b21a8;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s, transform 0.12s;
        }
        .role-login-crosslink:hover {
            background: rgba(196, 181, 253, 0.20);
            border-color: rgba(124, 58, 237, 0.55);
        }
        .role-login-crosslink:active { transform: scale(0.99); }

        .role-login-signup-block {
            padding-top: 18px;
            border-top: 1px dashed rgba(124, 58, 237, 0.28);
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .role-login-signup-lead {
            margin: 0;
            font-size: 0.78rem;
            color: rgba(36, 31, 51, 0.60);
            letter-spacing: 0.06em;
        }

        .role-login-signup {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 50px;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1.5px solid #a78bfa;
            background: rgba(255, 255, 255, 0.85);
            color: #7c3aed;
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-decoration: none;
            transition: background 0.2s, transform 0.12s;
        }
        .role-login-signup:hover {
            background: rgba(196, 181, 253, 0.18);
        }
        .role-login-signup:active { transform: scale(0.98); }

        .role-login-back {
            display: block;
            text-align: center;
            color: rgba(36, 31, 51, 0.50);
            text-decoration: none;
            font-size: 0.82rem;
            transition: color 0.2s;
        }
        .role-login-back:hover { color: #7c3aed; }

        @media (max-width: 640px) {
            .role-login-page {
                padding-top: 32px;
                padding-left: var(--content-padding-x, 12px);
                padding-right: var(--content-padding-x, 12px);
            }
            .role-login-card {
                padding: 20px 18px;
            }
        }

        @media (max-width: 360px) {
            .role-login-page {
                padding-left: 10px;
                padding-right: 10px;
            }
            .role-login-card {
                padding: 16px 14px;
            }
        }
    </style>
@endsection
