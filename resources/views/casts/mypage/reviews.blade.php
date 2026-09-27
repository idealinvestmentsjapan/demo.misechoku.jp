@extends('layouts.app-v2')

@section('title', 'レビュー一覧')
@section('body-class', 'page-cast-mypage')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/review.css') }}?v=20260927-flat">
    <link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
@endpush

@section('content')
<div class="content-wrapper animate-fadeIn">
    <div class="cast-mypage-sub-page">
        <section class="mypage-area">
            {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}

            @if(!empty($filterShopId ?? null))
                <div class="review-filter-chip">
                    <i class="fas fa-filter"></i>
                    <span>絞り込み：<strong>{{ $filterShopName ?: $filterShopId }}</strong> へ投稿したレビュー</span>
                    <a href="{{ route('cast.mypage.reviews') }}">
                        <i class="fas fa-times-circle"></i> 解除
                    </a>
                </div>
            @endif

            <section class="review-list-area">
        {{-- 総合評価サマリー --}}
        <div class="review-summary-container">
            <div class="summary-label">総合評価</div>
            <div class="summary-main">
                <span class="avg-score-big">{{ number_format((float)($castData['review_avg'] ?? 0), 1) }}</span>
                <div class="summary-right">
                    <div class="stars-gold-big">
                        @php
                            $bigScore = round(($castData['review_avg'] ?? 0) * 2) / 2;
                        @endphp
                        @for($i=1; $i<=5; $i++)
                            @if($i <= $bigScore)
                                <i class="fas fa-star"></i>
                            @elseif($i - 0.5 <= $bigScore)
                                <i class="fas fa-star-half-alt"></i>
                            @else
                                <i class="far fa-star"></i>
                            @endif
                        @endfor
                    </div>
                    <div class="total-count-text">全 <span>{{ $castData['review_count'] ?? 0 }}</span> 件のレビュー</div>
                </div>
            </div>
        </div>

        <div class="review-items">
            @if (empty($reviews))
                <div class="no-data-wrapper text-center py-20 opacity-50">
                    <p>まだレビューはありません。</p>
                </div>
            @else
                @foreach($reviews as $rev)
                <div class="review-card">
                    <div class="rev-bubble-header">
                        <span class="rev-score-small">
                            <i class="fas fa-star"></i> {{ number_format($rev['score'] ?? 0, 1) }}
                        </span>
                    </div>
                    <div class="rev-comment">
                        {!! nl2br(e($rev['text'] ?? '')) !!}
                    </div>
                    @if(!empty($rev['details']))
                        <div class="rev-toggle-row">
                            <span class="toggle-btn" onclick="toggleDetails(this)">
                                詳細 <i class="fas fa-caret-down"></i>
                            </span>
                        </div>
                        <div class="rev-details-list" style="display:none;">
                            @foreach($rev['details'] as $det)
                                <div class="detail-row">
                                    <span class="detail-label">{{ $det['content'] }}</span>
                                    <span class="detail-val">★ {{ number_format($det['score'], 1) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endforeach
            @endif
        </div>
            </section>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleDetails(btn) {
        const list = btn.parentElement.nextElementSibling;
        const isOpen = list && list.style.display !== 'none';
        if (!list) return;
        if (isOpen) {
            list.style.display = 'none';
            btn.innerHTML = '詳細 <i class="fas fa-caret-down"></i>';
        } else {
            list.style.display = 'block';
            btn.innerHTML = '閉じる <i class="fas fa-caret-up"></i>';
        }
    }
</script>
@endpush
