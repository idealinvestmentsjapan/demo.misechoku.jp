@extends('layouts.app-v2')

@section('title', 'マイページ')
@section('body-class', 'page-shop-mypage')

@php
    $displayName       = $shopData['shop_name'] ?? '--';
    $word              = trim($shopData['word'] ?? '');
    $wordPlaceholder   = '今、何してる？（タイムラインに公開されます）';
    $iconImage         = ($subImages[0]['url'] ?? null) ?? asset('assets/images/common/no-image.png');
    $hasGoodPayerBadge = !empty($shopData['badges']['good_payer'] ?? false);
    $reviewAvg         = number_format($shopData['review_avg'] ?? 0, 1);
    $reviewCount       = (int) ($shopData['review_count'] ?? 0);
@endphp

@section('content')
<div>

    <div class="px-5 pt-4 pb-6">

        {{-- ===== アイコン + ひとこと吹き出し（キャスト MyPage と同一のコンパクト構成） ===== --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="w-[84px] h-[84px] rounded-full overflow-hidden border-2 border-line-accent/40 shadow-card-3d bg-surface-from shrink-0">
                <img src="{{ $iconImage }}" alt="" class="w-full h-full object-cover" id="main-icon-display">
            </div>

            <div class="flex-1 min-w-0">
                {{-- コンパクト構成：ひとこと(1行目) + 最終更新(2行目) を左に、編集ボタンは右で2行にまたがる --}}
                <div class="relative flex items-stretch gap-2 bg-gradient-to-br from-surface-from to-base border border-line-accent/40 rounded-2xl shadow-card-3d px-3 py-2">
                    {{-- 吹き出しのしっぽ（アイコン側に向く） --}}
                    <span class="absolute top-1/2 -translate-y-1/2 -left-[8px] w-0 h-0 border-y-[8px] border-y-transparent border-r-[10px] border-r-line-accent/40"></span>
                    <span class="absolute top-1/2 -translate-y-1/2 -left-[6px] w-0 h-0 border-y-[7px] border-y-transparent border-r-[9px] border-r-surface-from"></span>

                    {{-- 左：ひとこと + 最終更新 --}}
                    <div class="flex-1 min-w-0 flex flex-col justify-center gap-0.5">
                        <p id="display-word"
                           data-placeholder="{{ $wordPlaceholder }}"
                           class="text-[13px] leading-snug {{ $word === '' ? 'text-text-sub' : 'text-text-main' }}">
                            {{ $word !== '' ? $word : $wordPlaceholder }}
                        </p>
                        <span id="display-word-updated" class="text-[10px] text-text-sub leading-none">
                            最終更新 {{ $shopData['appeal_updated_at'] ?? '未設定' }}
                        </span>
                    </div>

                    {{-- 右：編集ボタン（2行にまたがって縦中央配置） --}}
                    <button type="button" id="open-word-edit-btn"
                            aria-label="ひとことを編集"
                            class="shrink-0 self-stretch inline-flex flex-col items-center justify-center gap-0.5 px-2.5 rounded-lg text-[11px] font-medium text-text-sub hover:text-accent-text hover:bg-accent/10 active:scale-95 transition-all">
                        <x-ui.icon name="edit" class="text-[13px]" />
                        <span class="leading-none">編集</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== 店舗名 + 控えめバッヂ行 =====
             旧: 優良店/レビューの大型2カラムカード → 目立ちすぎのため
             店舗名の下の小型チップに格下げ（情報は維持、占有面積を1/4に）。
             閲覧数はレビュー・優良店バッヂと同じ「小さな指標チップ」列に集約し、
             見出し行は「店舗名 + 共有ボタン」だけにしてアクションを目立たせる。 --}}
        <div class="mb-4">
            <div class="flex items-center justify-between gap-3 mb-1.5">
                <h1 class="app-title text-[24px] text-text-main leading-tight truncate min-w-0">{{ $displayName }}</h1>
                @php $myShopShareId = (int) (auth()->guard('shop')->user()->shop_id ?? 0); @endphp
                @if($myShopShareId > 0)
                    <div class="shrink-0 profile-inline-actions">
                        @include('partials.share-menu', [
                            'shareUrl' => route('share.recruit.show', ['id' => $myShopShareId]),
                            'shareTitle' => $displayName . 'の求人情報',
                            'shareText' => $word !== '' ? $word : ($displayName . 'の求人情報です。'),
                            'menuId' => 'my-shop-share-menu',
                        ])
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                {{-- レビュー：枠なし・少し大きめ・末尾に chevron でタップ可を明示。
                     優良店バッヂ・閲覧数と同じ行で align-items:center により縦位置が揃う。 --}}
                <a href="{{ route('shop.mypage.review.index') }}"
                   class="inline-flex items-center gap-1 py-1 text-[13.5px] font-bold text-text-main hover:text-accent-text transition-colors">
                    <i class="fas fa-star text-[13px] text-amber-400"></i>
                    <span>{{ $reviewAvg }}</span>
                    <span class="font-normal text-text-sub text-[11px]">({{ $reviewCount }})</span>
                    <i class="fas fa-chevron-right text-[10px] text-accent-text/80 ml-0.5" aria-hidden="true"></i>
                </a>
                <button type="button" id="open-good-payer-badge-modal"
                        aria-haspopup="dialog" aria-controls="modal-good-payer-badge"
                        aria-label="優良店バッヂの説明を開く"
                        class="premium-badge-btn">
                    <x-ui.premium-badge :off="!$hasGoodPayerBadge"
                                        :label="'優良店' . ($hasGoodPayerBadge ? '' : '（未取得）')" />
                </button>
                <x-ui.view-count :count="(int) ($shopData['view_cnt'] ?? 0)"
                                 class="text-[13px] text-text-main ml-auto" />
            </div>
        </div>

        @php
            $isJobPublished  = (bool) ($jobSummary['is_published'] ?? false);
            $licenseApproved = (int) ($shopData['approval'] ?? 0) === 1;
        @endphp

        {{-- ===== 求人非公開アラート：気づかないと機会損失になるためページ最上部で明示 =====
             許可証未承認が原因の場合は、許可証登録（PROFILE タブ内 #license-section）へ直接誘導 --}}
        @unless($isJobPublished)
            <div class="mypage-alert" role="alert">
                <span class="mypage-alert__icon"><i class="fas fa-eye-slash"></i></span>
                <span class="mypage-alert__body">
                    <p class="mypage-alert__title">求人票が非公開になっています</p>
                    <p class="mypage-alert__text">
                        @if(!$licenseApproved)
                            掲載には営業許可証の承認が必要です。許可証を提出し、運営の承認をお待ちください。
                        @else
                            現在、キャストの検索・スワイプにお店が表示されません。公開設定を確認してください。
                        @endif
                    </p>
                </span>
                @if(!$licenseApproved)
                    <a href="{{ route('shop.mypage.documents.index') }}" class="mypage-alert__btn">許可証を登録する</a>
                @else
                    <a href="{{ route('shop.recruits.edit') }}" class="mypage-alert__btn">公開設定へ</a>
                @endif
            </div>
        @endunless

        {{-- ===== 管理メニュー =====
             求人票 → JOB タブ内、プロファイル → PROFILE タブ内、
             ライセンス・スタッフ管理 → サイドメニュー（ACCOUNT）に集約。
             トップは日常業務の「採用・入金管理」のみ（キャスト MyPage と同構成）。
             要対応バッジはヘッダー共有の $todoList（InjectHeaderBadges）から算出 --}}
        @php
            $todos = collect($todoList ?? []);
            $mgmtActionCount = $todos->whereIn('key', ['shop.deposit_pending_approval', 'shop.invoice_pending_payment'])->count();
        @endphp
        <div class="mypage-menu-grid mb-1">
            <a href="{{ route('shop.mypage.management') }}" class="mypage-tile mypage-tile--wide">
                <i class="fas fa-yen-sign mypage-tile__icon"></i>
                <span class="mypage-tile__label">採用・入金管理<span class="mypage-tile__sub">PAYMENT</span></span>
                @if($mgmtActionCount > 0)
                    <span class="mypage-tile__badge mypage-tile__badge--urgent" aria-label="要対応 {{ $mgmtActionCount }}件">{{ $mgmtActionCount }}</span>
                @endif
            </a>
        </div>

        {{-- ===== 「ヘルプ募集日を設定」（キャスト MyPage の「働きたい日を設定」と対称） =====
             採用・入金管理タイルの下に控えめに配置。時給設定・応募者一覧は
             /shop/help-recruitment に集約。宣言ルートは shop.owner 配下。 --}}
        @shopowner
        @php
            $availSelected   = $availabilityDates ?? [];
            $availActive     = count($availSelected) > 0;
            $availDeclareUrl = route('shop.help-recruitment.dates.declare');
            $availClearUrl   = route('shop.help-recruitment.dates.clear');
            $availMaxDates   = \App\Services\AvailabilityService::MAX_DATES;
            $availDaysAhead  = \App\Services\AvailabilityService::MAX_DAYS_AHEAD;
        @endphp
        <section id="availability-card"
                 class="cast-avail cast-avail--compact cast-avail--subtle {{ $availActive ? 'is-active' : '' }}"
                 data-availability-declare-url="{{ $availDeclareUrl }}"
                 data-availability-clear-url="{{ $availClearUrl }}"
                 data-availability-max="{{ $availMaxDates }}"
                 data-availability-days-ahead="{{ $availDaysAhead }}"
                 data-availability-selected="{{ json_encode(array_values($availSelected)) }}"
                 aria-labelledby="availability-card-title">
            <div class="cast-avail__row">
                <span class="cast-avail__icon" aria-hidden="true">
                    <i class="fas {{ $availActive ? 'fa-bolt' : 'fa-calendar-days' }}"></i>
                </span>
                <p id="availability-card-title" class="cast-avail__title">
                    ヘルプ募集日を設定
                    <span class="cast-avail__status-tag" data-availability-status-tag
                          {{ $availActive ? '' : 'hidden' }}>設定中</span>
                </p>
                <div class="cast-avail__actions">
                    <button type="button" class="cast-avail__btn cast-avail__btn--ghost" data-availability-open
                            aria-haspopup="dialog" aria-controls="availability-modal">
                        <span data-availability-open-label>{{ $availActive ? '変更' : '設定' }}</span>
                    </button>
                </div>
            </div>

            {{-- Modal popup: description + calendar + save/clear actions --}}
            <div class="cast-avail__modal" id="availability-modal" role="dialog"
                 aria-modal="true" aria-labelledby="availability-modal-title" hidden>
                <div class="cast-avail__backdrop" data-availability-close></div>
                <div class="cast-avail__dialog" role="document">
                    <header class="cast-avail__dialog-head">
                        <h2 id="availability-modal-title" class="cast-avail__dialog-title">
                            <i class="fas fa-calendar-days" aria-hidden="true"></i>
                            ヘルプ募集日を設定
                        </h2>
                        <button type="button" class="cast-avail__dialog-close"
                                data-availability-close aria-label="閉じる">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div class="cast-avail__dialog-body">
                        <p class="cast-avail__dialog-desc">
                            ヘルプで人手が欲しい日を選ぶと、その日に働けるキャストの
                            <strong>検索・SWIPE で優先表示</strong>されます。
                        </p>
                        <p class="cast-avail__dialog-hint">
                            最大{{ $availMaxDates }}日・{{ $availDaysAhead }}日先まで
                        </p>
                        <div class="cast-avail__cal" data-availability-calendar></div>
                    </div>
                    <footer class="cast-avail__dialog-foot">
                        <button type="button" class="cast-avail__btn cast-avail__btn--danger cast-avail__btn--text"
                                data-availability-clear
                                {{ $availActive ? '' : 'hidden' }}>
                            <i class="fas fa-xmark" aria-hidden="true"></i> 取消
                        </button>
                        <span class="cast-avail__dialog-foot-spacer"></span>
                        <button type="button" class="cast-avail__btn cast-avail__btn--ghost" data-availability-close>
                            戻る
                        </button>
                        <button type="button" class="cast-avail__btn cast-avail__btn--primary" data-availability-save disabled>
                            <i class="fas fa-check"></i> 決定
                        </button>
                    </footer>
                </div>
            </div>
        </section>
        @endshopowner

    </div>

    {{-- ===== Tabs：GALLERY / JOB / PROFILE の3タブ構成（キャスト MyPage とデザイン・名称を統一） ===== --}}
    <div data-tabs-scope>
        <div data-tabs class="border-t border-b border-line-accent/40 bg-base/90 backdrop-blur-md sticky top-0 z-10">
            <div class="flex">
                <button type="button" data-tab="gallery"
                        class="is-active flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-images text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">GALLERY</span>
                </button>
                <button type="button" data-tab="job"
                        class="relative flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-briefcase text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">JOB</span>
                    @unless($isJobPublished)
                        <span class="tab-alert-badge">非公開</span>
                    @endunless
                </button>
                <button type="button" data-tab="shop"
                        class="flex-1 py-3 flex flex-col items-center justify-center gap-0.5 transition-colors border-b-2 border-transparent text-text-sub [&.is-active]:text-accent-text [&.is-active]:border-accent">
                    <i class="fas fa-address-card text-[14px]"></i>
                    <span class="app-title text-[10px] tracking-widest">PROFILE</span>
                </button>
            </div>
        </div>

        {{-- ========== Gallery panel ========== --}}
        <div data-tab-panel="gallery" class="is-active">
            <ul id="gallery-list"
                data-sort-save-url="{{ route('shop.profile.images.order') }}"
                data-empty-image-url="{{ asset('assets/images/common/no-image.png') }}">
                @for($i = 0; $i < 8; $i++)
                @php $img = $subImages[$i] ?? null; @endphp
                <li class="gallery-grid-item" data-slot-index="{{ $i }}">
                    <div class="photo-slot {{ $img ? 'has-img' : '' }}"
                         data-image-id="{{ $img['id'] ?? '' }}"
                         data-image-url="{{ $img['url'] ?? '' }}">
                        @if($img)
                            <img src="{{ $img['url'] }}" alt="" loading="lazy">
                            @if($i === 0)<span class="photo-slot-badge">MAIN</span>@endif
                        @else
                            <span class="photo-slot-empty" aria-label="画像を追加"><i class="fas fa-plus"></i></span>
                        @endif
                    </div>
                </li>
                @endfor
            </ul>
        </div>

        {{-- ========== JOB panel：求人管理（cast-show.blade.php の JOB タブと同じ構造） ========== --}}
        <div data-tab-panel="job">
            <div class="p-4 flex flex-col gap-4">
                @php $js = $jobSummary ?? []; @endphp

                {{-- 求人票を編集（タブの内容を編集する入口。オーナー専用）
                     背景を薄く紫にトーンして情報カードから差別化 --}}
                @shopowner
                <a href="{{ route('shop.recruits.edit') }}"
                   class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-line-accent/50 bg-gradient-to-br from-accent/15 via-accent/6 to-surface-from shadow-card-3d hover:border-accent/70 active:scale-[0.99] transition-all">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <i class="fas fa-pen-to-square text-accent-text text-[14px]"></i>
                        <span class="text-[13px] font-bold text-text-main">求人票を編集する</span>
                    </span>
                    <i class="fas fa-chevron-right text-text-sub text-[11px] shrink-0"></i>
                </a>
                @endshopowner

                {{-- 求人ステータス + 応募数の概要バー（公開設定・応募状況の管理へ） --}}
                <x-ui.card class="p-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            {{-- 公開ステータスバッジ。プレミアムホワイト背景（薄紫）でも文字を読みやすくするため
                                 濃い緑/濃いスレートを直接指定（tint 塗りではなく濃色 text で可読性を担保） --}}
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold tracking-wide
                                {{ ($js['is_published'] ?? false)
                                    ? 'bg-emerald-500/12 text-emerald-700 border border-emerald-500/45'
                                    : 'bg-slate-500/10 text-slate-700 border border-slate-400/45' }}">
                                <i class="fas {{ ($js['is_published'] ?? false) ? 'fa-circle-check' : 'fa-pause' }} text-[10px]"></i>
                                {{ $js['status_label'] ?? '未設定' }}
                            </span>
                            {{-- 「ステータス管理」リンクは求人票を編集するボタンと同じ遷移先のため削除。
                                 非オーナー向けの案内文言だけ残す。 --}}
                            @unlessshopowner
                                <span class="text-sm text-text-sub">公開設定の変更はオーナーにご依頼ください</span>
                            @endshopowner
                        </div>
                        <div class="flex items-center gap-4 text-[11px]">
                            <span><span class="text-text-sub">応募</span> <strong class="text-text-main">{{ number_format($js['applicant_count'] ?? 0) }}</strong></span>
                            <span><span class="text-text-sub">採用</span> <strong class="text-text-main">{{ number_format($js['hired_count'] ?? 0) }}</strong></span>
                        </div>
                    </div>

                    {{-- 非公開時の理由と次アクション --}}
                    @unless($isJobPublished)
                        <p class="mt-2.5 pt-2.5 border-t border-line text-[11px] leading-relaxed text-text-sub">
                            @if(!$licenseApproved)
                                <i class="fas fa-file-signature text-danger text-[10px] mr-1"></i>掲載には営業許可証の承認が必要です。
                                <a href="{{ route('shop.mypage.documents.index') }}" class="font-bold text-accent-text underline">許可証を登録する</a>
                            @else
                                <i class="fas fa-circle-info text-[10px] mr-1"></i>公開すると検索・スワイプに表示されます。公開設定はオーナーが変更できます。
                            @endif
                        </p>
                    @endunless
                </x-ui.card>

                {{-- BONUS card --}}
                @if(($js['bonus_reward'] ?? 0) > 0 || !empty($js['bonus_condition'] ?? ''))
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-gift"></i> BONUS
                        </h3>
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center border-b border-line pb-2">
                                <span class="text-[12px] text-text-sub font-medium">ボーナス金</span>
                                <span class="text-[14px] font-extrabold text-amber-400">¥{{ number_format((int) ($js['bonus_reward'] ?? 0)) }}</span>
                            </div>
                            @if(!empty($js['bonus_condition'] ?? ''))
                                <div class="flex flex-col gap-1">
                                    <span class="text-[12px] text-text-sub font-medium">達成条件</span>
                                    <span class="text-[13px] text-text-main leading-relaxed">{{ $js['bonus_condition'] }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                {{-- WAGE card --}}
                @if(($js['regular_wage'] ?? 0) > 0 || ($js['trial_wage'] ?? 0) > 0 || ($js['help_wage'] ?? 0) > 0)
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-yen-sign"></i> WAGE
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if(($js['regular_wage'] ?? 0) > 0)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">本入り時給</span>
                                    <span class="text-[13px] font-bold text-text-main">¥{{ number_format($js['regular_wage']) }}</span>
                                </div>
                            @endif
                            @if(($js['trial_wage'] ?? 0) > 0)
                                <div class="flex justify-between items-center border-b border-line pb-2">
                                    <span class="text-[12px] text-text-sub font-medium">体入時給</span>
                                    <span class="text-[13px] font-bold text-text-main">¥{{ number_format($js['trial_wage']) }}</span>
                                </div>
                            @endif
                            @if(($js['help_wage'] ?? 0) > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-[12px] text-text-sub font-medium">ヘルプ時給</span>
                                    <span class="text-[13px] font-bold text-text-main">¥{{ number_format($js['help_wage']) }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                {{-- SHIFT card：勤務条件 --}}
                @if(!empty($js['working_hours'] ?? '') || !empty($js['working_days'] ?? '') || !empty($js['regular_holiday'] ?? '') || !empty($js['qualification'] ?? ''))
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-calendar-check"></i> SHIFT
                        </h3>
                        <div class="flex flex-col gap-3">
                            @if(!empty($js['working_hours'] ?? ''))
                                <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                                    <span class="text-[12px] text-text-sub font-medium shrink-0">勤務時間</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $js['working_hours'] }}</span>
                                </div>
                            @endif
                            @if(!empty($js['working_days'] ?? ''))
                                <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                                    <span class="text-[12px] text-text-sub font-medium shrink-0">勤務日数</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $js['working_days'] }}</span>
                                </div>
                            @endif
                            @if(!empty($js['regular_holiday'] ?? ''))
                                <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                                    <span class="text-[12px] text-text-sub font-medium shrink-0">定休日</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $js['regular_holiday'] }}</span>
                                </div>
                            @endif
                            @if(!empty($js['qualification'] ?? ''))
                                <div class="flex justify-between items-start gap-3">
                                    <span class="text-[12px] text-text-sub font-medium shrink-0">応募資格</span>
                                    <span class="text-[13px] font-bold text-text-main text-right">{{ $js['qualification'] }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endif

                {{-- ABOUT card：店長メッセージ + 仕事内容 --}}
                @if(!empty($js['pr_message'] ?? '') || !empty($js['job_content'] ?? ''))
                    <x-ui.card class="p-5">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text mb-4 flex items-center gap-2">
                            <i class="fas fa-comment-dots"></i> ABOUT
                        </h3>
                        @if(!empty($js['pr_message'] ?? ''))
                            <div class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $js['pr_message'] }}</div>
                        @endif
                        @if(!empty($js['job_content'] ?? ''))
                            <div class="@if(!empty($js['pr_message'] ?? '')) mt-3 pt-3 border-t border-line @endif">
                                <span class="text-[12px] text-text-sub font-medium block mb-1">仕事内容</span>
                                <div class="text-[13px] text-text-main leading-relaxed whitespace-pre-line">{{ $js['job_content'] }}</div>
                            </div>
                        @endif
                    </x-ui.card>
                @endif

                {{-- 未設定時のガイダンス --}}
                @if(empty($js['regular_wage'] ?? 0) && empty($js['bonus_reward'] ?? 0) && empty($js['pr_message'] ?? '') && empty($js['working_hours'] ?? ''))
                    <div class="p-5 rounded-card border border-dashed border-line-accent/40 text-center">
                        <i class="fas fa-circle-info text-accent-text text-[20px] mb-2 block"></i>
                        <p class="text-[12px] text-text-sub leading-relaxed">
                            まだ求人票の詳細が登録されていません。<br>
                            上の <strong class="text-accent-text">求人票を編集する</strong> から入力してください。
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ========== SHOP panel：店舗情報 + 許可証 ========== --}}
        <div data-tab-panel="shop">
            <div class="p-4 flex flex-col gap-4">
                {{-- プロファイルを編集（オーナー専用。スタッフには非表示）
                     元のカード型デザインに戻し、背景を薄く紫にトーンして情報カードから
                     差別化（求人票を編集するボタンと同じトーン）。 --}}
                @shopowner
                <a href="{{ route('shop.profile.edit') }}"
                   class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-line-accent/50 bg-gradient-to-br from-accent/15 via-accent/6 to-surface-from shadow-card-3d hover:border-accent/70 active:scale-[0.99] transition-all">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <i class="fas fa-store text-accent-text text-[14px]"></i>
                        <span class="text-[13px] font-bold text-text-main">プロファイルを編集する</span>
                    </span>
                    <i class="fas fa-chevron-right text-text-sub text-[11px] shrink-0"></i>
                </a>
                @endshopowner

                {{-- Shop Information --}}
                <x-ui.card class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="app-title text-[13px] tracking-widest text-accent-text flex items-center gap-2">
                            <i class="fas fa-store text-lg"></i> SHOP INFORMATION
                        </h3>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">店舗名</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['shop_name'] ?: '—' }}</span>
                        </div>
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">業種</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['industry'] ?? '未設定' }}</span>
                        </div>
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">郵便番号</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['zip'] ?: '—' }}</span>
                        </div>
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">住所</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ trim(($shopInfo['pref'] ?? '') . ($shopInfo['city'] ?? '') . ($shopInfo['addr1'] ?? '')) ?: '—' }}</span>
                        </div>
                        @if(!empty($shopInfo['tel'] ?? null))
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">電話</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['tel'] }}</span>
                        </div>
                        @endif
                        @if(!empty($shopInfo['business_hours_shop'] ?? null))
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">店舗の営業時間</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['business_hours_shop'] }}</span>
                        </div>
                        @endif
                        @if(!empty($shopInfo['nearest_stations'] ?? []))
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">最寄り駅</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{!! nl2br(e(implode("\n", $shopInfo['nearest_stations']))) !!}</span>
                        </div>
                        @elseif(!empty($shopInfo['nearest_station'] ?? null))
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">最寄り</span>
                            <span class="text-[13px] font-bold text-text-main text-right">{{ $shopInfo['nearest_station'] }}</span>
                        </div>
                        @endif
                        @if(!empty($shopInfo['working_hours'] ?? null) || !empty($shopInfo['working_days'] ?? null) || !empty($shopInfo['regular_holiday'] ?? null))
                        <div class="flex justify-between items-start border-b border-line pb-2 gap-3">
                            <span class="text-[12px] text-text-sub font-medium shrink-0">勤務・休日</span>
                            <span class="text-[13px] font-bold text-text-main text-right">
                                @if(!empty($shopInfo['working_hours'])){{ $shopInfo['working_hours'] }}@else時間未設定@endif
                                ／
                                @if(!empty($shopInfo['working_days'])){{ $shopInfo['working_days'] }}@else勤務日未設定@endif
                                @if(!empty($shopInfo['regular_holiday']))
                                    <br>定休: {{ $shopInfo['regular_holiday'] }}
                                @endif
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- タググループ --}}
                    @php $tagGroups = $shopInfo['tag_groups'] ?? []; @endphp
                    @if(!empty($tagGroups))
                        <div class="mt-4 pt-4 border-t border-line flex flex-col gap-3">
                            @foreach($tagGroups as $group)
                                @php
                                    $gLabel = (string) ($group['label'] ?? '');
                                    if (str_contains($gLabel, 'ご利用プラン')) continue;
                                    $gTags = array_values(array_filter(
                                        (array) ($group['tags'] ?? []),
                                        static fn ($t) => ! str_contains((string) $t, 'ご利用プラン')
                                    ));
                                @endphp
                                @if($gTags !== [])
                                    <div>
                                        <div class="text-[10px] text-text-sub uppercase tracking-wider mb-1">{{ $gLabel }}</div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($gTags as $t)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-accent/10 border border-line-accent/40 text-[12px] font-medium text-text-main">{{ $t }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Licenses：提出・管理は専用ページ（ライトモード）へ。ここは状況サマリーのみ --}}
                    @php
                        $docList = collect($documents ?? []);
                        $docTotal = $docList->count();
                        $docApproved = $docList->where('status', 'approved')->count();
                        $docPending = $docList->where('status', 'pending')->count();
                        $docRejected = $docList->where('status', 'rejected')->count();
                    @endphp
                    <div id="license-section" class="mt-5 pt-5 border-t border-amber-400/30" style="scroll-margin-top: 120px;">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <h3 class="app-title text-[13px] tracking-widest text-amber-300 mb-1">
                                    <i class="fas fa-file-signature mr-1" aria-hidden="true"></i>許可証の登録
                                </h3>
                                <p class="text-[12px] text-text-sub">
                                    承認 {{ $docApproved }}/{{ $docTotal }}件
                                    @if($docPending > 0)・審査中 {{ $docPending }}件@endif
                                    @if($docRejected > 0)・<span style="color: var(--color-danger);">差戻し {{ $docRejected }}件</span>@endif
                                </p>
                            </div>
                            <a href="{{ route('shop.mypage.documents.index') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full font-bold bg-accent text-on-accent shadow-[0_4px_12px_rgba(0,0,0,0.4)] active:scale-[0.98] transition-transform duration-150 text-[13px]">
                                <i class="fas fa-file-arrow-up" aria-hidden="true"></i> 提出・管理する
                            </a>
                        </div>
                    </div>
                </x-ui.card>

            </div>
        </div>
    </div>

