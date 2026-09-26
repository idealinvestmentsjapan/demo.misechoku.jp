@php
    $profileUrl = Route::has('shop.castprofileview.show') ? route('shop.castprofileview.show', $item['id']) : '#';
    $hitokoto = trim((string) ($item['hitokoto'] ?? ''));
    $hitokotoTime = (string) ($item['hitokoto_updated_at'] ?? '');
    $area = trim(implode(' ', array_filter([(string) ($item['pref'] ?? ''), (string) ($item['city'] ?? '')])));
@endphp
<li class="connection-item connection-item--clickable connection-item--search connection-item--shop-cast-list tl-row tl-row--cast">
    <a href="{{ $profileUrl }}" class="connection-item__link tl-row__link">
        {{-- 丸型アイコン --}}
        <div class="tl-row__thumb-wrap">
            <img loading="lazy" decoding="async" src="{{ $item['img'] ?? asset('assets/images/common/no-image.png') }}" alt="" class="tl-row__thumb">
        </div>

        <div class="tl-row__body">
            {{-- 1行目：名前 + 年齢（キープタブと同じく紫文字で名前の隣に併記） --}}
            <h3 class="tl-row__name">
                {{ $item['name'] ?? '' }}
                @if(!empty($item['age']))
                    <span class="tl-row__age">({{ $item['age'] }})</span>
                @endif
            </h3>

            {{-- 2行目：位置・距離 --}}
            <div class="tl-row__meta">
                @if($area !== '')
                    <span class="tl-row__loc">
                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ $area }}
                    </span>
                @endif
                @if(!empty($item['distance_label']))
                    <span class="tl-row__dist">
                        <i class="fas fa-route"></i>{{ $item['distance_label'] }}
                    </span>
                @endif
            </div>

            {{-- 3行目以降：ひとこと --}}
            @if($hitokoto !== '')
                <p class="tl-row__msg">{{ preg_replace('/\s+/u', ' ', $hitokoto) }}</p>
            @endif

            {{-- 最下部：控えめな最終更新（あればマッチ度も） --}}
            @if($hitokotoTime !== '' || !empty($item['match_summary']))
                <div class="tl-row__foot">
                    @if(!empty($item['match_summary']) && ($sort ?? '') === 'relevance')
                        <span class="tl-row__match" title="{{ implode(' / ', (array) ($item['match_reasons'] ?? [])) }}">
                            <i class="fas fa-bullseye" aria-hidden="true"></i>{{ $item['match_summary'] }}
                        </span>
                    @endif
                    @if($hitokotoTime !== '')
                        <time class="tl-row__time">{{ $hitokotoTime }}</time>
                    @endif
                </div>
            @endif
        </div>
    </a>
</li>
