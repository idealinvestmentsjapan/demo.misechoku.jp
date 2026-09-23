@php
    $recruitUrl = Route::has('cast.shopprofile.show') ? route('cast.shopprofile.show', $item['id']) : '#';
    $hitokoto = trim((string) ($item['hitokoto'] ?? ''));
    $hitokotoTime = (string) ($item['hitokoto_updated_at'] ?? '');
    $nearestStation = trim((string) ($item['nearest_station'] ?? ''));
    $locationLine = $nearestStation !== ''
        ? $nearestStation
        : trim((string) (($item['pref'] ?? '') . ' ' . ($item['city'] ?? '')));
    $locationIcon = $nearestStation !== '' ? 'fa-train' : 'fa-map-marker-alt';
@endphp
<li class="connection-item connection-item--clickable connection-item--shop-rich tl-row tl-row--shop tl-row--tall">
    <a href="{{ $recruitUrl }}" class="connection-item__link tl-row__link">
        {{-- 四角サムネイル：行の高さいっぱいまで拡大 --}}
        <div class="tl-row__thumb-wrap tl-row__thumb-wrap--square">
            <img loading="lazy" decoding="async" src="{{ $item['main_img'] ?? asset('assets/images/common/no-image.png') }}" alt="" class="tl-row__thumb">
        </div>

        <div class="tl-row__body">
            {{-- 1行目：店舗名 +（業種を小文字で右に）+ 優良店バッヂ --}}
            <h3 class="tl-row__name">
                <span class="tl-row__name-text">{{ $item['shop_name'] }}</span>
                @if(!empty($item['industry_label']))
                    <span class="tl-row__industry-inline">{{ $item['industry_label'] }}</span>
                @endif
                @if(!empty($item['is_excellent'])) <x-ui.premium-badge size="sm" />@endif
                @php
                    $liHelpDates = array_values(array_filter((array) ($item['help_date_labels'] ?? [])));
                @endphp
                @if($liHelpDates !== [])
                    <span class="shop-avail-tag" aria-label="日付指定のヘルプ募集" style="margin-left:4px;">
                        <i class="fas fa-bolt" aria-hidden="true"></i> {{ implode('・', array_slice($liHelpDates, 0, 2)) }} ヘルプ募集
                    </span>
                @elseif(!empty($item['available_active']))
                    <span class="shop-avail-tag" aria-label="本日ヘルプ募集" style="margin-left:4px;">
                        <i class="fas fa-bolt" aria-hidden="true"></i> 本日ヘルプ募集
                    </span>
                @endif
            </h3>

            {{-- 2行目：評価レビュー → 最寄り駅 → 距離 --}}
            <div class="tl-row__meta">
                @if(!empty($item['rating_display']))
                    <span class="tl-row__rating">
                        <i class="fas fa-star" aria-hidden="true"></i><span class="tl-row__rating-val">{{ $item['rating_display'] }}</span>
                    </span>
                @endif
                @if($locationLine !== '')
                    <span class="tl-row__loc">
                        <i class="fas {{ $locationIcon }}" aria-hidden="true"></i>{{ $locationLine }}
                    </span>
                @endif
                @if(!empty($item['distance_label']))
                    <span class="tl-row__dist">
                        <i class="fas fa-route"></i>{{ $item['distance_label'] }}
                    </span>
                @endif
            </div>

            {{-- 3行目：ボーナス金 + 時給（洗練された給与カード） --}}
            @php
                $hourly = (int) ($item['hourly_wage'] ?? 0);
                $reward = (int) ($item['reward'] ?? 0);
            @endphp
            @if($hourly > 0 || $reward > 0)
                <div class="tl-pay">
                    @if($reward > 0)
                        <div class="tl-pay__cell tl-pay__cell--bonus">
                            <span class="tl-pay__label">ボーナス</span>
                            <span class="tl-pay__amount"><span class="tl-pay__yen">¥</span>{{ number_format($reward) }}</span>
                        </div>
                    @endif
                    @if($hourly > 0)
                        <div class="tl-pay__cell tl-pay__cell--wage">
                            <span class="tl-pay__label">時給</span>
                            <span class="tl-pay__amount"><span class="tl-pay__yen">¥</span>{{ number_format($hourly) }}<span class="tl-pay__unit">〜</span></span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- 4行目以降：ひとこと --}}
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
