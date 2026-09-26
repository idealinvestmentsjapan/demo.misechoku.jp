@extends('layouts.app-v2')

@section('title', ($activeTab ?? '') === 'pane-ai' ? 'AIコンシェルジュ' : 'SEARCH')
@section('body-class', request()->is('cast/*') && ($activeTab ?? null) === 'pane-ai' ? 'page-search page-search-ai' : 'page-search')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/search.css') }}?v=20260926-summary-oneline">
<link rel="stylesheet" href="{{ asset('assets/css/search-location-bar.css') }}?v=20260926-decouple-sidebar">
<link rel="stylesheet" href="{{ asset('assets/css/sub-header.css') }}">
<style>
    /* SEARCH のタブ（検索 / キープ）：ラベル前のアイコン。文字と同じ色に追従させ、
       僅かな右マージンだけ入れて、フォントサイズは 1em 相当で自然に馴染ませる */
    .sub-header-wrapper .sub-header-tabs .tab-item .tab-item__icon {
        margin-right: 6px;
        font-size: 0.9em;
        opacity: 0.95;
    }
</style>
@endpush

@section('content')
@php
    // 現在のプレフィックス（shop または cast）を取得
    $prefix = request()->is('cast/*') ? 'cast' : 'shop';
    $showAiTab = $prefix === 'cast';
    $partsView = $prefix === 'cast' ? 'casts.parts' : 'shops.search.parts';
    $activeTab = $activeTab ?? 'pane-list';
    $searchTab = $searchTab ?? 'list';

    // Tabs: both cast / shop show "search / saved" only.
    // The AI concierge is NOT a sub-header tab: it opens full-screen (like TALK)
    // via the okojo character (bottom-right) on cast search pages.
    // On the AI pane itself, hide the tabs and show the header title instead.
    $searchQuery = request()->except(['tab', 'page']);
    if ($activeTab === 'pane-ai') {
        $tabsForHeader = [];
    } elseif ($showAiTab) {
        $tabsForHeader = [
            ['id' => 'pane-list', 'label' => '検索', 'icon' => 'fas fa-magnifying-glass', 'url' => route('cast.search.index', array_merge($searchQuery, ['tab' => 'list'])), 'active' => $activeTab === 'pane-list'],
            ['id' => 'pane-keep', 'label' => 'キープ', 'icon' => 'fas fa-bookmark', 'url' => route('cast.search.index', array_merge($searchQuery, ['tab' => 'keep'])), 'active' => $activeTab === 'pane-keep'],
        ];
    } else {
        $tabsForHeader = [
            ['id' => 'pane-list', 'label' => '検索', 'icon' => 'fas fa-magnifying-glass', 'url' => route('shop.search.index', $searchQuery), 'active' => $activeTab === 'pane-list'],
            ['id' => 'pane-keep', 'label' => 'キープ', 'icon' => 'fas fa-bookmark', 'url' => route('shop.search.index', array_merge($searchQuery, ['tab' => 'keep'])), 'active' => $activeTab === 'pane-keep'],
        ];
    }

    $aiTabUrl = $showAiTab ? route('cast.search.index', array_merge($searchQuery, ['tab' => 'ai'])) : null;
    $aiPersonalityTestUrl = $showAiTab
        ? asset('personality-test') . '?' . http_build_query(['return_to' => $aiTabUrl])
        : null;

    // Personalized suggestions for the AI concierge QA flow (2-3 chips per question).
    // Areas: prefer ones matching the cast's profile address. Industries: prefer saved preferences.
    $aiSuggest = null;
    if ($showAiTab && $activeTab === 'pane-ai') {
        $aiAreaNames = collect($detailSearchOptions['areas'] ?? [])->pluck('name')->filter()->values();
        $aiProfileAddress = (string) ($savedPreferences['profile_address'] ?? '');
        $aiScoredAreas = $aiAreaNames->sortByDesc(function ($name) use ($aiProfileAddress) {
            if ($aiProfileAddress === '') { return 0; }
            $score = 0;
            foreach (preg_split('/\s+/u', (string) $name) as $i => $part) {
                if ($part !== '' && mb_strpos($aiProfileAddress, $part) !== false) {
                    $score += ($i === 0 ? 1 : 2); // city match outweighs pref match
                }
            }
            return $score;
        })->values();

        $savedIndustryIds = array_map('intval', (array) ($savedPreferences['industry_ids'] ?? []));
        $aiIndustryList = collect($detailSearchOptions['industries'] ?? []);
        $aiPreferredIndustries = $aiIndustryList
            ->filter(fn ($row) => in_array((int) data_get($row, 'id', 0), $savedIndustryIds, true))
            ->pluck('name')->filter()->values();
        $aiOtherIndustries = $aiIndustryList->pluck('name')->filter()
            ->reject(fn ($name) => $aiPreferredIndustries->contains($name))->values();

        $aiSuggest = [
            'areas'      => $aiScoredAreas->take(2)->values()->all(),
            'industries' => $aiPreferredIndustries->concat($aiOtherIndustries)->take(2)->values()->all(),
            'wage_min'   => (int) ($savedPreferences['hourly_wage_min'] ?? 0),
        ];
    }
