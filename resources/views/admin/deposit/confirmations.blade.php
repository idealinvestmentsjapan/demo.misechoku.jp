@extends('layouts.admin')

@section('title', '入金確認')
@section('admin_page_title', '入金確認')

@section('content')
@php
    use App\Services\BillingManagementService as BMS;

    $stateBadge = function (int $sc): array {
        return match (true) {
            $sc === BMS::STATUS_INVOICE_ISSUED => ['cls' => 'is-shop', 'label' => '店舗入金待ち', 'icon' => 'fa-hourglass-half'],
            $sc === BMS::STATUS_SHOP_PAYMENT_REPORTED => ['cls' => 'is-admin', 'label' => '照合待ち', 'icon' => 'fa-bell'],
            $sc >= BMS::STATUS_SHOP_PAYMENT_CONFIRMED => ['cls' => 'is-done', 'label' => '照合済み', 'icon' => 'fa-circle-check'],
            default => ['cls' => 'is-admin-soft', 'label' => '確認中', 'icon' => 'fa-circle-question'],
        };
    };
@endphp

<div class="admin-page">
    <div class="u-flex-between">
        @include('admin.parts.page-title', [
            'eyebrow' => 'PAYMENT VERIFICATION',
            'title' => '入金確認',
            'info' => '
                <p><strong>この画面の役割：</strong>店舗から届いた入金報告を、ネットバンキングの明細と照合します。</p>
                <p>照合完了後、案件は自動で「<strong>キャスト振込</strong>」画面に移動します。振込作業はそちらで行ってください。</p>
                <p>照合には<strong>ネットバンキング画面のスクリーンショット</strong>が必須です。</p>
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
    <section class="dashboard-kpi-grid deposit-kpi-grid" data-deposit-kpis>
        <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link is-active {{ ($summary['payment_confirmation_pending'] ?? 0) > 0 ? 'is-attention' : '' }}"
                data-kpi-filter="pay_check" aria-pressed="true">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">照合待ち</div>
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['payment_confirmation_pending'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
            <div class="dashboard-kpi-trend is-up">運営で照合してください</div>
        </button>
        <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-kpi-filter="await_shop" aria-pressed="false">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">店舗入金待ち</div>
                <i class="fas fa-store"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['awaiting_shop_payment'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
            <div class="dashboard-kpi-trend">運営の対応は不要</div>
        </button>
        <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-kpi-filter="confirmed" aria-pressed="false">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">照合済み（直近）</div>
                <i class="fas fa-circle-check"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['recent_confirmed'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </button>
    </section>

    {{-- Compact list --}}
    <section class="admin-panel">
        <h2 class="admin-panel-title">入金案件一覧</h2>
        <p class="admin-note u-mb-12">行をタップすると詳細ウィンドウが開きます。照合作業もそこから行います。</p>

        @forelse($deposits as $deposit)
            @php
                $sc = (int) $deposit['status_code'];
                $badge = $stateBadge($sc);
                $filter = $sc === BMS::STATUS_INVOICE_ISSUED ? 'await_shop'
                    : ($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED ? 'pay_check' : 'confirmed');
                $daysReported = null;
                if (!empty($deposit['shop_payment_reported_at'])) {
                    try { $daysReported = (int) \Carbon\Carbon::parse($deposit['shop_payment_reported_at'])->diffInDays(now()); } catch (\Throwable) { /* ignore */ }
                }
                $modalId = 'confirm-modal-' . $deposit['id'];
            @endphp
            <div class="deposit-row" data-deposit-row data-deposit-cat="{{ $filter }}"
                 role="button" tabindex="0" data-open-modal="{{ $modalId }}">
                <div class="deposit-row__col deposit-row__col-id">#{{ $deposit['id'] }}</div>
                <div class="deposit-row__col deposit-row__col-name">
                    <div class="deposit-row__shop">{{ $deposit['shop_name'] }}</div>
                    <div class="deposit-row__cast">{{ $deposit['cast_name'] }}</div>
                </div>
                <div class="deposit-row__col deposit-row__col-badge">
                    <span class="actor-pill {{ $badge['cls'] }}">
                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                    </span>
                    @if($daysReported !== null && $sc === BMS::STATUS_SHOP_PAYMENT_REPORTED)
                        <span class="deposit-row__days {{ $daysReported >= 3 ? 'is-soon' : '' }}">
                            <i class="fas fa-clock"></i> 報告から{{ $daysReported }}日
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
                        <div class="ops-modal__eyebrow">入金確認 #{{ $deposit['id'] }}</div>
                        <h3 id="{{ $modalId }}-title" class="ops-modal__title">
                            {{ $deposit['shop_name'] }} / {{ $deposit['cast_name'] }}
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
                            <div class="ops-ref__row">
                                <dt>店舗入金報告日時</dt>
                                <dd>{{ $deposit['shop_payment_reported_at'] ?: '未報告' }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>店舗入金報告額</dt>
                                <dd>{{ $deposit['shop_payment_reported_amount'] ? '¥' . number_format((int) $deposit['shop_payment_reported_amount']) : '未報告' }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>店舗の参照番号</dt>
                                <dd>{{ $deposit['shop_payment_reference'] ?: '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    @if($sc === BMS::STATUS_SHOP_PAYMENT_REPORTED)
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
                    @elseif($sc === BMS::STATUS_INVOICE_ISSUED)
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
                        <a href="{{ route('admin.deposits.invoice.show', $deposit['id']) }}" target="_blank" rel="noopener" class="ops-modal__meta-link">
                            <i class="fas fa-file-invoice"></i> 請求書を参照
                        </a>
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

    // ---- Confirm-payment form: enable submit only when file + all checks are set ----
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

    // ---- KPI filter: hide non-matching rows ----
    var kpis = document.querySelectorAll('[data-deposit-kpis] [data-kpi-filter]');
    var rows = document.querySelectorAll('[data-deposit-row]');
    function applyFilter(key) {
        rows.forEach(function (r) {
            var cat = r.getAttribute('data-deposit-cat') || '';
            r.style.display = (key === 'all' || cat === key) ? '' : 'none';
        });
    }
    kpis.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var already = btn.getAttribute('aria-pressed') === 'true';
            var next = already ? 'all' : (btn.getAttribute('data-kpi-filter') || 'all');
            kpis.forEach(function (b) {
                var on = !already && b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            applyFilter(next);
        });
    });
    // Default filter: pay_check (照合待ち)
    applyFilter('pay_check');
});
</script>
@endpush

@push('admin-styles')
<style>
/* Compact row list */
.deposit-row {
    display: grid;
    grid-template-columns: 60px 1fr auto auto 24px;
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
.ops-modal__eyebrow { font-size: 0.68rem; letter-spacing: 0.14em; color: var(--admin-sub); font-weight: 700; }
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