</div>

{{-- ============================================================
     モーダル群
     ============================================================ --}}

{{-- ひとこと編集モーダル（Tailwind） --}}
<div id="modal-word"
     class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/60 backdrop-blur-sm p-5">
    <div class="w-full max-w-md bg-gradient-to-br from-surface-from to-base border border-line-accent/40 rounded-card shadow-card-3d p-5">
        <h3 class="app-title text-[13px] text-accent-text tracking-widest mb-3">ひとことを編集</h3>
        <textarea id="word-input" rows="3" maxlength="50"
                  placeholder="{{ $wordPlaceholder }}"
                  class="w-full px-3 py-2 rounded-panel bg-accent/10 border border-line-accent/40 text-text-main placeholder-gray-400 shadow-input-dark outline-none resize-none"></textarea>
        <p class="text-[10px] text-text-sub mt-2 mb-4">※更新するとタイムラインに反映されます</p>
        <div class="flex justify-end gap-2">
            <button type="button" id="word-edit-cancel-btn"
                    class="px-5 py-2 rounded-full border border-line-accent/40 text-text-main text-[13px] font-bold">戻る</button>
            <button type="button" id="word-edit-save-btn"
                    class="px-5 py-2 rounded-full bg-gradient-to-r from-accent-grad-from to-accent-grad-to text-on-accent-strong text-[13px] font-bold shadow-btn-3d">保存</button>
        </div>
    </div>
