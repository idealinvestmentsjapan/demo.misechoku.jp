<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Common\SettingController;
use App\Models\ShopPlanSubscription;
use App\Services\BillingManagementService;
use App\Services\PlanSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Premiumプラン入金管理（運営）。
 * ネットバンキングの入出金明細を目視で照合し、「入金確認済み」にすると
 * システムが Premium 機能を開放する。
 */
class PlanSubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanSubscriptionService $planService,
        private readonly BillingManagementService $billingManagementService,
    ) {
    }

    public function index()
    {
        $rows = collect();

        if (Schema::hasTable('shop_plan_subscriptions')) {
            $rows = ShopPlanSubscription::query()
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ShopPlanSubscription::STATUS_PENDING_PAYMENT])
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            $shopNames = DB::table('shop_profiles')
                ->whereIn('shop_id', $rows->pluck('shop_id')->unique()->values()->all())
                ->pluck('shop_name', 'shop_id');

            $rows->each(function (ShopPlanSubscription $sub) use ($shopNames) {
                $sub->setAttribute('shop_display_name', (string) ($shopNames[$sub->shop_id] ?? $sub->shop_id));
            });
        }

        return view('admin.plans.index', [
            'subscriptions' => $rows,
            'summary' => [
                'pending' => $rows->where('status', ShopPlanSubscription::STATUS_PENDING_PAYMENT)->count(),
                'active' => $rows->where('status', ShopPlanSubscription::STATUS_ACTIVE)->count(),
                'overdue' => $rows->filter(fn ($s) => (int) $s->status === ShopPlanSubscription::STATUS_PENDING_PAYMENT
                    && $s->payment_due_date !== null
                    && $s->payment_due_date->lt(today()))->count(),
            ],
        ]);
    }

    /** ③運営の入金確認（目視照合済み） → Premium有効化 */
    public function confirm(ShopPlanSubscription $subscription): RedirectResponse
    {
        if ((int) $subscription->status !== ShopPlanSubscription::STATUS_PENDING_PAYMENT) {
            return redirect()->route('admin.plans.index')->with('error', 'この契約は入金待ちではありません。');
        }

        $adminId = (string) (auth()->guard('admin')->id() ?? '');
        $sub = $this->planService->confirmPayment($subscription, $adminId);

        return redirect()->route('admin.plans.index')->with('status',
            "「{$sub->invoice_number}」を入金確認済みにしました。Premium機能が有効になりました（{$sub->ends_at?->format('Y/m/d')} まで）。");
    }

    public function downloadInvoice(ShopPlanSubscription $subscription)
    {
        return $this->documentResponse('invoice', $subscription);
    }

    public function downloadReceipt(ShopPlanSubscription $subscription)
    {
        if ($subscription->paid_confirmed_at === null) {
            return redirect()->route('admin.plans.index')->with('error', '入金確認前のため領収書は発行できません。');
        }
        return $this->documentResponse('receipt', $subscription);
    }

    private function documentResponse(string $type, ShopPlanSubscription $sub)
    {
        $doc = SettingController::buildPlanDocData($type, $sub, $this->billingManagementService);
        $view = $type === 'receipt' ? 'billing.plan-receipt' : 'billing.plan-invoice';
        $filename = ($type === 'receipt' ? '領収書_' : '請求書_') . $doc['number'] . '.pdf';

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return view($view, ['doc' => $doc, 'printMode' => true]);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, ['doc' => $doc, 'printMode' => false]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