@endphp

@if(!empty($tabsForHeader))
<div class="has-sub-header">
    @include('layouts.parts.sub-header', ['tabs' => $tabsForHeader])
</div>
@endif

<div class="{{ !empty($tabsForHeader) ? 'tab-page-body' : 'search-page-body' }}">
    {{-- 検索パネル：タイムライン＋一覧を統合した画面 --}}
    <div id="pane-list" class="tab-pane {{ $activeTab === 'pane-list' ? 'active' : '' }}" style="{{ $activeTab !== 'pane-list' ? 'display:none' : '' }}">
        {{-- 探索拠点：キャストのみ（店舗は住所固定）。フィルターの上に常時表示して
             検索結果の距離ソート／半径の前提を明示する。旧サイドメニュー「探索拠点の設定」から移設。 --}}
        @if($prefix === 'cast')
            <div class="search-location-bar">
                @include('layouts.parts.location-pill')
            </div>
        @endif
        {{-- 上部検索バー：スクロールしても固定（sticky）。
             詳細フィルター・指定中条件は開閉エリアに集約し、
             閉じると検索窓1行だけになり結果一覧が広がる。
             ※ デフォルトは「閉じ」状態で描画してちらつき防止（明示タップで開ける） --}}
        <div class="search-topbar is-collapsed" id="search-topbar">
            <div class="search-filter-box">
                @include($partsView . '.filter')
            </div>
        </div>

        {{-- 検索サマリ：件数・並び順・適用中の条件をピル/チップで表示（2026-09-24 デザイン刷新）
             従来は「1234件・ひとこと更新が新しい順」+ プレーンテキストの条件羅列で
             デザイン性を損なっていたため、視覚的なピル+チップに置換。並び順はタップで
             既存の sort パネル（#search-sort-trigger）を開くショートカットとして機能する。 --}}
        <div class="search-summary" role="status" aria-live="polite">
            <div class="search-summary__pills">
                <span class="search-summary__count" aria-label="検索結果 {{ number_format($resultCount ?? count($items)) }}件">
                    <i class="fas fa-list-check search-summary__count-ico" aria-hidden="true"></i>
                    <span class="search-summary__count-num">{{ number_format($resultCount ?? count($items)) }}</span>
                    <span class="search-summary__count-unit">件</span>
                </span>
                <button type="button" class="search-summary__sort"
                        onclick="const t=document.getElementById('search-sort-trigger'); if(t){t.click();}"
                        aria-label="並び替えを変更">
                    <i class="fas fa-sort-amount-down" aria-hidden="true"></i>
                    <span>{{ $sortOptions[$sort] ?? 'ひとこと更新が新しい順' }}</span>
                    <i class="fas fa-chevron-down search-summary__sort-caret" aria-hidden="true"></i>
                </button>
            </div>
            <div class="search-summary__chips" data-applied-search-chips hidden></div>
        </div>
        <ul class="connection-list connection-list--search">
            @forelse($items as $item)
                {{-- 役割に応じたリストアイテム（キャスト用/店舗用） --}}
                @include($partsView . '.list-item', ['item' => $item])
            @empty
                <li class="text-center py-12 px-4 text-sm text-text-main">
                    <p class="font-bold">現在の条件に合う{{ $prefix === 'cast' ? 'お店' : 'キャスト' }}が見つかりませんでした。</p>
                    <p class="text-text-sub my-3">エリアを広げるか、条件を減らしてお試しください。</p>
                    <button type="button" class="btn-secondary-cta" onclick="document.getElementById('open-detail-search').click()">検索条件を変更する</button>
                    <a class="btn-ghost-cta" href="{{ $prefix === 'cast' ? route('cast.search.index', ['tab' => 'list', 'filters_applied' => 1, 'location_mode' => 'none']) : route('shop.search.index', ['filters_applied' => 1, 'location_mode' => 'none']) }}">すべての条件を外す</a>
                    @if($showAiTab)<a class="btn-ghost-cta" href="{{ $aiTabUrl }}">AIコンシェルジュに相談する</a>@endif
                </li>
            @endforelse
        </ul>
        @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $items->hasPages())
        <nav class="flex items-center justify-between gap-3 px-4 py-6 text-sm" aria-label="検索結果のページ">
            @if($items->onFirstPage())<span aria-disabled="true">前のページ</span>@else<a class="btn-secondary-cta" href="{{ $items->previousPageUrl() }}">前のページ</a>@endif
            <span>{{ $items->currentPage() }} / {{ $items->lastPage() }} ページ</span>
            @if($items->hasMorePages())<a class="btn-secondary-cta" href="{{ $items->nextPageUrl() }}">次のページ</a>@else<span aria-disabled="true">次のページ</span>@endif
        </nav>
        @endif
    </div>

    @if($showAiTab)
        {{-- パネル：AIコンシェルジュ（TALK 同様の全画面チャット。入力欄は最下部固定） --}}
        <div id="pane-ai" class="tab-pane {{ $activeTab === 'pane-ai' ? 'active' : '' }}" style="{{ $activeTab !== 'pane-ai' ? 'display:none' : '' }}">
            <section
                class="ai-chat"
                data-ai-chat-root
                data-endpoint="{{ route('cast.search.ai-chat') }}"
                data-avatar="{{ asset('assets/images/guide/guide-character.png') }}"
                data-personality-type="{{ $personalityType ?? '' }}"
                data-area-options="{{ json_encode(collect($detailSearchOptions['areas'] ?? [])->pluck('name')->filter()->values()->all(), JSON_UNESCAPED_UNICODE) }}"
                data-ai-suggest="{{ json_encode($aiSuggest ?? (object) [], JSON_UNESCAPED_UNICODE) }}"
            >
                <header class="ai-chat__header">
                    <div class="ai-chat__header-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <div class="ai-chat__header-text">
                        <p class="ai-chat__header-title">AIコンシェルジュ</p>
                        <p class="ai-chat__header-sub">希望を教えると、AIがあなたに合うお店を提案します。選択肢のほか自由入力もOK。</p>
                    </div>
                </header>

                {{-- 接客タイプ診断の状態ストリップ（登録済み: タイプ表示 / 未登録: 診断導線） --}}
                <div class="ai-chat__notice">
                    @if(!empty($personalityType))
                        <span class="ai-chat__notice-label">
                            <i class="fas fa-user-check"></i> 接客タイプ <strong>{{ $personalityType }}</strong> 登録済み
                        </span>
                        <a href="{{ $aiPersonalityTestUrl }}" target="_blank" rel="noopener noreferrer" class="ai-chat__notice-link">再診断する</a>
                    @else
                        <span class="ai-chat__notice-label">
                            <i class="fas fa-wand-magic-sparkles"></i> 接客タイプ診断でおすすめの精度が上がります
                        </span>
                        <a href="{{ $aiPersonalityTestUrl }}" target="_blank" rel="noopener noreferrer" class="ai-chat__notice-link">診断する</a>
                    @endif
                </div>

                <div class="ai-chat__thread" data-ai-thread aria-live="polite"></div>

                {{-- Composer: quick-reply chips + free-text input (pinned to bottom).
                     The input is hidden during QA steps and revealed by the "other" chip,
                     then stays visible for free chat after the recommendation. --}}
                <div class="ai-chat__composer">
                    <div class="ai-chat__quick-replies" data-ai-quick-replies></div>
                    <form class="ai-chat__form" data-ai-input-form hidden>
                        <input type="text" class="ai-chat__input" data-ai-input maxlength="200"
                               placeholder="希望を入力してね（例：新宿で時給4,000円以上）" autocomplete="off">
                        <button type="submit" class="ai-chat__send" aria-label="送信"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </section>
        </div>
    @endif

    {{-- パネル：キープリスト（メッセージを送る前の保存リスト + おすすめ） --}}
    <div id="pane-keep" class="tab-pane {{ $activeTab === 'pane-keep' ? 'active' : '' }}" style="{{ $activeTab !== 'pane-keep' ? 'display:none' : '' }}">
        @if($activeTab === 'pane-keep')
            @include('common.search.keep-pane')
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/sub-header.js') }}"></script>
<script src="{{ asset('assets/js/search-detail.js') }}?v=20260924-summary-pills"></script>
<script src="{{ asset('assets/js/favorite-quick.js') }}?v=20260926-keep-rename"></script>
<script>
{{-- 上部検索バーの開閉：デフォルトは閉じ（HTML初期状態）→ タップで開閉するだけ。
     localStorage 保存はやめて、SEARCH を開くたびに常に閉じた状態からスタートさせる --}}
(function () {
    var bar = document.getElementById('search-topbar');
    var btn = document.getElementById('search-topbar-toggle');
    if (!bar || !btn) return;

    btn.addEventListener('click', function () {
        var collapsed = !bar.classList.contains('is-collapsed');
        bar.classList.toggle('is-collapsed', collapsed);
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
})();
</script>
@if($showAiTab)
<script src="{{ asset('assets/js/ai-chat.js') }}?v=20260924-concierge-ui"></script>
@endif
@endpush