</div>

{{-- 優良店バッヂの仕様モーダル（Tailwind） --}}
<div id="modal-good-payer-badge"
     class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/60 backdrop-blur-sm p-5"
     role="dialog" aria-modal="true" aria-labelledby="good-payer-badge-modal-title">
    <div class="w-full max-w-md bg-gradient-to-br from-surface-from to-base border border-amber-400/40 rounded-card shadow-card-3d p-5 relative">
        <button type="button" id="good-payer-badge-modal-close-top"
                aria-label="閉じる"
                class="absolute top-3 right-3 w-7 h-7 rounded-full flex items-center justify-center text-text-sub hover:text-text-main hover:bg-white/5 transition-colors">×</button>
        <h3 id="good-payer-badge-modal-title" class="app-title text-[13px] text-amber-400 tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-crown text-base"></i> 優良店バッヂの獲得条件
        </h3>
        <ul class="text-[13px] text-text-main leading-relaxed list-disc pl-5 space-y-1">
            <li>すべての案件が「店舗入金確認済み」まで完了している</li>
            <li>請求書発行から店舗入金確認までが10日以内である</li>
        </ul>
        <p class="text-[11px] text-text-sub mt-3">※ 条件は毎月見直され、基準を満たさなくなった場合はバッヂ表示が外れることがあります。</p>
    </div>
