{{-- 店舗プロフィール：cast 側からの閲覧専用ビュー（MyPage 同様の Instagram 風） --}}
@php
    $shopName     = $shop['name'] ?? ($recruit['store_name'] ?? '店舗');
    $iconImage    = $shop['main_img'] ?? ($galleryImages[0] ?? asset('assets/images/common/no-image.png'));
    $catchCopy    = trim((string) ($recruit['catch_copy'] ?? ''));
    $totalSlots   = max(9, count($galleryImages));
    $viewCount    = (int) ($recruit['view_cnt'] ?? $shop['view_cnt'] ?? 0);
    $ctaShopId    = $shop['id'] ?? $shop['shop_id'] ?? $recruit['id'] ?? $recruit['shop_id'] ?? null;
    $ctaHasHelp   = $usesJobTypes
        ? !empty($recruit_help['help_hourly_wage']) || !empty($recruit_help['hourly_wage'])
        : !empty($recruit['help_hourly_wage']);
    $ctaHasTrial  = $usesJobTypes
        ? !empty($recruit_trial['trial_hourly_wage']) || !empty($recruit_trial['hourly_wage'])
        : (!empty($recruit['trial_hourly_wage']) || $regularWage > 0);
    $wageFormat = function (?int $wage, ?int $wageMax) {
        if (!$wage && !$wageMax) return null;
        if ($wage && $wageMax && $wageMax > $wage) return '¥' . number_format($wage) . ' 〜 ¥' . number_format($wageMax);
        return '¥' . number_format($wage ?: $wageMax);
    };
    $regularWageDisp = $wageFormat($regularWage ?: null, $regularWageMax);
    $trialWageDisp   = $wageFormat((int) ($recruit['trial_hourly_wage'] ?? 0) ?: null, $trialWageMax);
    $helpWageDisp    = $wageFormat((int) ($recruit['help_hourly_wage'] ?? 0) ?: null, $helpWageMax);
    $businessHours   = trim((string) ($recruit['business_hours'] ?? $shop['business_hours_shop'] ?? ''));
    $nearestStation  = trim((string) ($shop['nearest_station'] ?? ''));
    $workingHours    = trim((string) ($recruit['working_hours'] ?? ''));
    $distanceText    = trim((string) ($distanceLabel ?? ''));

    // ----- 店舗プロファイル系の追加情報 -----
    $industryName    = trim((string) ($shop['industry_name'] ?? ''));
    $shopWord        = trim((string) ($shop['word'] ?? ''));
    $shopConcept     = trim((string) ($shop['concept'] ?? ''));
    $openDate        = trim((string) ($recruit['open_date'] ?? ''));
    $shopTagGroups   = isset($tagGroups) && is_array($tagGroups) ? $tagGroups : (array) ($shop['tag_groups'] ?? []);

    // ----- 求人系（勤務条件）の追加情報 -----
    $workingDays     = trim((string) ($recruit['working_days'] ?? ''));
    $regularHoliday  = trim((string) ($recruit['regular_holiday'] ?? ''));
    $qualification   = trim((string) ($recruit['qualification'] ?? ''));
    $jobContent      = trim((string) ($recruit['job_content'] ?? ''));
    $bonusRemarks    = trim((string) ($recruit['bonus_remarks'] ?? ''));
    $bonusDaysLocal  = trim((string) ($recruit['bonus_total_working_days'] ?? $recruit['bonus_working_days'] ?? ''));
    $bonusHoursLocal = trim((string) ($recruit['bonus_total_working_hours'] ?? $recruit['bonus_working_hours'] ?? ''));
    $bonusExtraLocal = trim((string) ($recruit['bonus_other_conditions'] ?? $recruit['bonus_condition'] ?? ''));
    $storeAtmosphere = trim((string) ($recruit['store_atmosphere'] ?? ''));
    $catchCopyMain   = $catchCopy;

    // ----- ヘッダー用：アピールひとこと（hitokoto を最優先、なければ catch_copy） -----
    $appealLine      = $shopWord !== '' ? $shopWord : $catchCopy;
    $appealTrunc     = $appealLine !== ''
        ? (mb_strlen($appealLine) > 90 ? mb_substr($appealLine, 0, 90) . '…' : $appealLine)
        : '';

    // ----- 優良店バッヂ / レビュー（キャストにも公開） -----
    $isPremiumShop = !empty($shop['is_premium']);
    $reviewAvg     = (float) ($shop['review_avg'] ?? 0);
    $reviewCount   = (int) ($shop['review_count'] ?? 0);
    $shopReviews   = (array) ($shop['reviews'] ?? []);

    // ----- ヘッダー用：最有力の時給表示（本入り→体入の順で採用） -----
    $primaryWageDisp = $regularWageDisp ?: ($trialWageDisp ?: $helpWageDisp);
    $primaryWageLabel = $regularWageDisp
        ? '本入り時給'
        : ($trialWageDisp ? '体入時給' : ($helpWageDisp ? 'ヘルプ時給' : '時給'));
