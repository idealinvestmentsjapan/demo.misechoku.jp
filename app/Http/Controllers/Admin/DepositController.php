<?php

namespace App\Http\Controllers\Admin;

use App\Services\BillingManagementService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class DepositController extends Controller
{
    public function __construct(private readonly BillingManagementService $billingManagementService)
    {
    }

    /**
     * 入金・振込管理一覧
     */
    public function index()
    {
        $dashboard = $this->billingManagementService->getAdminBillingDashboard();
        $exclude = [
            BillingManagementService::STATUS_CAST_REQUESTED,
            BillingManagementService::STATUS_SHOP_APPROVED,
        ];
        $deposits = collect($dashboard['deposits'])
            ->reject(fn (array $d) => in_array($d['status_code'], $exclude, true))
            ->values()
            ->all();

        $sevenDaysAgo = Carbon::now()->subDays(7);
        $unconfirmedOver7 = collect($deposits)->filter(function (array $d) use ($sevenDaysAgo) {
            if ($d['status_code'] !== BillingManagementService::STATUS_CAST_TRANSFERRED) {
                return false;
            }
            $transferredAt = $d['cast_transferred_at'] ?? null;
            if (!$transferredAt) {
                return false;
            }

            return Carbon::parse($transferredAt)->lt($sevenDaysAgo);
        })->count();

        $ds = collect($deposits);
        $summary = [
            'payment_confirmation_pending' => $ds->where('status_code', BillingManagementService::STATUS_SHOP_PAYMENT_REPORTED)->count(),
            'cast_transfer_pending' => $ds->where('status_code', BillingManagementService::STATUS_SHOP_PAYMENT_CONFIRMED)->count(),
            'invoice_total' => $ds->sum('invoice_amount'),
            'unconfirmed_cast_over_7days' => $unconfirmedOver7,
        ];

        return view('admin.deposit.index', [
            'deposits' => $deposits,
            'summary' => $summary,
            'adminBank' => $this->billingManagementService->getAdminBankAccount(),
        ]);
    }

    /**
     * 運営側：店舗への請求書発行
     */
    public function issueInvoice(Request $request, int $deposit)
    {
        $payload = $request->validate([
            'confirm_shop_approved' => 'required|accepted',
            'confirm_admin_bank_ready' => 'required|accepted',
        ]);

        $result = $this->billingManagementService->issueInvoice($deposit, $payload);

        return redirect()
            ->route('admin.invoices.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 運営側：発行済み請求書のメール再送（送付失敗リカバリー）
     */
    public function resendInvoiceMail(Request $request, int $deposit)
    {
        $result = $this->billingManagementService->resendInvoiceMail($deposit);

        $redirect = $request->input('return_to') === 'deposits'
            ? redirect()->route('admin.deposits.index')
            : redirect()->route('admin.invoices.index');

        return $redirect->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 運営側：店舗からの入金照合
     */
    public function confirmShopPayment(Request $request, int $deposit)
    {
        $payload = $request->validate([
            'confirmed_amount' => 'required|integer|min:1',
            'confirm_amount_checked' => 'required|accepted',
            'confirm_report_checked' => 'required|accepted',
            'confirm_bank_checked' => 'required|accepted',
        ]);

        $result = $this->billingManagementService->confirmShopPayment($deposit, $payload);

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 振込作業開始（支払準備中 → 振込中、他担当ロック）
     */
    public function transferStart(Request $request, int $deposit)
    {
        $operatorId = (string) (auth()->guard('admin')->id() ?? '');
        $result = $this->billingManagementService->startTransfer($deposit, $operatorId ?: null);

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 振込完了（証跡画像・チェックリスト・振込作業完了日必須。支払済は不可逆）
     */
    public function transferComplete(Request $request, int $deposit)
    {
        $payload = $request->validate([
            'transferred_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'evidence_screenshot' => 'required|file|image|max:10240',
            'checklist_confirmed_account' => 'required|accepted',
            'checklist_confirmed_amount' => 'required|accepted',
        ], [
            'evidence_screenshot.required' => '振込完了画面のスクリーンショットをアップロードしてください。',
            'evidence_screenshot.image' => '画像ファイル（JPEG/PNG等）を指定してください。',
        ]);

        $file = $request->file('evidence_screenshot');
        $path = $file->store('payment_evidence', 'public');

        $result = $this->billingManagementService->completeTransfer($deposit, [
            'transferred_at' => $payload['transferred_at'],
            'reference' => $payload['reference'] ?? null,
            'note' => $payload['note'] ?? null,
            'checklist_confirmed_account' => $payload['checklist_confirmed_account'],
            'checklist_confirmed_amount' => $payload['checklist_confirmed_amount'],
        ], $path);

        if (!$result['success']) {
            Storage::disk('public')->delete($path);
        }

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 振込タスクを無効化（組戻し・口座誤り時。口座修正後は別タスクで再発行）
     */
    public function paymentTaskInvalidate(Request $request, int $deposit)
    {
        $result = $this->billingManagementService->invalidatePaymentTask($deposit);

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 要返金フラグを立てる（支払後にレビュー不正等が判明した場合）
     */
    public function paymentTaskRefundFlag(Request $request, int $deposit)
    {
        $result = $this->billingManagementService->setPaymentTaskRefundRequired($deposit);

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 運営側：キャストへの振込実行（PaymentTask 未使用時の従来フロー）。
     * AD-109（transferComplete）と同じく証跡画像を必須にする。
     */
    public function transferCast(Request $request, int $deposit)
    {
        $payload = $request->validate([
            'transferred_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'evidence_screenshot' => 'required|file|image|max:10240',
            'confirm_transfer_amount' => 'required|accepted',
            'confirm_account_name' => 'required|accepted',
            'confirm_transfer_executed' => 'required|accepted',
            'confirm_receipt_checked' => 'required|accepted',
        ], [
            'evidence_screenshot.required' => '振込完了画面のスクリーンショットをアップロードしてください。',
            'evidence_screenshot.image' => '画像ファイル（JPEG/PNG等）を指定してください。',
        ]);

        $path = $request->file('evidence_screenshot')->store('payment_evidence', 'public');

        $result = $this->billingManagementService->executeCastTransfer($deposit, $payload, $path);

        if (!$result['success']) {
            Storage::disk('public')->delete($path);
        }

        return redirect()
            ->route('admin.deposits.index')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 管理画面から請求書プレビューを表示
     */
    public function showInvoice(int $deposit)
    {
        $invoice = $this->billingManagementService->getInvoiceData($deposit);

        abort_unless($invoice, 404);

        return view('admin.deposit.invoice', [
            'invoice' => $invoice,
            'printMode' => false,
        ]);
    }

    /**
     * 店舗へ送付する署名付き請求書ビュー（HTML）
     */
    public function showSignedInvoice(int $deposit)
    {
        $invoice = $this->billingManagementService->getInvoiceData($deposit);

        abort_unless($invoice, 404);

        return view('admin.deposit.invoice', [
            'invoice' => $invoice,
            'printMode' => true,
        ]);
    }

    /**
     * 管理画面：請求書をPDFでダウンロード（帳票テンプレート使用）
     */
    public function downloadInvoicePdf(int $deposit): Response
    {
        $invoice = $this->billingManagementService->getInvoiceData($deposit);

        abort_unless($invoice, 404);

        return $this->invoiceToPdfResponse($invoice, '請求書_' . Str::slug($invoice['invoice_number']) . '.pdf');
    }

    /**
     * 店舗向け：署名付きURLで請求書をPDFダウンロード
     * Dompdf 未導入時はHTMLを表示し、ブラウザの印刷でPDF保存を案内する。
     */
    public function showSignedInvoicePdf(int $deposit)
    {
        $invoice = $this->billingManagementService->getInvoiceData($deposit);

        abort_unless($invoice, 404);

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return view('admin.deposit.invoice', [
                'invoice' => $invoice,
                'printMode' => true,
            ])->with('status', 'PDFは「印刷」→「PDFに保存」でダウンロードできます。');
        }

        $filename = '請求書_' . Str::slug($invoice['invoice_number']) . '.pdf';

        return $this->invoiceToPdfResponse($invoice, $filename);
    }

    /**
     * 請求書帳票テンプレートをサンプルデータでPDFダウンロード（運営管理画面用）
     * DomPDF 未導入時は印刷用HTMLプレビューを返し、別タブで同じ画面が開く問題を避ける。
     */
    public function downloadInvoiceTemplate(): Response|\Illuminate\Contracts\View\View
    {
        $invoice = $this->billingManagementService->getInvoiceTemplateShellData();

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return view('admin.deposit.invoice-template-preview', ['invoice' => $invoice]);
        }

        return $this->invoiceToPdfResponse($invoice, '請求書_帳票テンプレート.pdf');
    }

    /**
     * 請求書データを帳票テンプレートでPDF化してレスポンスを返す。
     * barryvdh/laravel-dompdf 未導入 or 日本語フォント未設置時は印刷用HTMLへ誘導。
     */
    private function invoiceToPdfResponse(array $invoice, string $filename): Response
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            if ($invoice['deposit_id'] > 0) {
                return redirect()
                    ->route('admin.deposits.invoice.show', ['deposit' => $invoice['deposit_id']])
                    ->with('status', 'PDF生成には barryvdh/laravel-dompdf のインストールが必要です。画面の「印刷」から「PDFに保存」を選択してください。');
            }
            return redirect()
                ->route('admin.invoices.index')
                ->with('status', 'PDF生成には barryvdh/laravel-dompdf のインストールが必要です。テンプレートは「帳票テンプレートをダウンロード」で開いた画面の印刷からPDFに保存できます。');
        }

        // Japanese font must exist or dompdf will render JP text as garbled boxes.
        // The pdf:install-japanese-font command drops ipaexg.ttf into storage/fonts.
        $fontPath = storage_path('fonts/ipaexg.ttf');
        if (!is_file($fontPath)) {
            $msg = 'PDFの日本語フォントが未インストールのため、印刷プレビュー表示に切り替えました。'
                . 'サーバ側で `php artisan pdf:install-japanese-font` を一度実行するとPDFダウンロードが有効化されます。'
                . '（当面は「印刷 / 別名でPDF保存」ボタンでダウンロード可能です）';
            if ($invoice['deposit_id'] > 0) {
                return redirect()
                    ->route('admin.deposits.invoice.show', ['deposit' => $invoice['deposit_id']])
                    ->with('status', $msg);
            }
            return redirect()
                ->route('admin.invoices.index')
                ->with('status', $msg);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('billing.invoice-template', ['invoice' => $invoice]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}

