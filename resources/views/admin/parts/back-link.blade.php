{{-- 運営画面の共通「戻る」ボタン
     使い方:
        @include('admin.parts.back-link', ['url' => route('admin.casts.index')])
        @include('admin.parts.back-link', ['url' => route('admin.notices.index'), 'label' => 'お知らせ一覧へ戻る'])
--}}
<a href="{{ $url }}" class="btn-action btn-action-secondary admin-back-link">
    <i class="fas fa-arrow-left"></i>
    <span>{{ $label ?? '一覧へ戻る' }}</span>
</a>
