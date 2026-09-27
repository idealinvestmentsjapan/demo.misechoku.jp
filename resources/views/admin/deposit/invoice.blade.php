{{-- 請求書：帳票テンプレート（billing/invoice-body）を組み込み、管理画面用にツールバーを付与 --}}
@php
    use App\Services\BillingManagementService as BMS;
    $delivery = $invoice['delivery_status'] ?? ['code' => 'pre_issue', 'label' => '未発行', 'sent_at' => null];
    $statusCode = (int) ($invoice['status_code'] ?? 0);
    // Issue action available only when the deposit is shop-approved (status=2) and no invoice_number yet.
    $canIssue = $delivery['code'] === 'pre_issue' && $statusCode === BMS::STATUS_SHOP_APPROVED;
    $canSend = $delivery['code'] === 'issued_unsent';
    $isSent = $delivery['code'] === 'sent';
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書 {{ $invoice['invoice_number'] }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: "Helvetica Neue", Arial, "Hiragino Sans", "Meiryo", sans-serif; }
        .invoice-shell { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .invoice-status-bar {
            display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
            padding: 14px 18px; margin-bottom: 12px; border-radius: 12px; background: #ffffff;
            border: 1px solid #e5e7eb;
        }
        .invoice-status-bar__label { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; }
        .invoice-status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
            border: 1px solid transparent;
        }
        .invoice-status-badge.is-pre { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .invoice-status-badge.is-unsent { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .invoice-status-badge.is-sent { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .invoice-status-bar__meta { font-size: 12px; color: #6b7280; }
        .invoice-toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .invoice-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 10px 14px; border-radius: 10px; border: 1px solid #d1d5db; background: #111827;
            color: #fff; text-decoration: none; cursor: pointer; font-size: 14px; font-weight: 700;
        }
        .invoice-btn.is-secondary { background: #4b5563; }
        .invoice-btn.is-muted { background: #6b7280; }
        .invoice-btn.is-primary { background: #4A122A; box-shadow: 0 6px 18px rgba(74, 18, 42, 0.25); }
        .invoice-btn.is-primary:disabled { background: #9ca3af; box-shadow: none; cursor: not-allowed; }
        .invoice-btn.is-danger { background: #b91c1c; }
        .invoice-action-panel {
            padding: 18px 20px; margin-top: 16px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px;
        }
        .invoice-action-panel__title { font-size: 15px; font-weight: 800; margin: 0 0 8px; color: #111827; }
        .invoice-action-panel__desc { font-size: 13px; color: #4b5563; margin: 0 0 12px; line-height: 1.7; }
        .invoice-action-panel__checks { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .invoice-action-panel__check {
            display: inline-flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #111827; cursor: pointer;
        }
        .invoice-action-panel__check input { margin-top: 2px; }
        .invoice-action-panel__actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .invoice-flash {
            padding: 10px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 14px; font-weight: 600;
        }
        .invoice-flash.is-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .invoice-flash.is-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .invoice-paper { background: #fff; border-radius: 20px; padding: 36px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12); }
        .invoice-wrap { max-width: none; padding: 0; }
        .invoice-header { border-bottom: 2px solid #111827; padding-bottom: 12pt; margin-bottom: 16pt; }
        .invoice-title { font-size: 22pt; font-weight: 800; letter-spacing: 0.12em; margin: 0 0 8pt; }
        .invoice-meta { font-size: 9pt; color: #4b5563; line-height: 1.6; }
        .invoice-issuer { text-align: right; font-size: 9pt; color: #4b5563; margin-top: 4pt; }
        .invoice-to { margin-bottom: 16pt; }
        .invoice-to-name { font-size: 14pt; font-weight: 800; margin: 0 0 4pt; }
        .invoice-to-addr { font-size: 9pt; color: #4b5563; line-height: 1.5; }
        .invoice-total { margin: 14pt 0; padding: 12pt 14pt; background: #fdf4f7; border-radius: 8pt; }
        .invoice-total-label { font-size: 9pt; color: #4A122A; font-weight: 700; margin-bottom: 4pt; }
        .invoice-total-value { font-size: 18pt; font-weight: 800; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 8pt; font-size: 10pt; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 10pt 12pt; text-align: left; vertical-align: top; }
        .invoice-table th { width: 26%; background: #f9fafb; color: #4b5563; font-weight: 700; }
        .invoice-bank { margin-top: 18pt; padding: 14pt 16pt; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8pt; }
        .invoice-bank-title { margin: 0 0 8pt; font-size: 12pt; font-weight: 800; }
        .invoice-bank-detail { font-size: 10pt; line-height: 1.7; color: #374151; }
        @media print {
            body { background: #fff; }
            .invoice-shell { margin: 0; max-width: none; padding: 0; }
            .invoice-toolbar, .invoice-status-bar, .invoice-action-panel, .invoice-flash { display: none !important; }
            .invoice-paper { box-shadow: none; border-radius: 0; padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="invoice-shell">
        @if(!$printMode)
            @if(session('status'))
                <div class="invoice-flash is-success"><i class="fas fa-circle-check"></i> {{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="invoice-flash is-error"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
            @endif

            {{-- Delivery status bar --}}
            <div class="invoice-status-bar">
                <div class="invoice-status-bar__label">
                    <i class="fas fa-file-invoice"></i> 送信ステータス:
                    @if($delivery['code'] === 'sent')
                        <span class="invoice-status-badge is-sent">
                            <i class="fas fa-circle-check"></i> 送信済み ／ {{ $delivery['sent_at'] }}
                        </span>
                    @elseif($delivery['code'] === 'issued_unsent')
                        <span class="invoice-status-badge is-unsent">
                            <i class="fas fa-hourglass-half"></i> 未送信（プレビュー中）
                        </span>
                    @else
                        <span class="invoice-status-badge is-pre">
                            <i class="fas fa-pen-to-square"></i> 未発行
                        </span>
                    @endif
                </div>
                <div class="invoice-status-bar__meta">
                    店舗はアプリ内マイページで請求書を参照します。メール添付は行いません。
                </div>
            </div>

            <div class="invoice-toolbar">
                <a href="{{ route('admin.deposits.invoice.pdf', ['deposit' => $invoice['deposit_id']]) }}" class="invoice-btn is-secondary" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf"></i> PDFでダウンロード
                </a>
                <button type="button" class="invoice-btn is-muted" onclick="window.print()">
                    <i class="fas fa-print"></i> 印刷
                </button>
                <a href="{{ route('admin.invoices.index') }}" class="invoice-btn is-muted">
                    <i class="fas fa-arrow-left"></i> 請求書発行画面へ
                </a>
                <a href="{{ route('admin.deposits.index') }}" class="invoice-btn is-muted">
                    <i class="fas fa-list"></i> 入金確認・振込へ
                </a>
            </div>
        @endif

        <div class="invoice-paper">
            @include('billing.invoice-body')
        </div>

        @if(!$printMode)
            {{-- Action panel: issue OR send OR resend based on state --}}
            @if($canIssue)
                <section class="invoice-action-panel" aria-label="請求書の発行">
                    <h2 class="invoice-action-panel__title"><i class="fas fa-pen-to-square"></i> この内容で請求書を発行する</h2>
                    <p class="invoice-action-panel__desc">
                        上の金額・宛先・振込先が正しいか確認してください。発行すると請求番号が採番されます。
                        <strong>この時点ではまだ店舗には通知されません。</strong>発行後に「店舗に送信する」でアプリ内通知を送ります。
                    </p>
                    <form method="POST" action="{{ route('admin.deposits.invoice.issue', ['deposit' => $invoice['deposit_id']]) }}" data-invoice-issue-form>
                        @csrf
                        <div class="invoice-action-panel__checks">
                            <label class="invoice-action-panel__check">
                                <input type="checkbox" name="confirm_shop_approved" value="1" data-check-item required>
                                <span>店舗承認済み・金額に誤りがないことを確認した</span>
                            </label>
                            <label class="invoice-action-panel__check">
                                <input type="checkbox" name="confirm_admin_bank_ready" value="1" data-check-item required>
                                <span>運営の振込先口座情報が正しいことを確認した</span>
                            </label>
                        </div>
                        <div class="invoice-action-panel__actions">
                            <button type="submit" class="invoice-btn is-primary" data-issue-submit disabled>
                                <i class="fas fa-file-invoice"></i> この内容で発行する
                            </button>
                        </div>
                    </form>
                </section>
            @elseif($canSend)
                <section class="invoice-action-panel" aria-label="請求書の送信">
                    <h2 class="invoice-action-panel__title"><i class="fas fa-paper-plane"></i> 店舗にアプリ内通知で送信する</h2>
                    <p class="invoice-action-panel__desc">
                        「送信」を押すと店舗マネージャー全員のアプリ内お知らせ（通知トレイ）に請求書が届きます。
                        各ユーザーの通知設定に応じて、追加で Push / LINE にも自動で配信されます（メール添付は行いません）。
                    </p>
                    <form method="POST" action="{{ route('admin.deposits.invoice.send', ['deposit' => $invoice['deposit_id']]) }}"
                          onsubmit="return confirm('この請求書を店舗マネージャー宛にアプリ内で通知します。よろしいですか？');">
                        @csrf
                        <div class="invoice-action-panel__actions">
                            <button type="submit" class="invoice-btn is-primary">
                                <i class="fas fa-paper-plane"></i> 店舗に送信する
                            </button>
                            <span class="invoice-status-bar__meta">通知先: 店舗マネージャー全員（アクティブアカウント）</span>
                        </div>
                    </form>
                </section>
            @elseif($isSent)
                <section class="invoice-action-panel" aria-label="請求書の再送">
                    <h2 class="invoice-action-panel__title"><i class="fas fa-circle-check"></i> 送信済み</h2>
                    <p class="invoice-action-panel__desc">
                        {{ $delivery['sent_at'] }} に店舗マネージャーへアプリ内通知を送信しました。
                        店舗は「店舗マイページ → 採用・入金管理」から請求書を参照できます。
                        通知が届いていない・追加で通知したい場合は再送してください。
                    </p>
                    <form method="POST" action="{{ route('admin.deposits.invoice.send', ['deposit' => $invoice['deposit_id']]) }}"
                          onsubmit="return confirm('もう一度アプリ内通知を送信します。よろしいですか？');">
                        @csrf
                        <div class="invoice-action-panel__actions">
                            <button type="submit" class="invoice-btn is-secondary">
                                <i class="fas fa-arrows-rotate"></i> もう一度送信する
                            </button>
                        </div>
                    </form>
                </section>
            @endif
        @endif
    </div>

    @if(!$printMode)
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-invoice-issue-form]').forEach(function (form) {
                var submit = form.querySelector('[data-issue-submit]');
                var checks = form.querySelectorAll('[data-check-item]');
                function sync() {
                    submit.disabled = Array.from(checks).some(function (c) { return !c.checked; });
                }
                checks.forEach(function (c) { c.addEventListener('change', sync); });
                sync();
            });
        });
        </script>
    @endif
</body>
</html>
