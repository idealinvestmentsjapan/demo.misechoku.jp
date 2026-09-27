@extends('layouts.admin')

@section('title', '本人確認・書類審査')
{{-- verification 用の CSS は public/assets/css/admin-detail.css に集約（重複防止） --}}

@section('content')
    @php
        $castDocs = $castDocuments ?? [];
        $shopDocs = $shopDocuments ?? [];
        $countByStatus = function (array $docs, string $key): int {
            $n = 0;
            foreach ($docs as $d) {
                if (($d['status_key'] ?? '') === $key) $n++;
            }
            return $n;
        };
        $shopExpiredCount = 0;
        foreach ($shopDocs as $d) {
            if (($d['expiry_filter_key'] ?? '') === 'expired') $shopExpiredCount++;
        }
        // Landing tab: honor ?focus= when passed, otherwise show whichever side has
        // more 要対応 items so the dashboard/sidebar "要対応" click never lands on an
        // empty tab when the other side actually has pending reviews.
        $castPending = (int) ($summary['cast_pending'] ?? 0);
        $shopPending = (int) ($summary['shop_pending'] ?? 0);
        if (request('focus') === 'shop' || request('focus') === 'cast') {
            $defaultTab = request('focus');
        } else {
            $defaultTab = $shopPending > $castPending ? 'shop' : 'cast';
        }

        // アクター判定: pending=運営対応 / rejected=ユーザー再提出待ち / approved=完了
        $resolveActor = function (string $statusKey, string $userType): array {
            return match ($statusKey) {
                'pending' => ['cls' => 'is-admin', 'icon' => 'fa-bell', 'label' => '運営対応'],
                'rejected' => $userType === 'cast'
                    ? ['cls' => 'is-cast', 'icon' => 'fa-user', 'label' => 'キャスト再提出待ち']
                    : ['cls' => 'is-shop', 'icon' => 'fa-store', 'label' => '店舗再提出待ち'],
                'approved' => ['cls' => 'is-done', 'icon' => 'fa-circle-check', 'label' => '完了'],
                default => ['cls' => 'is-admin-soft', 'icon' => 'fa-circle-question', 'label' => '—'],
            };
        };
    @endphp

    <div class="admin-page">
        <div class="u-flex-between">
            @include('admin.parts.page-title', ['eyebrow' => 'KYC & DOCUMENTS', 'title' => '本人確認・書類審査'])
            @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.verification.index'])
        </div>

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- サマリー（表示のみ） --}}
        <section class="dashboard-kpi-grid verification-kpi-grid">
            <article class="dashboard-kpi-card {{ ($summary['cast_pending'] ?? 0) > 0 ? 'is-attention' : '' }}">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">本人確認 未処理</div>
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ $summary['cast_pending'] ?? 0 }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </article>
            <article class="dashboard-kpi-card {{ ($summary['shop_pending'] ?? 0) > 0 ? 'is-attention' : '' }}">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">店舗書類 未処理</div>
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ $summary['shop_pending'] ?? 0 }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </article>
            <article class="dashboard-kpi-card {{ $shopExpiredCount > 0 ? 'is-critical' : '' }}">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">店舗書類 期限切れ</div>
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ $shopExpiredCount }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </article>
        </section>

        {{-- タブ切替（キャスト / 店舗） --}}
        <div class="admin-tabs" role="tablist">
            <button type="button" class="admin-tab {{ $defaultTab === 'cast' ? 'is-active' : '' }}" data-verif-tab="cast" role="tab">
                <i class="fas fa-user"></i>
                <span>キャスト本人確認</span>
                <span class="admin-tab-badge {{ ($summary['cast_pending'] ?? 0) > 0 ? 'is-alert' : '' }}">{{ count($castDocs) }}</span>
            </button>
            <button type="button" class="admin-tab {{ $defaultTab === 'shop' ? 'is-active' : '' }}" data-verif-tab="shop" role="tab">
                <i class="fas fa-store"></i>
                <span>店舗提出書類</span>
                <span class="admin-tab-badge {{ ($summary['shop_pending'] ?? 0) > 0 ? 'is-alert' : '' }}">{{ count($shopDocs) }}</span>
            </button>
        </div>

        {{-- ===========================
             キャストタブパネル
             =========================== --}}
        <div class="admin-tab-panel {{ $defaultTab === 'cast' ? 'is-active' : '' }}" data-verif-panel="cast" id="cast-section">
            <div class="admin-page-toolbar">
                <div class="admin-page-toolbar-filters">
                    <button type="button" class="admin-filter-chip {{ request('cast_status', 'pending') === 'pending' ? 'is-active' : '' }}"
                        data-verif-quickfilter="pending" data-target-table="cast-verification-table">
                        <span>未承認</span>
                        <strong>{{ $countByStatus($castDocs, 'pending') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('cast_status') === 'rejected' ? 'is-active' : '' }}"
                        data-verif-quickfilter="rejected" data-target-table="cast-verification-table">
                        <span>不備・却下</span>
                        <strong>{{ $countByStatus($castDocs, 'rejected') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('cast_status') === 'approved' ? 'is-active' : '' }}"
                        data-verif-quickfilter="approved" data-target-table="cast-verification-table">
                        <span>承認済み</span>
                        <strong>{{ $countByStatus($castDocs, 'approved') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('cast_status') === 'all' ? 'is-active' : '' }}"
                        data-verif-quickfilter="all" data-target-table="cast-verification-table">
                        <span>すべて</span>
                        <strong>{{ count($castDocs) }}</strong>
                    </button>
                </div>
            </div>

            <div class="table-wrapper u-mt-12">
                <table class="admin-table admin-table--sticky-actions admin-table--stack">
                    <thead>
                        <tr>
                            <th>キャスト</th>
                            <th>登録情報（書類との照合用）</th>
                            <th>書類種別</th>
                            <th>ステータス</th>
                            <th>提出物</th>
                            <th>理由</th>
                            <th>更新日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="cast-verification-table">
                        @forelse($castDocs as $document)
                            @php $castActor = $resolveActor($document['status_key'], 'cast'); @endphp
                            <tr data-status="{{ $document['status_key'] }}" data-sort-rank="{{ $document['sort_rank'] }}" data-updated-at="{{ $document['updated_at_sort'] }}" data-keyword="{{ strtolower(($document['target_name'] ?? '').' '.($document['target_id'] ?? '').' '.($document['real_name'] ?? '').' '.($document['type_label'] ?? '')) }}">
                                <td>
                                    <strong>{{ $document['target_name'] ?: '—' }}</strong>
                                    <div class="text-xs text-muted">{{ $document['target_id'] }}</div>
                                    @if(!empty($document['email']))
                                        <div class="text-xs text-muted">{{ $document['email'] }}</div>
                                    @endif
                                </td>
                                <td data-label="登録情報">
                                    <div class="verification-profile">
                                        @if(!empty($document['real_name']))
                                            <div><span class="verification-profile-label">氏名</span>{{ $document['real_name'] }}</div>
                                        @endif
                                        @if(!empty($document['birthday']))
                                            <div><span class="verification-profile-label">生年月日</span>{{ $document['birthday'] }}</div>
                                        @endif
                                        @if(!empty($document['tel']))
                                            <div><span class="verification-profile-label">電話</span>{{ $document['tel'] }}</div>
                                        @endif
                                        @if(!empty($document['zip']) || !empty($document['address']))
                                            <div>
                                                <span class="verification-profile-label">住所</span>
                                                @if(!empty($document['zip']))〒{{ $document['zip'] }} @endif
                                                {{ $document['address'] }}
                                            </div>
                                        @endif
                                        @if(empty($document['real_name']) && empty($document['birthday']) && empty($document['tel']) && empty($document['address']))
                                            <span class="text-xs text-muted">プロフィール詳細未入力</span>
                                        @endif
                                    </div>
                                </td>
                                @php
                                    $isDocExpired = !empty($document['expired_at'])
                                        && \Illuminate\Support\Carbon::parse((string) $document['expired_at'])->isPast();
                                @endphp
                                <td data-label="書類種別">
                                    @if(!empty($document['category_label']) && $document['category_label'] !== '—')
                                        <div class="text-xs text-muted">{{ $document['category_label'] }}</div>
                                    @endif
                                    {{ $document['type_label'] }}
                                    @if(!empty($document['expired_at_label']))
                                        <div class="text-xs {{ $isDocExpired ? '' : 'text-muted' }}" @if($isDocExpired) style="color:#b91c1c; font-weight:700;" @endif>
                                            @if($isDocExpired)<i class="fas fa-triangle-exclamation"></i> 期限切れ: @else 有効期限: @endif{{ $document['expired_at_label'] }}
                                        </div>
                                    @endif
                                </td>
                                <td data-label="ステータス">
                                    <span class="actor-pill {{ $castActor['cls'] }}">
                                        <i class="fas {{ $castActor['icon'] }}"></i> {{ $castActor['label'] }}
                                    </span>
                                    <div class="u-mt-4">
                                        <span class="verification-status verification-status-{{ $document['status_key'] }}">{{ $document['status_label'] }}</span>
                                    </div>
                                    @if(!empty($document['approved_at_label']))
                                        <div class="text-xs text-muted u-mt-4">承認日時: {{ $document['approved_at_label'] }}</div>
                                    @endif
                                </td>
                                <td data-label="提出物">
                                    @php
                                        $profileFields = [];
                                        if (!empty($document['real_name'])) $profileFields[] = '氏名: ' . $document['real_name'];
                                        if (!empty($document['birthday'])) $profileFields[] = '生年月日: ' . $document['birthday'];
                                        if (!empty($document['tel'])) $profileFields[] = '電話: ' . $document['tel'];
                                        if (!empty($document['address'])) $profileFields[] = '住所: ' . ($document['zip'] ? '〒' . $document['zip'] . ' ' : '') . $document['address'];
                                        $profileText = implode("\n", $profileFields);
                                        $previewApprove = $document['status_code'] !== 2
                                            ? route('admin.verification.cast.approve', ['document' => $document['id']])
                                            : '';
                                    @endphp
                                    <div class="verification-thumbs">
                                        @if(!empty($document['front_url']))
                                            <button type="button" class="verification-thumb"
                                                data-verif-preview
                                                data-image="{{ $document['front_url'] }}"
                                                data-side="表面"
                                                data-target-name="{{ $document['target_name'] }}"
                                                data-type-label="{{ $document['type_label'] }}"
                                                data-profile="{{ $profileText }}"
                                                data-approve-action="{{ $previewApprove }}"
                                                data-reject-action="{{ route('admin.verification.cast.reject', ['document' => $document['id']]) }}"
                                                data-reject-title="キャスト本人確認書類を却下"
                                                data-reject-subject="{{ $document['target_name'] }} / {{ $document['type_label'] }}"
                                                data-template-group="document_reject_cast">
                                                <img src="{{ $document['front_url'] }}" alt="表面" loading="lazy">
                                                <span>表面</span>
                                            </button>
                                        @endif
                                        @if(!empty($document['back_url']))
                                            <button type="button" class="verification-thumb"
                                                data-verif-preview
                                                data-image="{{ $document['back_url'] }}"
                                                data-side="裏面"
                                                data-target-name="{{ $document['target_name'] }}"
                                                data-type-label="{{ $document['type_label'] }}"
                                                data-profile="{{ $profileText }}"
                                                data-approve-action="{{ $previewApprove }}"
                                                data-reject-action="{{ route('admin.verification.cast.reject', ['document' => $document['id']]) }}"
                                                data-reject-title="キャスト本人確認書類を却下"
                                                data-reject-subject="{{ $document['target_name'] }} / {{ $document['type_label'] }}"
                                                data-template-group="document_reject_cast">
                                                <img src="{{ $document['back_url'] }}" alt="裏面" loading="lazy">
                                                <span>裏面</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="理由">
                                    @if(!empty($document['ng_reason']))
                                        <div class="verification-comment">{{ $document['ng_reason'] }}</div>
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="更新日時" class="text-sm text-muted">{{ $document['updated_at_label'] ?: '-' }}</td>
                                <td class="stack-actions" style="min-width:200px;">
                                    @if($document['status_code'] !== 2)
                                        <form method="POST" action="{{ route('admin.verification.cast.approve', ['document' => $document['id']]) }}"
                                              style="display:inline-block; margin-bottom:6px;"
                                              onsubmit="return confirm('本人確認書類を承認します。\n対象: {{ $document['target_name'] }} / {{ $document['type_label'] }}{{ $isDocExpired ? '\n\n⚠ この書類は有効期限切れです。本当に承認しますか？' : '' }}\n\n承認するとキャスト本人に通知されます。');">
                                            @csrf
                                            <button type="submit" class="btn-action manage">承認</button>
                                        </form>
                                        <button type="button"
                                            class="btn-action btn-action-secondary verification-reject-trigger"
                                            data-reject-action="{{ route('admin.verification.cast.reject', ['document' => $document['id']]) }}"
                                            data-reject-title="キャスト本人確認書類を却下"
                                            data-reject-subject="{{ $document['target_name'] }} / {{ $document['type_label'] }}"
                                            data-template-group="document_reject_cast">
                                            却下
                                        </button>
                                    @else
                                        <span class="text-xs text-muted">承認済み</span>
                                    @endif
                                    @if(!empty($document['is_purge_candidate']))
                                        <div style="margin-top:6px;">
                                            <span class="badge" style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:4px; font-size:11px;">
                                                削除候補（{{ $document['purge_reason'] }}）
                                            </span>
                                            <form method="POST" action="{{ route('admin.verification.cast.purge', ['document' => $document['id']]) }}"
                                                  style="display:block; margin-top:4px;"
                                                  onsubmit="return confirm('本人確認書類を完全に削除します。\n対象: {{ $document['target_name'] }} / {{ $document['type_label'] }}\n\nこの操作は取り消せません。よろしいですか？');">
                                                @csrf
                                                {{-- 不可逆操作の確認チェック（サーバー側でも必須検証） --}}
                                                <label style="display:block; font-size:11px; margin:4px 0 2px; cursor:pointer;">
                                                    <input type="checkbox" name="confirm_purge_policy" value="1" required> 保持期間ポリシーの削除対象であることを確認した
                                                </label>
                                                <label style="display:block; font-size:11px; margin:0 0 6px; cursor:pointer;">
                                                    <input type="checkbox" name="confirm_purge_irreversible" value="1" required> 削除後は復元できないことを理解した
                                                </label>
                                                <button type="submit" class="btn-action" style="background:#b91c1c; color:#fff;">完全削除</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">提出された本人確認書類はありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===========================
             店舗タブパネル
             =========================== --}}
        <div class="admin-tab-panel {{ $defaultTab === 'shop' ? 'is-active' : '' }}" data-verif-panel="shop" id="shop-section">
            <div class="admin-page-toolbar">
                <div class="admin-page-toolbar-filters">
                    <button type="button" class="admin-filter-chip {{ request('shop_status', 'pending') === 'pending' ? 'is-active' : '' }}"
                        data-verif-quickfilter="pending" data-target-table="shop-verification-table">
                        <span>未承認</span>
                        <strong>{{ $countByStatus($shopDocs, 'pending') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('shop_status') === 'rejected' ? 'is-active' : '' }}"
                        data-verif-quickfilter="rejected" data-target-table="shop-verification-table">
                        <span>不備・却下</span>
                        <strong>{{ $countByStatus($shopDocs, 'rejected') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('shop_status') === 'approved' ? 'is-active' : '' }}"
                        data-verif-quickfilter="approved" data-target-table="shop-verification-table">
                        <span>承認済み</span>
                        <strong>{{ $countByStatus($shopDocs, 'approved') }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ request('shop_status') === 'all' ? 'is-active' : '' }}"
                        data-verif-quickfilter="all" data-target-table="shop-verification-table">
                        <span>すべて</span>
                        <strong>{{ count($shopDocs) }}</strong>
                    </button>
                    <button type="button" class="admin-filter-chip {{ $shopExpiredCount > 0 ? 'is-critical' : '' }}"
                        data-verif-expiry-filter="expired" data-target-table="shop-verification-table">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>期限切れのみ</span>
                        <strong>{{ $shopExpiredCount }}</strong>
                    </button>
                </div>
            </div>

            <div class="table-wrapper u-mt-12">
                <table class="admin-table admin-table--sticky-actions admin-table--stack">
                    <thead>
                        <tr>
                            <th>店舗</th>
                            <th>登録情報（書類との照合用）</th>
                            <th>書類</th>
                            <th>ステータス</th>
                            <th>提出物</th>
                            <th>理由</th>
                            <th>更新日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="shop-verification-table">
                        @forelse($shopDocs as $document)
                            @php $shopActor = $resolveActor($document['status_key'], 'shop'); @endphp
                            <tr data-status="{{ $document['status_key'] }}" data-expiry="{{ $document['expiry_filter_key'] ?? 'none' }}" data-sort-rank="{{ $document['sort_rank'] }}" data-updated-at="{{ $document['updated_at_sort'] }}" data-keyword="{{ strtolower(($document['target_name'] ?? '').' '.($document['target_id'] ?? '').' '.($document['type_label'] ?? '')) }}">
                                <td>
                                    <strong>{{ $document['target_name'] ?: '—' }}</strong>
                                    <div class="text-xs text-muted">{{ $document['target_id'] }}</div>
                                    @if(!empty($document['email']))
                                        <div class="text-xs text-muted">{{ $document['email'] }}</div>
                                    @endif
                                </td>
                                <td data-label="登録情報">
                                    <div class="verification-profile">
                                        @if(!empty($document['shop_name']))
                                            <div><span class="verification-profile-label">店舗名</span>{{ $document['shop_name'] }}</div>
                                        @endif
                                        @if(!empty($document['tel']))
                                            <div><span class="verification-profile-label">電話</span>{{ $document['tel'] }}</div>
                                        @endif
                                        @if(!empty($document['zip']) || !empty($document['address']))
                                            <div>
                                                <span class="verification-profile-label">住所</span>
                                                @if(!empty($document['zip']))〒{{ $document['zip'] }} @endif
                                                {{ $document['address'] }}
                                            </div>
                                        @endif
                                        @if(empty($document['shop_name']) && empty($document['tel']) && empty($document['address']))
                                            <span class="text-xs text-muted">プロフィール未入力</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="書類">
                                    {{ $document['type_label'] }}
                                    @if(!empty($document['expired_at_label']))
                                        <div class="text-xs {{ ($document['expiry_filter_key'] ?? '') === 'expired' ? '' : 'text-muted' }}">
                                            @if(($document['expiry_filter_key'] ?? '') === 'expired')
                                                <span class="admin-status-badge is-danger">期限切れ</span>
                                            @endif
                                            有効期限: {{ $document['expired_at_label'] }}
                                        </div>
                                    @endif
                                </td>
                                <td data-label="ステータス">
                                    <span class="actor-pill {{ $shopActor['cls'] }}">
                                        <i class="fas {{ $shopActor['icon'] }}"></i> {{ $shopActor['label'] }}
                                    </span>
                                    <div class="u-mt-4">
                                        <span class="verification-status verification-status-{{ $document['status_key'] }}">{{ $document['status_label'] }}</span>
                                    </div>
                                    @if(!empty($document['approved_at_label']))
                                        <div class="text-xs text-muted u-mt-4">承認日時: {{ $document['approved_at_label'] }}</div>
                                    @endif
                                </td>
                                <td data-label="提出物">
                                    @php
                                        $shopProfileFields = [];
                                        if (!empty($document['shop_name'])) $shopProfileFields[] = '店舗名: ' . $document['shop_name'];
                                        if (!empty($document['tel'])) $shopProfileFields[] = '電話: ' . $document['tel'];
                                        if (!empty($document['address'])) $shopProfileFields[] = '住所: ' . ($document['zip'] ? '〒' . $document['zip'] . ' ' : '') . $document['address'];
                                        $shopProfileText = implode("\n", $shopProfileFields);
                                        $previewShopApprove = $document['status_code'] !== 2
                                            ? route('admin.verification.shopdoc.approve', ['document' => $document['id']])
                                            : '';
                                    @endphp
                                    @if(!empty($document['file_url']))
                                        <button type="button" class="verification-thumb"
                                            data-verif-preview
                                            data-image="{{ $document['file_url'] }}"
                                            data-side="書類"
                                            data-target-name="{{ $document['target_name'] }}"
                                            data-type-label="{{ $document['type_label'] }}"
                                            data-profile="{{ $shopProfileText }}"
                                            data-approve-action="{{ $previewShopApprove }}"
                                            data-reject-action="{{ route('admin.verification.shopdoc.reject', ['document' => $document['id']]) }}"
                                            data-reject-title="店舗提出書類を却下"
                                            data-reject-subject="{{ $document['target_name'] }} / {{ $document['type_label'] }}"
                                            data-template-group="document_reject_shop">
                                            <img src="{{ $document['file_url'] }}" alt="書類" loading="lazy">
                                            <span>ファイル</span>
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td data-label="理由">
                                    @if(!empty($document['ng_reason']))
                                        <div class="verification-comment">{{ $document['ng_reason'] }}</div>
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="更新日時" class="text-sm text-muted">{{ $document['updated_at_label'] ?: '-' }}</td>
                                <td class="stack-actions" style="min-width:200px;">
                                    @if($document['status_code'] !== 2)
                                        @php $isShopDocExpired = ($document['expiry_filter_key'] ?? '') === 'expired'; @endphp
                                        <form method="POST" action="{{ route('admin.verification.shopdoc.approve', ['document' => $document['id']]) }}"
                                              style="display:inline-block; margin-bottom:6px;"
                                              onsubmit="return confirm('店舗提出書類を承認します。\n対象: {{ $document['target_name'] }} / {{ $document['type_label'] }}{{ $isShopDocExpired ? '\n\n⚠ この書類は有効期限切れです。本当に承認しますか？' : '' }}\n\n承認すると店舗に通知されます。');">
                                            @csrf
                                            <button type="submit" class="btn-action manage">承認</button>
                                        </form>
                                        <button type="button"
                                            class="btn-action btn-action-secondary verification-reject-trigger"
                                            data-reject-action="{{ route('admin.verification.shopdoc.reject', ['document' => $document['id']]) }}"
                                            data-reject-title="店舗提出書類を却下"
                                            data-reject-subject="{{ $document['target_name'] }} / {{ $document['type_label'] }}"
                                            data-template-group="document_reject_shop">
                                            却下
                                        </button>
                                    @else
                                        <span class="text-xs text-muted">承認済み</span>
                                    @endif
                                    @if(!empty($document['is_purge_candidate']))
                                        <div style="margin-top:6px;">
                                            <span class="badge" style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:4px; font-size:11px;">
                                                削除候補（{{ $document['purge_reason'] }}）
                                            </span>
                                            <form method="POST" action="{{ route('admin.verification.shopdoc.purge', ['document' => $document['id']]) }}"
                                                  style="display:block; margin-top:4px;"
                                                  onsubmit="return confirm('店舗提出書類を完全に削除します。\n対象: {{ $document['target_name'] }} / {{ $document['type_label'] }}\n\nこの操作は取り消せません。よろしいですか？');">
                                                @csrf
                                                {{-- 不可逆操作の確認チェック（サーバー側でも必須検証） --}}
                                                <label style="display:block; font-size:11px; margin:4px 0 2px; cursor:pointer;">
                                                    <input type="checkbox" name="confirm_purge_policy" value="1" required> 保持期間ポリシーの削除対象であることを確認した
                                                </label>
                                                <label style="display:block; font-size:11px; margin:0 0 6px; cursor:pointer;">
                                                    <input type="checkbox" name="confirm_purge_irreversible" value="1" required> 削除後は復元できないことを理解した
                                                </label>
                                                <button type="submit" class="btn-action" style="background:#b91c1c; color:#fff;">完全削除</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">提出された店舗書類はありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 書類プレビューモーダル（画像 + 登録情報照合 + 承認/却下） --}}
    <div id="verification-preview-modal" class="verification-preview-overlay" hidden>
        <div class="verification-preview" role="dialog" aria-modal="true" aria-labelledby="verification-preview-title">
            <header class="verification-preview__head">
                <div class="verification-preview__head-text">
                    <h3 id="verification-preview-title" class="verification-preview__title">—</h3>
                    <p class="verification-preview__subtitle" data-preview-subtitle>—</p>
                </div>
                <button type="button" class="verification-preview__close" data-preview-close aria-label="閉じる">
                    <i class="fas fa-xmark"></i>
                </button>
            </header>
            <div class="verification-preview__body">
                <div class="verification-preview__image-wrap">
                    <img data-preview-image alt="書類画像" />
                    <div class="verification-preview__zoom-hint"><i class="fas fa-magnifying-glass-plus"></i> クリックで拡大表示</div>
                </div>
                <aside class="verification-preview__side">
                    <div class="verification-preview__section">
                        <h4 class="verification-preview__section-title"><i class="fas fa-user-check"></i> 登録情報（書類と照合）</h4>
                        <pre class="verification-preview__profile" data-preview-profile>—</pre>
                    </div>
                    <div class="verification-preview__actions">
                        <form method="POST" data-preview-approve-form style="display:none;">
                            @csrf
                            <button type="submit" class="btn-action manage verification-preview__approve">
                                <i class="fas fa-circle-check"></i> 承認する
                            </button>
                        </form>
                        <button type="button" class="btn-action btn-action-secondary verification-preview__reject" data-preview-reject>
                            <i class="fas fa-circle-xmark"></i> 却下する
                        </button>
                    </div>
                    <p class="verification-preview__hint"><kbd>Esc</kbd> または背景クリックで閉じる</p>
                </aside>
            </div>
        </div>
    </div>

    {{-- 却下モーダル --}}
    <div id="verification-reject-modal" class="verification-modal-overlay" style="display:none;">
        <div class="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verification-reject-title">
            <div class="verification-modal-head">
                <h3 id="verification-reject-title">書類を却下</h3>
                <button type="button" class="verification-modal-close" aria-label="閉じる" onclick="closeRejectModal()">&times;</button>
            </div>
            <div class="verification-modal-body">
                <p id="verification-reject-subject" class="text-sm text-muted u-mb-12"></p>
                <div>
                    <div class="text-xs text-muted u-mb-8">テンプレート</div>
                    <div id="verification-reject-template-list" class="verification-template-list"></div>
                </div>
                <form id="verification-reject-form" method="POST">
                    @csrf
                    <input type="hidden" name="reject_action" id="verification-reject-action-input" value="{{ old('reject_action', '') }}">
                    <textarea name="ng_reason" id="verification-reject-reason" class="form-control" rows="5" placeholder="差し戻し理由を入力">{{ old('ng_reason') }}</textarea>
                    <div class="verification-modal-actions">
                        <button type="button" class="btn-action btn-action-secondary" onclick="closeRejectModal()">キャンセル</button>
                        <button type="submit" class="btn-action manage">却下する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ============== タブ切替 ==============
    var tabs = document.querySelectorAll('[data-verif-tab]');
    var panels = document.querySelectorAll('[data-verif-panel]');
    function activateTab(key) {
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-verif-tab') === key); });
        panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-verif-panel') === key); });
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-verif-tab'));
        });
    });

    // ============== KPI クリックで タブ + フィルタ 連動 ==============
    var kpis = document.querySelectorAll('[data-verif-kpis] [data-jump-tab]');
    kpis.forEach(function (kpi) {
        kpi.addEventListener('click', function () {
            var tabKey = kpi.getAttribute('data-jump-tab') || 'cast';
            var filterKey = kpi.getAttribute('data-jump-filter') || '';
            var expiryKey = kpi.getAttribute('data-jump-expiry') || '';
            activateTab(tabKey);
            var tableId = tabKey === 'shop' ? 'shop-verification-table' : 'cast-verification-table';
            if (filterKey) {
                var targetChip = document.querySelector('[data-verif-quickfilter="' + filterKey + '"][data-target-table="' + tableId + '"]');
                if (targetChip) targetChip.click();
            }
            if (expiryKey) {
                var expChip = document.querySelector('[data-verif-expiry-filter="' + expiryKey + '"][data-target-table="' + tableId + '"]');
                if (expChip) expChip.click();
            }
            var panel = document.querySelector('[data-verif-panel="' + tabKey + '"]');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ============== 書類プレビューモーダル ==============
    var preview = document.getElementById('verification-preview-modal');
    if (preview) {
        var prevTitle    = preview.querySelector('#verification-preview-title');
        var prevSubtitle = preview.querySelector('[data-preview-subtitle]');
        var prevImage    = preview.querySelector('[data-preview-image]');
        var prevProfile  = preview.querySelector('[data-preview-profile]');
        var prevApproveForm   = preview.querySelector('[data-preview-approve-form]');
        var prevRejectBtn     = preview.querySelector('[data-preview-reject]');
        var prevImgWrap  = preview.querySelector('.verification-preview__image-wrap');

        function openPreview(btn) {
            prevTitle.textContent = (btn.dataset.targetName || '—') + ' / ' + (btn.dataset.typeLabel || '—');
            prevSubtitle.textContent = '面: ' + (btn.dataset.side || '—');
            prevImage.src = btn.dataset.image || '';
            prevImage.alt = btn.dataset.side || '書類';
            prevProfile.textContent = btn.dataset.profile || '登録情報なし';

            // 承認: action が空（既に承認済み）の場合は非表示
            var approveAction = btn.dataset.approveAction || '';
            if (approveAction) {
                prevApproveForm.action = approveAction;
                prevApproveForm.style.display = '';
            } else {
                prevApproveForm.style.display = 'none';
            }

            // 却下ボタンに既存の却下フローのパラメータを渡す
            prevRejectBtn.dataset.rejectAction   = btn.dataset.rejectAction || '';
            prevRejectBtn.dataset.rejectTitle    = btn.dataset.rejectTitle || '書類を却下';
            prevRejectBtn.dataset.rejectSubject  = btn.dataset.rejectSubject || '';
            prevRejectBtn.dataset.templateGroup  = btn.dataset.templateGroup || '';

            // ズームリセット
            prevImage.classList.remove('is-zoom');
            preview.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closePreview() {
            preview.hidden = true;
            prevImage.src = '';
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-verif-preview]').forEach(function (btn) {
            btn.addEventListener('click', function () { openPreview(btn); });
        });
        preview.addEventListener('click', function (e) {
            if (e.target === preview || e.target.closest('[data-preview-close]')) closePreview();
        });
        document.addEventListener('keydown', function (e) {
            if (preview.hidden) return;
            if (e.key === 'Escape') closePreview();
        });
        // 画像クリックでズーム
        if (prevImage) {
            prevImage.addEventListener('click', function () {
                prevImage.classList.toggle('is-zoom');
            });
        }
        // 却下ボタンは既存の却下モーダルフローを起動
        prevRejectBtn.addEventListener('click', function () {
            closePreview();
            // 既存の verification-reject-trigger と同じデータ属性を持つので、合成イベントで再利用
            var ev = new Event('click', { bubbles: true });
            // 一時要素を作って既存ハンドラに流す
            var stub = document.createElement('button');
            stub.classList.add('verification-reject-trigger');
            Object.keys(prevRejectBtn.dataset).forEach(function (k) {
                stub.dataset[k] = prevRejectBtn.dataset[k];
            });
            document.body.appendChild(stub);
            stub.dispatchEvent(ev);
            document.body.removeChild(stub);
        });
    }

    // ============== 却下モーダル ==============
    var rejectTemplates = @json($rejectTemplates ?? []);
    var rejectModal = document.getElementById('verification-reject-modal');
    var rejectForm = document.getElementById('verification-reject-form');
    var rejectTitle = document.getElementById('verification-reject-title');
    var rejectSubject = document.getElementById('verification-reject-subject');
    var rejectReason = document.getElementById('verification-reject-reason');
    var rejectTemplateList = document.getElementById('verification-reject-template-list');

    function renderRejectTemplates(group) {
        if (!rejectTemplateList) return;
        rejectTemplateList.innerHTML = '';
        (rejectTemplates[group] || []).forEach(function (template) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'verification-template-button';
            button.textContent = template.title || 'テンプレート';
            button.addEventListener('click', function () {
                if (rejectReason) {
                    rejectReason.value = template.body || '';
                    rejectReason.focus();
                }
            });
            rejectTemplateList.appendChild(button);
        });
    }

    window.openRejectModal = function (action, title, subject, templateGroup) {
        if (!rejectModal || !rejectForm) return;
        rejectForm.setAttribute('action', action || '');
        document.getElementById('verification-reject-action-input').value = action || '';
        if (rejectTitle) rejectTitle.textContent = title || '書類を却下';
        if (rejectSubject) rejectSubject.textContent = subject || '';
        renderRejectTemplates(templateGroup || '');
        rejectModal.style.display = 'flex';
        if (rejectReason) setTimeout(function () { rejectReason.focus(); }, 0);
    };
    window.closeRejectModal = function () {
        if (!rejectModal) return;
        rejectModal.style.display = 'none';
    };
    document.querySelectorAll('.verification-reject-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            openRejectModal(button.dataset.rejectAction, button.dataset.rejectTitle, button.dataset.rejectSubject, button.dataset.templateGroup);
        });
    });
    if (rejectModal) {
        rejectModal.addEventListener('click', function (e) {
            if (e.target === rejectModal) closeRejectModal();
        });
    }

    // ============== フィルタリング ==============
    var filterState = {
        'cast-verification-table': { status: '{{ request('cast_status', 'pending') }}', keyword: '{{ request('cast_keyword', '') }}', expiry: 'all' },
        'shop-verification-table': { status: '{{ request('shop_status', 'pending') }}', keyword: '{{ request('shop_keyword', '') }}', expiry: 'all' },
    };

    function applyFilters(tableId) {
        var tbody = document.getElementById(tableId);
        if (!tbody) return;
        var state = filterState[tableId] || {};
        var rows = tbody.querySelectorAll('tr[data-status]');
        rows.forEach(function (row) {
            var matchStatus = !state.status || state.status === 'all' || row.dataset.status === state.status;
            var matchKw = !state.keyword || (row.dataset.keyword || '').indexOf(state.keyword) !== -1;
            var matchExpiry = !state.expiry || state.expiry === 'all' || row.dataset.expiry === state.expiry;
            row.hidden = !(matchStatus && matchKw && matchExpiry);
        });
    }

    document.querySelectorAll('[data-verif-quickfilter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var status = chip.getAttribute('data-verif-quickfilter');
            var tableId = chip.getAttribute('data-target-table');
            // 同じテーブルの同じ種類のチップから is-active を外す
            document.querySelectorAll('[data-target-table="' + tableId + '"][data-verif-quickfilter]').forEach(function (c) {
                c.classList.toggle('is-active', c === chip);
            });
            filterState[tableId].status = status;
            applyFilters(tableId);
        });
    });
    document.querySelectorAll('[data-verif-expiry-filter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var tableId = chip.getAttribute('data-target-table');
            var willEnable = !chip.classList.contains('is-active');
            chip.classList.toggle('is-active', willEnable);
            filterState[tableId].expiry = willEnable ? chip.getAttribute('data-verif-expiry-filter') : 'all';
            if (willEnable) {
                // 期限切れは承認済み書類で起きることが多いため、
                // ステータス絞り込み（既定=未承認）が併用されて 0 件に見えないよう「すべて」に切り替える
                var allChip = document.querySelector('[data-target-table="' + tableId + '"][data-verif-quickfilter="all"]');
                if (allChip) allChip.click();
            }
            applyFilters(tableId);
        });
    });
    document.querySelectorAll('.verification-filter[data-filter-key="keyword"]').forEach(function (input) {
        input.addEventListener('input', function () {
            var tableId = input.getAttribute('data-target-table');
            filterState[tableId].keyword = (input.value || '').toLowerCase().trim();
            applyFilters(tableId);
        });
    });

    // 初期適用
    applyFilters('cast-verification-table');
    applyFilters('shop-verification-table');

    // ============== 並び順（未承認→却下→承認 / 同階層は更新日時降順）==============
    function sortRows(tableId) {
        var tbody = document.getElementById(tableId);
        if (!tbody) return;
        var rows = Array.from(tbody.querySelectorAll('tr[data-status]'));
        rows.sort(function (a, b) {
            var rankA = Number(a.dataset.sortRank || 999);
            var rankB = Number(b.dataset.sortRank || 999);
            if (rankA !== rankB) return rankA - rankB;
            return Number(b.dataset.updatedAt || 0) - Number(a.dataset.updatedAt || 0);
        });
        rows.forEach(function (row) { tbody.appendChild(row); });
    }
    sortRows('cast-verification-table');
    sortRows('shop-verification-table');

    // ============== 旧 reject_action のリプレイ ==============
    var oldRejectAction = @json(old('reject_action'));
    if (oldRejectAction) {
        var matched = null;
        document.querySelectorAll('.verification-reject-trigger').forEach(function (button) {
            if (!matched && button.dataset.rejectAction === oldRejectAction) matched = button;
        });
        openRejectModal(
            oldRejectAction,
            matched ? matched.dataset.rejectTitle : '書類を却下',
            matched ? matched.dataset.rejectSubject : '',
            matched ? matched.dataset.templateGroup : ''
        );
    }
});
</script>
@endpush
