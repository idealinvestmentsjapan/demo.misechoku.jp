@extends('layouts.app-v2')

@php
    $title = 'システムエラー';
    if (isset($exception) && method_exists($exception, 'getStatusCode')) {
        if ($exception->getStatusCode() === 404) $title = 'ページが見つかりません';
        elseif ($exception->getStatusCode() === 503) $title = 'サービス利用不可';
    }

    // "ホームへ戻る" の遷移先：ログイン状態 → 該当ロールの SWIPE。未ログイン → URL から推定 → LP。
    // 優先順:
    //   1. ログイン中の guard で判断（cast 会員 / 店舗）
    //   2. リクエスト URL 配下が cast/* / shop/* なら同じ階層の SWIPE へ
    //   3. それ以外は LP (/) へ
    $homeHref = url('/');
    $homeLabel = 'ホームへ戻る';
    if (Auth::guard('member')->check() && Route::has('cast.home')) {
        $homeHref = route('cast.home');
        $homeLabel = 'SWIPE に戻る';
    } elseif (Auth::guard('shop')->check() && Route::has('shop.home')) {
        $homeHref = route('shop.home');
        $homeLabel = 'SWIPE に戻る';
    } elseif (request()->is('cast/*') && Route::has('cast.home')) {
        $homeHref = route('cast.home');
        $homeLabel = 'SWIPE に戻る';
    } elseif (request()->is('shop/*') && Route::has('shop.home')) {
        $homeHref = route('shop.home');
        $homeLabel = 'SWIPE に戻る';
    }
@endphp

@section('title', $title)
@section('body-class', 'page-error')

@push('head-styles')
<style>
    .error-page-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        /* --footer-height に safe-area 込み。二重減算しない。dvh フォールバック付き。 */
        min-height: calc(100vh - var(--header-height, 60px) - var(--footer-height, 75px));
        min-height: calc(100dvh - var(--header-height, 60px) - var(--footer-height, 75px));
        padding: 20px;
        text-align: center;
    }
    .error-screen-img {
        width: 100%;
        max-width: 320px;
        height: auto;
        display: block;
        margin-bottom: 18px;
        object-fit: contain;
    }
    .error-page-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--color-accent-text, #c4b5fd);
        letter-spacing: 0.06em;
        margin-bottom: 8px;
    }
    .error-page-desc {
        font-size: 13px;
        color: var(--color-text-sub, #a0a0a0);
        line-height: 1.6;
        margin-bottom: 18px;
    }
    .error-page-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: 999px;
        background: var(--accent, #d670a2);
        color: var(--on-accent, #1a0814);
        font-weight: 700;
        text-decoration: none;
        box-shadow:
            0 6px 14px rgba(0, 0, 0, 0.45),
            inset 0 1px 0 rgba(255, 255, 255, 0.20),
            inset 0 -1px 0 rgba(0, 0, 0, 0.18);
        transition: filter 0.15s, transform 0.12s;
    }
    .error-page-back:hover { filter: brightness(1.06); }
    .error-page-back:active {
        transform: scale(0.97);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.45), inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }
</style>
@endpush

@section('content')
<div class="error-page-wrap">
    <img src="{{ asset('assets/images/guide/system-error-screen.png') }}" alt="システムエラー" class="error-screen-img">
    <p class="error-page-title">{{ $title }}</p>
    <p class="error-page-desc">
        @if(isset($exception) && method_exists($exception, 'getStatusCode') && $exception->getStatusCode() === 404)
            お探しのページは見つかりませんでした。<br>URL をご確認の上、再度お試しください。
        @elseif(isset($exception) && method_exists($exception, 'getStatusCode') && $exception->getStatusCode() === 503)
            ただいまメンテナンス中です。しばらく時間を置いてから再度お試しください。
        @else
            一時的なエラーが発生しました。<br>時間をおいて再度お試しください。
        @endif
    </p>
    <a href="{{ $homeHref }}" class="error-page-back">
        <i class="fas fa-home"></i> {{ $homeLabel }}
    </a>
</div>
@endsection
