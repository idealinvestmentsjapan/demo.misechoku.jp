{{-- 請求書プレビュー：印刷／修正／店舗へ送信 の3ボタンに集約 --}}
@php
    use App\Services\BillingManagementService as BMS;
    $delivery = $invoice['delivery_status'] ?? ['code' => 'pre_issue', 'label' => '未発行', 'sent_at' => null];
    $statusCode = (int) ($invoice['status_code'] ?? 0);
    // Preview is post-issue only. If someone lands here pre-issue (SHOP_APPROVED but not yet issued),
    // hide the send/edit actions and prompt the admin to go back to the issue list.
    $isIssued = $delivery['code'] !== 'pre_issue';
    $isSent = $delivery['code'] === 'sent';
    // Send button label pivots to "再送" on repeated dispatch, but the primary button still reads
    // "店舗へ送信" per spec; the confirm dialog text reflects the resend flavour.
    $sendConfirmMsg = $isSent
        ? 'もう一度アプリ内通知を送信します。よろしいですか？'
        : 'この請求書を店舗マネージャー宛にアプリ内で通知します。よろしいですか？';
    $editConfirmMsg = '請求書の内容を手動で修正しますか？（通常は自動計算値を使用してください）';
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
            padding: 12px 16px; margin-bottom: 12px; border-radius: 12px; background: #ffffff;
            border: 1px solid #e5e7eb; font-size: 13px;
        }
        .invoice-status-bar__label { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; }
        .invoice-status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
            border: 1px solid transparent;
        }
        .invoice-status-badge.is-pre { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .invoice-status-badge.is-unsent { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .invoice-status-badge.is-sent { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .invoice-status-bar__meta { font-size: 12px; color: #6b7280; }

        .invoice-toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .invoice-toolbar form { margin: 0; }
        .invoice-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 10px 16px; border-radius: 10px; border: 1px solid #d1d5db;
            font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer;
            color: #111827; background: #ffffff;
        }
        .invoice-btn:hover { background: #f9fafb; }
        .invoice-btn.is-primary {
            background: #4A122A; border-color: #4A122A; color: #ffffff;
            box-shadow: 0 6px 18px rgba(74, 18, 42, 0.25);
        }
        .invoice-btn.is-primary:hover { background: #3a0e21; }
        .invoice-flash {
            padding: 10px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 14px; font-weight: 600;
        }
        .invoice-flash.is-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .invoice-flash.is-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .invoice-preissue-warn {
            padding: 14px 16px; margin-bottom: 12px; border-radius: 10px;
            background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 13px;
        }
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
            .invoice-toolbar, .invoice-status-bar, .invoice-flash, .invoice-preissue-warn { display: none !important; }
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

            {{-- Info-only status bar (not a button) --}}
            <div class="invoice-status-bar">
                <div class="invoice-status-bar__label">
                    <i class="fas fa-file-invoice"></i> 送信ステータス
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
                    店舗はアプリ内マイページで請求書を参照します（メール添付は行いません）。
                </div>
            </div>

            @if(!$isIssued)
                <div class="invoice-preissue-warn">
                    <i class="fas fa-triangle-exclamation"></i>
                    この案件はまだ請求書が発行されていません。
                    <a href="{{ route('admin.invoices.index') }}" style="color:inherit; font-weight:700; text-decoration:underline;">請求書発行画面</a>
                    から「発行」を押してください。
                </div>
            @endif

            {{-- Action toolbar: 印刷 / 修正 / 店舗へ送信 の3ボタンのみ --}}
            <div class="invoice-toolbar">
                <button type="button" class="invoice-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> 印刷
                </button>

                @if($isIssued)
                    <a href="{{ route('admin.deposits.invoice.edit', ['deposit' => $invoice['deposit_id']]) }}"
                       class="invoice-btn"
                       onclick="return confirm('{{ $editConfirmMsg }}');">
                        <i class="fas fa-pen-to-square"></i> 修正
                    </a>

                    <form method="POST" action="{{ route('admin.deposits.invoice.send', ['deposit' => $invoice['deposit_id']]) }}"
                          onsubmit="return confirm('{{ $sendConfirmMsg }}');">
                        @csrf
                        <button type="submit" class="invoice-btn is-primary">
                            <i class="fas fa-paper-plane"></i> 店舗へ送信
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <div class="invoice-paper">
            @include('billing.invoice-body')
        </div>
    </div>
</body>
</html>
