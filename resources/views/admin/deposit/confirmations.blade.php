@extends('layouts.admin')

@section('title', '入金確認')
@section('admin_page_title', '入金確認')

@section('content')
@php
    use App\Services\BillingManagementService as BMS;

    // 種別バッジ（3種類: ボーナス金 / ヘルプ採用金 / プラン入金）
    $kindMeta = [
        'bonus' => ['label' => 'ボーナス金', 'cls' => 'kind-bonus', 'icon' => 'fa-gift'],
        'help'  => ['label' => 'ヘルプ採用金', 'cls' => 'kind-help', 'icon' => 'fa-hand-holding-dollar'],
        'plan'  => ['label' => 'プラン入金', 'cls' => 'kind-plan', 'icon' => 'fa-crown'],
    ];

    // 状態バッジ（bonus/help と plan で分岐）
    $stateBadge = function (array $deposit) {
        $kind = $deposit['kind'] ?? 'bonus';
        $sc = (int) $deposit['status_code'];

        if ($kind === 'plan') {
            $isPending = $sc === BMS::STATUS_INVOICE_ISSUED;
            $overdue = !empty($deposit['plan_overdue']);
            $shopReported = !empty($deposit['plan_shop_reported']);
            if ($isPending) {
                if ($shopReported) {
                    return ['cls' => 'is-admin', 'label' => '店舗から振込通知あり', 'icon' => 'fa-bell'];
                }
                return $overdue
                    ? ['cls' => 'is-danger', 'label' => '期限超過（未入金）', 'icon' => 'fa-triangle-exclamation']
                    : ['cls' => 'is-admin', 'label' => '入金確認待ち', 'icon' => 'fa-hourglass-half'];
            }
            return ['cls' => 'is-done', 'label' => '有効', 'icon' => 'fa-circle-check'];
        }

        return match (true) {
            $sc === BMS::STATUS_INVOICE_ISSUED => ['cls' => 'is-shop', 'label' => '店舗入金待ち', 'icon' => 'fa-hourglass-half'],
            $sc === BMS::STATUS_SHOP_PAYMENT_REPORTED => ['cls' => 'is-admin', 'label' => '照合待ち', 'icon' => 'fa-bell'],
            $sc >= BMS::STATUS_SHOP_PAYMENT_CONFIRMED => ['cls' => 'is-done', 'label' => '照合済み', 'icon' => 'fa-circle-check'],
            default => ['cls' => 'is-admin-soft', 'label' => '確認中', 'icon' => 'fa-circle-question'],
        };
    };

    // Filter chip category for each row: pay_check（要対応） / await_shop（店舗の入金待ち）/ confirmed（照合済み）
    $filterKeyFor = function (array $d): string {
        $kind = $d['kind'] ?? 'bonus';
        $sc = (int) $d['status_code'];
        if ($kind === 'plan') {
            return $sc === BMS::STATUS_INVOICE_ISSUED ? 'pay_check' : 'confirmed';
        }
        if ($sc === BMS::STATUS_INVOICE_ISSUED) return 'await_shop';
        if ($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED) return 'pay_check';
        return 'confirmed';
    };
@endphp

