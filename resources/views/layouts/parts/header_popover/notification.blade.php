@php
    /** @var array<int, array<string,mixed>> $notifications */
    $notifications      = $notifications      ?? [];
    /** @var array<int, array{key:string,label:string,items:array<int, array<string,mixed>>}> $notificationGroups */
    $notificationGroups = $notificationGroups ?? [];
    /** @var array<int, array<string,mixed>> $operationalNotices */
    $operationalNotices = $operationalNotices ?? [];
    /** @var int $unreadNewsCount */
    $unreadNewsCount    = (int) ($unreadNewsCount ?? 0);

    // 未読件数（Server-truth）
    $unreadCount = 0;
    foreach ($notifications as $__n) {
        if (!empty($__n['is_unread'])) $unreadCount++;
    }
@endphp
<div id="header-notification-popup"
     class="header-popup notif-popup stop-propagation"
     style="display:none;"
     data-notification-popup
     data-unread-count="{{ $unreadCount }}">

    {{-- ========== ヘッダー ========== --}}
    <header class="notif-popup__head">
        <div class="notif-popup__head-title">
            <i class="fas fa-bell notif-popup__head-icon" aria-hidden="true"></i>
            <span class="notif-popup__head-label">お知らせ</span>
            {{-- タイトル横のカウントは廃止（ヘッダーアイコン上のバッジで十分） --}}
        </div>
        <div class="notif-popup__head-actions">
            @if($unreadCount > 0)
                <button type="button"
                        class="notif-popup__mark-all"
                        data-notif-mark-all
                        aria-label="すべて既読にする"
                        title="すべて既読">
                    <i class="fas fa-check-double" aria-hidden="true"></i>
                    <span class="notif-popup__mark-all-label">すべて既読</span>
                </button>
            @endif
            @if(Route::has('setting.notification'))
                <a href="{{ route('setting.notification') }}"
                   class="notif-popup__gear"
                   aria-label="通知設定を開く"
                   title="通知設定">
                    <i class="fas fa-gear" aria-hidden="true"></i>
                </a>
            @endif
            <button type="button"
                    class="notif-popup__close"
                    onclick="togglePopup('header-notification-popup')"
                    aria-label="閉じる">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    {{-- ========== フィルタタブ ========== --}}
    <div class="notif-popup__tabs" role="tablist" aria-label="通知の絞り込み">
        <button type="button"
                class="notif-popup__tab is-active"
                data-notif-filter="all"
                role="tab"
                aria-selected="true">
            すべて<span class="notif-popup__tab-num">{{ count($notifications) }}</span>
        </button>
        <button type="button"
                class="notif-popup__tab"
                data-notif-filter="unread"
                role="tab"
                aria-selected="false">
            未読<span class="notif-popup__tab-num" data-notif-unread-num>{{ $unreadCount }}</span>
        </button>
    </div>

    {{-- ========== 本体 ========== --}}
    <div class="notif-popup__body" data-notif-body>

        {{-- 運営からのお知らせ（存在時のみ、コンパクト） --}}
        @if(!empty($operationalNotices))
            <section class="notif-popup__section notif-popup__official">
                <h5 class="notif-popup__section-label">
                    <i class="fas fa-bullhorn" aria-hidden="true"></i>
                    運営からのお知らせ
                </h5>
                @foreach($operationalNotices as $notice)
                    @php
                        $noticeUrl   = $notice['url']   ?? null;
                        $noticeTitle = $notice['title'] ?? '';
                        $noticeAt    = $notice['published_at'] ?? null;
                        $tag = $noticeUrl ? 'a' : 'div';
                    @endphp
                    <{{ $tag }}
                        @if($noticeUrl) href="{{ $noticeUrl }}" @endif
                        class="notif-popup__official-item">
                        <span class="notif-popup__official-dot" aria-hidden="true"></span>
                        <span class="notif-popup__official-body">
                            <span class="notif-popup__official-title">{{ $noticeTitle }}</span>
                            @if(!empty($noticeAt))
                                <span class="notif-popup__official-date">{{ $noticeAt }}</span>
                            @endif
                        </span>
                        @if($noticeUrl)
                            <i class="fas fa-chevron-right notif-popup__official-chev" aria-hidden="true"></i>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </section>
        @endif

        {{-- 個人宛通知（日付グループ） --}}
        @if(!empty($notificationGroups))
            @foreach($notificationGroups as $group)
                @php
                    $groupKey   = $group['key']   ?? '';
                    $groupLabel = $group['label'] ?? '';
                    $groupItems = $group['items'] ?? [];
                @endphp
                <section class="notif-popup__section" data-notif-group>
                    <h5 class="notif-popup__section-label">{{ $groupLabel }}</h5>
                    @foreach($groupItems as $item)
                        @php
                            $url      = $item['url'] ?? null;
                            $unread   = (bool) ($item['is_unread'] ?? false);
                            $itemId   = $item['id'] ?? '';
                            $title    = $item['title'] ?? '';
                            $body     = $item['body'] ?? null;
                            $tLabel   = $item['created_at_label'] ?? null;
                            $tFull    = $item['created_at_full']  ?? null;
                            $icon     = $item['icon']  ?? 'fa-bell';
                            $color    = $item['color'] ?? 'muted';
                            $catLabel = $item['category_label'] ?? '';
                            $tag = $url ? 'a' : 'div';
                            // Route unread anchor clicks through the server-side visit endpoint so
                            // notifications.read_at is guaranteed to be written before navigation
                            // (avoids sendBeacon races that leave rows unread).
                            $hrefUrl = $url;
                            if ($url && $itemId !== '' && $unread && Route::has('notifications.visit')) {
                                $hrefUrl = route('notifications.visit', ['id' => $itemId]);
                            }
                        @endphp
                        <{{ $tag }}
                            @if($url) href="{{ $hrefUrl }}" @endif
                            class="notif-popup__item notif-popup__item--{{ $color }} {{ $unread ? 'is-unread' : '' }}"
                            data-notif-item
                            data-notif-id="{{ $itemId }}"
                            data-notif-unread="{{ $unread ? '1' : '0' }}">
                            <span class="notif-popup__item-icon" aria-hidden="true">
                                <i class="fas {{ $icon }}"></i>
                            </span>
                            <span class="notif-popup__item-body">
                                <span class="notif-popup__item-headrow">
                                    <span class="notif-popup__item-cat">{{ $catLabel }}</span>
                                    @if($tLabel)
                                        <time class="notif-popup__item-time" @if($tFull) title="{{ $tFull }}" datetime="{{ $tFull }}" @endif>{{ $tLabel }}</time>
                                    @endif
                                </span>
                                <span class="notif-popup__item-title">{{ $title }}</span>
                                @if(!empty($body))
                                    <span class="notif-popup__item-desc">{{ $body }}</span>
                                @endif
                            </span>
                            @if($unread)
                                <span class="notif-popup__item-dot" aria-label="未読" title="未読"></span>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </section>
            @endforeach
        @else
            <div class="notif-popup__empty">
                <span class="notif-popup__empty-illust" aria-hidden="true">
                    <i class="far fa-bell-slash"></i>
                </span>
                <p class="notif-popup__empty-title">通知はまだありません</p>
                <p class="notif-popup__empty-desc">新しいメッセージやいいねを受け取るとここに表示されます。</p>
            </div>
        @endif

        {{-- フィルタ「未読」で1件もない場合の空表示（JSが表示制御） --}}
        <div class="notif-popup__empty notif-popup__empty--unread" data-notif-unread-empty hidden>
            <span class="notif-popup__empty-illust" aria-hidden="true">
                <i class="fas fa-envelope-open"></i>
            </span>
            <p class="notif-popup__empty-title">未読の通知はありません</p>
            <p class="notif-popup__empty-desc">全ての通知を確認済みです。</p>
        </div>
    </div>
</div>
