{{-- ============================================================
     接客タイプ表示（プロフィール共通パーシャル）
     - 登録済み: 目立つカード（軸アイコンを4つ並べたイラスト帯付き） → タップで詳細解説モーダル
     - 未登録:   自分のプロフィールなら診断への導線、他者表示なら「--」行
     引数:
       $typeCode  : 4文字コード（例 LCIR）または空
       $canRetest : true なら診断（やり直し）導線を表示（自分のプロフィール用）
       $retestUrl : 診断ページ URL（canRetest 時のみ使用）
     ============================================================ --}}
@php
    $ptInfo = \App\Services\PersonalityTypeCatalog::get($typeCode ?? null);
    $ptRetestUrl = $retestUrl ?? (asset('personality-test') . '?' . http_build_query(['return_to' => url()->current()]));

    // Axis "tone" -> Tailwind class fragment. Kept inline so the palette stays
    // in one place and does not require a Tailwind theme rebuild.
    $ptToneChip = [
        'warm'    => 'from-orange-400/80 to-rose-500/80',
        'cool'    => 'from-sky-400/80 to-indigo-500/80',
        'romance' => 'from-pink-400/80 to-rose-500/80',
        'wisdom'  => 'from-violet-400/80 to-indigo-500/80',
        'spark'   => 'from-amber-400/80 to-orange-500/80',
        'calm'    => 'from-teal-400/80 to-emerald-500/80',
    ];
@endphp