@endphp

<div class="pb-6">

    <div class="px-5 pt-4 pb-5">

        {{-- 1. アバター + ひとこと吹き出し（キャスト MyPage と同じ吹き出し式） --}}
        <div class="flex items-start gap-3 mb-3">
            <div class="w-[84px] h-[84px] rounded-2xl overflow-hidden border-2 border-line-accent/40 shadow-card-3d bg-surface-from shrink-0">
                <img src="{{ $iconImage }}" alt="" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 min-w-0">
                <div class="relative min-h-[84px] flex flex-col justify-center bg-gradient-to-br from-surface-from to-base border border-line-accent/40 rounded-2xl shadow-card-3d px-3 py-2.5">
                    {{-- 吹き出しのしっぽ（アイコン側に向く） --}}
                    <span class="absolute top-5 -left-[8px] w-0 h-0 border-y-[8px] border-y-transparent border-r-[10px] border-r-line-accent/40"></span>
                    <span class="absolute top-5 -left-[6px] w-0 h-0 border-y-[7px] border-y-transparent border-r-[9px] border-r-surface-from"></span>
                    <p class="text-[13px] leading-relaxed {{ $appealTrunc !== '' ? 'text-text-main' : 'text-text-sub' }}">
                        {{ $appealTrunc !== '' ? $appealTrunc : 'ひとことはまだ登録されていません' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- 2. 店名（+ 優良店バッヂ）+ ライク + レビュー/エリア/業種 --}}
        <div class="mb-4 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <h1 class="app-title text-[22px] text-text-main leading-tight truncate min-w-0">{{ $shopName }}</h1>
                    @if($isPremiumShop)
                        {{-- 優良店バッヂ（全画面共通コンポーネント） --}}
                        <x-ui.premium-badge class="shrink-0" />
                    @endif
                </div>
                <x-ui.view-count :count="$viewCount" class="shrink-0 text-[13px] text-text-main" />
            </div>

            @php
                $shopHelpDates = array_values(array_filter((array) ($shop['help_date_labels'] ?? [])));
            @endphp
            @if($shopHelpDates !== [])
                <div class="mb-1">
                    <span class="shop-avail-tag" aria-label="日付指定のヘルプ募集">
                        <i class="fas fa-bolt" aria-hidden="true"></i> {{ implode('・', array_slice($shopHelpDates, 0, 5)) }} ヘルプ募集
                    </span>
                </div>
            @endif
            <div class="flex items-center gap-1.5 text-text-sub text-[12px] flex-wrap">
                @if($reviewCount > 0)
                    {{-- レビュー：タップで明細（SHOP タブ内 REVIEWS）へ --}}
                    <button type="button" data-open-shop-reviews
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-amber-400/50 bg-amber-400/10 text-[11.5px] font-extrabold text-amber-300">
                        <i class="fas fa-star text-[10px]"></i>{{ number_format($reviewAvg, 1) }}
                        <span class="font-bold text-amber-300/70">({{ $reviewCount }}件)</span>
                        <i class="fas fa-chevron-right text-[8px] opacity-70"></i>
                    </button>
                @endif
                @if($areaChip !== '')
                    <span class="inline-flex items-center gap-1"><i class="fas fa-map-marker-alt text-[10px]"></i>{{ $areaChip }}</span>
                @endif
                @if($distanceText !== '')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent/10 border border-line-accent/40 text-text-main font-bold text-[10.5px]">
                        <i class="fas fa-route text-[9px]"></i>現在地から {{ $distanceText }}
                    </span>
                @endif
                @if($industryName !== '')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent/10 border border-line-accent/40 text-accent-text font-bold tracking-wide text-[10.5px]">
                        <i class="fas fa-tag text-[9px]"></i>{{ $industryName }}
                    </span>
                @endif
            </div>
        </div>

        {{-- 3. ボーナス金 / 時給：SWIPE カードと同一の Premium Slate（横型一体スレート）。
             体入時給・ヘルプ時給の両方を表示する --}}
        @if($showBonusMain || $trialWageDisp || $helpWageDisp || $regularWageDisp)
            @php
                // 1列目：体入時給を優先。無ければ本入り時給を表示
                $wageRow1Label = $trialWageDisp ? '体入時給' : '本入り時給';
                $wageRow1Disp  = $trialWageDisp ?: $regularWageDisp;
            @endphp
            <div class="pslate mb-4">
                <div class="pslate__bonus">
                    <span class="pslate__label">ボーナス金</span>
                    <span class="pslate__amount">
                        @if($showBonusMain)<span class="pslate__yen">¥</span>{{ number_format($noruma) }}@else —@endif
                    </span>
                    @if($showBonusMain && $bonusConditionsText !== '')
                        <span class="pslate__cond">{{ $bonusConditionsText }}</span>
                    @endif
                </div>
                <span class="pslate__divider" aria-hidden="true"></span>
                <div class="pslate__wages">
                    <div class="pslate__wage">
                        <span class="pslate__label">{{ $wageRow1Label }}</span>
                        <span class="pslate__wage-amount">{{ $wageRow1Disp ?: '—' }}</span>
                    </div>
                    <div class="pslate__wage">
                        <span class="pslate__label">ヘルプ時給</span>
                        <span class="pslate__wage-amount">{{ $helpWageDisp ?: '—' }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- 4. アクション CTA：TALK を最上位に、応募系・KEEP・共有を横に --}}
        @php
            $isShopPreview = $isShopPreview ?? empty($forCast);
            // 店舗プレビュー時は cast.talk.room が member 認証必須のため、リンク先を '#' にして
            // クリックしても遷移しないようにし、プレビュー用の注釈を出す
            $mkTalkHref = function (array $params) use ($isShopPreview) {
                return $isShopPreview ? '#' : route('cast.talk.room', $params);
            };
        @endphp
        @if(!empty($ctaShopId))
            <div class="flex flex-col gap-2 mb-2">
                {{-- KEEP / 共有 の横一列。トークするは画面下部固定バー（.profile-talk-bar）に一本化 --}}
                <div class="fav-actions-row" style="margin-bottom: 20px;">
                    <button type="button"
                            @if($isShopPreview) disabled title="プレビュー：求職者はここでキープできます" @endif
                            class="fav-circle fav-circle--keep {{ $isShopPreview ? 'opacity-70' : '' }}"
                            aria-label="キープ"
                            aria-pressed="{{ !empty($recruit['is_kept']) ? 'true' : 'false' }}"
                            @unless($isShopPreview) data-fav-toggle @endunless
                            data-item-id="{{ $shop['id'] ?? '' }}"
                            data-item-type="shop"
                            data-action="keep">
                        <i class="fas fa-bookmark" aria-hidden="true"></i>
                        <span class="fav-circle__cap">KEEP</span>
                    </button>
                    @if(!empty($shareUrlResolved))
                        <div class="shrink-0 flex flex-col items-center">
                            @include('partials.share-menu', [
                                'shareUrl' => $shareUrlResolved,
                                'shareTitle' => $shareTitleResolved,
                                'shareText' => $shareTextResolved,
                                'menuId' => 'recruit-share-menu',
                            ])
                        </div>
                    @endif
                </div>

                {{-- 応募系（求人が登録されている場合のみ） --}}
                @if($ctaHasTrial || $ctaHasHelp)
                    <div class="flex gap-2">
                        @if($ctaHasTrial)
                            <a href="{{ $mkTalkHref(['id' => $ctaShopId, 'job_kind' => 'trial', 'talk_topic' => 'new_hire', 'initiate' => 1]) }}"
                               @if($isShopPreview) aria-disabled="true" onclick="return false;" @endif
                               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-full font-bold text-[12.5px] border border-line-accent/40 bg-accent/10 text-accent-text transition-all duration-300 {{ $isShopPreview ? 'cursor-default' : '' }}">
                                <i class="fas fa-paper-plane text-[11px]"></i> 新規採用に応募
                            </a>
                        @endif
                        @if($ctaHasHelp)
                            <a href="{{ $mkTalkHref(['id' => $ctaShopId, 'job_kind' => 'help', 'talk_topic' => 'help', 'initiate' => 1]) }}"
                               @if($isShopPreview) aria-disabled="true" onclick="return false;" @endif
                               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-full font-bold text-[12.5px] border border-line-accent/40 bg-accent/10 text-accent-text transition-all duration-300 {{ $isShopPreview ? 'cursor-default' : '' }}">
                                <i class="fas fa-hand-holding-heart text-[11px]"></i> ヘルプ応募
                            </a>
                        @endif
                    </div>
                @endif

                {{-- LIKE / KEEP / 共有 はトークと同じ横一列（上）に統合済み --}}
            </div>
        @endif
    </div>

    {{-- Tabs: GALLERY / JOB / SHOP --}}
    <div data-tabs-scope>
        <div data-tabs class="border-t border-b border-line-accent/40 bg-base/90 backdrop-blur-md sticky top-0 z-10">
            <div class="flex">
                <button type="button" data-tab="gallery"
                        class="is-active flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-images text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">GALLERY</span>
                </button>
                <button type="button" data-tab="job"
                        class="flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-briefcase text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">JOB</span>
                </button>
                <button type="button" data-tab="shop"
                        class="flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-store text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">SHOP</span>
                </button>
            </div>
        </div>

        {{-- ========== GALLERY ========== --}}
        <div data-tab-panel="gallery" class="is-active">
            @if(count($galleryImages) > 0)
                <ul id="profile-gallery-list">
                    @foreach($galleryImages as $i => $img)
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

        {{-- ========== JOB（求人情報） ========== --}}
        <div data-tab-panel="job">
            <div class="p-4 flex flex-col gap-4">

                {{-- CATCH（キャッチコピー）：ヘッダーの吹き出しに表示済みの文言と重複しない場合のみ掲載 --}}
                @if($catchCopyMain !== '' && $catchCopyMain !== $appealLine)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-3 flex items-center gap-2">
                            <i class="fas fa-bullhorn"></i> CATCH
                        </h3>
                        <p class="text-[13.5px] leading-relaxed text-text-main whitespace-pre-line">{{ $catchCopyMain }}</p>
                    </x-ui.card>
                @endif

                {{-- BONUS card --}}
                @if($showBonusMain || $bonusConditionsText !== '' || $bonusRemarks !== '')
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-gift"></i> BONUS
                        </h3>
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center border-b border-line pb-2">
                                <span class="text-[12px] text-text-sub font-medium">ボーナス金</span>
                                <span class="text-[14px] font-extrabold text-amber-400">¥{{ number_format($noruma) }}</span>
                            </div>
                            @if($bonusDaysLocal !== '')
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">達成勤務日数</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $bonusDaysLocal }}日以上</span>
                                </div>
                            @endif
                            @if($bonusHoursLocal !== '')
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">達成勤務時間</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $bonusHoursLocal }}時間以上</span>
                                </div>
                            @endif
                            @if($bonusExtraLocal !== '')
                                <div class="flex flex-col gap-1 border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">その他の条件</span>
                                    <span class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $bonusExtraLocal }}</span>
                                </div>
                            @endif
                            @if($bonusRemarks !== '')
                                <div class="flex flex-col gap-1">
                                    <span class="text-[12px] text-text-sub font-medium">備考</span>
                                    <span class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $bonusRemarks }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                {{-- WAGES card --}}
                @if($regularWageDisp || $trialWageDisp || $helpWageDisp)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-yen-sign"></i> WAGE
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if($regularWageDisp)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">本入り時給</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $regularWageDisp }}</span>
                                </div>
                            @endif
                            @if($trialWageDisp)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">体入時給</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $trialWageDisp }}</span>
                                </div>
                            @endif
                            @if($helpWageDisp)
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-text-sub font-medium">ヘルプ時給</span>
                                    <span class="text-[13px] font-bold text-text-main">{{ $helpWageDisp }}</span>
                                </div>
                            @endif
                        </div>
                        @if($salaryNotesMain !== '')
                            <p class="text-[11px] text-text-sub mt-3">{{ $salaryNotesMain }}</p>
                        @endif
                    </x-ui.card>
                @endif

                {{-- ABOUT / Message card --}}
                @if($messageBody !== '' || $jobSupplementMain !== '' || $jobContent !== '')
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-comment-dots"></i> ABOUT
                        </h3>
                        @if($messageBody !== '')
                            <div class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $messageBody }}</div>
                        @endif
                        @php $jobBody = $jobSupplementMain !== '' ? $jobSupplementMain : $jobContent; @endphp
                        @if($jobBody !== '')
                            <div class="mt-3 pt-3 border-t border-line">
                                <span class="text-[12px] text-text-sub font-medium block mb-1">仕事内容</span>
                                <div class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $jobBody }}</div>
                            </div>
                        @endif
                    </x-ui.card>
                @endif

                {{-- SHIFT / 勤務条件 card --}}
                @if($workingHours !== '' || $workingDays !== '' || $regularHoliday !== '' || $qualification !== '')
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-calendar-check"></i> SHIFT
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if($workingHours !== '')
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">勤務時間</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $workingHours }}</span>
                                </div>
                            @endif
                            @if($workingDays !== '')
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">勤務日数</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $workingDays }}</span>
                                </div>
                            @endif
                            @if($regularHoliday !== '')
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">定休日</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $regularHoliday }}</span>
                                </div>
                            @endif
                            @if($qualification !== '')
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-text-sub font-medium">応募資格</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $qualification }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                {{-- FEATURES card（求人タグ：働き方・歓迎条件・待遇） --}}
                @if($hasFeatureMatrix)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-tags"></i> FEATURES
                        </h3>
                        <div class="flex flex-col gap-3">
                            @foreach($matrixLabels as $key => $label)
                                @if(!empty($storeFeatures[$key]) && count((array) $storeFeatures[$key]) > 0)
                                    <div>
                                        <div class="text-[10px] text-text-sub uppercase tracking-wider mb-1 flex items-center gap-1">
                                            <i class="fas {{ $matrixIcons[$key] ?? 'fa-tag' }}"></i> {{ $label }}
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach((array) $storeFeatures[$key] as $tag)
                                                <span class="inline-flex items-center px-2 py-1 rounded bg-accent/10 border border-line-accent/30 text-[11px] text-text-main">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

            </div>
        </div>

        {{-- ========== SHOP（店舗プロフィール） ========== --}}
        <div data-tab-panel="shop">
            <div class="p-4 flex flex-col gap-4">

                {{-- REVIEWS：キャストからも明細を閲覧できる（ヘッダーのレビューチップから遷移） --}}
                @if($reviewCount > 0)
                    <x-ui.card class="p-5" id="shop-reviews-section" style="scroll-margin-top: 120px;">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-3 flex items-center gap-2">
                            <i class="fas fa-star text-amber-400"></i> REVIEWS
                            <span class="ml-auto text-[12px] font-extrabold text-amber-300 normal-case tracking-normal">
                                ★ {{ number_format($reviewAvg, 1) }} <span class="text-text-sub font-bold">/ {{ $reviewCount }}件</span>
                            </span>
                        </h3>
                        <div class="flex flex-col gap-3">
                            @foreach($shopReviews as $rv)
                                <div class="pb-3 border-b border-line last:border-b-0 last:pb-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($rv['score'] !== null)
                                            <span class="text-[12px] font-extrabold text-amber-300">
                                                @for($i = 1; $i <= 5; $i++){{ $i <= round($rv['score']) ? '★' : '☆' }}@endfor
                                            </span>
                                            <span class="text-[11px] font-bold text-text-main">{{ number_format($rv['score'], 1) }}</span>
                                        @endif
                                        @if($rv['date'] !== '')
                                            <span class="ml-auto text-[10px] text-text-sub">{{ $rv['date'] }}</span>
                                        @endif
                                    </div>
                                    @if($rv['text'] !== '')
                                        <p class="text-[12.5px] text-text-main leading-relaxed">{{ $rv['text'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-[10px] text-text-sub">実際に勤務したキャストからのレビューです（直近{{ count($shopReviews) }}件を表示）</p>
                    </x-ui.card>
                @endif

                {{-- SHOP INFO card --}}
                <x-ui.card class="p-5">
                    <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                        <i class="fas fa-store"></i> SHOP INFO
                    </h3>
                    <div class="flex flex-col gap-3">
                        <div class="flex justify-between items-center border-b border-line pb-2">
                            <span class="text-[12px] text-text-sub font-medium">店名</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopName }}</span>
                        </div>
                        @if($industryName !== '')
                            <div class="flex justify-between items-center border-b border-line pb-2">
                                <span class="text-[12px] text-text-sub font-medium">業種</span>
                                <span class="text-[13px] font-bold text-text-main text-right">{{ $industryName }}</span>
                            </div>
                        @endif
                        @if($openDate !== '')
                            <div class="flex justify-between items-center">
                                <span class="text-[12px] text-text-sub font-medium">開店日</span>
                                <span class="text-[13px] font-bold text-text-main text-right">{{ $openDate }}</span>
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- WORD カードは廃止（ひとことはヘッダーの吹き出しに集約）。雰囲気のみ掲載 --}}
                @if($storeAtmosphere !== '')
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-3 flex items-center gap-2">
                            <i class="fas fa-champagne-glasses"></i> ATMOSPHERE
                        </h3>
                        <div class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $storeAtmosphere }}</div>
                    </x-ui.card>
                @endif

                {{-- AMBIENCE（店内雰囲気・設備タグ） --}}
                @if(!empty($shopTagGroups))
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-wand-magic-sparkles"></i> AMBIENCE
                        </h3>
                        <div class="flex flex-col gap-3">
                            @foreach($shopTagGroups as $group)
                                @if(!empty($group['tags']))
                                    <div>
                                        <div class="text-[10px] text-text-sub uppercase tracking-wider mb-1">
                                            {{ $group['label'] ?? '' }}
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($group['tags'] as $tag)
                                                <span class="inline-flex items-center px-2 py-1 rounded bg-accent/10 border border-line-accent/30 text-[11px] text-text-main">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

                {{-- ACCESS card --}}
                <x-ui.card class="p-5">
                    <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                        <i class="fas fa-location-dot"></i> ACCESS
                    </h3>
                    <div class="flex flex-col gap-3">
                        @if($addressLine !== '')
                            <div class="flex flex-col gap-1">
                                <span class="text-[12px] text-text-sub font-medium">住所</span>
                                <span class="text-[13px] font-bold text-text-main">{{ $addressLine }}</span>
                            </div>
                        @endif
                        @if($nearestStation !== '')
                            <div class="flex justify-between items-center border-t border-line pt-2">
                                <span class="text-[12px] text-text-sub font-medium">最寄り駅</span>
                                <span class="text-[13px] font-bold text-text-main text-right">{{ $nearestStation }}</span>
                            </div>
                        @endif
                        @if($businessHours !== '')
                            <div class="flex justify-between items-center border-t border-line pt-2">
                                <span class="text-[12px] text-text-sub font-medium">営業時間</span>
                                <span class="text-[13px] font-bold text-text-main text-right">{{ $businessHours }}</span>
                            </div>
                        @endif
                        @if($addressLine !== '')
                            {{-- Keyless Google Maps embed (no API key). Swap to Maps Embed API once key referrer restriction is configured. --}}
                            <div class="border-t border-line pt-3 flex flex-col gap-2">
                                <div class="rounded-xl overflow-hidden border border-line">
                                    <iframe
                                        src="https://maps.google.com/maps?q={{ urlencode($addressLine) }}&z=16&hl=ja&output=embed"
                                        width="100%"
                                        height="220"
                                        style="border:0; display:block;"
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        title="{{ $addressLine }}の地図"
                                    ></iframe>
                                </div>
                                <a
                                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode($addressLine) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 self-end text-[12px] font-medium text-accent-text"
                                >
                                    <i class="fas fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                    Google マップで開く
                                </a>
                            </div>
                        @endif
                    </div>
                </x-ui.card>

            </div>
        </div>
    </div>
