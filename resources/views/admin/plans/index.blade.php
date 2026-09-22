@extends('layouts.admin')

@section('title', 'プラン入金管理')

@section('content')
    @php
        use App\Models\ShopPlanSubscription as SPS;
    @endphp

    <div class="admin-page">
        <div class="u-flex-between">
            @include('admin.parts.page-title', [
                'eyebrow' => 'PLAN PAYMENTS',
                'title' => 'プラン入金管理',
                'info' => '
                    <p>店舗の <strong>Premiumプラン</strong> の振込を管理します。</p>
                    <p>ネットバンキングの入出金明細を目視で照合し、確認できたら「<strong>入金確認済み</strong>」を押してください。押した時点で Premium 機能が自動で開放されます。</p>
                ',
            ])
        </div>

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
        @endif

        {{-- KPI --}}
        <section class="dashboard-kpi-grid">
            <div class="dashboard-kpi-card {{ ($summary['pending'] ?? 0) > 0 ? 'is-critical' : '' }}">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">入金確認待ち</div>
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['pending'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </div>
            <div class="dashboard-kpi-card">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">Premium有効店舗</div>
                    <i class="fas fa-crown"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['active'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </div>
            <div class="dashboard-kpi-card {{ ($summary['overdue'] ?? 0) > 0 ? 'is-critical' : '' }}">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">期限超過（未入金）</div>
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['overdue'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </div>
        </section>

        <div class="table-wrapper">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>店舗（ID / 請求書番号）</th>
                        <th>プラン</th>
                        <th>金額（税込）</th>
                        <th>振込期限</th>
                        <th>ステータス</th>
                        <th>有効期限</th>
                        <th>帳票</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $sub)
                        @php
                            $isPending = (int) $sub->status === SPS::STATUS_PENDING_PAYMENT;
                            $isActive = (int) $sub->status === SPS::STATUS_ACTIVE;
                            $isOverdue = $isPending && $sub->payment_due_date !== null && $sub->payment_due_date->lt(today());
                        @endphp
                        <tr>
                            <td>{{ $sub->shop_display_name }}<br><small style="color:#8b8b96;">{{ $sub->shop_id }} / {{ $sub->invoice_number }}</small></td>
                            <td data-label="プラン">Premium（{{ $sub->cycleLabel() }}）</td>
                            <td data-label="金額（税込）">¥{{ number_format((int) $sub->amount) }}</td>
                            <td data-label="振込期限">
                                {{ optional($sub->payment_due_date)->format('Y/m/d') }}
                                @if($isOverdue)<span class="admin-status-badge is-danger">期限超過</span>@endif
                            </td>
                            <td data-label="ステータス">
                                <span class="admin-status-badge {{ $isPending ? 'is-warning' : ($isActive ? 'is-success' : 'is-inactive') }}">
                                    {{ $sub->statusLabel() }}
                                </span>
                            </td>
                            <td data-label="有効期限">{{ optional($sub->ends_at)->format('Y/m/d') ?: '—' }}</td>
                            <td class="stack-actions" style="white-space:nowrap;">
                                <a href="{{ route('admin.plans.invoice', $sub) }}" class="btn-action btn-action-secondary" target="_blank" rel="noopener">請求書</a>
                                @if($sub->paid_confirmed_at)
                                    <a href="{{ route('admin.plans.receipt', $sub) }}" class="btn-action btn-action-secondary" target="_blank" rel="noopener">領収書</a>
                                @endif
                            </td>
                            <td class="stack-actions">
                                @if($isPending)
                                    <form method="POST" action="{{ route('admin.plans.confirm', $sub) }}"
                                          onsubmit="return confirm('ネットバンキングの明細で入金を確認しましたか？\n確認済みにすると Premium 機能が即時有効になります。');">
                                        @csrf
                                        <button type="submit" class="btn-action manage">
                                            <i class="fas fa-check"></i> 入金確認済みにする
                                        </button>
                                    </form>
                                @elseif($isActive)
                                    <small style="color:#8b8b96;">確認: {{ optional($sub->paid_confirmed_at)->format('Y/m/d H:i') }}</small>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center; color:#8b8b96; padding: 28px 0;">プラン契約はまだありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
