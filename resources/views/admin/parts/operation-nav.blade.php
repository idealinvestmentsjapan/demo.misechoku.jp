{{-- オペレーション系画面の横ナビ（兄弟画面切替＋件数バッジ）
     使い方: @include('admin.parts.operation-nav', ['active' => 'invoices'])
     active: invoices | deposits | verification | inquiries | tasks
--}}
@php
    $opBadges = $adminOperationBadges ?? [];
    $items = [
        ['key' => 'invoices', 'label' => '請求書発行', 'icon' => 'fa-file-invoice', 'route' => 'admin.invoices.index', 'badge_route' => 'admin.invoices.index'],
        ['key' => 'deposits', 'label' => '入金確認・振込', 'icon' => 'fa-money-bill-wave', 'route' => 'admin.deposits.index', 'badge_route' => 'admin.deposits.index'],
        ['key' => 'verification', 'label' => '書類審査', 'icon' => 'fa-id-card', 'route' => 'admin.verification.index', 'badge_route' => 'admin.verification.index'],
        ['key' => 'inquiries', 'label' => '問合せ対応', 'icon' => 'fa-envelope-open-text', 'route' => 'admin.support-inquiries.index', 'badge_route' => 'admin.support-inquiries.index'],
        ['key' => 'tasks', 'label' => 'タスク', 'icon' => 'fa-list-check', 'route' => 'admin.tasks.index', 'badge_route' => 'admin.tasks.index'],
    ];
    $current = $active ?? '';
@endphp
<nav class="admin-op-nav" aria-label="オペレーション">
    @foreach ($items as $item)
        @php
            $count = (int) ($opBadges[$item['badge_route']] ?? 0);
            $isActive = $current === $item['key'];
        @endphp
        <a href="{{ route($item['route']) }}" class="admin-op-nav-item {{ $isActive ? 'is-active' : '' }}">
            <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
            @if ($count > 0)
                <span class="admin-op-nav-badge {{ $count > 0 && $item['key'] !== 'tasks' ? 'is-alert' : '' }}">{{ $count }}</span>
            @endif
        </a>
    @endforeach
</nav>
