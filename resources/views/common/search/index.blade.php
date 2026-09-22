@extends('layouts.app-v2')

@section('title', 'SEARCH')
@section('body-class', request()->is('cast/*') && ($activeTab ?? null) === 'pane-ai' ? 'page-search page-search-ai' : 'page-search')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/search.css') }}?v=20260913-uiux">
<link rel="stylesheet" href="{{ asset('assets/css/search-location-bar.css') }}?v=20260808-footer-clear">
<link rel="stylesheet" href="{{ asset('assets/css/sub-header.css') }}">
<style>
    /* SEARCH のタブ（検索 / 保存済み）：ラベル前のアイコン。文字と同じ色に追従させ、
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

    // タブ：cast / shop とも「検索／保存済み（旧キープ）」。
    // AI診断はガイドの表示設定に依存せず、サブヘッダーから常に利用できる。
    // キープリストは旧 KEEPS（フッターメニュー）から SEARCH 内へ移設。
    // ラベルは「保存済み」に統一し、ブックマークアイコンを添えて保存物置き場だと直感的に伝える。
    $searchQuery = request()->except(['tab', 'page']);
    if ($showAiTab) {
        $tabsForHeader = [
            ['id' => 'pane-list', 'label' => '検索', 'icon' => 'fas fa-magnifying-glass', 'url' => route('cast.search.index', array_merge($searchQuery, ['tab' => 'list'])), 'active' => $activeTab === 'pane-list'],
            ['id' => 'pane-keep', 'label' => '保存済み', 'icon' => 'fas fa-bookmark', 'url' => route('cast.search.index', array_merge($searchQuery, ['tab' => 'keep'])), 'active' => $activeTab === 'pane-keep'],
            ['id' => 'pane-ai', 'label' => 'AI診断', 'url' => route('cast.search.index', array_merge($searchQuery, ['tab' => 'ai'])), 'active' => $activeTab === 'pane-ai'],
        ];
    } else {
        $tabsForHeader = [
            ['id' => 'pane-list', 'label' => '検索', 'icon' => 'fas fa-magnifying-glass', 'url' => route('shop.search.index', $searchQuery), 'active' => $activeTab === 'pane-list'],
            ['id' => 'pane-keep', 'label' => '保存済み', 'icon' => 'fas fa-bookmark', 'url' => route('shop.search.index', array_merge($searchQuery, ['tab' => 'keep'])), 'active' => $activeTab === 'pane-keep'],
        ];
    }

    $aiTabUrl = $showAiTab ? route('cast.search.index', array_merge($searchQuery, ['tab' => 'ai'])) : null;
    $aiPersonalityTestUrl = $showAiTab
        ? asset('personality-test') . '?' . http_build_query(['return_to' => $aiTabUrl])
        : null;
@endphp

@if(!empty($tabsForHeader))
<div class="has-sub-header">
    @include('layouts.parts.sub-header', ['tabs' => $tabsForHeader])
</div>
@endif

<div class="{{ !empty($tabsForHeader) ? 'tab-page-body' : 'search-page-body' }}">
    {{-- 検索パネル：タイムライン＋一覧を統合した画面 --}}
    <div id="pane-list" class="tab-pane {{ $activeTab === 'pane-list' ? 'active' : '' }}" style="{{ $activeTab !== 'pane-list' ? 'display:none' : '' }}">
        {{-- 上部検索バー：スクロールしても固定（sticky）。
             探索拠点・詳細フィルター・指定中条件は開閉エリアに集約し、
             閉じると検索窓1行だけになり結果一覧が広がる。
             ※ デフォルトは「閉じ」状態で描画してちらつき防止（明示タップで開ける） --}}
        <div class="search-topbar is-collapsed" id="search-topbar">
            <div class="search-filter-box">
                @include($partsView . '.filter')
            </div>
        </div>

        <div class="search-results-summary text-sm text-text-sub px-4 py-3" role="status">
            <p><strong>{{ number_format($resultCount ?? count($items)) }}件</strong>・{{ $sortOptions[$sort] ?? 'ひとこと更新が新しい順' }}</p>
            <p data-applied-search-summary></p>
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
                    @if($showAiTab)<a class="btn-ghost-cta" href="{{ $aiTabUrl }}">AI診断で探す</a>@endif
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
            >
                <header class="ai-chat__header">
                    <div class="ai-chat__header-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <div class="ai-chat__header-text">
                        <p class="ai-chat__header-title">5問でお店診断</p>
                        <p class="ai-chat__header-sub">選択肢に答えると、希望に合うお店を提案します。エリアは掲載店舗の所在地から選べます。</p>
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

                {{-- コンポーザー：クイックリプライ + 入力欄（チャット最下部に固定） --}}
                <div class="ai-chat__composer">
                    <div class="ai-chat__quick-replies" data-ai-quick-replies></div>
                    <p class="text-sm text-text-sub">上の選択肢から回答してください。</p>
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
<script src="{{ asset('assets/js/search-detail.js') }}?v=20260913-uiux"></script>
<script src="{{ asset('assets/js/favorite-quick.js') }}?v=20260815-saved-copy"></script>
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
<script src="{{ asset('assets/js/ai-chat.js') }}?v=20260913-uiux"></script>
@endif
@endpush
