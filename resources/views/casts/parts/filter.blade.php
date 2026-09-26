@php
    $sortOptions = $sortOptions ?? [];
    $sort = $sort ?? 'hitokoto';
@endphp
<div class="search-filter-inner">
    <div class="search-filter-minimal-wrap">
        {{-- 2026-09-26: 検索フィルター上部の並び替えアイコンを撤去（サマリ側「並び替え」ドロップダウンに一本化）。
             sort-panel の描画も index 側で行うため、ここには含めない。 --}}
        <div class="search-filter-minimal-row">
            <input type="text" id="search-keyword" class="search-filter-minimal__input" placeholder="エリア・店名などフリーワードで検索" value="{{ request('keyword', '') }}" autocomplete="off">
            <button type="button" class="search-filter-icon-btn search-filter-icon-btn--gold" id="search-keyword-submit" aria-label="検索">
                <i class="fas fa-search" aria-hidden="true"></i>
            </button>
            {{-- 右端：詳細フィルター（モーダルを直接開く。バッジ=指定中の条件数） --}}
            <button type="button" class="search-filter-icon-btn search-filter-icon-btn--filter" id="open-detail-search" aria-label="詳細フィルター" aria-controls="detail-search-modal" aria-expanded="false">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                <span id="detail-search-badge" class="search-filter-icon-btn__badge" style="display: none;" aria-hidden="true">0</span>
            </button>
        </div>
    </div>

    {{-- 探索拠点の設定は詳細フィルターモーダル側で完結。 --}}
</div>
@include('casts.parts.detail-search-modal')
