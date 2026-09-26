@php
    // Role detection: URL prefix first, then fall back to the authenticated
    // guard. The URL-only detection used to silently return the shop menu
    // on shared routes (/setting/*, /support/*, /about, /terms, /privacy)
    // even when a cast was logged in — because those paths don't start
    // with 'cast*' or 'shop*'.
    if (Request::is('cast*')) {
        $isCast = true;
        $isShop = false;
    } elseif (Request::is('shop*')) {
        $isCast = false;
        $isShop = true;
    } else {
        $isCast = auth()->guard('member')->check();
        $isShop = !$isCast && auth()->guard('shop')->check();
    }
    $typePath = $isCast ? 'cast' : 'shop';

    // 書類系の未済判定：ヘッダー共有の $todoList（InjectHeaderBadges）から算出
    $sidebarTodos = collect($todoList ?? []);
    $identityPendingBadge = $sidebarTodos->whereIn('key', ['cast.identity_unsubmitted', 'cast.identity_rejected', 'cast.identity_pending'])->isNotEmpty();
    $licensePendingBadge  = $sidebarTodos->whereIn('key', ['shop.license_unsubmitted', 'shop.license_rejected', 'shop.license_pending'])->isNotEmpty();
@endphp

<aside id="side-menu">
    <div class="sidebar-header">
        <a href="{{ $isCast ? route('cast.home') : route('shop.home') }}">
            <img src="{{ asset('assets/images/common/logo-yoko.png') }}" alt="ミセチョク" class="sidebar-logo">
        </a>
        <button class="btn-sidebar-close" onclick="document.getElementById('side-menu').classList.remove('open'); document.getElementById('menu-overlay').classList.remove('show'); document.body.style.overflow = '';">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-content">
        {{-- PWA: アプリをインストール（beforeinstallprompt 時のみ表示） --}}
        <div class="sidebar-section" id="pwa-install-section" style="display: none;">
            <div class="menu-label-header">APP</div>
            <button type="button" id="pwa-install-btn" class="pwa-install-btn">
                <i class="fas fa-download"></i> アプリをインストール
            </button>
        </div>

        {{-- ACCOUNT セクション：アカウント管理（設定・書類系を集約）。
             メニュー名は改行しない短い名称に統一。スタッフ追加は「アカウント管理」内から。
             書類系は未完了の間「未済」バッジを表示（やることリストにも常時掲載される） --}}
        <div class="sidebar-section">
            <div class="menu-label-header">ACCOUNT</div>
            <ul class="sidebar-sub-menu">
                <li><a href="{{ route('setting.account') }}"><i class="fas fa-cog"></i> アカウント管理</a></li>
                @if($isCast)
                    <li>
                        <a href="{{ route('cast.mypage.identity') }}">
                            <i class="fas fa-id-card"></i> 本人確認
                            @if($identityPendingBadge)<span class="sidebar-badge-pending">未済</span>@endif
                        </a>
                    </li>
                @else
                    {{-- 店舗：許可証の提出・管理（本人確認と同格の導線）
                         `fa-file-shield` は Font Awesome 6 Pro のみ（CDN 6.0.0 では非描画）
                         のため、Free で確実に描画される `fa-file-signature` に変更。 --}}
                    <li>
                        <a href="{{ route('shop.mypage.documents.index') }}">
                            <i class="fas fa-file-signature"></i> 許可証の提出・管理
                            @if($licensePendingBadge)<span class="sidebar-badge-pending">未済</span>@endif
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        {{-- SETTING セクション --}}
        <div class="sidebar-section">
            <div class="menu-label-header">SETTING</div>
            <ul class="sidebar-sub-menu">
                <li><a href="{{ url('/setting/notification') }}"><i class="fas fa-bell"></i> 通知設定</a></li>
                {{-- 探索拠点の設定は SWIPE ヘッダー右のアイコン（layouts/parts/header.blade.php）と
                     検索画面上部のピル（common/search/index.blade.php）に移設済み（2026-09-26）。
                     サイドメニュー経由の起動は廃止。 --}}
                @if(!$isCast)
                    {{-- Premium プラン選択はお金の操作 → オーナー専用リンク --}}
                    @shopowner
                        <li><a href="{{ url('/subscription') }}"><i class="fas fa-crown"></i> プラン選択</a></li>
                    @endshopowner
                    {{-- スタッフ管理：閲覧は全 shop_manager 可（自分の権限バッジが分かる）。
                         追加・削除は StaffController::authorizeOwner() / shop.owner middleware で
                         オーナーのみに制限。 --}}
                    <li><a href="{{ route('shop.mypage.staff.index') }}"><i class="fas fa-id-badge"></i> スタッフ管理</a></li>
                @endif
            </ul>
        </div>

        {{-- PREMIUM セクション（店舗のみ）--}}
        @if(!$isCast)
            <div class="sidebar-section">
                <div class="menu-label-header">PREMIUM</div>
                <ul class="sidebar-sub-menu">
                    <li><a href="{{ route('shop.mypage.viewers.index') }}"><i class="fas fa-eye"></i> 閲覧キャスト一覧</a></li>
                </ul>
            </div>
        @endif

        {{-- SUPPORT セクション --}}
        <div class="sidebar-section">
            <div class="menu-label-header">SUPPORT</div>
            <ul class="sidebar-sub-menu">
                <li><a href="{{ url("/{$typePath}/column") }}"><i class="fas fa-lightbulb"></i> お役立ちコラム</a></li>
                <li><a href="{{ url("/$typePath/htu") }}"><i class="fas fa-book"></i> ご利用ガイド</a></li>
                <li><a href="{{ url('/support/form') }}"><i class="fas fa-paper-plane"></i> 問い合わせ</a></li>
            </ul>
        </div>

        {{-- OFFICIAL セクション --}}
        <div class="sidebar-section">
            <div class="menu-label-header">OFFICIAL</div>
            <ul class="sidebar-sub-menu">
                <li><a href="{{ url('/about') }}"><i class="fas fa-building"></i> 運営会社</a></li>
                <li><a href="{{ url('/terms') }}"><i class="fas fa-file-contract"></i> 利用規約</a></li>
                <li><a href="{{ url('/privacy') }}"><i class="fas fa-shield-alt"></i> プライバシーポリシー</a></li>
            </ul>
        </div>
    </div>

    <div class="sidebar-footer">
        <button class="btn-logout" onclick="if(confirm('ログアウトしますか？')) location.href='{{ route('auth.logout') }}'">
            <i class="fas fa-sign-out-alt"></i> <span>ログアウト</span>
        </button>
    </div>