</div>

{{-- 画像大表示モーダル（旧構造を維持：mypage-gallery.js が依存） --}}
<div id="image-preview-modal" class="mypage-modal-overlay gallery-preview-overlay" role="dialog" aria-label="画像プレビュー">
    <div class="gallery-preview-inner">
        <img id="modal-img" src="" alt="" class="mypage-modal-preview-img">
        <div class="gallery-preview-actions">
            <button type="button" class="gpv-btn gpv-btn--ghost" id="gallery-preview-close-btn" aria-label="閉じる">
                <i class="fas fa-xmark" aria-hidden="true"></i><span>閉じる</span>
            </button>
            <button type="button" class="gpv-btn gpv-btn--primary" id="gallery-preview-recrop-btn" aria-label="編集">
                <i class="fas fa-crop-simple" aria-hidden="true"></i><span>編集</span>
            </button>
            <button type="button" class="gpv-btn gpv-btn--danger" id="gallery-preview-delete-btn" aria-label="削除">
                <i class="fas fa-trash-can" aria-hidden="true"></i><span>削除</span>
            </button>
        </div>
    </div>
</div>

{{-- 画像編集モーダル --}}
<div id="image-edit-modal" class="mypage-modal-overlay gallery-preview-overlay" role="dialog" aria-label="画像編集" style="display:none;">
    <div class="gallery-preview-inner image-edit-inner">
        <div class="image-edit-header">
            <h3 class="mypage-modal-title serif-font">画像を調整してアップロード</h3>
            <p class="image-edit-guide">
                推奨サイズは <strong>4:5（例：1080×1350px の縦長）</strong> です。<br>
                ピンチ・ドラッグで拡大縮小・位置調整できます。範囲枠内に収めたい部分を合わせてアップロードしてください。
            </p>
        </div>
        <div class="image-edit-preview-wrapper">
            <div class="image-edit-frame image-edit-frame--portrait">
                <img id="image-edit-preview" src="" alt="編集プレビュー" class="image-edit-preview-img">
                <div class="image-edit-frame-mask"></div>
            </div>
        </div>
        <div class="gallery-preview-actions image-edit-actions flex gap-2 justify-end">
            <button type="button" id="image-edit-cancel-btn"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full font-bold bg-gray-700 text-gray-200 border border-gray-600 shadow-md hover:bg-gray-600 active:translate-y-1 transition-all duration-300">
                キャンセル
            </button>
            <x-ui.button id="image-edit-confirm-btn" variant="grad" icon="check">アップロード</x-ui.button>
        </div>
    </div>
