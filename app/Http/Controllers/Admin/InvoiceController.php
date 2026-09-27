<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingManagementService;
use App\Services\InvoiceTemplateSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly InvoiceTemplateSettingsService $templateSettings
    ) {
    }

    /**
     * 請求書発行画面（発行待ち一覧・手動発行・帳票テンプレートDL・入金振込管理への導線）
     */
    public function index(): View
    {
        $dashboard = $this->billingManagementService->getAdminBillingDashboard();
        $pending = collect($dashboard['deposits'])
            ->whereIn('status_code', [
                BillingManagementService::STATUS_CAST_REQUESTED,
                BillingManagementService::STATUS_SHOP_APPROVED,
            ])
            ->sortBy(fn (array $d) => [$d['status_code'], $d['id']])
            ->values()
            ->all();
        $manualTargets = collect($dashboard['deposits'])->filter(fn ($d) => empty($d['invoice_number']))->values()->all();

        // 発行済み案件の送付状況一覧（新しい順、直近20件）
        $issued = collect($dashboard['deposits'])
            ->filter(fn ($d) => !empty($d['invoice_number']))
            ->sortByDesc(fn ($d) => $d['invoice_issued_at'] ?? '')
            ->take(20)
            ->values()
            ->all();
        $deliverySummary = [
            'sent' => 0,
            'issued_unsent' => 0,
        ];
        foreach ($issued as $d) {
            $code = $d['delivery_status']['code'] ?? '';
            if (isset($deliverySummary[$code])) {
                $deliverySummary[$code]++;
            }
        }

        $summary = $dashboard['summary'];
        $adminBank = $this->billingManagementService->getAdminBankAccount();

        return view('admin.invoices.index', [
            'pending' => $pending,
            'manualTargets' => $manualTargets,
            'issued' => $issued,
            'deliverySummary' => $deliverySummary,
            'summary' => $summary,
            'adminBank' => $adminBank,
        ]);
    }

    /**
     * 手動で請求書を発行（障害時等の回避策）
     */
    public function issueManual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => 'required|integer|min:1',
            'shop_name' => 'required|string|max:255',
            'shop_address' => 'nullable|string|max:500',
            'shop_email' => 'nullable|email|max:255',
            'cast_name' => 'required|string|max:255',
            'bonus_amount' => 'required|integer|min:0',
            'system_fee_amount' => 'required|integer|min:0',
            'invoice_amount' => 'required|integer|min:1',
            'cast_transfer_amount' => 'required|integer|min:0',
            'confirm_manual_workaround' => 'required|accepted',
            'confirm_admin_bank_ready' => 'required|accepted',
        ]);

        if ($validated['bonus_amount'] + $validated['system_fee_amount'] !== $validated['invoice_amount']) {
            return back()
                ->withInput()
                ->withErrors(['invoice_amount' => '請求金額合計は、ボーナス額と運営手数料の合計と一致させてください。']);
        }

        $result = $this->billingManagementService->issueInvoiceManually(
            (int) $validated['deposit_id'],
            $validated
        );

        if (!$result['success']) {
            return redirect()
                ->route('admin.invoices.index')
                ->with('error', $result['message']);
        }

        // 手動発行後もプレビュー画面へ遷移して「送信」を促す（メール自動送付は廃止）
        return redirect()
            ->route('admin.deposits.invoice.show', ['deposit' => (int) $validated['deposit_id']])
            ->with('status', $result['message']);
    }

    /**
     * 請求書テンプレート設定画面
     */
    public function templateSettings(): View
    {
        $settings = $this->templateSettings->getForInvoice();

        return view('admin.invoices.template-settings', [
            'issuer_name' => $settings['issuer_name'],
            'issuer_email' => $settings['issuer_email'],
            'logo_url' => $settings['logo_url'],
            'footer_text' => $settings['footer_text'],
        ]);
    }

    /**
     * 請求書テンプレート設定の保存
     */
    public function updateTemplateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'issuer_name' => 'nullable|string|max:255',
            'issuer_email' => 'nullable|email|max:255',
            'logo_url' => 'nullable|url|max:500',
            'footer_text' => 'nullable|string|max:2000',
        ]);

        $this->templateSettings->saveFromRequest($request->only([
            'issuer_name', 'issuer_email', 'logo_url', 'footer_text',
        ]));

        return redirect()
            ->route('admin.invoices.template-settings')
            ->with('status', '請求書テンプレートの設定を保存しました。');
    }
}
