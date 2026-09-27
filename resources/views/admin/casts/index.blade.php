@extends('layouts.admin')

@section('title', 'キャスト管理')

@section('content')
    @php
        $castList = $casts ?? collect();
        $totalCount = $castList->count();
        $activeCount = 0;
        $suspendedCount = 0;
        $pendingCount = 0;
        $idUnverifiedCount = 0;
        $inactiveLoginCount = 0;
        foreach ($castList as $c) {
            $st = (int) ($c['account_status'] ?? 0);
            if ($st === 1) $activeCount++;
            elseif ($st === 2) $suspendedCount++;
            else $pendingCount++;

            if (($c['identity_status'] ?? '') !== '確認済み') $idUnverifiedCount++;

            if (!empty($c['last_login_at'])) {
                $days = (int) \Illuminate\Support\Carbon::parse($c['last_login_at'])->diffInDays(now());
                if ($days >= 30) $inactiveLoginCount++;
            } else {
                $inactiveLoginCount++;
            }
        }
    @endphp

    <div class="admin-page">
        @include('admin.parts.page-title', [
            'eyebrow' => 'CASTS',
            'title' => 'キャスト管理',
            'info' => '
                <ul>
                    <li>登録キャストアカウントの一覧を表示します</li>
                    <li><strong>行をタップ</strong>で詳細画面に移動</li>
                    <li>本人確認状況・最終ログイン・状態（有効／停止中）を確認</li>
                    <li>停止操作・運用実績・非公開情報の確認は<strong>詳細画面</strong>から</li>
                </ul>
            ',
        ])

        {{-- 個人情報取扱い注意 --}}
        <div class="admin-alert admin-alert-warning admin-alert-thin">
            <i class="fas fa-shield-halved"></i>
            <strong>個人情報の取り扱い注意</strong>：氏名・生年月日・住所・連絡先は<em>詳細画面の非公開情報</em>に格納されており、解除操作のあるユーザのみ閲覧できます。一覧画面では公開ニックネームのみ表示されます。
        </div>

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif

        {{-- KPI（クリックでフィルタ） --}}
        <section class="dashboard-kpi-grid cast-kpi-grid" data-cast-kpis>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link is-active" data-cast-filter="all" aria-pressed="true">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">登録キャスト（合計）</div>
                    <i class="fas fa-users"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($totalCount) }}</span>
                    <span class="dashboard-kpi-unit">名</span>
                </div>
                <div class="dashboard-kpi-trend">すべて表示</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-cast-filter="active" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">有効</div>
                    <i class="fas fa-circle-check"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($activeCount) }}</span>
                    <span class="dashboard-kpi-unit">名</span>
                </div>
                <div class="dashboard-kpi-trend is-up">稼働中</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link {{ $suspendedCount > 0 ? 'is-critical' : '' }}" data-cast-filter="suspended" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">停止中</div>
                    <i class="fas fa-ban"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($suspendedCount) }}</span>
                    <span class="dashboard-kpi-unit">名</span>
                </div>
                <div class="dashboard-kpi-trend is-down">対応要</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link {{ $idUnverifiedCount > 0 ? 'is-attention' : '' }}" data-cast-filter="id_pending" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">本人確認 未完了</div>
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($idUnverifiedCount) }}</span>
                    <span class="dashboard-kpi-unit">名</span>
                </div>
                <div class="dashboard-kpi-trend">入金前に必要</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link {{ $inactiveLoginCount > 0 ? 'is-attention' : '' }}" data-cast-filter="dormant" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">30日以上 未ログイン</div>
                    <i class="fas fa-moon"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($inactiveLoginCount) }}</span>
                    <span class="dashboard-kpi-unit">名</span>
                </div>
                <div class="dashboard-kpi-trend">休眠候補</div>
            </button>
        </section>

        {{-- 並び替え --}}
        <div class="admin-page-toolbar">
            <div class="admin-page-toolbar-row">
                <label class="invoice-toolbar__sort">
                    <span><i class="fas fa-arrow-down-wide-short"></i> 並び順</span>
                    <select id="cast-sort">
                        <option value="last_login_desc" selected>最終ログインが新しい順</option>
                        <option value="last_login_asc">最終ログインが古い順</option>
                        <option value="registered_desc">登録日が新しい順</option>
                        <option value="registered_asc">登録日が古い順</option>
                        <option value="name_asc">名前（あいうえお順）</option>
                    </select>
                </label>
                <div class="invoice-toolbar__hits" id="cast-hits" aria-live="polite"></div>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table admin-table-clickable admin-table--stack">
                <thead>
                    <tr>
                        <th>キャスト（ID）</th>
                        <th>登録日</th>
                        <th>最終ログイン</th>
                        <th>本人確認</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody id="cast-table-body">
                    @forelse($casts as $cast)
                        @php
                            $isSuspended = (int) ($cast['account_status'] ?? 0) === 2;
                            $isActive = (int) ($cast['account_status'] ?? 0) === 1;
                            $isIdVerified = ($cast['identity_status'] ?? '') === '確認済み';

                            $loginAt = !empty($cast['last_login_at']) ? \Illuminate\Support\Carbon::parse($cast['last_login_at']) : null;
                            $loginDays = $loginAt ? (int) $loginAt->diffInDays(now()) : null;
                            $isDormant = $loginDays === null || $loginDays >= 30;
                            $loginTone = $loginDays === null
                                ? 'never'
                                : ($loginDays >= 90 ? 'critical' : ($loginDays >= 30 ? 'warning' : 'normal'));

                            $regAt = !empty($cast['registered_at']) ? \Illuminate\Support\Carbon::parse($cast['registered_at']) : null;
                            $statusKey = $isSuspended ? 'suspended' : ($isActive ? 'active' : 'pending');

                            $detailUrl = route('admin.casts.show', $cast['id']);
                            $searchKey = mb_strtolower(($cast['name'] ?? '') . ' ' . $cast['id']);
                        @endphp
                        <tr class="admin-row-clickable cast-row {{ $isSuspended ? 'is-suspended' : '' }}"
                            data-href="{{ $detailUrl }}"
                            data-cast-row
                            data-status="{{ $statusKey }}"
                            data-id-doc="{{ $isIdVerified ? 'verified' : 'pending' }}"
                            data-dormant="{{ $isDormant ? '1' : '0' }}"
                            data-search="{{ $searchKey }}"
                            data-last-login="{{ $loginAt ? $loginAt->getTimestamp() : 0 }}"
                            data-registered="{{ $regAt ? $regAt->getTimestamp() : 0 }}"
                            data-name="{{ $cast['name'] ?? '' }}"
                            tabindex="0"
                            role="link"
                            aria-label="キャスト詳細：{{ $cast['name'] }}">
                            <td>
                                <a href="{{ $detailUrl }}" class="admin-row-clickable__link">{{ $cast['name'] }}</a>
                                <div class="admin-table-sub"><code>{{ $cast['id'] }}</code></div>
                            </td>
                            <td data-label="登録日" class="text-sm">{{ $regAt ? $regAt->format('Y-m-d') : '—' }}</td>
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
                            <td data-label="本人確認">
                                @if($isIdVerified)
                                    <span class="admin-status-badge is-success"><i class="fas fa-circle-check"></i> 確認済み</span>
                                @else
                                    <span class="admin-status-badge is-warning"><i class="fas fa-id-card"></i> 未確認</span>
                                @endif
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
                            <td colspan="5" class="text-center">キャストアカウントがありません。</td>
                        </tr>
                    @endforelse
                    <tr id="cast-empty-row" hidden>
                        <td colspan="5" class="text-center text-muted">条件に一致するキャストはいません。</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-cast-row]'));
    var tbody = document.getElementById('cast-table-body');
    var kpis = document.querySelectorAll('[data-cast-kpis] [data-cast-filter]');
    var searchInput = document.getElementById('cast-search');
    var sortSelect = document.getElementById('cast-sort');
    var hitsEl = document.getElementById('cast-hits');
    var emptyRow = document.getElementById('cast-empty-row');

    var state = { filter: 'all', search: '', sort: 'last_login_desc' };

    function matches(row) {
        if (state.filter !== 'all') {
            switch (state.filter) {
                case 'active':     if (row.dataset.status !== 'active') return false; break;
                case 'suspended':  if (row.dataset.status !== 'suspended') return false; break;
                case 'id_pending': if (row.dataset.idDoc !== 'pending') return false; break;
                case 'dormant':    if (row.dataset.dormant !== '1') return false; break;
            }
        }
        if (state.search) {
            var q = state.search.toLowerCase();
            if ((row.dataset.search || '').indexOf(q) === -1) return false;
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
        if (hitsEl) hitsEl.textContent = visible + ' 名表示中';
        if (emptyRow) emptyRow.hidden = visible !== 0 || rows.length === 0;
    }

    kpis.forEach(function (kpi) {
        kpi.addEventListener('click', function () {
            state.filter = kpi.getAttribute('data-cast-filter') || 'all';
            kpis.forEach(function (k) {
                var on = k === kpi;
                k.classList.toggle('is-active', on);
                k.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            refresh();
        });
    });
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.search = searchInput.value.trim();
            refresh();
        });
    }
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