</div>

<input type="file" id="gallery-upload" class="sr-only" accept="image/*">
@endsection

@push('head-styles')
{{-- 旧 CSS deps（ギャラリーモーダル / cropper / licenses） --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/image-editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/shop-license-documents.css') }}?v=20260505">

<link rel="stylesheet" href="{{ asset('assets/css/mypage-tiles.css') }}">
<style>
    /* ===== Instagram風グリッド ===== */
    #gallery-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2px;
        padding: 0;
        margin: 0;
        list-style: none;
    }
    #gallery-list .gallery-grid-item {
        aspect-ratio: 1 / 1;
        padding: 0;
        margin: 0;
        overflow: hidden;
        position: relative;
        /* 縦スクロールはブラウザに任せる（Sortable.js forceFallback 対策）。
           長押し（delay 380ms）後の並び替えドラッグには影響しない */
        touch-action: pan-y;
    }
    #gallery-list .photo-slot {
        position: relative;
        width: 100%;
        height: 100%;
        padding: 0;
        border-radius: 0;
        overflow: hidden;
        cursor: pointer;
        box-sizing: border-box;
    }
    #gallery-list .photo-slot:not(.has-img) {
        border: 2px dashed rgba(255, 255, 255, 0.22);
        background: transparent;
    }
    #gallery-list .photo-slot > img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    #gallery-list .photo-slot-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        opacity: 0.45;
    }
    #gallery-list .photo-slot-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        line-height: 1;
    }

    /* menu-card の右端「＞」をアクセント紫の矢印だけにする（cast と同じ） */
    .shop-mypage-menu-card > span:last-child {
        width: auto !important;
        height: auto !important;
        border: 0 !important;
        border-radius: 0 !important;
        color: var(--color-accent-text) !important;
        opacity: 1 !important;
    }
    .shop-mypage-menu-card > span:last-child > i {
        font-size: 1.3rem !important;
        color: var(--color-accent-text) !important;
    }

