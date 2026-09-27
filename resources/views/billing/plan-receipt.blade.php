{{--
  Premiumプラン 領収書（PDF生成用・完全HTML）
  $doc: SettingController::buildPlanDocData() / $printMode: dompdf 未導入時 true
--}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>領収書 {{ $doc['number'] }}</title>
    <style>
        body { margin: 0; padding: 0; color: #111827; font-family: "ipaexg", "Noto Sans JP", "Hiragino Sans", "Meiryo", sans-serif; font-size: 11pt; }
        .doc-wrap { max-width: 210mm; margin: 0 auto; padding: 16mm; box-sizing: border-box; }
        .doc-header { border-bottom: 2px solid #111827; padding-bottom: 12pt; margin-bottom: 16pt; }
        .doc-title { font-size: 22pt; font-weight: 800; letter-spacing: 0.3em; margin: 0 0 8pt; }
        .doc-meta { font-size: 9pt; color: #4b5563; line-height: 1.6; }
        .doc-issuer { text-align: right; font-size: 9pt; color: #4b5563; margin-top: 4pt; }
        .doc-to-name { font-size: 14pt; font-weight: 800; margin: 0 0 4pt; }
        .doc-total { margin: 14pt 0; padding: 12pt 14pt; background: #ecfdf5; border-radius: 8pt; }
        .doc-total-label { font-size: 9pt; color: #047857; font-weight: 700; margin-bottom: 4pt; }
        .doc-total-value { font-size: 18pt; font-weight: 800; }
        .doc-table { width: 100%; border-collapse: collapse; margin-top: 8pt; font-size: 10pt; }
        .doc-table th, .doc-table td { border: 1px solid #e5e7eb; padding: 10pt 12pt; text-align: left; vertical-align: top; }
        .doc-table th { width: 30%; background: #f9fafb; color: #4b5563; font-weight: 700; }
        .doc-note { margin-top: 14pt; font-size: 9pt; color: #6b7280; line-height: 1.7; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #111827; color: #fff; padding: 10px 16px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .print-bar button { background: #10b981; color: #fff; border: 0; border-radius: 6px; padding: 8px 18px; font-weight: 700; cursor: pointer; }
        body.with-bar .doc-wrap { padding-top: 26mm; }
        @media print { .print-bar { display: none; } body.with-bar .doc-wrap { padding-top: 16mm; } }
    </style>
</head>
<body class="{{ !empty($printMode) ? 'with-bar' : '' }}">
    @if(!empty($printMode))
        <div class="print-bar">
            <span>ブラウザの印刷から「PDFに保存」を選択してください</span>
            <button type="button" onclick="window.print()">印刷 / PDF保存</button>
        </div>
    @endif
    <div class="doc-wrap">
        <div class="doc-header">
            <h1 class="doc-title">領収書</h1>
            <div class="doc-meta">
                領収書番号: {{ $doc['number'] }}<br>
                発行日: {{ optional($doc['issued_at'])->format('Y年n月j日') }}
            </div>
            <div class="doc-issuer">
                {{ $doc['issuer_name'] }}
                @if($doc['issuer_email'] !== '')<br>{{ $doc['issuer_email'] }}@endif
            </div>
        </div>

        <div class="doc-to">
            <p class="doc-to-name">{{ $doc['shop_name'] }} 御中</p>
        </div>

        <div class="doc-total">
            <div class="doc-total-label">領収金額（税込）</div>
            <div class="doc-total-value">¥{{ number_format($doc['amount']) }}</div>
        </div>

        <table class="doc-table">
            <tr><th>但し書き</th><td>{{ $doc['plan_label'] }} 利用料として</td></tr>
            @if($doc['period_label'] !== '')
                <tr><th>利用期間</th><td>{{ $doc['period_label'] }}</td></tr>
            @endif
            <tr><th>入金確認日</th><td>{{ optional($doc['paid_at'])->format('Y年n月j日') }}</td></tr>
            <tr><th>お支払い方法</th><td>銀行振込</td></tr>
        </table>

        <div class="doc-note">
            上記金額を正に領収いたしました。
            @if($doc['footer_text'] !== '')<br>{{ $doc['footer_text'] }}@endif
        </div>
    </div>
</body>
</html>
