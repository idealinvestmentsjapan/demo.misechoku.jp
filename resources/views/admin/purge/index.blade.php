@extends('layouts.admin')

@section('title', '書類 削除候補（バッチ運用）')

@section('content')
@php
    $steps = [
        ['no' => 1, 'label' => '取得',       'sub' => 'サーバから資料を一括取得（ZIP）'],
        ['no' => 2, 'label' => 'NAS移動',    'sub' => '自社NASへ移動 → 完了を記録'],
        ['no' => 3, 'label' => 'サーバから削除', 'sub' => '元ファイル・DBレコードを完全削除'],
    ];
    $fmt = fn ($carbon) => $carbon ? $carbon->format('Y-m-d H:i') : '—';
@endphp

<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'PURGE BATCH',
        'title' => '書類 削除候補（バッチ運用）',
        'info' => '
            <p>保持期間ポリシーを過ぎた本人確認・許可証の画像を、<strong>まとめてサーバから外に出して削除する</strong>ための運用画面です。</p>
            <ul>
                <li>削除対象は <strong>「承認済み」の書類のみ、かつ承認から 30日（1ヶ月）以上経過</strong> したものだけです</li>
                <li>単発の削除ではなく、定期的にまとめて実施します</li>
                <li>ワークフロー: <strong>取得（ZIP）→ NAS移動 → サーバから削除</strong></li>
                <li>削除は復元できません。NAS への移動を確実に行ってから実施してください</li>
            </ul>
        ',
    ])

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- 概要 --}}
    <section class="admin-section">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                今回のバッチ対象
                <span class="admin-section__title-count">合計 {{ $totalCount }} 件</span>
            </h2>
        </div>
        <div class="admin-detail-meta-row">
            <div>
                <span class="admin-detail-meta-row__label">キャスト本人確認</span>
                <span class="admin-detail-meta-row__value">{{ number_format($castCount) }} <small>件</small></span>
            </div>
            <div>
                <span class="admin-detail-meta-row__label">店舗許可証</span>
                <span class="admin-detail-meta-row__value">{{ number_format($shopCount) }} <small>件</small></span>
            </div>
            <div>
                <span class="admin-detail-meta-row__label">保持期間ポリシー</span>
                <span class="admin-detail-meta-row__value" style="font-size: 0.78rem; font-weight: 500; line-height: 1.4;">
                    承認済みかつ<br>approved_at から <strong>{{ $retentionPolicy['approved_days'] }}日</strong> 経過
                </span>
            </div>
        </div>
    </section>

    {{-- ワークフロー・ステッパー --}}
    <section class="admin-section">
        <div class="admin-section__head">
            <h2 class="admin-section__title">バッチ進行状況</h2>
        </div>
        <div class="purge-stepper">
            @foreach ($steps as $s)
                @php
                    $state = $currentStep > $s['no'] ? 'done' : ($currentStep === $s['no'] ? 'current' : 'upcoming');
                @endphp
                <div class="purge-stepper__item is-{{ $state }}">
                    <div class="purge-stepper__num">
                        @if($state === 'done')
                            <i class="fas fa-check"></i>
                        @else
                            {{ $s['no'] }}
                        @endif
                    </div>
                    <div class="purge-stepper__label">
                        <div class="purge-stepper__title">{{ $s['label'] }}</div>
                        <div class="purge-stepper__sub">{{ $s['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="purge-timestamps">
            <div><span>最終ダウンロード</span><strong>{{ $fmt($lastDownload) }}</strong></div>
            <div><span>NAS移動完了</span><strong>{{ $fmt($lastNasMoved) }}</strong></div>
            <div><span>前回の削除実行</span><strong>{{ $fmt($lastExecute) }}</strong></div>
        </div>
    </section>

    {{-- ステップ別のアクションカード --}}
    <section class="admin-section">
        <div class="admin-section__head">
            <h2 class="admin-section__title">アクション</h2>
        </div>

        <div class="purge-action-cards">
            {{-- Step 1: 取得 --}}
            <div class="purge-action-card {{ $currentStep === 1 ? 'is-active' : '' }} {{ $currentStep > 1 ? 'is-done' : '' }}">
                <div class="purge-action-card__head">
                    <span class="purge-action-card__no">STEP 1</span>
                    <span class="purge-action-card__name">サーバから資料を取得</span>
                </div>
                <p class="purge-action-card__desc">対象書類の実ファイルを ZIP でダウンロードします。ダウンロード後、自社 NAS へ移動してください。</p>
                @if($totalCount === 0)
                    <button type="button" class="btn-action" disabled>削除候補がありません</button>
                @else
                    <a href="{{ route('admin.purge.download') }}" class="btn-action manage">
                        <i class="fas fa-file-zipper"></i> ZIP でダウンロード（{{ $totalCount }}件）
                    </a>
                @endif
            </div>

            {{-- Step 2: NAS移動完了 --}}
            <div class="purge-action-card {{ $currentStep === 2 ? 'is-active' : '' }} {{ $currentStep > 2 ? 'is-done' : '' }}">
                <div class="purge-action-card__head">
                    <span class="purge-action-card__no">STEP 2</span>
                    <span class="purge-action-card__name">NAS移動の完了を記録</span>
                </div>
                <p class="purge-action-card__desc">ダウンロードした ZIP を自社 NAS へ格納したことを記録します。この記録があるまで削除は実行できません。</p>
                <form method="POST" action="{{ route('admin.purge.markNasMoved') }}" class="purge-action-card__form">
                    @csrf
                    <label class="purge-action-card__field">
                        <span>メモ（任意）</span>
                        <input type="text" name="note" maxlength="200" placeholder="例: NAS の /backup/purge/2026-09-27 に格納" {{ $currentStep < 2 ? 'disabled' : '' }}>
                    </label>
                    <button type="submit" class="btn-action" {{ $currentStep < 2 ? 'disabled' : '' }}>
                        <i class="fas fa-hard-drive"></i> NAS移動 完了
                    </button>
                </form>
            </div>

            {{-- Step 3: サーバから削除 --}}
            <div class="purge-action-card {{ $currentStep === 3 ? 'is-active' : '' }}">
                <div class="purge-action-card__head">
                    <span class="purge-action-card__no">STEP 3</span>
                    <span class="purge-action-card__name">サーバから削除（完了）</span>
                </div>
                <p class="purge-action-card__desc">対象書類の実ファイル（private ディスク）と DB レコードを完全削除します。この操作は<strong>復元できません</strong>。</p>
                <form method="POST" action="{{ route('admin.purge.execute') }}" class="purge-action-card__form"
                      onsubmit="return confirm('対象 {{ $totalCount }} 件をサーバから削除します。本当によろしいですか？');">
                    @csrf
                    <label class="purge-action-card__check">
                        <input type="checkbox" name="confirm_nas_moved" value="1" {{ $currentStep < 3 ? 'disabled' : '' }}>
                        <span>NAS への移動が完了していることを確認しました</span>
                    </label>
                    <label class="purge-action-card__check">
                        <input type="checkbox" name="confirm_irreversible" value="1" {{ $currentStep < 3 ? 'disabled' : '' }}>
                        <span>この削除が復元できないことを理解しました</span>
                    </label>
                    <button type="submit" class="btn-action btn-action-danger" {{ $currentStep < 3 || $totalCount === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-trash"></i> サーバから削除実行（{{ $totalCount }}件）
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- 対象書類の詳細一覧 --}}
    <section class="admin-section">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                対象書類の内訳
                <span class="admin-section__title-count">{{ $totalCount }} 件</span>
            </h2>
        </div>

        @if($totalCount === 0)
            <p class="admin-section__note">現在、保持期間を超過した書類はありません。</p>
        @else
            <div class="table-wrapper">
                <table class="admin-table admin-table--wide-nowrap">
                    <thead>
                        <tr>
                            <th>種別</th>
                            <th>対象</th>
                            <th>書類ID</th>
                            <th>ステータス</th>
                            <th>削除候補理由</th>
                            <th>最終更新</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($castDocs as $doc)
                            @php
                                $displayName = $doc->nickname ?: $doc->profile_name ?: $doc->cast_id;
                                $reason = app(\App\Services\DocumentReviewService::class)->labelForPurgeReason((int) $doc->status);
                                $statusLabel = match ((int) $doc->status) {
                                    \App\Models\CastIdentityDocument::STATUS_APPROVED => '承認済み',
                                    \App\Models\CastIdentityDocument::STATUS_REJECTED => '差戻し',
                                    \App\Models\CastIdentityDocument::STATUS_PENDING  => '未審査',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><span class="admin-status-badge is-info">キャスト本人確認</span></td>
                                <td>{{ $displayName }} <span class="admin-table-sub"><code>{{ $doc->cast_id }}</code></span></td>
                                <td>#{{ $doc->id }}</td>
                                <td>{{ $statusLabel }}</td>
                                <td>{{ $reason }}</td>
                                <td>{{ optional($doc->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        @foreach ($shopDocs as $doc)
                            @php
                                $displayName = $doc->shop_name ?: $doc->shop_id;
                                $reason = app(\App\Services\DocumentReviewService::class)->labelForPurgeReason((int) $doc->status);
                                $statusLabel = match ((int) $doc->status) {
                                    \App\Models\ShopLicenseDocument::STATUS_APPROVED => '承認済み',
                                    \App\Models\ShopLicenseDocument::STATUS_REJECTED => '差戻し',
                                    \App\Models\ShopLicenseDocument::STATUS_PENDING  => '未審査',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><span class="admin-status-badge is-warning">店舗許可証</span></td>
                                <td>{{ $displayName }} <span class="admin-table-sub"><code>{{ $doc->shop_id }}</code></span></td>
                                <td>#{{ $doc->id }}</td>
                                <td>{{ $statusLabel }}</td>
                                <td>{{ $reason }}</td>
                                <td>{{ optional($doc->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 削除履歴（監査ログ・直近50件） --}}
    <section class="admin-section">
        <div class="admin-section__head">
            <h2 class="admin-section__title">
                削除履歴
                <span class="admin-section__title-count">直近 {{ count($recentPurgeLogs) }} 件</span>
            </h2>
        </div>
        @if (empty($recentPurgeLogs))
            <p class="admin-section__note">
                まだ削除履歴はありません。
                @if (!\Illuminate\Support\Facades\Schema::hasTable('document_purge_logs'))
                    <br>
                    <i class="fas fa-triangle-exclamation" style="color: var(--status-warning-fg);"></i>
                    <strong>document_purge_logs テーブルが未作成です。</strong>下記の CREATE TABLE SQL を実行してください。
                @endif
            </p>
        @else
            <div class="table-wrapper">
                <table class="admin-table admin-table--wide-nowrap">
                    <thead>
                        <tr>
                            <th>削除日時</th>
                            <th>種別</th>
                            <th>対象</th>
                            <th>元書類ID</th>
                            <th>承認日</th>
                            <th>削除理由</th>
                            <th>実施者</th>
                            <th>バッチID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPurgeLogs as $log)
                            @php
                                $typeLabel = $log->document_type === 'cast_identity' ? 'キャスト本人確認' : '店舗許可証';
                                $typeToneClass = $log->document_type === 'cast_identity' ? 'is-info' : 'is-warning';
                            @endphp
                            <tr>
                                <td>{{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') : '—' }}</td>
                                <td><span class="admin-status-badge {{ $typeToneClass }}">{{ $typeLabel }}</span></td>
                                <td>{{ $log->subject_display_name ?: '—' }} <span class="admin-table-sub"><code>{{ $log->subject_id }}</code></span></td>
                                <td>#{{ $log->source_document_id }}</td>
                                <td>{{ $log->approved_at ? \Illuminate\Support\Carbon::parse($log->approved_at)->format('Y-m-d') : '—' }}</td>
                                <td>{{ $log->reason ?: '—' }}</td>
                                <td>{{ $log->deleted_by_email ?: ($log->deleted_by_admin_id ? '#' . $log->deleted_by_admin_id : '—') }}</td>
                                <td><code>{{ $log->batch_marker ?: '—' }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
