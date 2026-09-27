@extends('layouts.admin')

@section('title', 'キャスト詳細 ' . ($castId ?? ''))

@section('content')
@php
    $registeredAt = $cast->created_at ?? null;
    $lastLoginAt = $cast->last_login_at ?? null;
    $accountStatus = (int) ($cast->status ?? 0);
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
    $genderLabel = match ((int) ($profile->gender ?? 0)) {
        1 => '女性',
        2 => '男性',
        3 => 'その他',
        default => '—',
    };
    $birthday = !empty($profile->birthday ?? null) ? \Illuminate\Support\Carbon::parse($profile->birthday) : null;
    $age = $birthday ? $birthday->age : null;
    $identityStatus = (int) ($identity->status ?? 0);
    $identityLabel = match ($identityStatus) {
        2 => '確認済み',
        1 => '審査中',
        3 => '差戻し',
        default => '未提出',
    };
@endphp

<div class="admin-page">
    <div class="u-flex-between u-flex-wrap u-gap-12">
        @include('admin.parts.page-title', ['eyebrow' => 'CAST DETAIL', 'title' => 'キャスト ' . $castId])
        <div class="u-flex u-gap-8 u-flex-wrap">
            @if($isSuspended)
                <form method="POST" action="{{ route('admin.casts.unsuspend', $castId) }}" onsubmit="return confirm('このキャストアカウントの停止を解除しますか？');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="show">
                    <button type="submit" class="btn-action btn-action-secondary">
                        <i class="fas fa-rotate-left"></i> 停止解除
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.casts.suspend', $castId) }}" onsubmit="return confirm('このキャストアカウントを停止します。停止中はログイン後に「停止中」表示と問合せ送信のみ可能になります。よろしいですか？');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="show">
                    <button type="submit" class="btn-action btn-action-danger">
                        <i class="fas fa-ban"></i> アカウント停止
                    </button>
                </form>
            @endif
            @include('admin.parts.back-link', ['url' => route('admin.casts.index')])
        </div>
    </div>

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    @if($isSuspended)
        <div class="admin-alert admin-alert-warning">
            <i class="fas fa-ban"></i>
            このアカウントは <strong>停止中</strong> です。ログインはできますが、ログイン後の通常画面は表示されず、停止中の通知と問合せフォームのみアクセス可能になります。
        </div>
    @endif

    {{-- スティッキーアンカーナビ --}}
    <nav class="admin-anchor-nav" aria-label="セクション">
        <a href="#sec-overview" class="admin-anchor-nav__link"><i class="fas fa-circle-info"></i> 概要</a>
        <a href="#sec-public" class="admin-anchor-nav__link"><i class="fas fa-eye"></i> 公開情報</a>
        <a href="#sec-operation" class="admin-anchor-nav__link"><i class="fas fa-chart-line"></i> 運用実績</a>
        <a href="#sec-apps" class="admin-anchor-nav__link"><i class="fas fa-paper-plane"></i> 応募履歴
            @if(!empty($applications) && $applications->count() > 0)
                <span class="admin-anchor-nav__count">{{ $applications->count() }}</span>
            @endif
        </a>
        <a href="#sec-history" class="admin-anchor-nav__link"><i class="fas fa-file-invoice-dollar"></i> 入金・振込履歴
            @if(!empty($depositRows) && $depositRows->count() > 0)
                <span class="admin-anchor-nav__count">{{ $depositRows->count() }}</span>
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
                <span class="admin-detail-hero-id">{{ $castId }}</span>
                <span class="admin-status-badge {{ $accountStatusBadge }}">
                    @if($isSuspended)<i class="fas fa-ban"></i> @endif{{ $accountStatusLabel }}
                </span>
                <span class="admin-status-badge {{ $identityStatus === 2 ? 'is-success' : ($identityStatus === 3 ? 'is-danger' : 'is-warning') }}">
                    本人確認: {{ $identityLabel }}
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
                <span class="admin-detail-meta-row__label">最終ログイン</span>
                <span class="admin-detail-meta-row__value">
                    {{ $lastLoginAt ? \Illuminate\Support\Carbon::parse($lastLoginAt)->format('Y-m-d H:i') : '—' }}
                </span>
            </div>
            <div>
                <span class="admin-detail-meta-row__label">累計振込額</span>
                <span class="admin-detail-meta-row__value">
                    {{ number_format($totalEarnings) }} <small>円</small>
                </span>
            </div>
        </div>
    </section>

    {{-- 公開プロフィール --}}
    <section class="admin-section" id="sec-public">
        <div class="admin-section__head">
            <h2 class="admin-section__title">公開プロフィール</h2>
            <div class="admin-section__actions">
                <a href="{{ route('share.cast.show', $castId) }}" target="_blank" rel="noopener" class="btn-action btn-action-secondary">
                    <i class="fas fa-arrow-up-right-from-square"></i> プロフィール画面を開く
                </a>
            </div>
        </div>
        <p class="admin-section__note">
            ニックネーム・年齢・身長／体重／BWH・PR・職種などの<strong>公開情報</strong>は、ユーザーと同じ画面で確認できます。
        </p>
    </section>

    {{-- 運用実績 --}}
    <section class="admin-section" id="sec-operation">
        <div class="admin-section__head">
            <h2 class="admin-section__title">運用実績（請求／振込フロー）</h2>
        </div>
        @if($operationSummary)
            <div class="admin-summary-grid">
                <div><span>請求書送付</span><strong>{{ number_format($operationSummary['invoice_issued']) }}</strong></div>
                <div><span>振込実行</span><strong>{{ number_format($operationSummary['cast_transferred']) }}</strong></div>
                <div><span>完了</span><strong>{{ number_format($operationSummary['completed']) }}</strong></div>
                <div><span>合計件数</span><strong>{{ number_format($operationSummary['total']) }}</strong></div>
            </div>
            @if(!empty($operationSummary['latest_status_label']))
                <p class="admin-section__note">
                    最新ステータス: {{ $operationSummary['latest_status_label'] }}{{ !empty($operationSummary['latest_updated_at']) ? '（' . $operationSummary['latest_updated_at'] . '）' : '' }}
                </p>
            @endif
        @else
            <p class="admin-section__note">請求・振込フローの実績はありません。</p>
        @endif
    </section>

    {{-- 応募・採用履歴 --}}
    <section class="admin-section" id="sec-apps">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                応募・採用履歴
                <span class="admin-section__title-count">{{ $applications->count() }} 件</span>
            </h2>
        </div>
        @if($applications->isEmpty())
            <p class="admin-section__note">応募履歴はありません。</p>
        @else
            <div class="table-wrapper">
                <table class="admin-table admin-table--stack">
                    <thead>
                        <tr>
                            <th>応募日</th>
                            <th>店舗</th>
                            <th>ステータス</th>
                            <th>結果日</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $app)
                            @php
                                $appStatus = (int) ($app->status ?? 0);
                                $appStatusLabel = match ($appStatus) {
                                    1 => 'チャット中',
                                    2 => '面談調整中',
                                    3 => '面談確定',
                                    4 => '採用',
                                    5 => '不採用',
                                    6 => '本採用',
                                    7 => '本入店不採用',
                                    default => '—',
                                };
                                $appStatusBadge = match ($appStatus) {
                                    1, 2, 3 => 'is-warning',
                                    4, 6    => 'is-success',
                                    5, 7    => 'is-danger',
                                    default => 'is-inactive',
                                };
                            @endphp
                            <tr>
                                <td>{{ $app->created_at ? \Illuminate\Support\Carbon::parse($app->created_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="店舗">
                                    @if(!empty($app->shop_id))
                                        <a href="{{ route('admin.shops.show', $app->shop_id) }}">{{ $app->shop_name ?: $app->shop_id }}</a>
                                    @else
                                        {{ $app->shop_name ?: '—' }}
                                    @endif
                                </td>
                                <td data-label="ステータス"><span class="admin-status-badge {{ $appStatusBadge }}">{{ $appStatusLabel }}</span></td>
                                <td data-label="結果日">{{ $app->result_date ? \Illuminate\Support\Carbon::parse($app->result_date)->format('Y-m-d') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 入金・振込履歴 --}}
    <section class="admin-section" id="sec-history">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                入金・振込履歴
                <span class="admin-section__title-count">{{ $depositRows->count() }} 件</span>
            </h2>
        </div>
        @if($depositRows->isEmpty())
            <p class="admin-section__note">入金・振込履歴はありません。</p>
        @else
            <div class="table-wrapper">
                <table class="admin-table admin-table--stack">
                    <thead>
                        <tr>
                            <th>請求番号</th>
                            <th>店舗</th>
                            <th>採用ボーナス</th>
                            <th>振込額</th>
                            <th>請求日</th>
                            <th>振込日</th>
                            <th>完了日</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($depositRows as $row)
                            <tr>
                                <td>{{ $row->invoice_number ?: '—' }}</td>
                                <td data-label="店舗">{{ $row->shop_name ?: '—' }}</td>
                                <td data-label="採用ボーナス">{{ $row->bonus_amount !== null ? number_format($row->bonus_amount) . ' 円' : '—' }}</td>
                                <td data-label="振込額">{{ $row->cast_transfer_amount !== null ? number_format($row->cast_transfer_amount) . ' 円' : '—' }}</td>
                                <td data-label="請求日">{{ $row->invoice_issued_at ? \Illuminate\Support\Carbon::parse($row->invoice_issued_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="振込日">{{ $row->cast_transferred_at ? \Illuminate\Support\Carbon::parse($row->cast_transferred_at)->format('Y-m-d') : '—' }}</td>
                                <td data-label="完了日">{{ $row->completed_at ? \Illuminate\Support\Carbon::parse($row->completed_at)->format('Y-m-d') : '—' }}</td>
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
        'unlockUrl' => route('admin.casts.unlock-private', $castId),
        'lockUrl' => route('admin.casts.lock-private', $castId),
    ])

    @if($isUnlocked)
        <section class="admin-section admin-private-section" id="sec-private">
            <div class="admin-section__head">
                <h2 class="admin-section__title">非公開情報（連絡先・本人情報）</h2>
                <div class="admin-section__actions">
                    <span class="admin-private-status__pill admin-private-status__pill--inline">
                        <i class="fas fa-eye"></i> 解除中
                    </span>
                </div>
            </div>
            <div class="inquiry-detail-meta">
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">本名</div>
                    <div class="inquiry-detail-meta-value">{{ $profile->name ?? '—' }}</div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">本名（カナ）</div>
                    <div class="inquiry-detail-meta-value">{{ $profile->name_kana ?? '—' }}</div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">生年月日</div>
                    <div class="inquiry-detail-meta-value">
                        {{ $birthday ? $birthday->format('Y-m-d') : '—' }}
                        @if($age !== null)（{{ $age }}歳）@endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">性別</div>
                    <div class="inquiry-detail-meta-value">{{ $genderLabel }}</div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">メールアドレス</div>
                    <div class="inquiry-detail-meta-value">
                        @if(!empty($cast->email))
                            <a href="mailto:{{ $cast->email }}">{{ $cast->email }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item">
                    <div class="inquiry-detail-meta-label">電話番号</div>
                    <div class="inquiry-detail-meta-value">
                        @if(!empty($profile->tel))
                            <a href="tel:{{ $profile->tel }}">{{ $profile->tel }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                    <div class="inquiry-detail-meta-label">住所</div>
                    <div class="inquiry-detail-meta-value">
                        {{ trim(($profile->zip ?? '') ? '〒' . $profile->zip . ' ' : '') }}{{ $profile->pref ?? '' }}{{ $profile->city ?? '' }}{{ $profile->addr1 ?? '' }}{{ $profile->addr2 ?? '' }}{{ $profile->addr3 ?? '' }}
                        @if(empty($profile->zip) && empty($profile->pref) && empty($profile->city)) — @endif
                    </div>
                </div>
                <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                    <div class="inquiry-detail-meta-label">運営メモ</div>
                    <div class="inquiry-detail-meta-value u-text-pre">{{ $profile->memo ?? '—' }}</div>
                </div>
                @if(!empty($profile->ng_reason))
                    <div class="inquiry-detail-meta-item inquiry-detail-meta-item--full">
                        <div class="inquiry-detail-meta-label">NG 理由</div>
                        <div class="inquiry-detail-meta-value u-text-pre">{{ $profile->ng_reason }}</div>
                    </div>
                @endif
            </div>
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

        @if($providers->isNotEmpty())
            <section class="admin-section" id="sec-providers">
                <div class="admin-section__head">
                    <h2 class="admin-section__title">外部認証連携</h2>
                </div>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr><th>プロバイダ</th><th>プロバイダID</th><th>連携日時</th></tr>
                        </thead>
                        <tbody>
                            @foreach($providers as $p)
                                <tr>
                                    <td>{{ $p->provider }}</td>
                                    <td><code>{{ $p->provider_id }}</code></td>
                                    <td>{{ $p->created_at ? \Illuminate\Support\Carbon::parse($p->created_at)->format('Y-m-d H:i') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
