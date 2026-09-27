@extends('layouts.admin')

@section('title', 'キャスト管理')

@section('content')
    @php
        // 集計（フィルタチップ用）
        $castList = $casts ?? collect();
        $totalCount = $castList->count();
        $activeCount = 0;
        $suspendedCount = 0;
        $idUnverifiedCount = 0;
        $inactiveLoginCount = 0;
        foreach ($castList as $c) {
            $st = (int) ($c['account_status'] ?? 0);
            if ($st === 1) $activeCount++;
            elseif ($st === 2) $suspendedCount++;

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
                    <li>本人確認・最終ログイン・状態を確認</li>
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

        {{-- 絞り込み（軽量チップ）＋ 並び替え --}}
        <div class="admin-page-toolbar">
            <div class="admin-page-toolbar-filters" data-cast-filters>
                <button type="button" class="admin-filter-chip is-active" data-cast-filter="all">
                    <span>すべて</span><strong>{{ $totalCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-cast-filter="active">
                    <span>有効</span><strong>{{ $activeCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip {{ $suspendedCount > 0 ? 'is-critical' : '' }}" data-cast-filter="suspended">
                    <span>停止中</span><strong>{{ $suspendedCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-cast-filter="id_pending">
                    <span>本人確認 未完了</span><strong>{{ $idUnverifiedCount }}</strong>
                </button>
                <button type="button" class="admin-filter-chip" data-cast-filter="dormant">
                    <span>30日以上未ログイン</span><strong>{{ $inactiveLoginCount }}</strong>
                </button>
            </div>
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
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table admin-table-clickable admin-table--stack">
                <thead>
                    <tr>
                        <th>キャスト（ID / 登録日）</th>
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
                                <div class="admin-table-sub">
                                    <code>{{ $cast['id'] }}</code>
                                    @if($regAt)
                                        <span class="admin-table-sub__sep">・</span>登録 {{ $regAt->format('Y-m-d') }}
                                    @endif
                                </div>
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
                            <td colspan="4" class="text-center">キャストアカウントがありません。</td>
                        </tr>
                    @endforelse
                    <tr id="cast-empty-row" hidden>
                        <td colspan="4" class="text-center text-muted">条件に一致するキャストはいません。</td>
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
    var chips = document.querySelectorAll('[data-cast-filters] [data-cast-filter]');
    var sortSelect = document.getElementById('cast-sort');
    var emptyRow = document.getElementById('cast-empty-row');

    var state = { filter: 'all', sort: 'last_login_desc' };

    function matches(row) {
        switch (state.filter) {
            case 'all': return true;
            case 'active':     return row.dataset.status === 'active';
            case 'suspended':  return row.dataset.status === 'suspended';
            case 'id_pending': return row.dataset.idDoc === 'pending';
            case 'dormant':    return row.dataset.dormant === '1';
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
            state.filter = chip.getAttribute('data-cast-filter') || 'all';
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