</style>
{{-- Candidate-date availability card styles (shared with cast mypage) --}}
<link rel="stylesheet" href="{{ asset('assets/css/mypage-availability.css') }}?v=20260926-availability-subtle">
@endpush

@push('scripts')
{{-- ===== ギャラリー機能：元のスクリプト群 ===== --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="{{ asset('assets/js/gallery-sortable.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script src="{{ asset('assets/js/image-editor.js') }}"></script>
<script>
window.MYPAGE_GALLERY_CONFIG = {
    csrfToken: @json(csrf_token()),
    uploadUrl: @json(route('shop.profile.upload.image')),
    deleteUrlTemplate: @json(route('shop.profile.image.delete', ['id' => '__ID__'])),
    cropAspectW: 4,
    cropAspectH: 5,
    cropMaxWidth: 1080,
    cropMaxHeight: 1350
};
</script>
<script src="{{ asset('assets/js/mypage-gallery.js') }}"></script>

{{-- #license-section ハッシュ：SHOP タブを開いて許可証セクションへスクロール
     （サイドメニュー VERIFICATION・やることリストからの遷移用） --}}
<script>
(function () {
    'use strict';
    function openLicenseSection() {
        var shopTab = document.querySelector('[data-tab="shop"]');
        if (shopTab) shopTab.click();
        window.setTimeout(function () {
            var section = document.getElementById('license-section');
            if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
    }
    if (window.location.hash === '#license-section') openLicenseSection();
    window.addEventListener('hashchange', function () {
        if (window.location.hash === '#license-section') openLicenseSection();
    });
    // 同一ページ内のリンク（非公開アラート等）は hashchange が発火しないケースがあるため click でも開く
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href*="#license-section"]');
        if (!a) return;
        var url = new URL(a.getAttribute('href'), window.location.href);
        if (url.pathname === window.location.pathname) {
            e.preventDefault();
            if (window.location.hash !== '#license-section') window.location.hash = 'license-section';
            openLicenseSection();
        }
    });
})();
</script>

{{-- ひとこと編集 + 優良店バッヂモーダル + プロフィール編集導線 --}}
<script>
(function () {
    'use strict';
    var placeholderText = @json('今、何してる？（タイムラインに公開されます）');

    // === ひとこと編集 ===
    var openWordBtn = document.getElementById('open-word-edit-btn');
    var wordModal = document.getElementById('modal-word');
    var displayEl = document.getElementById('display-word');
    var input = document.getElementById('word-input');
    var saveBtn = document.getElementById('word-edit-save-btn');
    var cancelBtn = document.getElementById('word-edit-cancel-btn');
    var updatedEl = document.getElementById('display-word-updated');

    function showWordModal() {
        if (!wordModal) return;
        wordModal.classList.remove('hidden');
        wordModal.classList.add('flex');
        if (displayEl && input) {
            var cur = displayEl.innerText.trim();
            input.value = (cur === placeholderText) ? '' : cur;
        }
        setTimeout(function () { if (input) input.focus(); }, 50);
    }
    function hideWordModal() {
        if (!wordModal) return;
        wordModal.classList.add('hidden');
        wordModal.classList.remove('flex');
    }

    if (openWordBtn) openWordBtn.addEventListener('click', showWordModal);
    if (cancelBtn) cancelBtn.addEventListener('click', hideWordModal);
    if (wordModal) wordModal.addEventListener('click', function (e) {
        if (e.target === wordModal) hideWordModal();
    });
    if (saveBtn) saveBtn.addEventListener('click', function () {
        if (saveBtn.disabled) return;
        var val = (input ? input.value : '').trim();
        saveBtn.disabled = true;
        fetch(@json(route('shop.mypage.word')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token())
            },
            body: JSON.stringify({ word: val })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                if (displayEl) {
                    displayEl.innerText = val || placeholderText;
                    displayEl.classList.toggle('text-text-sub', !val);
                    displayEl.classList.toggle('text-text-main', !!val);
                }
                if (updatedEl && res.appeal_updated_at) {
                    updatedEl.innerText = '最終更新 ' + res.appeal_updated_at;
                }
                hideWordModal();
            } else {
                (window.appToast || window.alert)((res && res.message) || '保存に失敗しました', 'error');
            }
        })
        .catch(function () { (window.appToast || window.alert)('保存に失敗しました', 'error'); })
        .finally(function () { saveBtn.disabled = false; });
    });

    // === 優良店バッヂモーダル ===
    var openBadge = document.getElementById('open-good-payer-badge-modal');
    var badgeModal = document.getElementById('modal-good-payer-badge');
    var closeBadgeTop = document.getElementById('good-payer-badge-modal-close-top');
    function showBadgeModal() {
        if (!badgeModal) return;
        badgeModal.classList.remove('hidden');
        badgeModal.classList.add('flex');
        if (closeBadgeTop) closeBadgeTop.focus();
    }
    function hideBadgeModal() {
        if (!badgeModal) return;
        badgeModal.classList.add('hidden');
        badgeModal.classList.remove('flex');
        if (openBadge) openBadge.focus();
    }
    if (openBadge && badgeModal) {
        openBadge.addEventListener('click', showBadgeModal);
        badgeModal.addEventListener('click', function (e) {
            if (e.target === badgeModal) hideBadgeModal();
        });
    }
    if (closeBadgeTop) closeBadgeTop.addEventListener('click', hideBadgeModal);

})();
</script>

{{-- Candidate-date availability card behavior (shared with cast mypage) --}}
<script>
window.MYPAGE_AVAILABILITY_CONFIG = { csrfToken: @json(csrf_token()) };
</script>
<script src="{{ asset('assets/js/mypage-availability.js') }}?v=20260926-availability-subtle"></script>
@endpush
