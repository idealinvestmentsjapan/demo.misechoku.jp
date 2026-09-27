@extends('layouts.admin')

@section('title', '入金・振込管理')

@section('content')
    @php
        use App\Services\BillingManagementService as BMS;

        // 5 ステップ定義
        $stepDefs = [
            ['key' => 'cast_request', 'label' => 'キャスト依頼', 'min_status' => BMS::STATUS_CAST_REQUESTED],
            ['key' => 'shop_approve', 'label' => '店舗承認',     'min_status' => BMS::STATUS_SHOP_APPROVED],
            ['key' => 'invoice',      'label' => '請求書発行',   'min_status' => BMS::STATUS_INVOICE_ISSUED],
            ['key' => 'shop_pay',     'label' => '入金確認',     'min_status' => BMS::STATUS_SHOP_PAYMENT_CONFIRMED],
            ['key' => 'cast_pay',     'label' => '振込・完了',   'min_status' => BMS::STATUS_COMPLETED],
        ];

        // 案件のフィルタ集計
        $depositList = $deposits ?? [];
        $catCounts = [
            'all' => count($depositList),
            'pay_check' => 0,   // 店舗入金照合待ち
            'transfer'  => 0,   // キャスト振込待ち
            'completed' => 0,   // 完了
            'alert'     => 0,   // 7日以上未確認
        ];
        foreach ($depositList as $d) {
            $sc = (int) ($d['status_code'] ?? 0);
            if ($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED) $catCounts['pay_check']++;
            if ($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED) $catCounts['transfer']++;
            if ($sc >= BMS::STATUS_COMPLETED) $catCounts['completed']++;
            $isAlert = $sc === BMS::STATUS_CAST_TRANSFERRED && !empty($d['cast_transferred_at'])
                && \Carbon\Carbon::parse($d['cast_transferred_at'])->lt(now()->subDays(7));
            if ($isAlert) $catCounts['alert']++;
        }
        $filterChips = [
            ['key' => 'all',       'label' => 'すべて'],
            ['key' => 'pay_check', 'label' => '入金照合待ち'],
            ['key' => 'transfer',  'label' => 'キャスト振込待ち'],
            ['key' => 'alert',     'label' => '要確認(7日)'],
            ['key' => 'completed', 'label' => '完了'],
        ];

        // 案件のカテゴリ判定
        $resolveCat = function (int $sc, bool $isAlert): string {
            if ($isAlert) return 'alert';
            if ($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED) return 'pay_check';
            if ($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED) return 'transfer';
            if ($sc >= BMS::STATUS_COMPLETED) return 'completed';
            return 'other';
        };

        // アクター判定（誰のタスクか）
        // status:
        //   1 CAST_REQUESTED        → 店舗待ち（承認）
        //   2 SHOP_APPROVED         → 運営対応（請求書発行）
        //   3 INVOICE_ISSUED        → 店舗待ち（入金）
        //   4 SHOP_PAYMENT_REPORTED → 運営対応（入金照合）
        //   5 SHOP_PAYMENT_CONFIRMED→ 運営対応（キャスト振込）
        //   6 CAST_TRANSFERRED      → キャスト待ち（入金確認）
        //   7+ COMPLETED            → 完了
        $resolveActor = function (int $sc): array {
            return match (true) {
                $sc === BMS::STATUS_CAST_REQUESTED => ['cls' => 'is-shop', 'icon' => 'fa-store', 'label' => '店舗待ち'],
                $sc === BMS::STATUS_SHOP_APPROVED => ['cls' => 'is-admin', 'icon' => 'fa-bell', 'label' => '運営対応'],
                $sc === BMS::STATUS_INVOICE_ISSUED => ['cls' => 'is-shop', 'icon' => 'fa-store', 'label' => '店舗待ち'],
                $sc === BMS::STATUS_SHOP_PAYMENT_REPORTED => ['cls' => 'is-admin', 'icon' => 'fa-bell', 'label' => '運営対応'],
                $sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED => ['cls' => 'is-admin', 'icon' => 'fa-bell', 'label' => '運営対応'],
                $sc === BMS::STATUS_CAST_TRANSFERRED => ['cls' => 'is-cast', 'icon' => 'fa-user', 'label' => 'キャスト待ち'],
                $sc >= BMS::STATUS_COMPLETED => ['cls' => 'is-done', 'icon' => 'fa-circle-check', 'label' => '完了'],
                default => ['cls' => 'is-admin-soft', 'icon' => 'fa-circle-question', 'label' => '確認中'],
            };
        };
    @endphp

    <div class="admin-page">
        <div class="u-flex-between">
            @include('admin.parts.page-title', [
                'eyebrow' => 'PAYMENTS & TRANSFERS',
                'title' => '入金・振込管理',
                'info' => '
                    <p><strong>店舗入金の照合</strong>と<strong>キャストへの振込記録</strong>を管理します。</p>
                    <p>請求書発行は「<strong>請求書発行</strong>」画面で行ってください。</p>
                ',
            ])
            @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.deposits.index'])
        </div>

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
        @endif
        @if(!empty($summary['unconfirmed_cast_over_7days']))
            <div class="admin-alert admin-alert-error">
                <strong>要確認：</strong> 振込済みのうち、キャストの入金確認が7日以上ない案件が {{ $summary['unconfirmed_cast_over_7days'] }} 件あります。個別フォローを推奨します。
            </div>
        @endif
        @if($errors->any())
            <div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- KPI（クリックで下のフィルタと同期） --}}
        <section class="dashboard-kpi-grid deposit-kpi-grid" data-deposit-kpis>
            <button type="button"
                class="dashboard-kpi-card dashboard-kpi-card--link"
                data-kpi-filter="pay_check"
                aria-pressed="false"
                title="クリックで絞り込み">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">店舗入金照合待ち</div>
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['payment_confirmation_pending'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </button>
            <button type="button"
                class="dashboard-kpi-card dashboard-kpi-card--link"
                data-kpi-filter="transfer"
                aria-pressed="false"
                title="運営が今すぐ振込可">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">キャスト振込待ち</div>
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['cast_transfer_pending'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </button>
            <button type="button"
                class="dashboard-kpi-card dashboard-kpi-card--link {{ ($summary['unconfirmed_cast_over_7days'] ?? 0) > 0 ? 'is-critical' : '' }}"
                data-kpi-filter="alert"
                aria-pressed="false"
                title="キャスト未確認 7日超">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">要確認（7日）</div>
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($summary['unconfirmed_cast_over_7days'] ?? 0) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
            </button>
            <article class="dashboard-kpi-card">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">請求総額</div>
                    <i class="fas fa-yen-sign"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">¥{{ number_format($summary['invoice_total'] ?? 0) }}</span>
                </div>
            </article>
        </section>

        {{-- フィルタ＋検索 --}}
        <div class="admin-page-toolbar">
            <div class="admin-page-toolbar-filters" data-deposit-filters>
                @foreach ($filterChips as $chip)
                    <button type="button"
                        class="admin-filter-chip {{ $chip['key'] === 'all' ? 'is-active' : '' }} {{ $chip['key'] === 'alert' && ($catCounts['alert'] ?? 0) > 0 ? 'is-critical' : '' }}"
                        data-deposit-filter="{{ $chip['key'] }}">
                        <span>{{ $chip['label'] }}</span>
                        <strong>{{ $catCounts[$chip['key']] ?? 0 }}</strong>
                    </button>
                @endforeach
                <div class="deposit-bulk-toggle deposit-bulk-toggle--icon" role="group" aria-label="一括表示制御">
                    <button type="button" class="deposit-bulk-toggle__btn" data-deposit-bulk="collapse" title="すべて折りたたむ" aria-label="すべて折りたたむ">
                        <i class="fas fa-compress-alt" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="deposit-bulk-toggle__btn" data-deposit-bulk="expand" title="すべて展開" aria-label="すべて展開">
                        <i class="fas fa-expand-alt" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        @forelse($depositList as $deposit)
            @php
                $task = $deposit['payment_task'] ?? null;
                $taskStatus = $task ? (int) $task->status : null;
                $sc = (int) $deposit['status_code'];
                $isUnconfirmedOver7 = $sc === BMS::STATUS_CAST_TRANSFERRED && !empty($deposit['cast_transferred_at'])
                    && \Carbon\Carbon::parse($deposit['cast_transferred_at'])->lt(now()->subDays(7));
                $cat = $resolveCat($sc, $isUnconfirmedOver7);
                $isCompleted = $sc >= BMS::STATUS_COMPLETED && !$isUnconfirmedOver7;
                $keyword = strtolower($deposit['shop_name'] . ' ' . $deposit['cast_name'] . ' ' . $deposit['id']);
                // 7日以上未確認の場合はキャスト待ちでも運営フォロー必須としてマーキング
                $actor = $isUnconfirmedOver7
                    ? ['cls' => 'is-admin', 'icon' => 'fa-triangle-exclamation', 'label' => '運営フォロー（キャスト未確認）']
                    : $resolveActor($sc);
                // 進行中で 5 日以上更新がない案件は停滞チップを表示（オペレーション上の見逃し防止）
                $stallDays = null;
                if (!$isCompleted && !empty($deposit['updated_at_label'])) {
                    try {
                        $d = \Carbon\Carbon::parse($deposit['updated_at_label'])->diffInDays(now());
                        if ($d >= 5) $stallDays = $d;
                    } catch (\Throwable $e) { /* 非表示 */ }
                }
            @endphp

            <details
                class="admin-accordion {{ $isUnconfirmedOver7 ? 'billing-alert-unconfirmed' : '' }}"
                id="deposit-{{ $deposit['id'] }}"
                data-deposit-row
                data-deposit-cat="{{ $cat }}"
                data-keyword="{{ $keyword }}"
                {{ $isCompleted ? '' : 'open' }}
            >
                <summary class="admin-accordion-summary">
                    <div class="deposit-summary-content">
                        <span class="deposit-summary-id">#{{ $deposit['id'] }}</span>
                        <span class="deposit-summary-name">{{ $deposit['shop_name'] }} / {{ $deposit['cast_name'] }}</span>
                        <span class="actor-pill {{ $actor['cls'] }}">
                            <i class="fas {{ $actor['icon'] }}"></i> {{ $actor['label'] }}
                        </span>
                        @if($stallDays !== null)
                            <span class="actor-pill is-admin-soft" title="{{ $stallDays }}日間ステータス更新がありません">
                                <i class="fas fa-hourglass-half"></i> {{ $stallDays }}日停滞
                            </span>
                        @endif
                        <span class="billing-status-chip">{{ $deposit['status_label'] }}</span>
                        @php
                            $doneSteps = 0;
                            foreach ($stepDefs as $s) { if ($sc >= $s['min_status']) $doneSteps++; }
                            $totalSteps = count($stepDefs);
                        @endphp
                        <span class="deposit-summary-progress" aria-label="進捗 {{ $doneSteps }}/{{ $totalSteps }}">
                            <span class="deposit-summary-progress__bar">
                                <span style="width: {{ round($doneSteps / max($totalSteps, 1) * 100) }}%"></span>
                            </span>
                            <span class="deposit-summary-progress__text">STEP {{ $doneSteps }}/{{ $totalSteps }}</span>
                        </span>
                        <span class="deposit-summary-amount">¥{{ number_format($deposit['invoice_amount']) }}</span>
                    </div>
                </summary>
                <div class="admin-accordion-body">
                    {{-- ヘッダ：次アクション + 請求書ボタン --}}
                    <div class="deposit-card-head">
                        <div class="deposit-card-head-row">
                            <div>
                                <div class="deposit-card-head-id">#{{ $deposit['id'] }}</div>
                                <h3 class="deposit-card-head-title">{{ $deposit['shop_name'] }} / {{ $deposit['cast_name'] }}</h3>
                            </div>
                            <div class="management-actions" style="margin-top:0;">
                                @if(!empty($deposit['invoice_number']))
                                    @php $delivery = $deposit['mail_delivery_status'] ?? ['code' => 'pre_issue']; @endphp
                                    @if($delivery['code'] === 'sent')
                                        <span class="admin-status-badge is-success" title="{{ $delivery['sent_at'] }} に店舗へメール送付済み">
                                            <i class="fas fa-circle-check"></i> 送付済み {{ $delivery['sent_at'] }}
                                        </span>
                                    @elseif($delivery['code'] === 'unsent')
                                        <span class="admin-status-badge is-warning" title="メール送付が完了していません">
                                            <i class="fas fa-triangle-exclamation"></i> メール未送付
                                        </span>
                                    @elseif($delivery['code'] === 'no_email')
                                        <span class="admin-status-badge is-info" title="店舗のメールアドレスが未登録">
                                            <i class="fas fa-envelope"></i> メール未登録（マイページ通知のみ）
                                        </span>
                                    @endif
                                    <a href="{{ route('admin.deposits.invoice.show', $deposit['id']) }}" class="btn-action btn-action-secondary" target="_blank" rel="noopener">
                                        <i class="fas fa-file-invoice"></i> 請求書
                                    </a>
                                    @if(in_array($delivery['code'] ?? '', ['unsent', 'sent'], true))
                                        <form method="POST" action="{{ route('admin.deposits.invoice.resend', $deposit['id']) }}" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="return_to" value="deposits">
                                            <button type="submit" class="btn-action btn-action-secondary" title="店舗にメール再送">
                                                <i class="fas fa-paper-plane"></i> {{ $delivery['code'] === 'sent' ? 'メール再送' : 'メール送信' }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @if(!empty($deposit['next_action']))
                            <div class="deposit-card-next-action">
                                <span class="actor-pill {{ $actor['cls'] }}">
                                    <i class="fas {{ $actor['icon'] }}"></i> {{ $actor['label'] }}
                                </span>
                                <span>{{ $deposit['next_action'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- ステップインジケータ --}}
                    <div class="deposit-step-indicator" aria-label="進捗">
                        @foreach ($stepDefs as $step)
                            @php
                                $isDone = $sc >= $step['min_status'];
                                // current は「次にやるステップ」: 最も小さい未完了
                                $isCurrent = !$isDone && (
                                    !isset($currentSet) || empty($currentSet)
                                );
                                if ($isCurrent && !isset($currentSet)) {
                                    $currentSet = true;
                                }
                            @endphp
                            <div class="deposit-step {{ $isDone ? 'is-done' : ($isCurrent ? 'is-current' : '') }}">
                                <div class="deposit-step-dot">
                                    @if($isDone)
                                        <i class="fas fa-check"></i>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </div>
                                <div class="deposit-step-label">{{ $step['label'] }}</div>
                            </div>
                        @endforeach
                        @php unset($currentSet); @endphp
                    </div>

                    <div class="billing-detail-card">
                        <div>
                            {{-- メタ情報 --}}
                            <div class="billing-meta-list">
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">請求番号</div>
                                    <div class="billing-meta-value">{{ $deposit['invoice_number'] ?: '未発行' }}</div>
                                </div>
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">請求発行日 / 支払期限</div>
                                    <div class="billing-meta-value">
                                        {{ $deposit['invoice_issued_at'] ?: '未発行' }}
                                        @if($deposit['invoice_due_date'])
                                            / {{ $deposit['invoice_due_date'] }}
                                        @endif
                                    </div>
                                </div>
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">店舗入金報告</div>
                                    <div class="billing-meta-value">{{ $deposit['shop_payment_reported_at'] ?: '未報告' }}</div>
                                </div>
                            </div>

                            <div class="billing-amount-list">
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">キャスト振込額</div>
                                    <div class="billing-meta-value">¥{{ number_format($deposit['cast_transfer_amount']) }}</div>
                                </div>
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">運営手数料</div>
                                    <div class="billing-meta-value">¥{{ number_format($deposit['system_fee_amount']) }}</div>
                                </div>
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">店舗請求額</div>
                                    <div class="billing-meta-value">¥{{ number_format($deposit['invoice_amount']) }}</div>
                                </div>
                                <div class="billing-meta-item">
                                    <div class="billing-meta-label">店舗報告金額</div>
                                    <div class="billing-meta-value">
                                        {{ $deposit['shop_payment_reported_amount'] ? '¥' . number_format($deposit['shop_payment_reported_amount']) : '未報告' }}
                                    </div>
                                </div>
                            </div>

                            @if(!empty($deposit['bonus_condition']) || !empty($deposit['review_comment']) || !empty($deposit['review_details']))
                                <div class="billing-review-box">
                                    @if(!empty($deposit['bonus_condition']))
                                        <h3 class="billing-review-title">求人に登録されたボーナス達成条件</h3>
                                        <div class="billing-review-text">{{ $deposit['bonus_condition'] }}</div>
                                    @endif
                                    @if(!empty($deposit['review_posted_at']) || !empty($deposit['review_comment']) || !empty($deposit['review_details']))
                                        <h3 class="billing-review-title" style="margin-top:12px;">
                                            キャストレビュー
                                            @if(!empty($deposit['review_posted_at']))
                                                ({{ $deposit['review_posted_at'] }})
                                            @endif
                                            @if(!empty($deposit['review_average']))
                                                / 総合 {{ number_format((float) $deposit['review_average'], 1) }}
                                            @endif
                                        </h3>
                                        @if(!empty($deposit['review_details']))
                                            <div class="billing-review-grid">
                                                @foreach($deposit['review_details'] as $detail)
                                                    <div class="billing-review-item">
                                                        <span>{{ $detail['name'] }}</span>
                                                        <strong>{{ number_format((float) $detail['score'], 1) }} / 5</strong>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(!empty($deposit['review_comment']))
                                            <div class="billing-review-text" style="margin-top:10px;">{{ $deposit['review_comment'] }}</div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="billing-action-box">
                                <h3 class="billing-action-title">口座情報</h3>
                                <p class="admin-note">店舗口座: {{ $deposit['has_shop_bank'] ? '登録済み' : '未登録' }}</p>
                                <p class="admin-note">キャスト口座: {{ $deposit['has_cast_bank'] ? '登録済み' : '未登録' }}</p>
                                @if(!empty($deposit['cast_transfer_reference']))
                                    <p class="admin-note">振込管理番号: {{ $deposit['cast_transfer_reference'] }}</p>
                                @endif
                            </div>

                            @if($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED)
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">店舗入金照合</h3>
                                    <p class="admin-note">ネットバンキングの入金明細画面のスクリーンショットを証跡として添付してください（監査ログとして保管されます）。</p>
                                    <form method="POST" action="{{ route('admin.deposits.shop-payment.confirm', $deposit['id']) }}" class="billing-inline-form" enctype="multipart/form-data" data-confirm-shop-payment-form>
                                        @csrf
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">確認済み金額</label>
                                            <input type="number" name="confirmed_amount" class="admin-input" value="{{ $deposit['invoice_amount'] }}" min="1" required>
                                        </div>
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">ネットバンキングのスクリーンショット <span class="required">必須</span></label>
                                            <input type="file" name="evidence_screenshot" accept="image/*" class="admin-input" data-shop-evidence-file required>
                                            <small class="admin-note">JPEG / PNG など画像ファイル（10MB 以内）。入金金額・振込元・日時が写った画面を推奨。</small>
                                        </div>
                                        <div class="billing-check-grid" data-check-group>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_amount_checked" value="1" data-check-item> 金額を照合した</label>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_report_checked" value="1" data-check-item> 店舗の入金報告日時を確認した</label>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_bank_checked" value="1" data-check-item> 銀行口座の着金を確認した</label>
                                        </div>
                                        <div class="management-actions">
                                            <button type="submit" class="btn-action manage" data-check-submit data-shop-payment-submit disabled>
                                                <i class="fas fa-check"></i> 店舗入金を確認済みにする
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                            @if(!empty($deposit['shop_payment_evidence_path']))
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">店舗入金 証跡</h3>
                                    <p class="admin-note">
                                        {{ $deposit['shop_payment_confirmed_at'] ?: '' }} に照合済み
                                        @if(!empty($deposit['shop_payment_reference']))
                                            ／ 参照番号 {{ $deposit['shop_payment_reference'] }}
                                        @endif
                                    </p>
                                    <a href="{{ asset('storage/' . ltrim($deposit['shop_payment_evidence_path'], '/')) }}" target="_blank" rel="noopener" class="btn-action btn-action-secondary">
                                        <i class="fas fa-image"></i> 証跡画像を開く
                                    </a>
                                </div>
                            @endif

                            @if($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && $task && in_array($taskStatus, [1, 2], true))
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">キャスト振込（金額は自動計算・変更不可）</h3>
                                    <p class="admin-note">振込額・手数料はすべて読み取り専用です。コピーボタンでネットバンキングへ貼り付けてください。</p>
                                    <div class="billing-amount-list" style="margin-top:12px;">
                                        <div class="billing-meta-item">
                                            <div class="billing-meta-label">店舗入金額</div>
                                            <div class="billing-copy-wrap">
                                                <div class="billing-meta-value billing-amount-readonly">¥{{ number_format($task->shop_received_amount) }}</div>
                                                <button type="button" class="btn-copy" data-copy-target="{{ number_format($task->shop_received_amount) }}">コピー</button>
                                            </div>
                                        </div>
                                        <div class="billing-meta-item">
                                            <div class="billing-meta-label">プラットフォーム手数料</div>
                                            <div class="billing-meta-value billing-amount-readonly">¥{{ number_format($task->platform_fee_amount) }}</div>
                                        </div>
                                        <div class="billing-meta-item">
                                            <div class="billing-meta-label">銀行振込手数料</div>
                                            <div class="billing-meta-value billing-amount-readonly">¥{{ number_format($task->bank_fee_amount) }}</div>
                                        </div>
                                        <div class="billing-meta-item">
                                            <div class="billing-meta-label">キャスト振込額（コピーして振込画面へ）</div>
                                            <div class="billing-copy-wrap">
                                                <div class="billing-meta-value billing-amount-readonly">¥{{ number_format($task->payout_amount) }}</div>
                                                <button type="button" class="btn-copy" data-copy-target="{{ number_format($task->payout_amount) }}">コピー</button>
                                            </div>
                                        </div>
                                    </div>

                                    @if($taskStatus === 1)
                                        <form method="POST" action="{{ route('admin.deposits.transfer-start', $deposit['id']) }}" class="billing-inline-form" style="margin-top:14px;">
                                            @csrf
                                            <p class="admin-note">ネットバンキングで振込を実行する前に「振込チェック開始」を押すと、他の担当者が同時に作業しないようロックされます。</p>
                                            <div class="management-actions">
                                                <button type="submit" class="btn-action manage">
                                                    <i class="fas fa-lock"></i> 振込チェック開始
                                                </button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('admin.deposits.payment-task.invalidate', $deposit['id']) }}" class="billing-inline-form" style="margin-top:10px;" onsubmit="return confirm('振込タスクを無効にしますか？口座修正後は別タスクで再発行してください。');">
                                            @csrf
                                            <button type="submit" class="btn-action danger">
                                                <i class="fas fa-ban"></i> 振込タスクを無効にする（組戻し・口座誤り時）
                                            </button>
                                        </form>
                                    @endif

                                    @if($taskStatus === 2)
                                        <form method="POST" action="{{ route('admin.deposits.transfer-complete', $deposit['id']) }}" class="billing-inline-form" enctype="multipart/form-data" data-transfer-complete-form style="margin-top:14px;">
                                            @csrf
                                            <div class="admin-form-row" style="margin-bottom:0;">
                                                <label class="admin-label">振込作業完了日時 <span class="required">必須</span></label>
                                                <input type="datetime-local" name="transferred_at" class="admin-input" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                                            </div>
                                            <div class="admin-form-row" style="margin-bottom:0;">
                                                <label class="admin-label">振込管理番号</label>
                                                <input type="text" name="reference" class="admin-input" placeholder="TRF-20260313-01">
                                            </div>
                                            <div class="admin-form-row" style="margin-bottom:0;">
                                                <label class="admin-label">証跡画像 <span class="required">必須</span></label>
                                                <input type="file" name="evidence_screenshot" accept="image/*" class="admin-input" data-evidence-file required>
                                            </div>
                                            <div class="billing-check-grid" data-check-group>
                                                <label class="billing-check-item"><input type="checkbox" name="checklist_confirmed_account" value="1" data-check-item> 振込先名義・口座番号が正しいことを確認した</label>
                                                <label class="billing-check-item"><input type="checkbox" name="checklist_confirmed_amount" value="1" data-check-item> 振込金額が正しいことを確認した</label>
                                            </div>
                                            <div class="management-actions">
                                                <button type="submit" class="btn-action manage" data-check-submit disabled data-complete-submit>
                                                    <i class="fas fa-yen-sign"></i> 支払済にする
                                                </button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('admin.deposits.payment-task.invalidate', $deposit['id']) }}" class="billing-inline-form" style="margin-top:10px;" onsubmit="return confirm('振込タスクを無効にしますか？');">
                                            @csrf
                                            <button type="submit" class="btn-action danger">
                                                <i class="fas fa-ban"></i> 振込タスクを無効にする
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            @if($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && $task && in_array($taskStatus, [3, 4], true))
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">振込タスク</h3>
                                    @if($taskStatus === 3)
                                        <p class="billing-note">この案件は支払済です。編集・巻き戻しはできません。キャストの入金確認をお待ちください。</p>
                                        @if(empty($task->refund_required))
                                            <form method="POST" action="{{ route('admin.deposits.payment-task.refund-flag', $deposit['id']) }}" style="margin-top:10px;" onsubmit="return confirm('要返金フラグを立てますか？');">
                                                @csrf
                                                <button type="submit" class="btn-action warning">
                                                    <i class="fas fa-flag"></i> 要返金フラグを立てる
                                                </button>
                                            </form>
                                        @else
                                            <span class="admin-status-badge is-warning u-mt-8">要返金フラグが立っています</span>
                                        @endif
                                    @else
                                        <p class="billing-note">この振込タスクは無効です。口座修正後は別タスクで再発行してください。</p>
                                    @endif
                                </div>
                            @endif

                            @if($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && !$task)
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">キャスト振込記録</h3>
                                    <form method="POST" action="{{ route('admin.deposits.cast-transfer.execute', $deposit['id']) }}" class="billing-inline-form" enctype="multipart/form-data">
                                        @csrf
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">振込日時</label>
                                            <input type="datetime-local" name="transferred_at" class="admin-input" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                                        </div>
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">振込管理番号</label>
                                            <input type="text" name="reference" class="admin-input" placeholder="TRF-20260313-01">
                                        </div>
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">備考</label>
                                            <textarea name="note" class="admin-input" rows="3" placeholder="銀行窓口で実行、受付票確認済み"></textarea>
                                        </div>
                                        {{-- 証跡画像（AD-109 の振込完了と同じく必須。証跡なしの振込記録を防止） --}}
                                        <div class="admin-form-row" style="margin-bottom:0;">
                                            <label class="admin-label">振込完了画面のスクリーンショット <span style="color:#fca5a5;">必須</span></label>
                                            <input type="file" name="evidence_screenshot" class="admin-input" accept="image/*" required>
                                        </div>
                                        <div class="billing-check-grid" data-check-group>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_transfer_amount" value="1" data-check-item> 金額を確認した</label>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_account_name" value="1" data-check-item> 口座名義を確認した</label>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_transfer_executed" value="1" data-check-item> 銀行で振込を実行した</label>
                                            <label class="billing-check-item"><input type="checkbox" name="confirm_receipt_checked" value="1" data-check-item> 受付票を確認した</label>
                                        </div>
                                        <div class="management-actions">
                                            <button type="submit" class="btn-action manage" data-check-submit disabled>
                                                <i class="fas fa-yen-sign"></i> キャスト振込を記録する
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                            @if(!in_array($sc, [BMS::STATUS_SHOP_APPROVED, BMS::STATUS_SHOP_PAYMENT_REPORTED, BMS::STATUS_SHOP_PAYMENT_CONFIRMED], true))
                                <div class="billing-action-box">
                                    <h3 class="billing-action-title">現在の状況</h3>
                                    <p class="billing-note">この案件で今すぐ必要な運営アクションはありません。進行に応じてタスク管理へ自動表示されます。</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </details>
        @empty
            <div class="admin-panel">
                <p class="admin-note">入金・振込データがまだありません。</p>
            </div>
        @endforelse

        <div class="admin-panel" id="deposit-empty-filter" hidden>
            <p class="admin-note">条件に一致する案件はありません。</p>
        </div>
    </div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ============== チェックリストで完了ボタン制御 ==============
    document.querySelectorAll('[data-check-group]').forEach(function (group) {
        var submit = group.parentElement.querySelector('[data-check-submit]');
        var items = group.querySelectorAll('[data-check-item]');
        if (!submit || !items.length) return;
        var sync = function () {
            submit.disabled = Array.from(items).some(function (item) { return !item.checked; });
        };
        items.forEach(function (item) { item.addEventListener('change', sync); });
        sync();
    });

    // ============== 振込完了フォーム：証跡画像＋チェック＋日時 ==============
    document.querySelectorAll('[data-transfer-complete-form]').forEach(function (form) {
        var submit = form.querySelector('[data-complete-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        var fileInput = form.querySelector('[data-evidence-file]');
        var dateInput = form.querySelector('input[name="transferred_at"]');
        function syncComplete() {
            var checksOk = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            var hasDate = dateInput && dateInput.value.trim() !== '';
            submit.disabled = !(checksOk && hasFile && hasDate);
        }
        if (submit) {
            checks.forEach(function (c) { c.addEventListener('change', syncComplete); });
            if (fileInput) fileInput.addEventListener('change', syncComplete);
            if (dateInput) dateInput.addEventListener('change', syncComplete);
            syncComplete();
        }
    });

    // ============== 店舗入金照合フォーム：証跡画像＋3チェック ==============
    document.querySelectorAll('[data-confirm-shop-payment-form]').forEach(function (form) {
        var submit = form.querySelector('[data-shop-payment-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        var fileInput = form.querySelector('[data-shop-evidence-file]');
        function syncShopConfirm() {
            var checksOk = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            submit.disabled = !(checksOk && hasFile);
        }
        if (submit) {
            checks.forEach(function (c) { c.addEventListener('change', syncShopConfirm); });
            if (fileInput) fileInput.addEventListener('change', syncShopConfirm);
            syncShopConfirm();
        }
    });

    // ============== コピー ==============
    document.querySelectorAll('.btn-copy[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var value = this.getAttribute('data-copy-target');
            if (!value) return;
            var done = function () {
                var t = btn.textContent;
                btn.textContent = 'コピーしました';
                setTimeout(function () { btn.textContent = t; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = value;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                done();
            }
        });
    });

    // ============== フィルタ＋検索 ==============
    var chips = document.querySelectorAll('[data-deposit-filter]');
    var rows = document.querySelectorAll('[data-deposit-row]');
    var searchInput = document.getElementById('deposit-search');
    var emptyHint = document.getElementById('deposit-empty-filter');
    var currentFilter = 'all';
    var currentKeyword = '';

    function apply() {
        var visible = 0;
        rows.forEach(function (row) {
            var cat = row.getAttribute('data-deposit-cat') || '';
            var kw = row.getAttribute('data-keyword') || '';
            var matchFilter = currentFilter === 'all' || cat === currentFilter;
            var matchKw = currentKeyword === '' || kw.indexOf(currentKeyword) !== -1;
            var show = matchFilter && matchKw;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (emptyHint) emptyHint.hidden = visible !== 0 || rows.length === 0;
    }

    function activateChip(filterKey) {
        chips.forEach(function (c) {
            var on = c.getAttribute('data-deposit-filter') === filterKey;
            c.classList.toggle('is-active', on);
        });
        currentFilter = filterKey;
        apply();
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            activateChip(chip.getAttribute('data-deposit-filter'));
        });
    });
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentKeyword = (searchInput.value || '').toLowerCase().trim();
            apply();
        });
    }

    // ============== KPIクリック → フィルタ同期 ==============
    var kpiButtons = document.querySelectorAll('[data-deposit-kpis] [data-kpi-filter]');
    kpiButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-kpi-filter') || 'all';
            // KPI 自身のトグル: 同じKPIをもう一度押したら all に戻す
            var already = btn.getAttribute('aria-pressed') === 'true';
            var nextKey = already ? 'all' : key;
            kpiButtons.forEach(function (b) {
                var on = !already && b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            activateChip(nextKey);
            // 視覚的フィードバック: 一覧位置までスクロール
            var first = document.querySelector('[data-deposit-row]');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ============== 一括展開／折りたたみ ==============
    var bulkButtons = document.querySelectorAll('[data-deposit-bulk]');
    bulkButtons.forEach(function (b) {
        b.addEventListener('click', function () {
            var mode = b.getAttribute('data-deposit-bulk');
            rows.forEach(function (row) {
                if (row.style.display === 'none') return; // フィルタで非表示の行はスキップ
                if (mode === 'expand') row.open = true;
                if (mode === 'collapse') row.open = false;
            });
        });
    });
});
</script>
@endpush
