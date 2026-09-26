@extends('layouts.app-v2')

@section('title', 'SWIPE')
@section('body-class', 'no-scroll page-home')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/home.css') }}?v=20260926-footer-safearea">
{{-- Perf / tier-chip / cssMode overrides live in a separate file; load AFTER home.css. --}}
<link rel="stylesheet" href="{{ asset('assets/css/home-perf.css') }}?v=20260809-perf-bundle">
@endpush

@php
    $itemType = $itemType ?? 'cast';
    $isShop = ($itemType === 'shop');
    $isRecruit = ($itemType === 'recruit');
    // 詳細ページ・トークルート・KEEP対象は「見ている側」で分岐。
    //   - 求人カード(cast側)：見ている店舗=詳細先、KEEP対象=shop
    //   - キャストカード(shop側)：見ているキャスト=詳細先、KEEP対象=cast
    $detailRoute = $isRecruit ? 'cast.shopprofile.show' : ($isShop ? 'cast.shopprofile.show' : 'shop.castprofileview.show');
    $talkRoute   = ($isRecruit || $isShop) ? 'cast.talk.room' : 'shop.talk.room';
    $keepItemType = $isRecruit ? 'shop' : ($isShop ? 'shop' : 'cast');
@endphp
@section('content')
<div id="home-screen" data-discovery-mode="{{ $itemType }}">
    {{-- Help recruitment ribbon (top-overlay, ~52px). Shop entry / Cast band. --}}
    @php $helpBand = $helpBand ?? null; @endphp
    @if($helpBand && ($helpBand['kind'] ?? '') === 'shop-entry')
        <a href="{{ route('shop.help-recruitment.index') }}" class="home-help-band home-help-band--shop">
            <span class="home-help-band__icon"><i class="fas fa-bolt"></i></span>
            <span class="home-help-band__text">
                <span class="home-help-band__title">ヘルプ募集を管理する</span>
                <span class="home-help-band__sub">
                    @if(!empty($helpBand['has_dates']))
                        {{ implode('・', $helpBand['dates']) }} で募集中
                    @else
                        募集日を選ぶとキャストの検索・SWIPE で優先表示
                    @endif
                </span>
            </span>
            <span class="home-help-band__chev"><i class="fas fa-chevron-right"></i></span>
        </a>
    @elseif($helpBand && ($helpBand['kind'] ?? '') === 'cast-band' && !empty($helpBand['shops']))
        <div class="home-help-band home-help-band--cast">
            <div class="home-help-band__header">
                <span class="home-help-band__badge">
                    <i class="fas fa-bolt"></i>
                    <span>本日・明日ヘルプ募集中 <span class="home-help-band__count">{{ count($helpBand['shops']) }}</span></span>
                </span>
                <a href="{{ route('cast.search.index', ['tab' => 'search']) }}" class="home-help-band__more">全部見る <i class="fas fa-chevron-right"></i></a>
            </div>
            <ul class="home-help-band__chips">
                @foreach($helpBand['shops'] as $s)
                    <li class="home-help-band__chip">
                        <a href="{{ route('cast.shopprofile.show', ['id' => $s['id']]) }}" class="home-help-band__chip-link">
                            <img src="{{ $s['image'] }}" alt="" class="home-help-band__chip-img" loading="lazy">
                            <span class="home-help-band__chip-info">
                                <span class="home-help-band__chip-date">{{ $s['date_label'] }}</span>
                                <span class="home-help-band__chip-name">{{ $s['name'] }}</span>
                                @if($s['wage_label'] !== '')
                                    <span class="home-help-band__chip-wage">{{ $s['wage_label'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- メインスワイパー（上下） --}}
    <div class="main-swiper swiper">
        <div class="swiper-wrapper">
            @foreach($items as $item)
            {{-- ================================================================
                 カード共通スケルトン（画像=上65% / 情報=下35%・両カード同じ構造）
                 - スワイプ操作性を店舗ホームとキャストホームで揃えるため、
                   写真スワイパーは常に上部65%に限定し、下部35%は縦スワイプ専用ゾーンに。
                 - 表示項目・ボタンだけが役割によって切り替わる（$isRecruit / $isShop）
                 ================================================================ --}}
            @php
                $locationLabel = trim((string) ($item['location_label'] ?? ''));
                $isPassport    = ($item['location_mode'] ?? '') === 'passport';
                $stationLine   = trim((string) ($item['nearest_station'] ?? ''));
                $areaLine      = trim(($item['pref'] ?? '') . ' ' . ($item['city'] ?? ''));
                $hasRating     = !empty($item['rating']) && $item['rating'] > 0;
                $trialR        = $item['trial_hourly_range']  ?? null;
                $helpR         = $item['help_hourly_range']   ?? null;
                $bonusRg       = $item['signup_bonus_range']  ?? null;
                $mo            = $item['manager_overlay']     ?? ['show' => false];
            @endphp
            <div class="swiper-slide cast-card glass-card {{ $isRecruit ? 'cast-card--recruit' : 'cast-card--profile' }}">

                {{-- 1. 画像エリア（両カード共通・上部65%） --}}
                <div class="rc-img-wrap home-photo-wrap" data-detail-url="{{ route($detailRoute, $item['id']) }}">
                    @include('shops.home.partials._photo-swiper', [
                        'images'    => $item['images'] ?? [],
                        'altName'   => $item['name'],
                        'isRecruit' => $isRecruit,
                    ])

                    {{-- 求人カードのみ：店長からのメッセージ（画像上部オーバーレイ） --}}
                    @if($isRecruit && !empty($mo['show']))
                        <div class="rc-manager-msg" aria-label="キャッチコピー">
                            <div class="rc-manager-msg__backdrop">
                                <div class="rc-manager-msg__inner">
                                    @if(!empty($mo['line1_html']))
                                        <p class="rc-manager-msg__line1">{!! $mo['line1_html'] !!}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- 画像下端を黒に溶かすグラデーション（両カード共通） --}}
                    <div class="rc-img-gradient" aria-hidden="true"></div>
                </div>

                {{-- 2. 情報エリア（両カード共通・下部35%） --}}
                <div class="rc-bottom-bar" aria-label="{{ $isRecruit ? '店舗情報' : 'プロフィール' }}">
                    <div class="rc-bottom-bar__stack">

                        {{-- 求人カードのみ：優良店バッヂ + 日付指定ヘルプ募集 --}}
                        @php
                            $helpDateLabels = array_values(array_filter((array) ($item['help_date_labels'] ?? [])));
                        @endphp
                        @if($isRecruit && (!empty($item['is_premium']) || $helpDateLabels !== []))
                            <div class="rc-premium-row" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                @if(!empty($item['is_premium']))
                                    <button type="button" class="premium-badge-btn stop-propagation" data-open-premium-info aria-haspopup="dialog" aria-controls="modal-premium-info" aria-label="優良店バッヂの達成条件">
                                        <x-ui.premium-badge />
                                    </button>
                                @endif
                                @if($helpDateLabels !== [])
                                    <span class="shop-avail-tag" aria-label="日付指定のヘルプ募集">
                                        <i class="fas fa-bolt" aria-hidden="true"></i> {{ implode('・', array_slice($helpDateLabels, 0, 3)) }} ヘルプ募集
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- キャストカードのみ：Tier チップ（今すぐ入れる / オンライン中） --}}
                        @if(!$isRecruit && !$isShop)
                            @include('shops.home.partials._tier-chip', ['tierChipItem' => $item])
                        @endif

                        {{-- 1行目：名前 + KEEP（両カード共通） --}}
                        <div class="rc-name-row">
                            <h2 class="rc-shop-name serif-font">{{ $item['name'] }}@if(!$isRecruit && !$isShop && isset($item['age']))<span class="age">({{ $item['age'] }})</span>@endif</h2>
                            @include('shops.home.partials._keep-button', [
                                'itemId'   => $item['id'],
                                'itemType' => $keepItemType,
                                'isKept'   => !empty($item['is_kept']),
                            ])
                        </div>

                        {{-- 2行目：メタ情報（表示内容だけ役割で分岐） --}}
                        <div class="rc-line rc-line--meta">
                            @if($isRecruit)
                                @if(!empty($item['industry_name']))
                                    <span class="rc-genre">{{ $item['industry_name'] }}</span>
                                @endif
                                <span class="rc-loc">
                                    <i class="fas {{ $stationLine !== '' ? 'fa-train' : 'fa-map-marker-alt' }}" aria-hidden="true"></i>{{ $stationLine !== '' ? $stationLine : ($areaLine !== '' ? $areaLine : 'エリア未設定') }}
                                </span>
                                @if(!empty($item['distance_label']))
                                    <span class="rc-dist"><i class="fas fa-route" aria-hidden="true"></i>自分から {{ $item['distance_label'] }}</span>
                                @endif
                                @if($hasRating)
                                    <span class="rc-rating-inline">
                                        <span class="rc-star" aria-hidden="true">★</span>{{ number_format((float)$item['rating'], 1) }}@if((int)($item['review_count'] ?? 0) > 0)<span class="rc-review-cnt">({{ (int)$item['review_count'] }}件)</span>@endif
                                    </span>
                                @endif
                            @else
                                @if(!empty($item['industry_name']))
                                    <span class="rc-genre">{{ $item['industry_name'] }}</span>
                                @endif
                                @if($locationLabel !== '')
                                    <span class="rc-loc {{ $isPassport ? 'rc-loc--passport' : '' }}"
                                          @if($isPassport) title="パスポートモード：本人が指定した位置" @endif>
                                        <i class="fas {{ $isPassport ? 'fa-plane-departure' : 'fa-map-marker-alt' }}" aria-hidden="true"></i>{{ $locationLabel }}
                                    </span>
                                @endif
                                @if(!$isShop && !empty($item['night_work_label']))
                                    <span class="cc-exp {{ $item['night_work_label'] === '経験あり' ? 'is-exp' : '' }}">{{ $item['night_work_label'] }}</span>
                                @endif
                            @endif
                        </div>

                        {{-- 3行目：役割別の補足情報 --}}
                        @if($isRecruit)
                            {{-- 求人カード：ボーナス金 + 体入・ヘルプ時給の一体スレート --}}
                            <div class="pslate">
                                <div class="pslate__bonus">
                                    <span class="pslate__label">ボーナス金</span>
                                    <span class="pslate__amount">
                                        @if(!empty($bonusRg))<span class="pslate__yen">¥</span>{{ number_format((int)$bonusRg['lo']) }}@if((int)$bonusRg['hi'] > (int)$bonusRg['lo'])〜@endif
                                        @else —
                                        @endif
                                    </span>
                                </div>
                                <span class="pslate__divider" aria-hidden="true"></span>
                                <div class="pslate__wages">
                                    <div class="pslate__wage">
                                        <span class="pslate__label">体入時給</span>
                                        <span class="pslate__wage-amount">@if(!empty($trialR))¥{{ number_format((int)$trialR['lo']) }}〜@else —@endif</span>
                                    </div>
                                    <div class="pslate__wage">
                                        <span class="pslate__label">ヘルプ時給</span>
                                        <span class="pslate__wage-amount">@if(!empty($helpR))¥{{ number_format((int)$helpR['lo']) }}〜@else —@endif</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- キャスト/店舗プロフィールカード：距離 + タグ --}}
                            @if(!empty($item['distance_label']))
                                <div class="cc-line cc-line--dist">
                                    <span class="rc-dist"><i class="fas fa-route" aria-hidden="true"></i>自分から {{ $item['distance_label'] }}</span>
                                </div>
                            @endif
                            @if(!empty($item['tags']))
                                <div class="card-tags-row">
                                    @foreach($item['tags'] as $tag)
                                        <span class="tag-pill">#{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        {{-- 4行目：トークCTA（両カード共通・採用フローの起点として最強調） --}}
                        @include('shops.home.partials._talk-cta', [
                            'talkRoute' => $talkRoute,
                            'itemId'    => $item['id'],
                        ])
                    </div>
                </div>

                {{-- 3. スワイプ誘導ガイド（両カード共通） --}}
                <div class="discovery-swipe-guide" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="18 15 12 9 6 15"/>
                    </svg>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 優良店バッヂの達成条件モーダル（スワイプカードのバッヂタップで開く） --}}
    <div id="modal-premium-info" class="premium-info-modal" hidden role="dialog" aria-modal="true" aria-labelledby="premium-info-title">
        <div class="premium-info-modal__overlay" data-close-premium-info></div>
        <div class="premium-info-modal__panel">
            <button type="button" class="premium-info-modal__close" data-close-premium-info aria-label="閉じる">×</button>
            <h3 id="premium-info-title" class="premium-info-modal__title">
                <i class="fas fa-crown" aria-hidden="true"></i> 優良店バッヂの獲得条件
            </h3>
            <ul class="premium-info-modal__list">
                <li>すべての案件が「店舗入金確認済み」まで完了している</li>
                <li>請求書発行から店舗入金確認までが10日以内である</li>
            </ul>
            <p class="premium-info-modal__note">※ 条件は毎月見直され、基準を満たさなくなった場合はバッヂ表示が外れることがあります。</p>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        var modal = document.getElementById('modal-premium-info');
        if (!modal) return;
        function open(e) { if (e) { e.preventDefault(); e.stopPropagation(); } modal.hidden = false; document.body.style.overflow = 'hidden'; }
        function close() { modal.hidden = true; document.body.style.overflow = ''; }
        document.addEventListener('click', function (e) {
            var trg = e.target.closest('[data-open-premium-info]');
            if (trg) open(e);
        }, true);
        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-close-premium-info]')) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
    })();
    </script>
    @endpush

    {{-- 上下スワイプガイド：上向きに流れるシェブロン + ラベル。
         表示から数秒はしっかり見せ、その後は薄く残る（スワイプ操作で非表示） --}}
    <div class="swipe-updown-guide" id="swipe-updown-guide" aria-hidden="true">
        <span class="swipe-updown-guide__chevs">
            <i class="fas fa-chevron-up"></i>
            <i class="fas fa-chevron-up"></i>
        </span>
        <span class="swipe-updown-guide__label">上にスワイプで次へ</span>
    </div>

    @push('scripts')
    <script>
    {{-- 上下スワイプガイド：一度でも縦スワイプしたら消す（学習済みユーザーへのノイズ防止） --}}
    (function () {
        var guide = document.getElementById('swipe-updown-guide');
        var screen = document.getElementById('home-screen');
        if (!guide || !screen) return;
        var startY = null;
        function dismiss() {
            guide.classList.add('is-dismissed');
            screen.removeEventListener('touchstart', onStart);
            screen.removeEventListener('touchmove', onMove);
            screen.removeEventListener('wheel', dismiss);
        }
        function onStart(e) {
            startY = e.touches && e.touches[0] ? e.touches[0].clientY : null;
        }
        function onMove(e) {
            if (startY === null || !e.touches || !e.touches[0]) return;
            if (Math.abs(e.touches[0].clientY - startY) > 40) dismiss();
        }
        screen.addEventListener('touchstart', onStart, { passive: true });
        screen.addEventListener('touchmove', onMove, { passive: true });
        screen.addEventListener('wheel', dismiss, { passive: true });
    })();
    </script>
    @endpush

    {{-- スワイプ操作オンボーディング（初回・久しぶりのみ表示） --}}
    <div class="home-onboarding-overlay" id="home-swipe-onboarding" aria-hidden="true">
        <div class="home-onboarding-inner">
            <div class="home-onboarding-header">
                <span class="home-onboarding-kicker">SWIPE GUIDE</span>
                <h2 class="home-onboarding-title">スワイプで直感的にチェック</h2>
                <p class="home-onboarding-lead">まずはホームの操作だけ覚えればOKです。</p>
            </div>
            <div class="home-onboarding-body">
                <div class="home-onboarding-row">
                    <span class="home-onboarding-icon">↑↓</span>
                    <span class="home-onboarding-desc">上下で次 / 前のアカウントへ</span>
                </div>
                <div class="home-onboarding-row">
                    <span class="home-onboarding-icon">←→</span>
                    <span class="home-onboarding-desc">左右で同じアカウントの別写真へ</span>
                </div>
                <div class="home-onboarding-row">
                    <span class="home-onboarding-icon">TAP</span>
                    <span class="home-onboarding-desc">{{ $isRecruit ? 'タップで求人詳細を表示' : 'タップでプロフィール詳細を表示' }}</span>
                </div>
            </div>
            <div class="home-onboarding-footer">
                画面をタップしてガイドを閉じる
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/home.js') }}?v=20260811-relayout-on-return"></script>
{{-- LIKE / KEEP の共通トグル（全画面この1本に統一） --}}
<script src="{{ asset('assets/js/favorite-quick.js') }}?v=20260926-keep-rename"></script>
@endpush