</div>

{{-- ライトボックス：Swiper で複数画像の左右スワイプ／矢印切替に対応 --}}
@if(count($galleryImages) > 0)
<div id="lightbox-overlay" class="lightbox-overlay" onclick="closeLightbox(event)">
    <div class="swiper lightbox-swiper" onclick="event.stopPropagation()">
        <div class="swiper-wrapper">
            @foreach($galleryImages as $i => $img)
                <div class="swiper-slide"><img src="{{ $img }}" alt="" class="lightbox-image"></div>
            @endforeach
        </div>
        @if(count($galleryImages) > 1)
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

{{-- ヘッダーのレビューチップ → SHOP タブを開いて REVIEWS 明細へスクロール --}}
<script>
(function () {
    'use strict';
    document.querySelectorAll('[data-open-shop-reviews]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var shopTab = document.querySelector('[data-tab="shop"]');
            if (shopTab) shopTab.click();
            window.setTimeout(function () {
                var section = document.getElementById('shop-reviews-section');
                if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        });
    });
})();
</script>

{{-- 画面下部固定の「トークする」バー（SWIPEカードのCTAと同一デザイン）。
     店舗プレビュー時は見た目そのまま・無効状態で表示（求職者に見える形の確認用） --}}
@if(!empty($ctaShopId))
    @php $talkBarPreview = $isShopPreview ?? empty($forCast); @endphp
    <div class="profile-talk-bar" role="complementary" aria-label="トークを開始">
        <a href="{{ $talkBarPreview ? '#' : route('cast.talk.room', ['id' => $ctaShopId, 'talk_topic' => 'other', 'initiate' => 1]) }}"
           class="profile-talk-bar__btn"
           @if($talkBarPreview) aria-disabled="true" onclick="return false;" title="プレビュー：求職者はここからトークに遷移します" @endif>
            <i class="fas fa-comment-dots" aria-hidden="true"></i> トークする
        </a>
    </div>
@endif