</aside>

<style>
.sidebar-header { 
    padding: 20px; 
    border-bottom: 1px solid rgba(168, 85, 247, 0.16); 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}
.btn-sidebar-close { background: none; border: none; color: #F5E6E6; font-size: 1.2rem; cursor: pointer; border-radius: 999px; padding: 4px; transition: background 0.2s ease, color 0.2s ease; }
.btn-sidebar-close:hover { background: rgba(168, 85, 247, 0.15); color: #e6dffc; }
.sidebar-logo { height: 30px; }
.sidebar-content { flex: 1; overflow-y: auto; padding: 20px; }
.sidebar-section { margin-bottom: 30px; }

.menu-label-header { 
    font-size: 10px; 
    color: rgba(168, 85, 247, 0.7); 
    margin-bottom: 15px; 
    letter-spacing: 2px; 
    font-weight: 600;
    opacity: 0.7;
}

.sidebar-sub-menu { list-style: none; padding: 0; margin: 0; }
.sidebar-sub-menu li { margin-bottom: 15px; }
.sidebar-sub-menu a, .menu-summary { 
    color: #c0c0c0; 
    text-decoration: none; 
    font-size: 0.95rem; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    cursor: pointer;
}
.sidebar-sub-menu a {
    padding: 8px 10px;
    border-radius: 12px;
    transition: background 0.2s ease, color 0.2s ease, transform 0.12s ease, box-shadow 0.12s ease;
}
.sidebar-sub-menu a:hover {
    background: rgba(168, 85, 247, 0.12);
    color: #F5E6E6;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.45);
}
.sidebar-sub-menu a i, .menu-summary i:first-child {
    color: #a78bfa;
    width: 20px;
    text-align: center;
}

/* 書類系の「未済」バッジ（フラット統一ルール: 未済・緊急 = danger ベタ） */
.sidebar-badge-pending {
    margin-left: auto;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    background: #e15c5c;
    border: 0;
    color: #fff;
    white-space: nowrap;
}

.menu-summary {
    list-style: none;
    justify-content: space-between;
    width: 100%;
}
.menu-summary::-webkit-details-marker { display: none; }

.sidebar-details[open] .arrow {
    transform: rotate(180deg);
}
.arrow {
    font-size: 0.8rem;
    transition: transform 0.3s;
    opacity: 0.5;
}

.sidebar-footer { padding: 20px; padding-bottom: max(20px, env(safe-area-inset-bottom, 0px)); border-top: 1px solid rgba(168, 85, 247, 0.16); }
.btn-logout {
    width: 100%; padding: 12px; background: rgba(32, 7, 10, 0.9);
    border: 1px solid rgba(248, 113, 113, 0.7); color: #fecaca;
    border-radius: 999px; cursor: pointer; font-weight: 600;
    letter-spacing: 0.12em;
    font-size: 0.8rem;
    text-transform: uppercase;
    transition: background 0.25s ease, border-color 0.25s ease, transform 0.12s ease, box-shadow 0.12s ease;
}
.btn-logout:hover {
    background: rgba(185, 28, 28, 0.9);
    color: #fff;
    border-color: rgba(252, 165, 165, 0.9);
    box-shadow: 0 6px 18px rgba(0,0,0,0.6);
    transform: translateY(-1px);
}

.pwa-install-btn {
    width: 100%; padding: 12px 16px; background: radial-gradient(circle at 0% 0%, rgba(196, 181, 253, 0.25), rgba(18,4,5,0.98));
    border: 1px solid rgba(168, 85, 247, 0.7); color: #e6dffc; border-radius: 999px;
    cursor: pointer; font-size: 0.95rem; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px;
    transition: 0.3s;
}
.pwa-install-btn:hover { background: rgba(168, 85, 247, 0.15); color: #e5c84a; }
</style>