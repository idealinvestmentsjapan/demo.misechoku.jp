@extends('layouts.admin')

@section('title', '請求書内容の修正')
@section('admin_page_title', '請求書内容の修正')

@section('content')
<div class="admin-page">
    <div class="admin-panel">
        <div class="admin-alert admin-alert-warning" style="margin-bottom:14px;">
            <strong>例外運用</strong>：通常は自動計算値で発行してください。
            この画面は障害・特別な取引条件などで内容を手動で調整する必要がある場合のみ使用します。
            送信済みの請求書を修正すると、店舗側の表示金額と食い違う可能性があるため、修正後は必要に応じて店舗へ再送してください。
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

        <h2 class="admin-panel-title">
            <i class="fas fa-pen-to-square"></i> 請求書 {{ $invoice['invoice_number'] }}
        </h2>
        <p class="admin-note u-mb-12">
            対象案件: 店舗「{{ $invoice['shop_name'] ?: '未登録' }}」／ キャスト「{{ $invoice['cast_name'] ?: '未登録' }}」
        </p>

        <form method="POST" action="{{ route('admin.deposits.invoice.update', ['deposit' => $invoice['deposit_id']]) }}" id="invoice-edit-form">
            @csrf

            <h3 class="admin-panel-subtitle u-mt-16">金額</h3>
            <p class="admin-note u-mb-8">「ボーナス金 + 運営手数料 = 請求金額」となるように入力してください。</p>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_bonus_amount">ボーナス金 <span class="required">必須</span></label>
                <div class="invoice-edit-amount">
                    <input type="number" id="edit_bonus_amount" name="bonus_amount" class="admin-input"
                           value="{{ old('bonus_amount', $invoice['bonus_amount']) }}" min="0" step="1" required>
                    <span class="invoice-edit-amount__unit">円</span>
                </div>
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_system_fee_amount">運営手数料 <span class="required">必須</span></label>
                <div class="invoice-edit-amount">
                    <input type="number" id="edit_system_fee_amount" name="system_fee_amount" class="admin-input"
                           value="{{ old('system_fee_amount', $invoice['system_fee_amount']) }}" min="0" step="1" required>
                    <span class="invoice-edit-amount__unit">円</span>
                </div>
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_invoice_amount">請求金額（税込） <span class="required">必須</span></label>
                <div class="invoice-edit-amount">
                    <input type="number" id="edit_invoice_amount" name="invoice_amount" class="admin-input"
                           value="{{ old('invoice_amount', $invoice['invoice_amount']) }}" min="1" step="1" required
                           data-invoice-total>
                    <span class="invoice-edit-amount__unit">円</span>
                </div>
                <small class="admin-note" id="invoice-sum-check"></small>
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_cast_transfer_amount">キャスト振込予定額 <span class="required">必須</span></label>
                <div class="invoice-edit-amount">
                    <input type="number" id="edit_cast_transfer_amount" name="cast_transfer_amount" class="admin-input"
                           value="{{ old('cast_transfer_amount', $invoice['cast_transfer_amount']) }}" min="0" step="1" required>
                    <span class="invoice-edit-amount__unit">円</span>
                </div>
            </div>

            <h3 class="admin-panel-subtitle u-mt-16">表示名（帳票の宛先・対象）</h3>
            <p class="admin-note u-mb-8">未入力なら登録済みの店舗・キャスト情報を自動使用します。</p>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_shop_name">宛先 店舗名</label>
                <input type="text" id="edit_shop_name" name="shop_name" class="admin-input"
                       value="{{ old('shop_name', $invoice['shop_name']) }}" maxlength="255"
                       placeholder="請求書に印字する店舗名（御中）">
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_shop_address">宛先 住所</label>
                <input type="text" id="edit_shop_address" name="shop_address" class="admin-input"
                       value="{{ old('shop_address', $invoice['shop_address']) }}" maxlength="500">
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_shop_email">宛先 メール（表示のみ）</label>
                <input type="email" id="edit_shop_email" name="shop_email" class="admin-input"
                       value="{{ old('shop_email', $invoice['shop_email']) }}" maxlength="255">
            </div>

            <div class="admin-form-row">
                <label class="admin-label" for="edit_cast_name">対象キャスト氏名</label>
                <input type="text" id="edit_cast_name" name="cast_name" class="admin-input"
                       value="{{ old('cast_name', $invoice['cast_name']) }}" maxlength="255">
            </div>

            <div class="management-actions u-mt-16" style="gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn-action manage" data-invoice-save>
                    <i class="fas fa-floppy-disk"></i> 保存
                </button>

                <a href="{{ route('admin.deposits.invoice.show', ['deposit' => $invoice['deposit_id']]) }}" class="btn-action btn-action-secondary">
                    <i class="fas fa-xmark"></i> キャンセル（プレビューへ戻る）
                </a>
            </div>
        </form>

        <hr style="margin: 24px 0; border: 0; border-top: 1px solid var(--sales-border, #e5e7eb);">

        <h3 class="admin-panel-subtitle">自動計算結果に戻す</h3>
        <p class="admin-note u-mb-8">
            金額 4 項目と表示名 4 項目を、システムの自動計算結果に戻します。手動で入力した内容は破棄されます。
        </p>
        <form method="POST" action="{{ route('admin.deposits.invoice.reset', ['deposit' => $invoice['deposit_id']]) }}"
              onsubmit="return confirm('金額と表示名を自動計算結果に戻します。手動で入力した値は破棄されます。よろしいですか？');">
            @csrf
            <button type="submit" class="btn-action btn-action-secondary">
                <i class="fas fa-arrows-rotate"></i> 自動計算結果に戻す
            </button>
        </form>
    </div>
</div>
@endsection

@push('admin-styles')
<style>
.invoice-edit-amount { display: flex; align-items: center; gap: 8px; max-width: 320px; }
.invoice-edit-amount .admin-input { flex: 1; text-align: right; font-variant-numeric: tabular-nums; }
.invoice-edit-amount__unit { color: var(--admin-muted, #8b6e77); font-size: 0.85rem; font-weight: 600; }
.admin-panel-subtitle {
    margin: 0 0 8px;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--admin-fg, #111827);
    border-left: 3px solid var(--admin-primary, #4A122A);
    padding-left: 10px;
}
</style>
@endpush

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var bonus  = document.getElementById('edit_bonus_amount');
    var fee    = document.getElementById('edit_system_fee_amount');
    var total  = document.getElementById('edit_invoice_amount');
    var hint   = document.getElementById('invoice-sum-check');
    var save   = document.querySelector('[data-invoice-save]');
    if (!bonus || !fee || !total) return;

    function sync() {
        var b = parseInt(bonus.value, 10) || 0;
        var f = parseInt(fee.value, 10) || 0;
        var t = parseInt(total.value, 10) || 0;
        var expected = b + f;
        if (t === expected) {
            hint.textContent = 'ボーナス金 + 運営手数料 = ' + expected.toLocaleString() + ' 円（一致）';
            hint.style.color = 'var(--sales-up, #047857)';
            if (save) save.disabled = false;
        } else {
            hint.textContent = 'ボーナス金 + 運営手数料 = ' + expected.toLocaleString() + ' 円 ／ 現在の請求金額 ' + t.toLocaleString() + ' 円（不一致）';
            hint.style.color = 'var(--sales-down, #b91c1c)';
            if (save) save.disabled = true;
        }
    }
    [bonus, fee, total].forEach(function (el) { el.addEventListener('input', sync); });
    sync();
});
</script>
@endpush
