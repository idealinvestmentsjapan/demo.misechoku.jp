{{-- キャストプロフィール本文：MyPage と同じ Instagram 風デザイン（GALLERY / DETAILS タブ） --}}
@php
    $isOwn = $isOwn ?? false;
    $showInteractionActions = $showInteractionActions ?? true;
    $profileImages = !empty($cast['images']) ? array_values(array_filter((array) $cast['images'])) : [];
    if (empty($profileImages) && !empty($cast['img'])) {
        $profileImages = [$cast['img']];
    }
    $iconImage = $profileImages[0] ?? asset('assets/images/common/no-image.png');
    $castDisplayName = $cast['nickname'] ?? $cast['name'] ?? 'キャスト';
    $age = $cast['age'] ?? null;
    $intro = trim((string) ($cast['intro'] ?? $cast['pr'] ?? ''));
    $introTrunc = mb_substr($intro, 0, 80) . (mb_strlen($intro) > 80 ? '…' : '');
    $viewCount = (int) ($cast['view_cnt'] ?? 0);
    $location = trim(implode(' / ', array_filter([$cast['pref'] ?? null, $cast['city'] ?? null])));
    $totalSlots = max(9, count($profileImages));
@endphp

<div class="pb-6">

    <div class="px-5 pt-4 pb-6">

        {{-- ===== ヘッダー：アイコン + ひとこと吹き出し（MyPage / 店舗プロフィールと同じ形式） ===== --}}
        @php
            $word = trim((string) ($cast['word'] ?? ''));
            $bubbleText = $word !== '' ? $word : $intro;
            $bubbleTrunc = $bubbleText !== ''
                ? (mb_strlen($bubbleText) > 90 ? mb_substr($bubbleText, 0, 90) . '…' : $bubbleText)
                : '';
        @endphp
        <div class="flex items-start gap-3 mb-3">
            <div class="w-[84px] h-[84px] rounded-full overflow-hidden border-2 border-line-accent/40 shadow-card-3d bg-surface-from shrink-0">
                <img src="{{ $iconImage }}" alt="" class="w-full h-full object-cover">
            </div>
            @if($bubbleTrunc !== '')
                <div class="flex-1 min-w-0">
                    <div class="relative min-h-[84px] flex flex-col justify-center bg-gradient-to-br from-surface-from to-base border border-line-accent/40 rounded-2xl shadow-card-3d px-3 py-2.5">
                        {{-- 吹き出しのしっぽ（アイコン側に向く） --}}
                        <span class="absolute top-5 -left-[8px] w-0 h-0 border-y-[8px] border-y-transparent border-r-[10px] border-r-line-accent/40"></span>
                        <span class="absolute top-5 -left-[6px] w-0 h-0 border-y-[7px] border-y-transparent border-r-[9px] border-r-surface-from"></span>
                        <p class="text-[13px] leading-relaxed text-text-main">
                            {{ $bubbleTrunc }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ===== 名前/年齢（右端: 共有・KEEP のアイコンのみ）+ 場所/閲覧数 ===== --}}
        @php $favCastId = (string) ($cast['id'] ?? $cast['cast_id'] ?? ''); @endphp
        <div class="mb-4 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <h1 class="app-title text-[22px] text-text-main leading-tight truncate min-w-0">
                    {{ $castDisplayName }}@if($age)<span class="text-[16px] text-text-sub ml-1">({{ $age }})</span>@endif
                </h1>
                @if(!$isOwn && $showInteractionActions)
                    <div class="profile-inline-actions shrink-0" id="profile-inline-actions">
                        @if(!empty($shareUrl))
                            @include('partials.share-menu', [
                                'shareUrl' => $shareUrl,
                                'shareTitle' => $shareTitle ?? ($castDisplayName . 'のプロフィール'),
                                'shareText' => $shareText ?? $intro,
                                'menuId' => 'cast-share-menu',
                            ])
                        @endif
                        <button type="button" id="btn-profile-keep"
                                class="fav-circle fav-circle--keep"
                                data-fav-toggle data-action="keep" data-item-type="cast" data-item-id="{{ $favCastId }}"
                                aria-label="キープ" aria-pressed="{{ ($cast['is_kept'] ?? false) ? 'true' : 'false' }}">
                            <i class="fas fa-bookmark" aria-hidden="true"></i>
                        </button>
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-[12px] text-text-sub">
                @if($location !== '')
                    <span class="inline-flex items-center gap-1">
                        <i class="fas fa-map-marker-alt text-[10px]"></i>{{ $location }}
                    </span>
                @endif
                @if(!empty($distanceLabel ?? null))
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent/10 border border-line-accent/30 text-accent-text text-[10.5px] font-bold">
                        <i class="fas fa-route text-[9px]"></i> {{ $distanceLabel }}
                    </span>
                @endif
                {{-- 閲覧数：位置情報の隣に表示（0 も出さないほうがすっきり） --}}
                @if($viewCount > 0)
                    <x-ui.view-count :count="$viewCount" class="text-[12px] text-text-sub" />
                @endif
            </div>
        </div>

        @if($isOwn)
            <a href="{{ route('cast.profile.edit') }}"
               class="flex w-full items-center justify-center gap-2 px-6 py-3 rounded-full font-bold bg-accent text-on-accent shadow-[0_4px_12px_rgba(0,0,0,0.4)] active:scale-[0.98] transition-transform duration-150 mb-5">
                <i class="fas fa-pen"></i> プロフィール編集
            </a>
        @endif
    </div>

    {{-- ===== Tabs：GALLERY / PROFILE（キャスト MyPage と同じ 2 タブ構成）=====
         2026-09-27: 相手プロフィール画面を MyPage と同じタブ式に統一。
         キャストには「求人票」がないため中段タブは無し（cast.mypage の GALLERY/PROFILE と対称）。 --}}
    <div data-tabs-scope>
        <div data-tabs class="border-t border-b border-line-accent/40 bg-base/90 backdrop-blur-md sticky top-0 z-10">
            <div class="flex">
                <button type="button" data-tab="gallery"
                        class="is-active flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-images text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">GALLERY</span>
                </button>
                <button type="button" data-tab="profile"
                        class="flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-address-card text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">PROFILE</span>
                </button>
            </div>
        </div>

        {{-- ========== GALLERY ========== --}}
        <div data-tab-panel="gallery" class="is-active">
            @if(count($profileImages) > 0)
                <ul id="profile-gallery-list">
                    @foreach($profileImages as $i => $img)
                        <li class="profile-gallery-item">
                            <button type="button" class="profile-gallery-slot has-img js-lightbox-target" data-image-url="{{ $img }}" aria-label="写真 {{ $i + 1 }} を拡大">
                                <img src="{{ $img }}" alt="" loading="lazy">
                                @if($i === 0)<span class="profile-gallery-badge">MAIN</span>@endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="p-8 text-center text-text-sub text-[13px]">
                    <i class="far fa-image text-2xl mb-2 block opacity-50"></i>
                    写真はまだ登録されていません
                </div>
            @endif
        </div>

        {{-- ========== PROFILE：スペック・自己PR・接客タイプ・キャリア ========== --}}
        <div data-tab-panel="profile">
            <div class="p-4 flex flex-col gap-4">

                @php
                    // 全項目まとめて判定して、DETAILS が完全に空のときは "まだプロフィール未入力" を出す
                    $hasBirth   = !empty($cast['birth_year']) && !empty($cast['birth_month']) && !empty($cast['birth_day']);
                    $hasHtWt    = !empty($cast['height']) || !empty($cast['weight']);
                    $hasBwh     = !empty($cast['bust'] ?? $cast['b'] ?? null)
                                || !empty($cast['waist'] ?? $cast['w'] ?? null)
                                || !empty($cast['hip'] ?? $cast['h'] ?? null);
                    $hasIntro   = $intro !== '';
                    $hasBasic   = $hasBirth || $hasHtWt || $hasBwh || $hasIntro;
                    $bwhReal    = trim(implode(' / ', array_filter([
                        ($cast['bust']  ?? $cast['b'] ?? '') ?: null,
                        ($cast['waist'] ?? $cast['w'] ?? '') ?: null,
                        ($cast['hip']   ?? $cast['h'] ?? '') ?: null,
                    ])));
                @endphp
                @if($hasBasic)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-user-circle"></i> BASIC
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if($hasBirth)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">生年月日</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $cast['birth_year'] }}年{{ $cast['birth_month'] }}月{{ $cast['birth_day'] }}日</span>
                                </div>
                            @endif
                            @if($hasHtWt)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">身長 / 体重</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">
                                        @php
                                            $htwt = array_filter([
                                                !empty($cast['height']) ? $cast['height'] . 'cm' : null,
                                                !empty($cast['weight']) ? $cast['weight'] . 'kg' : null,
                                            ]);
                                        @endphp
                                        {{ implode(' / ', $htwt) }}
                                    </span>
                                </div>
                            @endif
                            @if($hasBwh)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">B / W / H</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $bwhReal }}</span>
                                </div>
                            @endif
                            @if($hasIntro)
                                <div class="flex flex-col gap-1">
                                    <span class="text-[12px] text-text-sub font-medium">自己PR</span>
                                    <span class="text-[13px] font-medium text-text-main leading-relaxed">{!! nl2br(e($intro)) !!}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                @php
                    $hasPtype  = !empty($cast['personality_type']);
                    $hasLooks  = !empty($cast['my_field']);
                    $hasInner  = !empty($cast['my_inner_skills']);
                    $hasStyle  = $hasPtype || $hasLooks || $hasInner;
                @endphp
                @if($hasStyle)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-tags"></i> STYLE &amp; TAGS
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if($hasPtype)
                                {{-- 接客タイプ：目立つカード（タップで解説。他者表示なので再診断導線なし） --}}
                                @include('casts.profile.parts.personality-type', [
                                    'typeCode'  => $cast['personality_type'],
                                    'canRetest' => false,
                                ])
                            @endif
                            @if($hasLooks)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">ルックス</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $cast['my_field'] }}</span>
                                </div>
                            @endif
                            @if($hasInner)
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-text-sub font-medium">性格・内面</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $cast['my_inner_skills'] }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                @php
                    $profession = trim((string) ($cast['profession'] ?? $cast['current_job'] ?? ''));
                    $industryNames = trim((string) ($cast['industry_names'] ?? $cast['desired_job'] ?? ''));
                    $hasNight  = !empty($cast['night_work_label']);
                    $hasCareer = $hasNight || $profession !== '' || $industryNames !== '';
                @endphp
                @if($hasCareer)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-briefcase"></i> CAREER
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if($hasNight)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">ナイトワーク経験</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $cast['night_work_label'] }}</span>
                                </div>
                            @endif
                            @if($profession !== '')
                                <div class="flex justify-between items-start gap-3 border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium shrink-0">現職業</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{!! nl2br(e($profession)) !!}</span>
                                </div>
                            @endif
                            @if($industryNames !== '')
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-text-sub font-medium">希望職種</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $industryNames }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                @if(!$hasBasic && !$hasStyle && !$hasCareer)
                    <div class="p-8 text-center text-text-sub text-[13px]">
                        <i class="far fa-address-card text-2xl mb-2 block opacity-50"></i>
                        プロフィール項目はまだ登録されていません
                    </div>
                @endif

                @if($isOwn)
                    <a href="{{ route('cast.mypage.reviews') }}"
                       class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full font-bold border border-line-accent/40 bg-base/60 text-text-main hover:bg-accent/10 transition-all duration-300">
                        <i class="fas fa-star"></i> レビュー一覧を見る
                    </a>
                @endif

            </div>
        </div>
    </div>