<div class="admin-page">
    <div class="u-flex-between">
        @include('admin.parts.page-title', [
            'eyebrow' => 'PAYMENT VERIFICATION',
            'title' => '入金確認',
            'info' => '
                <p><strong>この画面の役割：</strong>3種類の入金対象（<strong>ボーナス金 / ヘルプ採用金 / プラン入金</strong>）を一元管理します。</p>
                <p>ネットバンキングの入出金明細と照合し、対応する行の「確認済みにする」を押してください。</p>
                <p>ボーナス金 / ヘルプ採用金は照合後、キャストへの振込が「<strong>キャスト振込</strong>」画面から実行できます。プラン入金は確認と同時に Premium 機能が有効になります。</p>
            ',
        ])
        @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.deposits.confirmations'])
    </div>

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- KPI --}}
    <section class="dashboard-kpi-grid deposit-kpi-grid">
        <article class="dashboard-kpi-card {{ ($summary['payment_confirmation_pending'] ?? 0) > 0 ? 'is-attention' : '' }}">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">照合待ち（ボーナス/ヘルプ）</div>
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['payment_confirmation_pending'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card {{ ($summary['plan_payment_pending'] ?? 0) > 0 ? 'is-attention' : '' }}">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">入金確認待ち（プラン）</div>
                <i class="fas fa-crown"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['plan_payment_pending'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">店舗入金待ち</div>
                <i class="fas fa-store"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['awaiting_shop_payment'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">照合済み（直近）</div>
                <i class="fas fa-circle-check"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['recent_confirmed'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
    </section>

    {{-- 状態フィルタ + 種別フィルタ（AND で絞り込む） --}}
    <div class="admin-page-toolbar-filters" data-deposit-filters>
        <button type="button" class="admin-filter-chip is-active" data-deposit-filter="pay_check">
            <span>運営対応の要対応のみ</span>
            <strong>{{ number_format(($summary['payment_confirmation_pending'] ?? 0) + ($summary['plan_payment_pending'] ?? 0)) }}</strong>
        </button>
        <button type="button" class="admin-filter-chip" data-deposit-filter="all">
            <span>すべて表示</span>
        </button>
    </div>
    <div class="admin-page-toolbar-filters deposit-kind-filters" data-deposit-kind-filters>
        <button type="button" class="admin-filter-chip is-active" data-deposit-kind="all">
            <span>すべての種別</span>
        </button>
        <button type="button" class="admin-filter-chip" data-deposit-kind="bonus">
            <i class="fas fa-gift"></i><span>ボーナス金</span>
        </button>
        <button type="button" class="admin-filter-chip" data-deposit-kind="help">
            <i class="fas fa-hand-holding-dollar"></i><span>ヘルプ採用金</span>
        </button>
        <button type="button" class="admin-filter-chip" data-deposit-kind="plan">
            <i class="fas fa-crown"></i><span>プラン入金</span>
            @if(($summary['plan_payment_pending'] ?? 0) > 0)
                <strong>{{ number_format($summary['plan_payment_pending']) }}</strong>
            @endif
        </button>
    </div>

    {{-- Compact list --}}
    <section class="admin-panel">
        <h2 class="admin-panel-title">入金案件一覧</h2>
        <p class="admin-note u-mb-12">行をタップすると詳細ウィンドウが開きます。確認作業もそこから行います。</p>

        @forelse($deposits as $deposit)
            @php
                $kind = $deposit['kind'] ?? 'bonus';
                $kMeta = $kindMeta[$kind] ?? $kindMeta['bonus'];
                $badge = $stateBadge($deposit);
                $filter = $filterKeyFor($deposit);
                $daysReported = null;
                if (!empty($deposit['shop_payment_reported_at'])) {
                    try { $daysReported = (int) \Carbon\Carbon::parse($deposit['shop_payment_reported_at'])->diffInDays(now()); } catch (\Throwable) { /* ignore */ }
                }
                // Plan rows: highlight overdue as "報告から N 日" equivalent
                $daysOverdue = null;
                if ($kind === 'plan' && !empty($deposit['plan_overdue']) && !empty($deposit['invoice_due_date'])) {
                    try { $daysOverdue = (int) \Carbon\Carbon::parse($deposit['invoice_due_date'])->diffInDays(now()); } catch (\Throwable) { /* ignore */ }
                }
                $modalId = 'confirm-modal-' . $kind . '-' . $deposit['id'];
            @endphp
            <div class="deposit-row" data-deposit-row data-deposit-cat="{{ $filter }}" data-deposit-kind="{{ $kind }}"
                 role="button" tabindex="0" data-open-modal="{{ $modalId }}">
                <div class="deposit-row__col deposit-row__col-id">
                    <span class="kind-pill {{ $kMeta['cls'] }}" title="{{ $kMeta['label'] }}">
                        <i class="fas {{ $kMeta['icon'] }}"></i>
                        <span class="kind-pill__label">{{ $kMeta['label'] }}</span>
                    </span>
                </div>
                <div class="deposit-row__col deposit-row__col-name">
                    <div class="deposit-row__shop">{{ $deposit['shop_name'] }}</div>
                    <div class="deposit-row__cast">
                        @if($kind === 'plan')
                            Premium（{{ $deposit['plan_cycle_label'] ?? '月払い' }}）／ {{ $deposit['invoice_number'] ?: '—' }}
                        @else
                            {{ $deposit['cast_name'] }}
                        @endif
                    </div>
                </div>
                <div class="deposit-row__col deposit-row__col-badge">
                    <span class="actor-pill {{ $badge['cls'] }}">
                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                    </span>
                    @if($daysReported !== null && (int) $deposit['status_code'] === BMS::STATUS_SHOP_PAYMENT_REPORTED)
                        <span class="deposit-row__days {{ $daysReported >= 3 ? 'is-soon' : '' }}">
                            <i class="fas fa-clock"></i> 報告から{{ $daysReported }}日
                        </span>
                    @elseif($kind === 'plan' && $daysReported !== null && !empty($deposit['plan_shop_reported']))
                        <span class="deposit-row__days {{ $daysReported >= 3 ? 'is-soon' : '' }}">
                            <i class="fas fa-clock"></i> 通知から{{ $daysReported }}日
                        </span>
                    @elseif($daysOverdue !== null && $daysOverdue > 0)
                        <span class="deposit-row__days is-soon">
                            <i class="fas fa-clock"></i> 期限超過{{ $daysOverdue }}日
                        </span>
                    @endif
                </div>
                <div class="deposit-row__col deposit-row__col-amount">¥{{ number_format((int) $deposit['invoice_amount']) }}</div>
                <div class="deposit-row__col deposit-row__col-cta">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>

            {{-- Modal --}}
            <dialog class="ops-modal" id="{{ $modalId }}" aria-labelledby="{{ $modalId }}-title" data-ops-modal>
                <form method="dialog" class="ops-modal__close-form">
                    <button type="submit" class="ops-modal__close" aria-label="閉じる">
                        <i class="fas fa-xmark"></i>
                    </button>
                </form>
                <header class="ops-modal__head">
                    <div>
                        <div class="ops-modal__eyebrow">
                            <span class="kind-pill {{ $kMeta['cls'] }} kind-pill--sm">
                                <i class="fas {{ $kMeta['icon'] }}"></i> {{ $kMeta['label'] }}
                            </span>
                            <span>#{{ $deposit['id'] }}</span>
                        </div>
                        <h3 id="{{ $modalId }}-title" class="ops-modal__title">
                            {{ $deposit['shop_name'] }}@if($kind !== 'plan') / {{ $deposit['cast_name'] }} @endif
                        </h3>
                    </div>
                    <span class="actor-pill {{ $badge['cls'] }}"><i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}</span>
                </header>

                <div class="ops-modal__body">
                    {{-- 参考情報 --}}
                    <section class="ops-ref">
                        <div class="ops-ref__label">
                            <i class="fas fa-circle-info"></i> 参考情報（照合の判断材料）
                        </div>
                        <dl class="ops-ref__grid">
                            <div class="ops-ref__row">
                                <dt>請求番号</dt>
                                <dd>{{ $deposit['invoice_number'] ?: '—' }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>請求発行日</dt>
                                <dd>{{ $deposit['invoice_issued_at'] ?: '—' }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>支払期限</dt>
                                <dd>{{ $deposit['invoice_due_date'] ?: '—' }}</dd>
                            </div>
                            @if($kind === 'plan')
                                <div class="ops-ref__row">
                                    <dt>プラン</dt>
                                    <dd>Premium（{{ $deposit['plan_cycle_label'] ?? '月払い' }}）</dd>
                                </div>
                                <div class="ops-ref__row">
                                    <dt>有効期限（確認後）</dt>
                                    <dd>{{ $deposit['plan_ends_at'] ?: '—' }}</dd>
                                </div>
                                <div class="ops-ref__row">
                                    <dt>店舗の振込通知</dt>
                                    <dd>
                                        @if(!empty($deposit['shop_payment_reported_at']))
                                            <i class="fas fa-bell" style="color:#b45309"></i>
                                            {{ $deposit['shop_payment_reported_at'] }}
                                        @else
                                            未通知
                                        @endif
                                    </dd>
                                </div>
                                @if(!empty($deposit['shop_payment_reference']))
                                    <div class="ops-ref__row">
                                        <dt>店舗の参照情報</dt>
                                        <dd>{{ $deposit['shop_payment_reference'] }}</dd>
                                    </div>
                                @endif
                            @else
                                <div class="ops-ref__row">
                                    <dt>店舗入金報告日時</dt>
                                    <dd>{{ $deposit['shop_payment_reported_at'] ?: '未報告' }}</dd>
                                </div>
                                <div class="ops-ref__row">
                                    <dt>店舗入金報告額</dt>
                                    <dd>
                                        {{ $deposit['shop_payment_reported_amount'] ? '¥' . number_format((int) $deposit['shop_payment_reported_amount']) : '未報告' }}
                                        @if($deposit['shop_payment_reported_amount'] && (int) $deposit['shop_payment_reported_amount'] !== (int) $deposit['invoice_amount'])
                                            <span class="ops-ref__warn" title="店舗の自己申告値が請求金額とズレています。銀行明細で実着金額を必ず確認してください。">
                                                <i class="fas fa-triangle-exclamation"></i> 請求金額とズレ
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="ops-ref__row">
                                    <dt>店舗の参照番号</dt>
                                    <dd>{{ $deposit['shop_payment_reference'] ?: '—' }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    @if($kind === 'plan')
                        @if((int) $deposit['status_code'] === BMS::STATUS_INVOICE_ISSUED)
                            {{-- プラン入金確認フォーム（証跡不要・目視確認 → 有効化） --}}
                            <section class="ops-input">
                                <div class="ops-input__label">
                                    <i class="fas fa-pen-to-square"></i> 入金確認（ネットバンキング明細を目視確認）
                                </div>
                                @if(!empty($deposit['plan_shop_reported']))
                                    <p class="admin-note" style="color:#b45309;">
                                        <i class="fas fa-bell"></i>
                                        店舗から振込通知が届いています（{{ $deposit['shop_payment_reported_at'] }}
                                        @if(!empty($deposit['shop_payment_reference']))／ 参照: {{ $deposit['shop_payment_reference'] }}@endif
                                        ）。銀行明細を照合してください。
                                    </p>
                                @endif
                                <p class="admin-note">
                                    請求金額 <strong>¥{{ number_format((int) $deposit['invoice_amount']) }}</strong> の入金を銀行明細で確認したら、下のボタンを押してください。押した時点で Premium 機能が自動的に有効になります。
                                </p>
                                <form method="POST" action="{{ route('admin.deposits.plan-payment.confirm', $deposit['plan_id']) }}"
                                      class="ops-input__form" data-plan-confirm-form>
                                    @csrf
                                    <div class="billing-check-grid" data-check-group>
                                        <label class="billing-check-item"><input type="checkbox" data-check-item> ネットバンキング明細で入金を確認した</label>
                                        <label class="billing-check-item"><input type="checkbox" data-check-item> 請求金額と着金額が一致している</label>
                                    </div>
                                    <div class="management-actions">
                                        <button type="submit" class="btn-action manage" data-plan-submit disabled
                                                onclick="return confirm('入金確認済みにすると Premium 機能が即時有効になります。よろしいですか？');">
                                            <i class="fas fa-check"></i> 入金確認済みにする（Premium有効化）
                                        </button>
                                    </div>
                                </form>
                            </section>
                        @else
                            {{-- Plan already active — offer receipt + link back to plan management --}}
                            <section class="ops-info-note is-success">
                                <div>
                                    <strong>入金確認済み</strong>
                                    @if(!empty($deposit['shop_payment_confirmed_at']))
                                        ／ {{ $deposit['shop_payment_confirmed_at'] }}
                                    @endif
                                    @if(!empty($deposit['plan_ends_at']))
                                        ／ 有効期限 {{ $deposit['plan_ends_at'] }}
                                    @endif
                                </div>
                                <div class="ops-info-note__actions">
                                    <a href="{{ route('admin.plans.receipt', $deposit['plan_id']) }}" target="_blank" rel="noopener" class="btn-action btn-action-secondary">
                                        <i class="fas fa-file-lines"></i> 領収書
                                    </a>
                                </div>
                            </section>
                        @endif
                    @elseif((int) $deposit['status_code'] === BMS::STATUS_SHOP_PAYMENT_REPORTED)
                        {{-- 入力情報 --}}
                        <section class="ops-input">
                            <div class="ops-input__label">
                                <i class="fas fa-pen-to-square"></i> 入力情報（この画面で入力してください）
                            </div>
                            <form method="POST" action="{{ route('admin.deposits.shop-payment.confirm', $deposit['id']) }}"
                                  enctype="multipart/form-data" data-confirm-shop-payment-form class="ops-input__form">
                                @csrf
                                <div class="admin-form-row">
                                    <label class="admin-label">確認済み金額 <span class="required">必須</span></label>
                                    <input type="number" name="confirmed_amount" class="admin-input"
                                           value="{{ (int) $deposit['invoice_amount'] }}" min="1" required>
                                    <small class="admin-note">請求金額と一致していることを確認してください。</small>
                                </div>
                                <div class="admin-form-row">
                                    <label class="admin-label">ネットバンキング画面のスクリーンショット <span class="required">必須</span></label>
                                    <input type="file" name="evidence_screenshot" accept="image/*" class="admin-input" data-shop-evidence-file required>
                                    <small class="admin-note">JPEG / PNG など画像ファイル（10MB以内）。入金金額・振込元・日時が写った画面を推奨。</small>
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
                        </section>
                    @elseif((int) $deposit['status_code'] === BMS::STATUS_INVOICE_ISSUED)
                        <section class="ops-info-note">
                            <i class="fas fa-hourglass-half"></i>
                            店舗からの入金報告を待っています。入金報告が届くと、この画面で照合作業を行えるようになります。
                        </section>
                    @else
                        {{-- Already confirmed - show evidence link, and a link to the transfer screen --}}
                        <section class="ops-info-note is-success">
                            <div>
                                <strong>照合済み</strong> {{ $deposit['shop_payment_confirmed_at'] ?? '' }}
                                @if(!empty($deposit['shop_payment_reference']))
                                    ／ 参照番号 {{ $deposit['shop_payment_reference'] }}
                                @endif
                            </div>
                            <div class="ops-info-note__actions">
                                @if(!empty($deposit['shop_payment_evidence_path']))
                                    <a href="{{ asset('storage/' . ltrim($deposit['shop_payment_evidence_path'], '/')) }}" target="_blank" rel="noopener" class="btn-action btn-action-secondary">
                                        <i class="fas fa-image"></i> 証跡画像を開く
                                    </a>
                                @endif
                                <a href="{{ route('admin.deposits.transfers') }}" class="btn-action btn-action-secondary">
                                    <i class="fas fa-arrow-right"></i> キャスト振込画面へ
                                </a>
                            </div>
                        </section>
                    @endif

                    <div class="ops-modal__meta">
                        @if($kind === 'plan')
                            <a href="{{ route('admin.plans.invoice', $deposit['plan_id']) }}" target="_blank" rel="noopener" class="ops-modal__meta-link">
                                <i class="fas fa-file-invoice"></i> 請求書を参照（プラン）
                            </a>
                        @else
                            <a href="{{ route('admin.deposits.invoice.show', $deposit['id']) }}" target="_blank" rel="noopener" class="ops-modal__meta-link">
                                <i class="fas fa-file-invoice"></i> 請求書を参照
                            </a>
                        @endif
                    </div>
                </div>
            </dialog>
        @empty
            <p class="admin-note">入金確認の対象となる案件はありません。</p>
        @endforelse
    </section>
</div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- Modal open/close ----
    document.querySelectorAll('[data-open-modal]').forEach(function (row) {
        row.addEventListener('click', function () {
            var id = row.getAttribute('data-open-modal');
            var dlg = document.getElementById(id);
            if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
            else if (dlg) dlg.setAttribute('open', '');
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                row.click();
            }
        });
    });
    document.querySelectorAll('[data-ops-modal]').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
            // Click outside content = close
            var rect = dlg.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                dlg.close();
            }
        });
    });

    // ---- Bonus/help confirm form: enable submit only when file + all checks are set ----
    document.querySelectorAll('[data-confirm-shop-payment-form]').forEach(function (form) {
        var submit = form.querySelector('[data-shop-payment-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        var file = form.querySelector('[data-shop-evidence-file]');
        function sync() {
            var checksOk = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            var hasFile = file && file.files && file.files.length > 0;
            submit.disabled = !(checksOk && hasFile);
        }
        checks.forEach(function (c) { c.addEventListener('change', sync); });
        if (file) file.addEventListener('change', sync);
        sync();
    });

    // ---- Plan confirm form: enable submit when both checks are set ----
    document.querySelectorAll('[data-plan-confirm-form]').forEach(function (form) {
        var submit = form.querySelector('[data-plan-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        function sync() {
            var ok = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            submit.disabled = !ok;
        }
        checks.forEach(function (c) { c.addEventListener('change', sync); });
        sync();
    });

    // ---- 2-axis filters (status × kind) ----
    var statusChips = document.querySelectorAll('[data-deposit-filters] [data-deposit-filter]');
    var kindChips = document.querySelectorAll('[data-deposit-kind-filters] [data-deposit-kind]');
    var rows = document.querySelectorAll('[data-deposit-row]');
    var currentStatus = 'pay_check';
    var currentKind = 'all';
    function applyFilter() {
        rows.forEach(function (r) {
            var cat = r.getAttribute('data-deposit-cat') || '';
            var kind = r.getAttribute('data-deposit-kind') || '';
            var okStatus = (currentStatus === 'all' || cat === currentStatus);
            var okKind = (currentKind === 'all' || kind === currentKind);
            r.style.display = (okStatus && okKind) ? '' : 'none';
        });
    }
    statusChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            currentStatus = chip.getAttribute('data-deposit-filter') || 'pay_check';
            statusChips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
            applyFilter();
        });
    });
    kindChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            currentKind = chip.getAttribute('data-deposit-kind') || 'all';
            kindChips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
            applyFilter();
        });
    });
    applyFilter();
});
</script>
@endpush

