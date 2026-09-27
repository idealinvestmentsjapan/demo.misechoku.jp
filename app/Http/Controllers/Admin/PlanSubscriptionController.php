<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Common\SettingController;
use App\Models\ShopPlanSubscription;
use App\Services\BillingManagementService;
use App\Services\PdfService;
use App\Services\PlanSubscriptionService;
use Illuminate\Http\RedirectResponse;

/**
 * Premiumプラン入金関連のドキュメント出力（請求書 / 領収書 PDF）。
 *
 * 入金確認 UI は `admin.deposits.confirmations`（Admin\DepositController）に統合済み。
 * 旧 `admin.plans.confirm` POST は後方互換のため残しているが、遷移先は統合画面。
 */
class PlanSubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanSubscriptionService $planService,
        private readonly BillingManagementService $billingManagementService,
    ) {
    }

    /** 旧 POST エンドポイント。統合画面（admin.deposits.confirmations）へリダイレクトする。 */
    public function confirm(ShopPlanSubscription $subscription): RedirectResponse
    {
        if ((int) $subscription->status !== ShopPlanSubscription::STATUS_PENDING_PAYMENT) {
            return redirect()->route('admin.deposits.confirmations')->with('error', 'この契約は入金待ちではありません。');
        }

        $adminId = (string) (auth()->guard('admin')->id() ?? '');
        $sub = $this->planService->confirmPayment($subscription, $adminId);

        return redirect()->route('admin.deposits.confirmations')->with('status',
            "「{$sub->invoice_number}」を入金確認済みにしました。Premium機能が有効になりました（{$sub->ends_at?->format('Y/m/d')} まで）。");
    }

    public function downloadInvoice(ShopPlanSubscription $subscription)
    {
        return $this->documentResponse('invoice', $subscription);
    }

    public function downloadReceipt(ShopPlanSubscription $subscription)
    {
        if ($subscription->paid_confirmed_at === null) {
            return redirect()->route('admin.deposits.confirmations')->with('error', '入金確認前のため領収書は発行できません。');
        }
        return $this->documentResponse('receipt', $subscription);
    }

    private function documentResponse(string $type, ShopPlanSubscription $sub)
    {
        $doc = SettingController::buildPlanDocData($type, $sub, $this->billingManagementService);
        $view = $type === 'receipt' ? 'billing.plan-receipt' : 'billing.plan-invoice';
        $filename = ($type === 'receipt' ? '領収書_' : '請求書_') . $doc['number'] . '.pdf';

        if (!class_exists(\Mpdf\Mpdf::class)) {
            return view($view, ['doc' => $doc, 'printMode' => true]);
        }

        return PdfService::download($view, ['doc' => $doc, 'printMode' => false], $filename);
    }
}
