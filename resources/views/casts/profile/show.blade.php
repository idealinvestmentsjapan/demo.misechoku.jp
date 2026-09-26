@extends('layouts.app-v2')

@section('title', 'Cast Profile')
@section('body-class', 'page-cast-profile')
@section('meta_description', trim((string) (($cast['intro'] ?? $cast['pr'] ?? '') ?: 'ミセチョクのキャストプロフィールです。')))
@section('meta_image', $cast['img'] ?? asset('assets/images/common/no-image.png'))
@section('canonical', $shareUrl ?? url()->current())

@push('head-styles')
<link rel="stylesheet" href="{{ asset('assets/css/fav-actions.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/personality-type.css') }}?v=20260926-ptype-illust">
<style>
    /* ===== プロフィールギャラリー：Instagram 風 3 列（cast/mypage と同パターン） ===== */
    #profile-gallery-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2px;
        padding: 0;
        margin: 0;
        list-style: none;
    }
    .profile-gallery-item {
        aspect-ratio: 1 / 1;
        padding: 0;
        margin: 0;
        overflow: hidden;
        position: relative;
    }
    .profile-gallery-slot {
        position: relative;
        width: 100%;
        height: 100%;
        padding: 0;
        border: 0;
        border-radius: 0;
        overflow: hidden;
        cursor: pointer;
        box-sizing: border-box;
        background: transparent;
    }
    .profile-gallery-slot:not(.has-img) {
        border: 2px dashed rgba(255, 255, 255, 0.22);
    }
    .profile-gallery-slot > img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .profile-gallery-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        opacity: 0.45;
    }
    .profile-gallery-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        font-size: 9px;
        font-weight: 700;
        color: #111;
        background: linear-gradient(to right, #fbcfe8, #f472b6);
        padding: 2px 6px;
        border-radius: 4px;
        line-height: 1;
    }

    /* ライトボックス：共通の最低限スタイル（v2 でも動くように） */
    .lightbox-overlay {
        position: fixed;
        inset: 0;
        z-index: 2000;
        background: rgba(0, 0, 0, 0.92);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .lightbox-overlay.is-open {
        display: flex;
    }
    .lightbox-image {
        max-width: 100%;
        max-height: 90vh;
        object-fit: contain;
        border-radius: 12px;
    }
    /* Swiper container inside the lightbox overlay (multi-image gallery) */
    .lightbox-swiper { width: 100%; max-width: 900px; height: 90vh; }
    .lightbox-swiper .swiper-slide {
        display: flex; align-items: center; justify-content: center;
    }
    .lightbox-swiper .swiper-slide img {
        max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px;
    }
    .lightbox-swiper .swiper-button-prev,
    .lightbox-swiper .swiper-button-next { color: #fff; }
    .lightbox-swiper .swiper-pagination-bullet { background: rgba(255,255,255,0.55); opacity: 1; }
    .lightbox-swiper .swiper-pagination-bullet-active { background: #fff; }
    .lightbox-close {
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        border: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 18px;
    }
</style>
@endpush

@section('content')
@include('casts.profile.parts.show-content', [
    'cast' => $cast,
    'isOwn' => false,
    'showInteractionActions' => $showInteractionActions ?? true,
    'shareUrl' => $shareUrl ?? null,
    'shareTitle' => $shareTitle ?? (($cast['nickname'] ?? $cast['name']) . 'のプロフィール'),
    'shareText' => $shareText ?? ($cast['intro'] ?? $cast['pr'] ?? ''),
    'distanceLabel' => $distanceLabel ?? null,
    'distanceKm' => $distanceKm ?? null,
])
@endsection

@push('scripts')
{{-- KEEP / LIKE トグル（data-fav-toggle）+ トースト。従来の #btn-profile-keep/like は
     ハンドラ未実装の死にボタンだったため、共通機構に載せ替え --}}
<script src="{{ asset('assets/js/favorite-quick.js') }}?v=20260926-keep-rename"></script>
<script>
(function () {
    'use strict';
    // ===== ライトボックス：GALLERY スロット（.js-lightbox-target）クリックで拡大 =====
    // Swiper 化により、クリックしたサムネの index から開いて左右スワイプ／矢印で移動できる。
    var overlay = document.getElementById('lightbox-overlay');
    if (!overlay) return;
    var swiperEl = overlay.querySelector('.lightbox-swiper');
    var slideCount = swiperEl ? swiperEl.querySelectorAll('.swiper-slide').length : 0;
    var swiperInstance = null;
    function ensureSwiper() {
        if (swiperInstance || !swiperEl || typeof window.Swiper !== 'function') return;
        swiperInstance = new window.Swiper(swiperEl, {
            loop: false,
            navigation: slideCount > 1 ? {
                nextEl: swiperEl.querySelector('.swiper-button-next'),
                prevEl: swiperEl.querySelector('.swiper-button-prev')
            } : false,
            pagination: slideCount > 1 ? {
                el: swiperEl.querySelector('.swiper-pagination'),
                clickable: true
            } : false,
            keyboard: { enabled: true }
        });
    }
    function openLightbox(index) {
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        ensureSwiper();
        if (swiperInstance) {
            swiperInstance.update();
            swiperInstance.slideTo(index, 0);
        }
    }
    window.closeLightbox = function (e) {
        if (e && e.target && !e.target.classList.contains('lightbox-overlay') && !e.target.closest('.lightbox-close')) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#profile-gallery-list .js-lightbox-target').forEach(function (el, i) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                openLightbox(i);
            });
        });
    });
})();
</script>
@endpush
