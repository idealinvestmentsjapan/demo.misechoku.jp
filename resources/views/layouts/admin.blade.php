@php
    $currentRoute = optional(request()->route())->getName();
    $pageTitle = trim($__env->yieldContent('admin_page_title')) ?: trim($__env->yieldContent('title')) ?: 'ダッシュボード';
    $authedAdmin = auth()->guard('admin')->user();
    $menuGroups = [
        [
            'title' => 'オペレーション',
            'items' => [
                ['label' => '請求書発行', 'route' => 'admin.invoices.index', 'icon' => 'fa-file-invoice', 'badge' => null, 'badge_class' => '', 'permission' => 'operations.invoices'],
                ['label' => '入金確認・振込', 'route' => 'admin.deposits.index', 'icon' => 'fa-money-bill-wave', 'badge' => null, 'badge_class' => '', 'permission' => 'operations.deposits'],
                ['label' => 'プラン入金管理', 'route' => 'admin.plans.index', 'icon' => 'fa-crown', 'badge' => null, 'badge_class' => '', 'permission' => 'operations.deposits'],
                ['label' => '身分証・書類審査', 'route' => 'admin.verification.index', 'icon' => 'fa-id-card', 'badge' => null, 'badge_class' => '', 'permission' => 'operations.verification'],
                ['label' => '問合せ対応', 'route' => 'admin.inquiries.index', 'icon' => 'fa-triangle-exclamation', 'badge' => null, 'badge_class' => '', 'permission' => 'operations.inquiries'],
            ],
        ],
        [
            'title' => 'コンテンツ',
            'items' => [
                ['label' => 'お知らせ管理', 'route' => 'admin.notices.index', 'icon' => 'fa-bell', 'badge' => null, 'badge_class' => '', 'permission' => 'content.notices'],
                ['label' => 'コラム管理', 'route' => 'admin.columns.index', 'icon' => 'fa-pen-nib', 'badge' => null, 'badge_class' => '', 'permission' => 'content.columns'],
                ['label' => 'サポート問合せ', 'route' => 'admin.support-inquiries.index', 'icon' => 'fa-envelope-open-text', 'badge' => null, 'badge_class' => '', 'permission' => 'content.notices'],
                ['label' => 'ユーザー通報', 'route' => 'admin.user_reports.index', 'icon' => 'fa-flag', 'badge' => null, 'badge_class' => '', 'permission' => 'content.notices'],
            ],
        ],
        [
            'title' => 'マスタ設定',
            'items' => [
                ['label' => 'NGワード管理', 'route' => 'admin.ngwords.index', 'icon' => 'fa-ban', 'badge' => null, 'badge_class' => '', 'permission' => 'master.ngwords'],
                ['label' => 'マスタメンテナンス', 'route' => 'admin.masters.index', 'icon' => 'fa-database', 'badge' => null, 'badge_class' => '', 'permission' => 'master.masters'],
                ['label' => '通知・タスク仕様', 'route' => 'admin.notification-spec.index', 'icon' => 'fa-bell', 'badge' => null, 'badge_class' => '', 'permission' => 'master.notification_spec'],
                ['label' => 'オコジョガイド設定', 'route' => 'admin.character-guide.index', 'icon' => 'fa-comment-dots', 'badge' => null, 'badge_class' => '', 'permission' => 'master.character_guide'],
                ['label' => 'トーク定型文マスタ', 'route' => 'admin.talk-quick-replies.index', 'icon' => 'fa-bolt', 'badge' => null, 'badge_class' => '', 'permission' => 'master.talk_quick_replies'],
            ],
        ],
        [
            'title' => 'アナリティクス',
            'items' => [
                ['label' => '売上・ユーザー数増減', 'route' => 'admin.sales.index', 'icon' => 'fa-chart-column', 'badge' => null, 'badge_class' => '', 'permission' => 'analytics.sales'],
            ],
        ],
        [
            'title' => 'アカウント管理',
            'items' => [
                ['label' => '店舗管理', 'route' => 'admin.shops.index', 'icon' => 'fa-building', 'badge' => null, 'badge_class' => '', 'permission' => 'accounts.shops.view'],
                ['label' => 'キャスト管理', 'route' => 'admin.casts.index', 'icon' => 'fa-users', 'badge' => null, 'badge_class' => '', 'permission' => 'accounts.casts.view'],
                ['label' => '運営アカウント管理', 'route' => 'admin.admin-accounts.index', 'icon' => 'fa-user-gear', 'badge' => null, 'badge_class' => '', 'permission' => 'accounts.admins'],
            ],
        ],
        [
            'title' => '規約管理',
            'items' => [
                ['label' => '運営協会', 'route' => 'admin.policies.show', 'route_params' => ['key' => 'about'], 'icon' => 'fa-landmark', 'badge' => null, 'badge_class' => '', 'permission' => 'policies.manage'],
                ['label' => '利用規約', 'route' => 'admin.policies.show', 'route_params' => ['key' => 'terms'], 'icon' => 'fa-file-contract', 'badge' => null, 'badge_class' => '', 'permission' => 'policies.manage'],
                ['label' => 'プライバシーポリシー', 'route' => 'admin.policies.show', 'route_params' => ['key' => 'privacy'], 'icon' => 'fa-user-shield', 'badge' => null, 'badge_class' => '', 'permission' => 'policies.manage'],
            ],
        ],
    ];

    // 権限フィルタ：authedAdmin が存在する場合のみ非保有を除外（未ログインの旧UIは従来挙動）
    if ($authedAdmin && method_exists($authedAdmin, 'hasPermission')) {
        foreach ($menuGroups as &$g) {
            $g['items'] = array_values(array_filter($g['items'], function ($it) use ($authedAdmin) {
                $perm = $it['permission'] ?? null;
                return $perm === null || $authedAdmin->hasPermission($perm);
            }));
        }
        unset($g);
        $menuGroups = array_values(array_filter($menuGroups, fn ($g) => count($g['items'] ?? []) > 0));
    }
    $opBadges = $adminOperationBadges ?? [];
    $opAchievements = $adminOperationAchievements ?? [];
    foreach ($menuGroups as &$group) {
        if (($group['title'] ?? '') !== 'オペレーション') {
            continue;
        }
        foreach ($group['items'] as &$item) {
            $route = $item['route'] ?? '';
            if (! isset($opBadges[$route])) {
                continue;
            }
            $n = (int) $opBadges[$route];
            $item['badge'] = $n > 0 ? (string) $n : null;
            $item['badge_class'] = $n > 0 ? 'badge-gold' : '';
        }
        unset($item);
    }
    unset($group);
    $sectionMap = [
        'admin.dashboard' => 'ダッシュボード',
        'admin.invoices.*' => 'オペレーション',
        'admin.deposits.*' => 'オペレーション',
        'admin.plans.*' => 'オペレーション',
        'admin.verification.*' => 'オペレーション',
        'admin.tasks.*' => 'オペレーション',
        'admin.inquiries.*' => 'オペレーション',
        'admin.notices.*' => 'コンテンツ',
        'admin.columns.*' => 'コンテンツ',
        'admin.ngwords.*' => 'マスタ設定',
        'admin.masters.*' => 'マスタ設定',
        'admin.notification-spec.*' => 'マスタ設定',
        'admin.character-guide.*' => 'マスタ設定',
        'admin.talk-quick-replies.*' => 'マスタ設定',
        'admin.sales.*' => 'アナリティクス',
        'admin.shops.*' => 'アカウント管理',
        'admin.casts.*' => 'アカウント管理',
        'admin.admin-accounts.*' => 'アカウント管理',
        'admin.bank.*' => 'アカウント管理',
        'admin.policies.*' => '規約管理',
    ];
    $headerSection = '管理画面';
    foreach ($sectionMap as $pattern => $label) {
        if (request()->routeIs($pattern)) {
            $headerSection = $label;
            break;
        }
    }
    $headerDetail = $currentRoute === 'admin.dashboard' ? '全体サマリー' : $pageTitle;
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | 管理画面</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- メインアプリと同じ Noto Sans JP + Montserrat（DESIGN.md §3 と統一） --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800;900&family=Montserrat:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}?v=20260802-split">
    {{-- Detail-page + role-UI styles (extracted 2026-08-02); load AFTER admin.css --}}
    <link rel="stylesheet" href="{{ asset('assets/css/admin-detail.css') }}?v=20260802-split">
    {{-- モバイル最適化（admin.css の後に読み込んで上書き） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/admin-mobile.css') }}?v=20260815-admin-mobile-v4">
    @stack('admin-styles')
    {{-- 入力コンポーネントの全画面統一（文字列/文章/数値/日付/選択） --}}
    <link rel="stylesheet" href="{{ asset('assets/css/form-controls.css') }}?v=20260719-light-all">
</head>
<body>
    <div id="admin-mobile-overlay" class="admin-mobile-overlay"></div>
    <div class="admin-layout">
        <aside id="admin-sidebar" class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-brand">
                    <span class="admin-brand-title">ミセチョク</span>
                    <span class="admin-brand-badge">ADMIN</span>
                </div>
                <button type="button" class="admin-sidebar-close" id="admin-sidebar-close" aria-label="メニューを閉じる">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <div class="admin-sidebar-body">
                <a href="{{ route('admin.dashboard') }}" class="admin-home-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                    <i class="fas fa-table-columns"></i>
                    <span>ダッシュボード</span>
                </a>

                @foreach ($menuGroups as $group)
                    <section class="admin-nav-group">
                        <div class="admin-nav-group-title">
                            <span class="admin-nav-group-title-text">{{ $group['title'] }}</span>
                            <span class="admin-nav-group-title-line" aria-hidden="true"></span>
                        </div>
                        <nav class="admin-nav-list">
                            @foreach ($group['items'] as $item)
                                @php
                                    $navRouteParams = $item['route_params'] ?? [];
                                    $navHref = route($item['route'], $navRouteParams);
                                    $navActive = isset($item['route_params']['key'])
                                        ? request()->routeIs('admin.policies.show') && (string) request()->route('key') === (string) $item['route_params']['key']
                                        : request()->routeIs($item['route']);
                                @endphp
                                <a href="{{ $navHref }}" class="admin-nav-link {{ $navActive ? 'is-active' : '' }}">
                                    <span class="admin-nav-link-main">
                                        <i class="fas {{ $item['icon'] }}"></i>
                                        @if(($group['title'] ?? '') === 'オペレーション')
                                            <span class="admin-nav-link-text-wrap">
                                                <span class="admin-nav-link-label">{{ $item['label'] }}</span>
                                                <span class="admin-nav-link-achievement">実績 {{ number_format((int) ($opAchievements[$item['route']] ?? 0)) }}件</span>
                                            </span>
                                        @else
                                            <span class="admin-nav-link-label">{{ $item['label'] }}</span>
                                        @endif
                                    </span>
                                    @if (!empty($item['badge']))
                                        <span class="admin-badge {{ $item['badge_class'] }}">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </nav>
                    </section>
                @endforeach

                @php
                    $authedName = $authedAdmin->name ?? '管理者';
                    $authedRoleLabel = $authedAdmin && method_exists($authedAdmin, 'isAdmin') && $authedAdmin->isAdmin()
                        ? 'SUPER ADMIN'
                        : ($authedAdmin ? 'OPERATOR' : 'GUEST');
                    $avatarLetters = mb_strtoupper(mb_substr($authedName ?: 'AD', 0, 2));
                @endphp
                <div class="admin-sidebar-user">
                    <div class="admin-sidebar-user-row">
                        <div class="admin-user-avatar">{{ $avatarLetters }}</div>
                        <div>
                            <div class="admin-user-name">{{ $authedName }}</div>
                            <div class="admin-user-role">{{ $authedRoleLabel }}</div>
                        </div>
                    </div>
                    <button type="button" class="admin-logout-btn" onclick="if(confirm('ログアウトしますか？')) location.href='{{ route('admin.login') }}'">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>ログアウト</span>
                    </button>
                </div>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-header-left">
                    <button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-label="メニューを開く">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="admin-breadcrumb">
                        <span class="admin-breadcrumb-current">{{ $headerSection }}</span>
                        @if(!empty($headerDetail) && $headerDetail !== $headerSection)
                            <span class="admin-breadcrumb-sep" aria-hidden="true">/</span>
                            <span>{{ $headerDetail }}</span>
                        @endif
                    </div>
                </div>

                <div class="admin-header-right">
                    <div class="admin-search-wrap">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" class="admin-search-input" placeholder="検索...">
                    </div>
                    {{-- 未済タスク（請求・書類審査・問合せなどの要対応一覧） --}}
                    <div style="position: relative;">
                        @php
                            $nAdminTasks = (int) ($adminNotificationCount ?? 0);
                        @endphp
                        <button type="button" class="admin-header-icon" id="admin-task-toggle" aria-label="未済タスク" data-popover-target="admin-task-popover-el">
                            <i class="fas fa-list-check"></i>
                            @if ($nAdminTasks > 0)
                                <span class="admin-header-notification-count" aria-hidden="true">{{ $nAdminTasks > 99 ? '99+' : $nAdminTasks }}</span>
                            @endif
                        </button>
                        <div id="admin-task-popover-el" class="admin-task-popover" aria-hidden="true">
                            <div class="admin-task-popover-head">
                                <div class="admin-task-popover-title">未済タスク</div>
                                <div class="admin-task-popover-badge">{{ $nAdminTasks }}件</div>
                            </div>
                            <div class="admin-task-popover-list">
                                @forelse ($adminNotifications ?? [] as $item)
                                    <a href="{{ $item['url'] }}" class="admin-task-item">
                                        <span class="admin-task-item-icon {{ $item['class'] }}">
                                            <i class="fas {{ $item['icon'] }}"></i>
                                        </span>
                                        <span>
                                            <span class="admin-task-item-title">{{ $item['title'] }}</span>
                                            <span class="admin-task-item-time">{{ $item['time_label'] }}</span>
                                        </span>
                                    </a>
                                @empty
                                    <div class="admin-task-popover-empty">未済のタスクはありません。</div>
                                @endforelse
                            </div>
                            <div class="admin-task-popover-foot">
                                <a href="{{ route('admin.dashboard') }}" class="admin-task-popover-link">ダッシュボードで全体を確認</a>
                            </div>
                        </div>
                    </div>

                    {{-- お知らせ（admin 宛の個人通知：notifications テーブル） --}}
                    <div style="position: relative;">
                        @php
                            $nAdminInbox = (int) ($adminInboxUnread ?? 0);
                        @endphp
                        <button type="button" class="admin-header-icon" id="admin-notification-toggle" aria-label="お知らせ" data-popover-target="admin-notification-popover">
                            <i class="fas fa-bell"></i>
                            @if ($nAdminInbox > 0)
                                <span class="admin-header-notification-count" aria-hidden="true">{{ $nAdminInbox > 99 ? '99+' : $nAdminInbox }}</span>
                            @endif
                        </button>
                        <div id="admin-notification-popover" class="admin-task-popover" aria-hidden="true">
                            <div class="admin-task-popover-head">
                                <div class="admin-task-popover-title">お知らせ</div>
                                <div class="admin-task-popover-badge">未読 {{ $nAdminInbox }}件</div>
                            </div>
                            <div class="admin-task-popover-list">
                                @forelse ($adminInboxItems ?? [] as $item)
                                    <a href="{{ $item['url'] }}" class="admin-task-item admin-inbox-item {{ !empty($item['is_unread']) ? 'is-unread' : '' }}">
                                        <span class="admin-task-item-icon">
                                            <i class="fas fa-bell"></i>
                                        </span>
                                        <span>
                                            <span class="admin-task-item-title">{{ $item['title'] }}</span>
                                            <span class="admin-task-item-time">{{ $item['time_label'] }}</span>
                                        </span>
                                    </a>
                                @empty
                                    <div class="admin-task-popover-empty">お知らせはありません。</div>
                                @endforelse
                            </div>
                            <div class="admin-task-popover-foot">
                                <button type="button" id="admin-inbox-read-all" class="admin-task-popover-link" style="background:transparent;border:0;cursor:pointer;">すべて既読にする</button>
                            </div>
                        </div>
                    </div>
                    <span class="admin-header-avatar">{{ $avatarLetters ?? 'AD' }}</span>
                </div>
            </header>

            <div class="admin-content">
                <div class="admin-content-shell">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    {{-- グローバル トースト：alert() の置き換えに使う window.appToast(msg, variant) --}}
    <script src="{{ asset('assets/js/app-toast.js') }}?v=20260913-uiux" defer></script>

    <script>
        (function () {
            var sidebar = document.getElementById('admin-sidebar');
            var overlay = document.getElementById('admin-mobile-overlay');
            var openBtn = document.getElementById('admin-menu-toggle');
            var closeBtn = document.getElementById('admin-sidebar-close');

            function openSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.add('is-open');
                overlay.classList.add('is-open');
            }

            function closeSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.remove('is-open');
                overlay.classList.remove('is-open');
            }

            // ヘッダーポップオーバー（未済タスク / お知らせ）：どちらか一方だけ開く
            var popoverPairs = [];
            document.querySelectorAll('[data-popover-target]').forEach(function (btn) {
                var pop = document.getElementById(btn.getAttribute('data-popover-target'));
                if (pop) popoverPairs.push({ btn: btn, pop: pop });
            });

            function closeAllPopovers(except) {
                popoverPairs.forEach(function (pair) {
                    if (pair.pop === except) return;
                    pair.pop.classList.remove('is-open');
                    pair.pop.setAttribute('aria-hidden', 'true');
                });
            }

            popoverPairs.forEach(function (pair) {
                pair.btn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var isOpen = pair.pop.classList.toggle('is-open');
                    pair.pop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    if (isOpen) closeAllPopovers(pair.pop);
                });
            });

            if (openBtn) openBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay) {
                overlay.addEventListener('click', function () {
                    closeSidebar();
                    closeAllPopovers(null);
                });
            }
            document.addEventListener('click', function (event) {
                var inside = popoverPairs.some(function (pair) {
                    return pair.pop.contains(event.target) || pair.btn.contains(event.target);
                });
                if (!inside) closeAllPopovers(null);
            });

            // お知らせ：すべて既読にする（cast/shop/admin 共通の既読APIを使用）
            var readAllBtn = document.getElementById('admin-inbox-read-all');
            if (readAllBtn) {
                readAllBtn.addEventListener('click', function () {
                    readAllBtn.disabled = true;
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    fetch('{{ route('notifications.read-all') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : '',
                        },
                        credentials: 'same-origin',
                    }).then(function () { window.location.reload(); })
                      .catch(function () { readAllBtn.disabled = false; });
                });
            }
        })();

        // インフォボタン（ページタイトル横の (i)）のポップオーバー開閉
        (function () {
            function closeAllInfoPopovers(except) {
                document.querySelectorAll('.admin-info-btn').forEach(function (btn) {
                    if (btn === except) return;
                    var id = btn.getAttribute('aria-controls');
                    var pop = id ? document.getElementById(id) : null;
                    if (pop) pop.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                });
            }
            document.querySelectorAll('.admin-info-btn').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var id = btn.getAttribute('aria-controls');
                    var pop = id ? document.getElementById(id) : null;
                    if (!pop) return;
                    var willOpen = pop.hidden === true;
                    closeAllInfoPopovers(btn);
                    pop.hidden = !willOpen;
                    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
            });
            document.querySelectorAll('.admin-info-popover__close').forEach(function (closer) {
                closer.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var pop = closer.closest('.admin-info-popover');
                    if (!pop) return;
                    pop.hidden = true;
                    var btn = document.querySelector('.admin-info-btn[aria-controls="' + pop.id + '"]');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            });
            document.addEventListener('click', function (event) {
                if (event.target.closest('.admin-info-popover') || event.target.closest('.admin-info-btn')) return;
                closeAllInfoPopovers(null);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeAllInfoPopovers(null);
            });
        })();

        // 行クリックで詳細画面へ遷移する共通ハンドラ（.admin-row-clickable[data-href]）
        (function () {
            document.querySelectorAll('.admin-row-clickable').forEach(function (row) {
                var href = row.getAttribute('data-href');
                if (!href) return;
                row.addEventListener('click', function (event) {
                    // 行内の <a> / <button> / <form> 要素のクリックは元の挙動を優先
                    if (event.target.closest('a, button, input, form, label, select, textarea')) return;
                    window.location.href = href;
                });
                row.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    if (event.target.closest('a, button, input, form, label, select, textarea')) return;
                    event.preventDefault();
                    window.location.href = href;
                });
            });
        })();
    </script>
    @include('partials.bank-autocomplete-scripts')
    @stack('admin-scripts')
</body>
</html>