@push('admin-styles')
<style>
/* Compact row list */
.deposit-row {
    display: grid;
    grid-template-columns: 130px 1fr auto auto 24px;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    border: 1px solid var(--admin-line);
    background: var(--admin-surface);
    border-radius: 10px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.deposit-row:hover { background: var(--admin-surface-alt); border-color: var(--admin-line-strong); }
.deposit-row:focus-visible { outline: 2px solid var(--admin-primary); outline-offset: 2px; }
.deposit-row__col-id { font-family: Menlo, monospace; color: var(--admin-sub); font-size: 0.85rem; }
.deposit-row__shop { font-weight: 700; color: var(--admin-text); }
.deposit-row__cast { font-size: 0.8rem; color: var(--admin-sub); margin-top: 2px; }
.deposit-row__col-badge { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.deposit-row__days { font-size: 0.72rem; color: var(--admin-sub); }
.deposit-row__days.is-soon { color: #f59e0b; font-weight: 600; }
.deposit-row__col-amount { font-weight: 800; font-variant-numeric: tabular-nums; color: var(--admin-text); }
.deposit-row__col-cta { color: var(--admin-sub); }
@media (max-width: 700px) {
    .deposit-row { grid-template-columns: 1fr auto 24px; row-gap: 6px; }
    .deposit-row__col-id { grid-column: 1 / 2; grid-row: 1; }
    .deposit-row__col-name { grid-column: 1 / 3; grid-row: 2; }
    .deposit-row__col-badge { grid-column: 1 / 3; grid-row: 3; align-items: flex-start; }
    .deposit-row__col-amount { grid-column: 2 / 3; grid-row: 1; }
    .deposit-row__col-cta { grid-column: 3; grid-row: 1 / 4; align-self: center; }
}

/* Kind pill (3 categories: bonus / help / plan) */
.kind-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700;
    border: 1px solid transparent;
}
.kind-pill i { font-size: 0.68rem; }
.kind-pill--sm { padding: 2px 7px; font-size: 0.66rem; }
.kind-pill.kind-bonus { background: rgba(168, 85, 247, 0.14); color: #c084fc; border-color: rgba(168, 85, 247, 0.35); }
.kind-pill.kind-help  { background: rgba(59, 130, 246, 0.14); color: #60a5fa; border-color: rgba(59, 130, 246, 0.35); }
.kind-pill.kind-plan  { background: rgba(234, 179, 8, 0.14); color: #eab308; border-color: rgba(234, 179, 8, 0.4); }
.kind-pill__label { white-space: nowrap; }

/* Second filter row (kind filter) */
.deposit-kind-filters { margin-top: -4px; }
.deposit-kind-filters .admin-filter-chip i { margin-right: 4px; font-size: 0.78rem; }

/* Modal shell (native <dialog>) */
.ops-modal {
    padding: 0;
    border: 0;
    border-radius: 16px;
    max-width: 720px;
    width: calc(100vw - 32px);
    background: var(--admin-surface);
    color: var(--admin-text);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
}
.ops-modal::backdrop { background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px); }
.ops-modal__close-form { position: absolute; top: 12px; right: 12px; margin: 0; }
.ops-modal__close {
    background: transparent; border: 0; color: var(--admin-sub); font-size: 20px; cursor: pointer;
    width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
}
.ops-modal__close:hover { background: var(--admin-surface-alt); color: var(--admin-text); }
.ops-modal__head {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
    padding: 20px 24px 12px; border-bottom: 1px solid var(--admin-line);
}
.ops-modal__eyebrow { font-size: 0.68rem; letter-spacing: 0.14em; color: var(--admin-sub); font-weight: 700; display: inline-flex; gap: 8px; align-items: center; }
.ops-modal__title { font-size: 1.05rem; font-weight: 800; color: var(--admin-text); margin: 4px 0 0; }
.ops-modal__body { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; }

/* Reference section (muted, read-only feel) */
.ops-ref {
    background: var(--admin-surface-alt);
    border: 1px dashed var(--admin-line);
    border-radius: 12px;
    padding: 14px 16px;
}
.ops-ref__label {
    font-size: 0.72rem; letter-spacing: 0.06em; font-weight: 700;
    color: var(--admin-sub); margin-bottom: 10px; display: inline-flex; gap: 6px; align-items: center;
}
.ops-ref__grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 16px; margin: 0;
}
.ops-ref__row dt { font-size: 0.7rem; color: var(--admin-sub); margin: 0 0 2px; font-weight: 600; }
.ops-ref__row dd { font-size: 0.9rem; color: var(--admin-text); margin: 0; font-variant-numeric: tabular-nums; }
.ops-ref__warn {
    display: inline-flex; align-items: center; gap: 4px; margin-left: 8px;
    padding: 2px 8px; border-radius: 999px;
    background: rgba(245, 158, 11, 0.14); color: #f59e0b;
    font-size: 0.72rem; font-weight: 700; font-variant-numeric: normal;
}
.ops-ref__warn i { font-size: 0.7rem; }
@media (max-width: 500px) { .ops-ref__grid { grid-template-columns: 1fr; } }

/* Input section (prominent, actionable) */
.ops-input {
    background: rgba(168, 85, 247, 0.06);
    border: 1px solid var(--admin-primary-border);
    border-radius: 12px;
    padding: 14px 16px;
}
.ops-input__label {
    font-size: 0.75rem; letter-spacing: 0.06em; font-weight: 800;
    color: var(--admin-primary); margin-bottom: 12px; display: inline-flex; gap: 6px; align-items: center;
}
.ops-input__form .admin-form-row { margin-bottom: 12px; }

/* Info-only status notes */
.ops-info-note {
    padding: 12px 14px; border-radius: 10px;
    background: rgba(148, 163, 184, 0.10); border: 1px solid var(--admin-line);
    display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;
}
.ops-info-note.is-success {
    background: rgba(4, 120, 87, 0.10);
    border-color: rgba(4, 120, 87, 0.35);
    color: #86efac;
}
.ops-info-note__actions { display: flex; gap: 8px; flex-wrap: wrap; }

.ops-modal__meta { display: flex; justify-content: flex-end; padding-top: 4px; border-top: 1px dashed var(--admin-line); }
.ops-modal__meta-link { color: var(--admin-sub); font-size: 0.85rem; text-decoration: none; }
.ops-modal__meta-link:hover { color: var(--admin-text); text-decoration: underline; }
</style>
@endpush