@if($ptInfo)
    {{-- ============ 目立たせたタイプカード（タップで解説） ============ --}}
    <button type="button" id="open-personality-type-modal"
            aria-haspopup="dialog" aria-controls="personality-type-modal"
            class="ptype-card group w-full relative overflow-hidden text-left
                   rounded-2xl border border-line-accent/40
                   bg-gradient-to-br from-accent/15 via-surface-from to-base
                   shadow-card-3d hover:border-accent/70 active:scale-[0.99] transition-all">
        {{-- 背景装飾：星屑イラスト（純装飾なので aria-hidden） --}}
        <span aria-hidden="true" class="ptype-card__deco">
            <i class="fas fa-star ptype-card__deco-star ptype-card__deco-star--1"></i>
            <i class="fas fa-sparkles ptype-card__deco-star ptype-card__deco-star--2"></i>
            <i class="fas fa-star ptype-card__deco-star ptype-card__deco-star--3"></i>
        </span>

        <div class="relative flex items-center gap-3 px-4 pt-3.5 pb-3">
            {{-- 左：メインオーブ（グラデ + キラキラ + 中央のワンドアイコン） --}}
            <span class="shrink-0 relative w-14 h-14 rounded-full flex items-center justify-center
                         bg-gradient-to-br from-accent-grad-from to-accent-grad-to text-on-accent-strong
                         shadow-[inset_0_2px_3px_rgba(255,255,255,0.35),0_6px_14px_rgba(0,0,0,0.35)]">
                <i class="fas fa-wand-magic-sparkles text-[20px]"></i>
                {{-- オーブ周りのキラキラ --}}
                <i aria-hidden="true" class="fas fa-star absolute -top-1 -right-1 text-[9px] text-on-accent-strong/90 drop-shadow"></i>
                <i aria-hidden="true" class="fas fa-star absolute -bottom-0.5 -left-1 text-[7px] text-on-accent-strong/70"></i>
            </span>

            {{-- 中央：ラベル + タイトル --}}
            <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-extrabold tracking-[0.18em] text-accent-text uppercase">
                    接客タイプ
                    <span class="ptype-card__code app-title">{{ $ptInfo['code'] }}</span>
                </span>
                <span class="text-[15px] font-extrabold text-text-main leading-snug truncate">
                    {{ $ptInfo['title'] }}
                </span>
            </span>

            {{-- 右：「解説を見る」インジケータ --}}
            <span class="shrink-0 inline-flex flex-col items-center gap-0.5 text-text-sub group-hover:text-accent-text transition-colors">
                <i class="fas fa-circle-info text-[16px]"></i>
                <span class="text-[9px] font-bold tracking-wider">解説</span>
            </span>
        </div>

        {{-- 4軸アイコンの帯（イラスト行） --}}
        <div class="ptype-axis-strip">
            @foreach($ptInfo['axes'] as $axis)
                <span class="ptype-axis-chip bg-gradient-to-br {{ $ptToneChip[$axis['tone']] ?? 'from-accent-grad-from/80 to-accent-grad-to/80' }}">
                    <i class="fas {{ $axis['icon'] }}"></i>
                    <span class="ptype-axis-chip__code app-title">{{ $axis['code'] }}</span>
                </span>
            @endforeach
        </div>
    </button>

    {{-- ============ 詳細解説モーダル ============ --}}
    <div id="personality-type-modal" role="dialog" aria-modal="true" aria-label="接客タイプの解説"
         class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/60 backdrop-blur-sm p-5">
        <div class="w-full max-w-[560px] max-h-[86vh] overflow-hidden flex flex-col rounded-2xl
                    border border-line-accent/40 bg-gradient-to-br from-surface-from to-base shadow-card-3d">

            {{-- ヒーローヘッダー：装飾たっぷり --}}
            <div class="ptype-hero relative overflow-hidden">
                <span aria-hidden="true" class="ptype-hero__deco">
                    <i class="fas fa-star ptype-hero__deco-star ptype-hero__deco-star--a"></i>
                    <i class="fas fa-sparkles ptype-hero__deco-star ptype-hero__deco-star--b"></i>
                    <i class="fas fa-star ptype-hero__deco-star ptype-hero__deco-star--c"></i>
                    <i class="fas fa-sparkles ptype-hero__deco-star ptype-hero__deco-star--d"></i>
                    <i class="fas fa-star ptype-hero__deco-star ptype-hero__deco-star--e"></i>
                </span>

                <button type="button" id="close-personality-type-modal" aria-label="閉じる"
                        class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center
                               bg-black/25 text-white/95 hover:bg-black/40 transition-colors">
                    <i class="fas fa-times"></i>
                </button>

                <div class="relative px-5 pt-6 pb-5 flex flex-col items-center text-center gap-2">
                    {{-- 大型オーブ --}}
                    <span class="ptype-hero__orb">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </span>
                    <span class="ptype-hero__label">接客タイプ</span>
                    <span class="ptype-hero__code app-title">{{ $ptInfo['code'] }}</span>
                    <h3 class="ptype-hero__title">{{ $ptInfo['title'] }}</h3>

                    {{-- 軸のアイコン列（ヒーロー版） --}}
                    <div class="ptype-hero__axis-row">
                        @foreach($ptInfo['axes'] as $axis)
                            <span class="ptype-hero__axis-chip bg-gradient-to-br {{ $ptToneChip[$axis['tone']] ?? 'from-accent-grad-from to-accent-grad-to' }}">
                                <i class="fas {{ $axis['icon'] }}"></i>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- スクロール領域 --}}
            <div class="overflow-y-auto px-5 py-4 flex flex-col gap-4">
                {{-- 強み（キラキラアイコン付き見出し） --}}
                <div class="ptype-block ptype-block--strength">
                    <p class="ptype-block__label">
                        <i class="fas fa-star"></i>
                        STRENGTH — 強み
                    </p>
                    <p class="ptype-block__body ptype-block__body--strong">{{ $ptInfo['strength'] }}</p>
                </div>

                {{-- 解説 --}}
                <div>
                    <p class="ptype-block__label ptype-block__label--plain">
                        <i class="fas fa-comment-dots"></i>
                        ABOUT — このタイプについて
                    </p>
                    <p class="ptype-block__body">{{ $ptInfo['description'] }}</p>
                </div>

                {{-- 4軸の内訳（大型アイコンで内訳をイラスト化） --}}
                <div>
                    <p class="ptype-block__label ptype-block__label--plain">
                        <i class="fas fa-shapes"></i>
                        4つの軸
                    </p>
                    <ul class="flex flex-col gap-2.5">
                        @foreach($ptInfo['axes'] as $axis)
                            <li class="ptype-axis-row">
                                <span class="ptype-axis-row__orb bg-gradient-to-br {{ $ptToneChip[$axis['tone']] ?? 'from-accent-grad-from to-accent-grad-to' }}">
                                    <i class="fas {{ $axis['icon'] }}"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5 mb-0.5">
                                        <span class="ptype-axis-row__code app-title">{{ $axis['code'] }}</span>
                                        <span class="text-[12px] font-extrabold text-text-main">{{ $axis['label'] }}</span>
                                    </span>
                                    <span class="block text-[11px] text-text-sub leading-relaxed">{{ $axis['text'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- 気をつけたいこと --}}
                <div class="ptype-block ptype-block--caution">
                    <p class="ptype-block__label ptype-block__label--muted">
                        <i class="fas fa-triangle-exclamation"></i>
                        POINT — 気をつけたいこと
                    </p>
                    <p class="ptype-block__body ptype-block__body--muted">{{ $ptInfo['weakness'] }}</p>
                </div>

                @if(!empty($canRetest))
                    <a href="{{ $ptRetestUrl }}"
                       class="mt-1 inline-flex items-center justify-center gap-2 w-full px-4 py-3 rounded-full
                              border border-line-accent/40 bg-accent/10 text-accent-text
                              text-[13px] font-bold hover:bg-accent/20 active:scale-[0.99] transition-all">
                        <i class="fas fa-arrow-rotate-left text-[12px]"></i> 診断をやり直す
                    </a>
                @endif
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';
        var openBtn = document.getElementById('open-personality-type-modal');
        var modal = document.getElementById('personality-type-modal');
        var closeBtn = document.getElementById('close-personality-type-modal');
        if (!openBtn || !modal) return;
        function show() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        function hide() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        openBtn.addEventListener('click', show);
        if (closeBtn) closeBtn.addEventListener('click', hide);
        modal.addEventListener('click', function (e) { if (e.target === modal) hide(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
    })();
    </script>
@elseif(!empty($canRetest))
    {{-- ============ 未登録（自分のプロフィール）：診断への導線 ============ --}}
    <a href="{{ $ptRetestUrl }}"
       class="ptype-cta group w-full relative overflow-hidden block
              rounded-2xl border-2 border-dashed border-line-accent/50
              hover:border-accent/70 active:scale-[0.99] transition-all">
        <span aria-hidden="true" class="ptype-cta__deco">
            <i class="fas fa-heart ptype-cta__deco-icon ptype-cta__deco-icon--1"></i>
            <i class="fas fa-lightbulb ptype-cta__deco-icon ptype-cta__deco-icon--2"></i>
            <i class="fas fa-bolt ptype-cta__deco-icon ptype-cta__deco-icon--3"></i>
            <i class="fas fa-moon ptype-cta__deco-icon ptype-cta__deco-icon--4"></i>
        </span>

        <div class="relative flex items-center gap-3 px-4 py-4">
            <span class="shrink-0 w-14 h-14 rounded-full flex items-center justify-center
                         bg-gradient-to-br from-accent-grad-from to-accent-grad-to text-on-accent-strong
                         shadow-[inset_0_2px_3px_rgba(255,255,255,0.35),0_6px_14px_rgba(0,0,0,0.35)]">
                <i class="fas fa-wand-magic-sparkles text-[20px]"></i>
            </span>
            <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                <span class="text-[10px] font-extrabold tracking-[0.18em] text-accent-text uppercase">
                    接客タイプ · 診断してみよう
                </span>
                <span class="text-[14px] font-extrabold text-text-main leading-snug">
                    あなたの接客スタイルは？
                </span>
                <span class="text-[11px] text-text-sub">
                    4つの質問で16タイプから診断
                </span>
            </span>
            <span class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-accent-text">
                診断
                <i class="fas fa-chevron-right text-[10px]"></i>
            </span>
        </div>
    </a>
@else
    {{-- ============ 未登録（他者からの表示）：従来どおりの行表示 ============ --}}
    <div class="flex justify-between items-center border-b border-line pb-2">
        <span class="text-[12px] text-text-sub font-medium">接客タイプ</span>
        <span class="text-[13px] font-bold text-text-main">--</span>
    </div>
@endif
