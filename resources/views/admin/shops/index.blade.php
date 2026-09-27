@extends('layouts.admin')

@section('title', '店舗管理')

@section('content')
    @php
        // 集計（フィルタチップ用）
        $shopList = $shops ?? collect();
        $totalCount = $shopList->count();
        $activeCount = 0;
        $suspendedCount = 0;
        $docUnverifiedCount = 0;
        $inactiveLoginCount = 0;
        $premiumCount = 0;
        foreach ($shopList as $sh) {
            $st = (int) ($sh['account_status'] ?? 0);
            if ($st === 1) $activeCount++;
            elseif ($st === 2) $suspendedCount++;

            if (($sh['document_status'] ?? '') !== '確認済み') $docUnverifiedCount++;

            if (!empty($sh['last_login_at'])) {
                $days = (int) \Illuminate\Support\Carbon::parse($sh['last_login_at'])->diffInDays(now());
                if ($days >= 30) $inactiveLoginCount++;
            } else {
                $inactiveLoginCount++;
            }

            $p = $sh['plan_info'] ?? null;
            if ($p && (int) ($p['status'] ?? 0) === 2) $premiumCount++;
        }
    @endphp

    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'SHOPS',
            'title' => '店舗管理',
            'info' => '
                <ul>
                    <li>登録店舗アカウントの一覧を表示します</li>
                    <li><strong>行をタップ</strong>で詳細画面に移動</li>
                    <li>プラン・書類確認・最終ログイン・状態を確認</li>
                    <li>停止操作・運用実績・非公開情報の確認は<strong>詳細画面</strong>から</li>
                </ul>
            ',
        ])

        @if (session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- 絞り込み（軽量チップ）＋ 並び替え --}}
        <div class="admin-page-toolbar">
            <div class="admin-page-toolbar-filters" data-shop-filters>
                <button type="button" class="admin-filter-chip is-active" data-shop-filter="all">
                    <span>すべて</span><strong>{{ $totalCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-shop-filter="active">
                    <span>有効</span><strong>{{ $activeCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-shop-filter="premium">
                    <span>Premium</span><strong>{{ $premiumCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip {{ $suspendedCount > 0 ? 'is-critical' : '' }}" data-shop-filter="suspended">
                    <span>停止中</span><strong>{{ $suspendedCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-shop-filter="doc_pending">
                    <span>書類未確認</span><strong>{{ $docUnverifiedCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-shop-filter="dormant">
                    <span>30日以上未ログイン</span><strong>{{ $inactiveLoginCount }}</strong>
                </button>
            </div>
            <div class="admin-page-toolbar-row">
                <label class="invoice-toolbar__sort">
                    <span><i class="fas fa-arrow-down-wide-short"></i> 並び順</span>
                    <select id="shop-sort">
                        <option value="last_login_desc" selected>最終ログインが新しい順</option>
                        <option value="last_login_asc">最終ログインが古い順</option>
                        <option value="registered_desc">登録日が新しい順</option>
                        <option value="registered_asc">登録日が古い順</option>
                        <option value="name_asc">店舗名（あいうえお順）</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table admin-table-clickable admin-table--stack">
                <thead>
                    <tr>
                        <th>店舗（ID / 登録日）</th>
                        <th>プラン / 契約期間</th>
                        <th>最終ログイン</th>
                        <th>書類</th>
                        <th>求人</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody id="shop-table-body">
                    @forelse($shops as $shop)
                        @php
                            $isSuspended = (int) ($shop['account_status'] ?? 0) === 2;
                            $isActive = (int) ($shop['account_status'] ?? 0) === 1;
                            $isDocVerified = ($shop['document_status'] ?? '') === '確認済み';

                            $loginAt = !empty($shop['last_login_at']) ? \Illuminate\Support\Carbon::parse($shop['last_login_at']) : null;
                            $loginDays = $loginAt ? (int) $loginAt->diffInDays(now()) : null;
                            $isDormant = $loginDays === null || $loginDays >= 30;
                            $loginTone = $loginDays === null
                                ? 'never'
                                : ($loginDays >= 90 ? 'critical' : ($loginDays >= 30 ? 'warning' : 'normal'));

                            $regAt = !empty($shop['registered_at']) ? \Illuminate\Support\Carbon::parse($shop['registered_at']) : null;
                            $statusKey = $isSuspended ? 'suspended' : ($isActive ? 'active' : 'pending');

                            $p = $shop['plan_info'] ?? null;
                            $isPremium = $p && (int) ($p['status'] ?? 0) === 2;
                            $planKey = $isPremium ? 'premium' : 'none';

                            $detailUrl = route('admin.shops.show', $shop['id']);
                            $searchKey = mb_strtolower($shop['name'] . ' ' . $shop['id']);
                        @endphp
                        <tr class="admin-row-clickable shop-row {{ $isSuspended ? 'is-suspended' : '' }}"
                            data-href="{{ $detailUrl }}"
                            data-shop-row
                            data-status="{{ $statusKey }}"
                            data-doc="{{ $isDocVerified ? 'verified' : 'pending' }}"
                            data-dormant="{{ $isDormant ? '1' : '0' }}"
                            data-plan="{{ $planKey }}"
                            data-search="{{ $searchKey }}"
                            data-last-login="{{ $loginAt ? $loginAt->getTimestamp() : 0 }}"
                            data-registered="{{ $regAt ? $regAt->getTimestamp() : 0 }}"
                            data-name="{{ $shop['name'] }}"
                            tabindex="0"
                            role="link"
                            aria-label="店舗詳細：{{ $shop['name'] }}">
                            <td>
                                <a href="{{ $detailUrl }}" class="admin-row-clickable__link">{{ $shop['name'] }}</a>
                                <div class="admin-table-sub">
                                    <code>{{ $shop['id'] }}</code>
                                    @if($regAt)
                                        <span class="admin-table-sub__sep">・</span>登録 {{ $regAt->format('Y-m-d') }}
                                    @endif
                                </div>
                            </td>
                            <td data-label="プラン" class="shop-plan-cell">
                                @if($isPremium)
                                    <span class="admin-status-badge is-premium"><i class="fas fa-crown"></i> {{ $p['label'] }}</span>
                                    <div class="shop-plan-period">
                                        {{ $p['starts_at'] ? \Illuminate\Support\Carbon::parse($p['starts_at'])->format('Y-m-d') : '—' }}
                                        <span class="shop-plan-period__sep">〜</span>
                                        {{ $p['ends_at'] ? \Illuminate\Support\Carbon::parse($p['ends_at'])->format('Y-m-d') : '—' }}
                                    </div>
                                    @if(!empty($p['paid_at']))
                                        <div class="shop-plan-paid">入金 {{ \Illuminate\Support\Carbon::parse($p['paid_at'])->format('Y-m-d') }}</div>
                                    @endif
                                @elseif($p && (int) ($p['status'] ?? 0) === 1)
                                    <span class="admin-status-badge is-warning"><i class="fas fa-hourglass-half"></i> 入金待ち</span>
                                @elseif($p && (int) ($p['status'] ?? 0) === 3)
                                    <span class="admin-status-badge is-inactive">期間満了</span>
                                @elseif($p && (int) ($p['status'] ?? 0) === 4)
                                    <span class="admin-status-badge is-inactive">キャンセル</span>
                                @else
                                    <span class="text-muted">未加入</span>
                                @endif
                            </td>
                            <td data-label="最終ログイン" class="shop-login shop-login--{{ $loginTone }}">
                                @if($loginAt)
                                    <span class="shop-login__date">{{ $loginAt->format('Y-m-d H:i') }}</span>
                                    <span class="shop-login__age">
                                        @if($loginTone === 'critical')<i class="fas fa-moon"></i>@elseif($loginTone === 'warning')<i class="fas fa-clock"></i>@endif
                                        {{ $loginDays }}日前
                                    </span>
                                @else
                                    <span class="shop-login__none"><i class="fas fa-circle-question"></i> ログイン履歴なし</span>
                                @endif
                            </td>
                            <td data-label="書類">
                                @if($isDocVerified)
                                    <span class="admin-status-badge is-success"><i class="fas fa-circle-check"></i> 確認済み</span>
                                @else
                                    <span class="admin-status-badge is-warning"><i class="fas fa-hourglass-half"></i> 未確認</span>
                                @endif
                            </td>
                            <td data-label="求人">
                                <span class="admin-status-badge {{ ($shop['job_status_key'] ?? 'inactive') === 'active' ? 'is-success' : 'is-inactive' }}">
                                    {{ $shop['job_status'] ?? '未設定' }}
                                </span>
                            </td>
                            <td data-label="状態">
                                @if($isSuspended)
                                    <span class="admin-status-badge is-danger"><i class="fas fa-ban"></i> 停止中</span>
                                @elseif($isActive)
                                    <span class="admin-status-badge is-success">有効</span>
                                @else
                                    <span class="admin-status-badge is-inactive">仮登録</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">店舗アカウントがありません。</td>
                        </tr>
                    @endforelse
                    <tr id="shop-empty-row" hidden>
                        <td colspan="6" class="text-center text-muted">条件に一致する店舗はありません。</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-shop-row]'));
    var tbody = document.getElementById('shop-table-body');
    var chips = document.querySelectorAll('[data-shop-filters] [data-shop-filter]');
    var sortSelect = document.getElementById('shop-sort');
    var emptyRow = document.getElementById('shop-empty-row');

    var state = { filter: 'all', sort: 'last_login_desc' };

    function matches(row) {
        switch (state.filter) {
            case 'all': return true;
            case 'active':      return row.dataset.status === 'active';
            case 'suspended':   return row.dataset.status === 'suspended';
            case 'premium':     return row.dataset.plan === 'premium';
            case 'doc_pending': return row.dataset.doc === 'pending';
            case 'dormant':     return row.dataset.dormant === '1';
        }
        return true;
    }

    function applySort() {
        var sorted = rows.slice().sort(function (a, b) {
            switch (state.sort) {
                case 'last_login_desc': return (+b.dataset.lastLogin) - (+a.dataset.lastLogin);
                case 'last_login_asc':  return (+a.dataset.lastLogin) - (+b.dataset.lastLogin);
                case 'registered_desc': return (+b.dataset.registered) - (+a.dataset.registered);
                case 'registered_asc':  return (+a.dataset.registered) - (+b.dataset.registered);
                case 'name_asc':        return (a.dataset.name || '').localeCompare(b.dataset.name || '', 'ja');
            }
            return 0;
        });
        sorted.forEach(function (r) { tbody.appendChild(r); });
        if (emptyRow) tbody.appendChild(emptyRow);
    }

    function refresh() {
        var visible = 0;
        rows.forEach(function (row) {
            var show = matches(row);
            row.hidden = !show;
            if (show) visible++;
        });
        if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            state.filter = chip.getAttribute('data-shop-filter') || 'all';
            chips.forEach(function (c) {
                c.classList.toggle('is-active', c === chip);
            });
            refresh();
        });
    });
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            state.sort = sortSelect.value;
            applySort();
        });
    }

    applySort();
    refresh();
});
</script>
@endpush
