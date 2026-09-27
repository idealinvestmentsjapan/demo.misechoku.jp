@extends('layouts.admin')

@section('title', '店舗詳細 ' . ($shopId ?? ''))

@section('content')
@php
    $registeredAt = $shop->created_at ?? null;
    $accountStatus = (int) ($shop->status ?? 0);
    $isSuspended = $accountStatus === 2;
    $accountStatusLabel = match ($accountStatus) {
        1 => '本登録済み',
        2 => '停止中',
        0 => '仮登録／無効',
        default => 'ステータス: ' . $accountStatus,
    };
    $accountStatusBadge = match ($accountStatus) {
        1 => 'is-active',
        2 => 'is-danger',
        default => 'is-inactive',
    };
    $licenseStatus = (int) ($shop->license_status ?? 0);
    $licenseLabel = match ($licenseStatus) {
        3 => '確認済み',
        2 => '審査中',
        4 => '差戻し',
        default => '未提出',
    };
    $latestManagerLogin = $managers->max('last_login_at');
@endphp

<div class="admin-page">
    <div class="u-flex-between u-flex-wrap u-gap-12">
        @include('admin.parts.page-title', ['eyebrow' => 'SHOP DETAIL', 'title' => '店舗 ' . $shopId])
        <div class="u-flex u-gap-8 u-flex-wrap">
            @if($isSuspended)
                <form method="POST" action="{{ route('admin.shops.unsuspend', $shopId) }}" onsubmit="return confirm('この店舗アカウントの停止を解除しますか？');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="show">
                    <button type="submit" class="btn-action btn-action-secondary">
                        <i class="fas fa-rotate-left"></i> 停止解除
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.shops.suspend', $shopId) }}" onsubmit="return confirm('この店舗アカウントを停止します。停止中はログイン後に「停止中」表示と問合せ送信のみ可能になります。よろしいですか？');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="show">
                    <button type="submit" class="btn-action btn-action-danger">
                        <i class="fas fa-ban"></i> アカウント停止
                    </button>
                </form>
            @endif
            @include('admin.parts.back-link', ['url' => route('admin.shops.index')])
        </div>
    </div>

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    @if($isSuspended)
        <div class="admin-alert admin-alert-warning">
            <i class="fas fa-ban"></i>
            この店舗アカウントは <strong>停止中</strong> です。配下の管理者ユーザもログイン後に「停止中」表示と問合せフォームのみアクセス可能となります。
        </div>
    @endif

    {{-- スティッキーアンカーナビ --}}
    <nav class="admin-anchor-nav" aria-label="セクション">
        <a href="#sec-overview" class="admin-anchor-nav__link"><i class="fas fa-circle-info"></i> 概要</a>
        <a href="#sec-public" class="admin-anchor-nav__link"><i class="fas fa-eye"></i> 公開情報</a>
        <a href="#sec-operation" class="admin-anchor-nav__link"><i class="fas fa-chart-line"></i> 運用実績</a>
        <a href="#sec-history" class="admin-anchor-nav__link"><i class="fas fa-file-invoice-dollar"></i> 入金履歴
            @if(!empty($applicationDeposits) && $applicationDeposits->count() > 0)
                <span class="admin-anchor-nav__count">{{ $applicationDeposits->count() }}</span>
            @endif
        </a>
        @if($isUnlocked)
            <a href="#sec-private" class="admin-anchor-nav__link admin-anchor-nav__link--private"><i class="fas fa-lock-open"></i> 非公開情報</a>
        @endif
    </nav>

    {{-- 概要（ヒーロー） --}}
    <section class="admin-section" id="sec-overview">
        <div class="admin-section__head">
            <div class="admin-detail-hero-title-row">
                <h2 class="admin-detail-hero-name">{{ $displayName }}</h2>
                <span class="admin-detail-hero-id">{{ $shopId }}</span>
                <span class="admin-status-badge {{ $accountStatusBadge }}">
                    @if($isSuspended)<i class="fas fa-ban"></i> @endif{{ $accountStatusLabel }}
                </span>
                <span class="admin-status-badge {{ $licenseStatus === 3 ? 'is-success' : ($licenseStatus === 4 ? 'is-danger' : 'is-warning') }}">
                    書類確認: {{ $licenseLabel }}
                </span>
            </div>
        </div>
        <div class="admin-detail-meta-row">
            <div>
                <span class="admin-detail-meta-row__label">登録日</span>
                <span class="admin-detail-meta-row__value">
                    {{ $registeredAt ? \Illuminate\Support\Carbon::parse($registeredAt)->format('Y-m-d H:i') : '—' }}
                </span>
            </div>
            <div>
                <span class="admin-detail-meta-row__label">最終ログイン（管理者）</span>
                <span class="admin-detail-meta-row__value">
                    {{ $latestManagerLogin ? \Illuminate\Support\Carbon::parse($latestManagerLogin)->format('Y-m-d H:i') : '—' }}
                </span>
            </div>
            <div>
                <span class="admin-detail-meta-row__label">累計請求額</span>
                <span class="admin-detail-meta-row__value">
                    {{ number_format($totalBilled) }} <small>円</small>
                </span>
            </div>
        </div>
    </section>

    {{-- 公開プロフィール／求人情報 --}}
    <section class="admin-section" id="sec-public">
        <div class="admin-section__head">
            <h2 class="admin-section__title">公開プロフィール／求人情報</h2>
            @php $shopNumericId = (int) ltrim((string) $shopId, 'sS0'); @endphp
            <div class="admin-section__actions">
                <a href="{{ route('share.recruit.show', $shopNumericId) }}" target="_blank" rel="noopener" class="btn-action btn-action-secondary">
                    <i class="fas fa-arrow-up-right-from-square"></i> 求人画面を開く
                </a>
            </div>
        </div>
        <p class="admin-section__note">
            店舗名・エリア・キャッチ・求人条件などの<strong>公開情報</strong>は、求職者と同じ求人画面で確認できます。
        </p>
    </section>

    {{-- 運用実績 --}}
    <section class="admin-section" id="sec-operation">
        <div class="admin-section__head">
            <h2 class="admin-section__title">運用実績（請求／入金フロー）</h2>
        </div>
        @if($operationSummary)
            <div class="admin-summary-grid">
                <div><span>請求書送付</span><strong>{{ number_format($operationSummary['invoice_issued']) }}</strong></div>
                <div><span>入金確認</span><strong>{{ number_format($operationSummary['payment_confirmed']) }}</strong></div>
                <div><span>振込実行</span><strong>{{ number_format($operationSummary['cast_transferred']) }}</strong></div>
                <div><span>完了</span><strong>{{ number_format($operationSummary['completed']) }}</strong></div>
            </div>
            @if(!empty($operationSummary['latest_status_label']))
                <p class="admin-section__note">
                    最新ステータス: {{ $operationSummary['latest_status_label'] }}{{ !empty($operationSummary['latest_updated_at']) ? '（' . $operationSummary['latest_updated_at'] . '）' : '' }}
                </p>
            @endif
        @else
            <p class="admin-section__note">請求・入金フローの実績はありません。</p>
        @endif
    </section>

    {{-- 入金履歴 --}}
    <section class="admin-section" id="sec-history">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                請求・入金履歴
                <span class="admin-section__title-count">{{ $applicationDeposits->count() }} 件</span>
            </h2>
        </div>
        @if($applicationDeposits->isEmpty())
            <p class="admin-section__note">請求・入金履歴はありません。</p>
        @else
            <div class="table-wrapper">
                <table class="admin-table admin-table--stack">
                    <thead>
                        <tr>
                            <th>請求番号</th>
                            <th>キャスト</th>
                            <th>請求額</th>
                            <th>採用ボーナス</th>
                            <th>請求日</th>
                            <th>入金確認</th>
                            <th>完了日</th>
                            <th>ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applicationDeposits as $d)
                            @php
                                $hasInvoice = !empty($d->invoice_issued_at);
                                $hasShopPaid = !empty($d->shop_payment_confirmed_at);
                                $hasCompleted = !empty($d->completed_at);
                                if ($hasCompleted) {
                                    $statusLabel = '完了';
                                    $statusClass = 'is-success';
                                } elseif ($hasShopPaid) {
                                    $statusLabel = '入金確認済';
                                    $statusClass = 'is-info';
                                } elseif ($hasInvoice) {
                                    $statusLabel = '請求書発行済';
                                    $statusClass = 'is-warning';
                                } else {
                                    $statusLabel = '請求未発行';
                                    $statusClass = 'is-inactive';
                                }
                            @endphp
                            <tr>
                                <td>{{ $d->invoice_number ?: '—' }}</td>
                                <td data-label="キャスト">
                                    @if(!empty($d->cast_id))
                                        <a href="{{ route('admin.casts.show', $d->cast_id) }}">{{ $d->cast_nickname ?: $d->cast_id }}</a>
                                    @else — @endif
                                </td>
                                <td data-label="請求額" class="u-text-num">{{ $d->invoice_amount !== null ? number_format($d->invoice_amount) . ' 円' : '—' }}</td>
                                <td data-label="ボーナス" class="u-text-num">{{ $d->bonus_amount !== null ? number_format($d->bonus_amount) . ' 円' : '—' }}</td>
                                <td data-label="請求日">{{ $d->invoice_issued_at ? \Illuminate\Support\Carbon::parse($d->invoice_issued_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="入金確認">{{ $d->shop_payment_confirmed_at ? \Illuminate\Support\Carbon::parse($d->shop_payment_confirmed_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="完了日">{{ $d->completed_at ? \Illuminate\Support\Carbon::parse($d->completed_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="ステータス"><span class="admin-status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 非公開情報ゲート --}}
    @include('admin.parts.private-gate', [
        'isUnlocked' => $isUnlocked,
        'unlockTtlSeconds' => $unlockTtlSeconds,
        'unlockUrl' => route('admin.shops.unlock-private', $shopId),
        'lockUrl' => route('admin.shops.lock-private', $shopId),
    ])

    @if($isUnlocked)
        <section class="admin-section admin-private-section" id="sec-private">
            <div class="admin-section__head">
                <h2 class="admin-section__title">非公開情報（連絡先・口座・運営メモ）</h2>
                <div class="admin-section__actions">
                    <span class="admin-private-status__pill admin-private-status__pill--inline">
                        <i class="fas fa-eye"></i> 解除中
                    </span>
                </div>
            </div>
            <div class="inquiry-detail-meta">
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">代表メールアドレス</div>
                    <div class="inquiry-detail-meta-value">
                        @if(!empty($shop->email))
                            <a href="mailto:{{ $shop->email }}">{{ $shop->email }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">店舗電話番号</div>
                    <div class="inquiry-detail-meta-value">
                        @if(!empty($profile->tel))
                            <a href="tel:{{ $profile->tel }}">{{ $profile->tel }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                    <div class="inquiry-detail-meta-label">所在地</div>
                    <div class="inquiry-detail-meta-value">
                        {{ ($profile->zip ?? '') ? '〒' . $profile->zip . ' ' : '' }}{{ $profile->pref ?? '' }}{{ $profile->city ?? '' }}{{ $profile->addr2 ?? '' }}{{ $profile->addr3 ?? '' }}
                        @if(empty($profile->zip) && empty($profile->pref) && empty($profile->city)) — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                    <div class="inquiry-detail-meta-label">運営メモ</div>
                    <div class="inquiry-detail-meta-value u-text-pre">{{ $profile->memo ?? '—' }}</div>
                </div>
            </div>
        </section>

        <section class="admin-section" id="sec-managers">
            <div class="admin-section__head">
                <h2 class="admin-section__title">
                    店舗管理者アカウント
                    <span class="admin-section__title-count">{{ $managers->count() }} 名</span>
                </h2>
            </div>
            @if($managers->isEmpty())
                <p class="admin-section__note">管理者アカウントは未登録です。</p>
            @else
                <div class="table-wrapper">
                    <table class="admin-table admin-table--stack">
                        <thead>
                            <tr>
                                <th>名前</th>
                                <th>メール</th>
                                <th>権限</th>
                                <th>状態</th>
                                <th>最終ログイン</th>
                                <th>登録日</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($managers as $m)
                                @php
                                    $isOwner = (int) ($m->role ?? 0) === 1;
                                    $isMgrActive = (int) ($m->status ?? 0) === 1;
                                @endphp
                                <tr>
                                    <td>{{ $m->name ?: '—' }}</td>
                                    <td data-label="メール">
                                        @if(!empty($m->email))
                                            <a href="mailto:{{ $m->email }}">{{ $m->email }}</a>
                                        @else — @endif
                                    </td>
                                    <td data-label="権限">
                                        @if($isOwner)
                                            <span class="manager-role-badge manager-role-badge--owner"><i class="fas fa-crown"></i> オーナー</span>
                                        @else
                                            <span class="manager-role-badge manager-role-badge--staff">スタッフ</span>
                                        @endif
                                    </td>
                                    <td data-label="状態">
                                        <span class="admin-status-badge {{ $isMgrActive ? 'is-success' : 'is-inactive' }}">{{ $isMgrActive ? '有効' : '無効' }}</span>
                                    </td>
                                    <td data-label="最終ログイン">{{ $m->last_login_at ? \Illuminate\Support\Carbon::parse($m->last_login_at)->format('Y-m-d H:i') : '—' }}</td>
                                    <td data-label="登録日">{{ $m->created_at ? \Illuminate\Support\Carbon::parse($m->created_at)->format('Y-m-d') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="admin-section" id="sec-bank">
            <div class="admin-section__head">
                <h2 class="admin-section__title">振込先銀行口座</h2>
            </div>
            @if($bank)
                <div class="inquiry-detail-meta">
                    <div class="inquiry-detail-meta-item">
                        <div class="inquiry-detail-meta-label">銀行</div>
                        <div class="inquiry-detail-meta-value">{{ $bank->bank_name }}（{{ $bank->bank_code }}）</div>
                    </div>
                    <div class="inquiry-detail-meta-item">
                        <div class="inquiry-detail-meta-label">支店</div>
                        <div class="inquiry-detail-meta-value">{{ $bank->branch_name }}（{{ $bank->branch_code }}）</div>
                    </div>
                    <div class="inquiry-detail-meta-item">
                        <div class="inquiry-detail-meta-label">口座種別</div>
                        <div class="inquiry-detail-meta-value">{{ $bank->account_type === 'ordinary' ? '普通' : ($bank->account_type === 'checking' ? '当座' : $bank->account_type) }}</div>
                    </div>
                    <div class="inquiry-detail-meta-item">
                        <div class="inquiry-detail-meta-label">口座番号</div>
                        <div class="inquiry-detail-meta-value">{{ $bank->account_number }}</div>
                    </div>
                    <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                        <div class="inquiry-detail-meta-label">口座名義</div>
                        <div class="inquiry-detail-meta-value">{{ $bank->account_name }}</div>
                    </div>
                </div>
            @else
                <p class="admin-section__note">振込先口座は未登録です。</p>
            @endif
        </section>
    @endif
</div>
@endsection
