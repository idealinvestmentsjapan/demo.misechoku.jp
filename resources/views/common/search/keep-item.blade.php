@php
    $profileRoute = $profileRoute ?? 'shop.castprofileview.show';
    $isCastPortal = (bool) ($isCastPortal ?? false);
    // ポータル別：店舗ポータルは「キャスト」を keep（item_type=cast）、キャストポータルは「お店」を keep（item_type=shop）
    $itemType = $isCastPortal ? 'shop' : 'cast';
    $locLine = trim((string) (($c['pref'] ?? '') . ($c['city'] ?? '')));
@endphp
<li class="connection-item connection-item--clickable tl-row tl-row--{{ $itemType }} tl-row--has-actions" data-fav-remove-on-deactivate>
    <a href="{{ route($profileRoute, $c['id']) }}" class="connection-item__link tl-row__link">
        <div class="tl-row__thumb-wrap">
            <img loading="lazy" decoding="async" src="{{ $c['img'] ?? asset('assets/images/common/no-image.png') }}"
                 alt="" class="tl-row__thumb"
                 onerror="this.onerror=null; this.src='{{ asset('assets/images/common/user-default.svg') }}'">
        </div>
        <div class="tl-row__body">
            <h3 class="tl-row__name">
                {{ $c['name'] ?? '' }}
                @if(!empty($c['age']))
                    <span class="tl-row__age">({{ $c['age'] }})</span>
                @endif
            </h3>
            @if($locLine !== '')
                <div class="tl-row__meta">
                    <span class="tl-row__loc"><i class="fas fa-map-marker-alt"></i>{{ $locLine }}</span>
                </div>
            @endif
            @if(!empty($c['updated_at']))
                <div class="tl-row__kept-at">
                    <i class="fas fa-bookmark" aria-hidden="true"></i>{{ $c['updated_at'] }}にキープ
                </div>
            @endif
        </div>
    </a>
    <div class="tl-row__actions" aria-label="クイックアクション">
        <button type="button" class="tl-row__action-btn tl-row__action-btn--keep"
                data-fav-toggle data-action="keep" data-item-type="{{ $itemType }}" data-item-id="{{ $c['id'] }}"
                aria-label="キープを解除" aria-pressed="true">
            <i class="fas fa-bookmark" aria-hidden="true"></i>
        </button>
    </div>
</li>