</div>

{{-- ライトボックス：Swiper で複数画像の左右スワイプ／矢印切替に対応 --}}
@if(count($profileImages) > 0)
<div id="lightbox-overlay" class="lightbox-overlay" onclick="closeLightbox(event)">
    <div class="swiper lightbox-swiper" onclick="event.stopPropagation()">
        <div class="swiper-wrapper">
            @foreach($profileImages as $i => $img)
                <div class="swiper-slide"><img src="{{ $img }}" alt="" class="lightbox-image"></div>
            @endforeach
        </div>
        @if(count($profileImages) > 1)
            <div class="swiper-button-prev" aria-label="前の写真"></div>
            <div class="swiper-button-next" aria-label="次の写真"></div>
            <div class="swiper-pagination"></div>
        @endif
    </div>
    <button type="button" class="lightbox-close" aria-label="閉じる" onclick="closeLightbox(event)">
        <i class="fas fa-times"></i>
    </button>
</div>
@endif

{{-- 画面下部固定の「トークする」バー（店舗→キャスト） --}}
@php $fixedTalkCastId = (string) ($cast['id'] ?? $cast['cast_id'] ?? ''); @endphp
@if($fixedTalkCastId !== '' && Route::has('shop.talk.room'))
    <div class="profile-talk-bar" role="complementary" aria-label="トークを開始">
        <a href="{{ route('shop.talk.room', ['id' => $fixedTalkCastId, 'talk_topic' => 'other', 'initiate' => 1]) }}"
           class="profile-talk-bar__btn">
            <i class="fas fa-comment-dots" aria-hidden="true"></i> トークする
        </a>
    </div>
@endif
