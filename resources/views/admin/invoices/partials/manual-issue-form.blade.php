{{-- 手動請求：帳票レイアウトに沿った入力欄 --}}
@php
    $first = $manualTargets[0] ?? null;
@endphp
<form method="POST" action="{{ route('admin.invoices.issue-manual') }}" id="form-manual-invoice">
    @csrf

    @if($errors->any())
        <div class="admin-alert admin-alert-error" style="margin-bottom: 14px;">
            {{ $errors->first() }}
        </div>
    @endif

    <p class="admin-note" style="margin-bottom: 14px;">
        下の枠は請求書帳票と同じ構成です。宛先・金額を確認・修正してから発行してください。請求金額合計は「ボーナス額＋運営手数料」と一致させてください。
    </p>

    <div class="invoice-manual-template-wrap">
        <div class="invoice-manual-field">
            <label class="admin-label" for="manual-deposit-select">対象の入金申請</label>
            <select name="deposit_id" id="manual-deposit-select" class="admin-input" required>
                <option value="">選択してください</option>
                @foreach($manualTargets as $d)
                    <option
                        value="{{ $d['id'] }}"
                        @selected((string) old('deposit_id', $first['id'] ?? '') === (string) $d['id'])
                        data-shop-name="{{ e($d['shop_name'] ?? '') }}"
                        data-shop-address="{{ e($d['shop_address'] ?? '') }}"
                        data-shop-email="{{ e($d['shop_email'] ?? '') }}"
                        data-cast-name="{{ e($d['cast_name'] ?? '') }}"
                        data-bonus="{{ (int) ($d['bonus_amount'] ?? 0) }}"
                        data-system="{{ (int) ($d['system_fee_amount'] ?? 0) }}"
                        data-invoice="{{ (int) ($d['invoice_amount'] ?? 0) }}"
                        data-cast-transfer="{{ (int) ($d['cast_transfer_amount'] ?? 0) }}"
                    >
                        #{{ $d['id'] }} {{ $d['shop_name'] }} / {{ $d['cast_name'] }}（{{ $d['status_label'] }}）
                    </option>
                @endforeach
            </select>
        </div>

        <div class="invoice-manual-paper">
            <p class="invoice-manual-paper-title">請求書に印字する内容（手動入力）</p>

            <div class="invoice-manual-grid">
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_shop_name">宛名（店舗名）</label>
                    <input type="text" name="shop_name" id="manual_shop_name" class="admin-input" value="{{ old('shop_name', $first['shop_name'] ?? '') }}" required maxlength="255" placeholder="店舗名">
                </div>
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_cast_name">対象キャスト名</label>
                    <input type="text" name="cast_name" id="manual_cast_name" class="admin-input" value="{{ old('cast_name', $first['cast_name'] ?? '') }}" required maxlength="255">
                </div>
            </div>
            <div class="invoice-manual-field">
                <label class="admin-label" for="manual_shop_address">住所</label>
                <input type="text" name="shop_address" id="manual_shop_address" class="admin-input" value="{{ old('shop_address', $first['shop_address'] ?? '') }}" maxlength="500" placeholder="店舗の住所">
            </div>
            <div class="invoice-manual-field">
                <label class="admin-label" for="manual_shop_email">連絡用メール</label>
                <input type="email" name="shop_email" id="manual_shop_email" class="admin-input" value="{{ old('shop_email', $first['shop_email'] ?? '') }}" maxlength="255" placeholder="通知先（任意）">
            </div>

            <div class="invoice-manual-grid invoice-manual-amounts">
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_bonus">ボーナス額（円）</label>
                    <input type="number" name="bonus_amount" id="manual_bonus" class="admin-input" min="0" step="1" value="{{ old('bonus_amount', $first['bonus_amount'] ?? 0) }}" required>
                </div>
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_system">運営手数料（円）</label>
                    <input type="number" name="system_fee_amount" id="manual_system" class="admin-input" min="0" step="1" value="{{ old('system_fee_amount', $first['system_fee_amount'] ?? 0) }}" required>
                </div>
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_cast_transfer">キャスト振込予定額（円）</label>
                    <input type="number" name="cast_transfer_amount" id="manual_cast_transfer" class="admin-input" min="0" step="1" value="{{ old('cast_transfer_amount', $first['cast_transfer_amount'] ?? 0) }}" required>
                    <small class="invoice-manual-hint">銀行振込手数料 220 円はキャスト負担のため、通常は「ボーナス額 − 220 円」を入力します。</small>
                </div>
                <div class="invoice-manual-field">
                    <label class="admin-label" for="manual_invoice_total">請求金額合計（円）</label>
                    <input type="number" name="invoice_amount" id="manual_invoice_total" class="admin-input" min="1" step="1" value="{{ old('invoice_amount', $first['invoice_amount'] ?? 1) }}" required>
                    <small class="invoice-manual-hint">ボーナス＋運営手数料と一致させてください（変更時は自動で再計算されます）</small>
                </div>
            </div>
        </div>
    </div>

    <div class="invoice-manual-check" data-check-group>
        <label>
            <input type="checkbox" name="confirm_manual_workaround" value="1" data-check-item required {{ old('confirm_manual_workaround') ? 'checked' : '' }}>
            システム不具合等の回避策として手動発行することを理解し、宛先・金額を確認した
        </label>
        <label>
            <input type="checkbox" name="confirm_admin_bank_ready" value="1" data-check-item required {{ old('confirm_admin_bank_ready') ? 'checked' : '' }}>
            請求書に記載する運営口座情報を確認した
        </label>
    </div>
    <div class="management-actions" style="margin-top: 16px;">
        <button type="submit" class="btn-action manage" data-check-submit disabled>
            <i class="fas fa-paper-plane"></i> 手動で請求書を発行する
        </button>
    </div>
</form>
