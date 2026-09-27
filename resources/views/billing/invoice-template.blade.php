{{--
  請求書帳票テンプレート（PDF生成用・完全HTML）
  運営がフォーマットに沿って発行し、店舗がダウンロードする際の1枚物。
--}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@if(!empty($invoice['template_only']))請求書 帳票テンプレート@else請求書 {{ $invoice['invoice_number'] }}@endif</title>
    <style>
        /* mPDF bundles sun-exta / sun-extb (CJK). autoLangToFont is enabled in
           App\Services\PdfService, so Japanese runs will auto-pick sun-exta even
           if font-family below is not resolved. */
        body { margin: 0; padding: 0; color: #111827; font-family: "sun-exta", sans-serif; font-size: 11pt; }
        .invoice-wrap { max-width: 210mm; margin: 0 auto; padding: 16mm; box-sizing: border-box; }
        .invoice-header { border-bottom: 2px solid #111827; padding-bottom: 12pt; margin-bottom: 16pt; }
        .invoice-title { font-size: 22pt; font-weight: 800; letter-spacing: 0.12em; margin: 0 0 8pt; }
        .invoice-meta { font-size: 9pt; color: #4b5563; line-height: 1.6; }
        .invoice-issuer { text-align: right; font-size: 9pt; color: #4b5563; margin-top: 4pt; }
        .invoice-to { margin-bottom: 16pt; }
        .invoice-to-name { font-size: 14pt; font-weight: 800; margin: 0 0 4pt; }
        .invoice-to-addr { font-size: 9pt; color: #4b5563; line-height: 1.5; }
        .invoice-total { margin: 14pt 0; padding: 12pt 14pt; background: #eff6ff; border-radius: 8pt; }
        .invoice-total-label { font-size: 9pt; color: #1d4ed8; font-weight: 700; margin-bottom: 4pt; }
        .invoice-total-value { font-size: 18pt; font-weight: 800; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 8pt; font-size: 10pt; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 10pt 12pt; text-align: left; vertical-align: top; }
        .invoice-table th { width: 26%; background: #f9fafb; color: #4b5563; font-weight: 700; }
        .invoice-bank { margin-top: 18pt; padding: 14pt 16pt; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8pt; }
        .invoice-bank-title { margin: 0 0 8pt; font-size: 12pt; font-weight: 800; }
        .invoice-bank-detail { font-size: 10pt; line-height: 1.7; color: #374151; }
    </style>
</head>
<body>
    @include('billing.invoice-body')
</body>
</html>
