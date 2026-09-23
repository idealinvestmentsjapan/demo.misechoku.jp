@extends('layouts.app-v2')

@section('title', $article->title)

@php
    $indexRoute = $isGuest
        ? 'pages.support.column'
        : ($isCast ? 'cast.column.index' : 'shop.column.index');
@endphp

@section('content')
<div class="support-column-page support-column-detail">
    <nav class="support-column-breadcrumb">
        <a href="{{ route($indexRoute) }}">お役立ちコラム一覧</a>
        <span aria-hidden="true">／</span>
        <span>{{ $article->title }}</span>
    </nav>

    <article class="support-column-article">
        <header class="support-column-article-header">
            <h1 class="support-column-title">{{ $article->title }}</h1>
            <p class="support-column-item-meta">
                @if($article->columnCategory)
                    カテゴリ：{{ $article->columnCategory->name }} ／
                @endif
                公開：{{ $article->published_at?->format('Y-m-d H:i') ?? '-' }}
            </p>
        </header>
        <div class="support-column-body">
            @php $paragraphs = preg_split('/\n{2,}/', trim($article->body ?? '')); @endphp
            @foreach($paragraphs as $para)
                @if(trim($para) !== '')
                    <p>{!! nl2br(e(trim($para))) !!}</p>
                @endif
            @endforeach
        </div>
    </article>
</div>
@endsection

@push('styles')
<style>
.support-column-page.support-column-detail {
    padding: 24px 16px 56px;
    color: #f0eef4;
}

.support-column-breadcrumb {
    font-size: 0.78rem;
    margin-bottom: 28px;
    color: #9d9aa4;
    line-height: 1.5;
}

.support-column-breadcrumb a {
    color: var(--color-gold, #a78bfa);
    text-decoration: none;
}

.support-column-breadcrumb a:hover {
    text-decoration: underline;
}

.support-column-article {
    max-width: 42rem;
    margin: 0 auto;
}

.support-column-article-header {
    margin-bottom: 1.75rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.10);
}

.support-column-article .support-column-title {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.5;
    margin: 0 0 0.5rem;
    letter-spacing: 0.02em;
}

.support-column-item-meta {
    font-size: 0.8rem;
    color: #9d9aa4;
    margin: 0;
    line-height: 1.4;
}

.support-column-body {
    font-size: 0.9375rem;
    line-height: 1.9;
    color: #d4d4d4;
    word-break: break-word;
    overflow-wrap: break-word;
    letter-spacing: 0.02em;
}

.support-column-body p {
    margin: 0 0 1.2em;
}

.support-column-body p:last-child {
    margin-bottom: 0;
}
</style>
@endpush
