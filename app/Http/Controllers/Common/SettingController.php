<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\CastProvider;
use App\Models\ShopPlanSubscription;
use App\Services\BillingManagementService;
use App\Services\InvoiceTemplateSettingsService;
use App\Services\NotificationPreferenceService;
use App\Services\PlanSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferenceService)
    {
    }

    /**
     * 通知設定（キャスト／店舗共通）
     */
    public function notification()
    {
        $isCast = request()->is('cast/*');

        [$actorType, $actorId] = $this->resolveActor();

        $defaultPrefs = [
            'push_enabled' => true,
            'line_enabled' => true,
            'interview_reminder_enabled' => true,
            'deadline_reminder_enabled' => true,
        ];
        foreach (NotificationPreferenceService::CATEGORY_COLUMNS as $column) {
            $defaultPrefs[$column] = true;
        }

        $prefs = ($actorType && $actorId)
            ? $this->preferenceService->get($actorType, $actorId)
            : $defaultPrefs;

        return view('common.setting.notification', [
            'isCast' => $isCast,
            'isLoggedIn' => auth()->guard('member')->check() || auth()->guard('shop')->check(),
            'notificationPrefs' => $prefs,
        ]);
    }

    /**
     * 通知設定を保存
     */
    public function updateNotification(Request $request)
    {
        [$actorType, $actorId] = $this->resolveActor();
        if (!$actorType || !$actorId) {
            return redirect()->route('setting.notification')->withErrors(['ログイン後に設定してください。']);
        }

        $prefs = [
            'push_enabled' => $request->boolean('push_enabled'),
            'line_enabled' => $request->boolean('line_enabled'),
            'interview_reminder_enabled' => $request->boolean('interview_reminder_enabled'),
            'deadline_reminder_enabled' => $request->boolean('deadline_reminder_enabled'),
        ];
        foreach (NotificationPreferenceService::CATEGORY_COLUMNS as $column) {
            // Checkboxes not rendered for a role (e.g. review for casts) stay enabled.
            $prefs[$column] = $request->has($column) ? $request->boolean($column) : true;
        }

        $this->preferenceService->save($actorType, $actorId, $prefs);

        return redirect()->route('setting.notification')->with('message', '通知設定を更新しました。');
    }

    public function account()
    {
        $isCast = request()->is('cast/*');
        $lineLinked = false;
        $lineLinkUrl = route('setting.line.link');
        $currentEmail = '';

        if (auth()->guard('member')->check()) {
            $user = auth()->guard('member')->user();
            $currentEmail = (string) ($user->email ?? '');
            $lineLinked = CastProvider::query()
                ->where('cast_id', $user->getAuthIdentifier())
                ->where('provider', 'line')
                ->exists();
        } elseif (auth()->guard('shop')->check()) {
            $user = auth()->guard('shop')->user();
            $currentEmail = (string) ($user->email ?? '');
            $lineLinked = !empty($user->line_user_id);
        }

        return view('common.setting.account', compact('isCast', 'lineLinked', 'lineLinkUrl', 'currentEmail'));
    }

    /**
     * メールアドレス変更
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        [$actorType, $actorId] = $this->resolveActor();
        if (!$actorType || !$actorId) {
            return redirect()->route('setting.account')->withErrors(['ログイン後に設定してください。']);
        }

        [$table, $idColumn] = $this->resolveAccountTable($actorType);
        if (!$table) {
            return redirect()->route('setting.account')->withErrors(['対象アカウントが見つかりません。']);
        }

        $data = $request->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique($table, 'email')->ignore($actorId, $idColumn)],
            'current_password' => ['required', 'string'],
        ], [
            'new_email.unique' => 'このメールアドレスは既に登録されています。',
        ]);

        $row = DB::table($table)->where($idColumn, $actorId)->first();
        if (!$row || !Hash::check($data['current_password'], (string) $row->password)) {
            return back()->withErrors(['current_password' => '現在のパスワードが正しくありません。'])->withInput();
        }

        DB::table($table)
            ->where($idColumn, $actorId)
            ->update(['email' => $data['new_email'], 'updated_at' => now()]);

        return redirect()->route('setting.account')->with('message', 'メールアドレスを更新しました。');
    }

    /**
     * パスワード変更
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        [$actorType, $actorId] = $this->resolveActor();
        if (!$actorType || !$actorId) {
            return redirect()->route('setting.account')->withErrors(['ログイン後に設定してください。']);
        }

        [$table, $idColumn] = $this->resolveAccountTable($actorType);
        if (!$table) {
            return redirect()->route('setting.account')->withErrors(['対象アカウントが見つかりません。']);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.confirmed' => '新しいパスワード（確認用）が一致しません。',
            'new_password.min' => 'パスワードは8文字以上で入力してください。',
        ]);

        $row = DB::table($table)->where($idColumn, $actorId)->first();
        if (!$row || !Hash::check($data['current_password'], (string) $row->password)) {
            return back()->withErrors(['current_password' => '現在のパスワードが正しくありません。'])->withInput();
        }

        if (Hash::check($data['new_password'], (string) $row->password)) {
            return back()->withErrors(['new_password' => '新しいパスワードは現在のパスワードと異なるものを入力してください。'])->withInput();
        }

        DB::table($table)
            ->where($idColumn, $actorId)
            ->update([
                'password' => Hash::make($data['new_password']),
                'updated_at' => now(),
            ]);

        return redirect()->route('setting.account')->with('message', 'パスワードを更新しました。');
    }

    /**
     * LINE 連携を解除
     */
    public function unlinkLine(Request $request): RedirectResponse
    {
        [$actorType, $actorId] = $this->resolveActor();
        if (!$actorType || !$actorId) {
            return redirect()->route('setting.account')->withErrors(['ログイン後に設定してください。']);
        }

        if ($actorType === 'cast') {
            CastProvider::query()
                ->where('cast_id', $actorId)
                ->where('provider', 'line')
                ->delete();
        } elseif ($actorType === 'shop_manager') {
            if (Schema::hasColumn('shop_managers', 'line_user_id')) {
                DB::table('shop_managers')
                    ->where('id', $actorId)
                    ->update(['line_user_id' => null, 'updated_at' => now()]);
            }
        }

        return redirect()->route('setting.account')->with('message', 'LINE連携を解除しました。');
    }

    /**
     * 退会（ソフトデリート）
     */
    public function withdraw(Request $request): RedirectResponse
    {
        [$actorType, $actorId] = $this->resolveActor();
        if (!$actorType || !$actorId) {
            return redirect()->route('setting.account')->withErrors(['ログイン後に設定してください。']);
        }

        $request->validate([
            'agreement' => ['accepted'],
            'current_password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'agreement.accepted' => '退会内容に同意のチェックを入れてください。',
        ]);

        [$table, $idColumn] = $this->resolveAccountTable($actorType);
        if (!$table) {
            return redirect()->route('setting.account')->withErrors(['対象アカウントが見つかりません。']);
        }

        $row = DB::table($table)->where($idColumn, $actorId)->first();
        if (!$row || !Hash::check($request->input('current_password'), (string) $row->password)) {
            return back()->withErrors(['current_password' => '現在のパスワードが正しくありません。'])->withInput();
        }

        if (!Schema::hasColumn($table, 'deleted_at')) {
            return back()->withErrors(['退会処理が現在ご利用いただけません。お手数ですが運営までお問い合わせください。']);
        }

        // 店舗マネージャの場合、最後のオーナーは退会不可（先に別オーナーを立てる必要あり）
        if ($actorType === 'shop') {
            $me = \App\Models\ShopManager::find($actorId);
            if ($me && (int) $me->role === \App\Models\ShopManager::ROLE_OWNER) {
                $otherOwners = \App\Models\ShopManager::query()
                    ->where('shop_id', $me->shop_id)
                    ->where('id', '!=', $me->id)
                    ->where('role', \App\Models\ShopManager::ROLE_OWNER)
                    ->where('status', \App\Models\ShopManager::STATUS_ACTIVE)
                    ->count();
                if ($otherOwners === 0) {
                    return back()->withErrors([
                        'agreement' => 'あなたは店舗の最後のオーナーです。先に別のスタッフをオーナーに引き継いでからでないと退会できません。詳しくはサポートまでお問い合わせください。',
                    ])->withInput();
                }
            }
        }

        // 匿名化 + ソフト削除。email はクリアして再登録可能に。
        // 過去の応募・入金・トークとの参照整合性のため PK は残す。
        $anonymizeUpdate = [
            'deleted_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn($table, 'email')) {
            // ユニーク制約のあるカラムは NULL 化（NULL は複数許容）
            $anonymizeUpdate['email'] = null;
        }
        if (Schema::hasColumn($table, 'password')) {
            $anonymizeUpdate['password'] = null;
        }
        if (Schema::hasColumn($table, 'remember_token')) {
            $anonymizeUpdate['remember_token'] = null;
        }
        if (Schema::hasColumn($table, 'line_user_id')) {
            $anonymizeUpdate['line_user_id'] = null;
        }
        if (Schema::hasColumn($table, 'name')) {
            $anonymizeUpdate['name'] = null;
        }

        DB::transaction(function () use ($actorType, $actorId, $table, $idColumn, $anonymizeUpdate) {
            DB::table($table)
                ->where($idColumn, $actorId)
                ->update($anonymizeUpdate);

            // 付随プロフィールの PII を匿名化
            if ($actorType === 'cast') {
                if (Schema::hasTable('cast_profiles')) {
                    DB::table('cast_profiles')
                        ->where('cast_id', $actorId)
                        ->update([
                            'nickname'   => '退会したユーザー',
                            'name'       => null,
                            'name_kana'  => null,
                            'tel'        => null,
                            'zip'        => null,
                            'addr'       => null,
                            'building'   => null,
                            'pr'         => null,
                            'latitude'   => null,
                            'longitude'  => null,
                            'available_until' => null,
                            'updated_at' => now(),
                        ]);
                }
                // 本人確認書類はコンプライアンス上、明示削除ではなく参照不能化のみ
                if (Schema::hasTable('cast_images')) {
                    DB::table('cast_images')->where('cast_id', $actorId)->delete();
                }
                if (Schema::hasTable('cast_posts')) {
                    DB::table('cast_posts')->where('cast_id', $actorId)->delete();
                }
            }
            // shop_manager 単位の匿名化のみ（shop 全体の匿名化はここでは行わない）
        });

        // ログアウト
        if ($actorType === 'cast') {
            Auth::guard('member')->logout();
        } else {
            Auth::guard('shop')->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginRoute = $actorType === 'cast' ? 'cast.login' : 'shop.login';
        return redirect()->route($loginRoute)->with('message', '退会手続きが完了しました。ご利用ありがとうございました。');
    }

    /* ============================================================
       プラン（Premium）
       契約 → 振込案内 → 運営の入金確認で有効化。請求書/領収書DL対応。
       ============================================================ */

    public function subscription(PlanSubscriptionService $planService, BillingManagementService $billing)
    {
        $shopId = $this->currentShopId();

        $activeSub = $shopId ? $planService->activeFor($shopId) : null;
        $pendingSub = $shopId ? $planService->pendingFor($shopId) : null;

        return view('common.setting.subscription', [
            'isShop' => $shopId !== null,
            'activeSub' => $activeSub,
            'pendingSub' => $pendingSub,
            'prices' => PlanSubscriptionService::PRICES,
            'scoutLimitFree' => PlanSubscriptionService::SCOUT_LIMIT_FREE,
            'scoutLimitPremium' => PlanSubscriptionService::SCOUT_LIMIT_PREMIUM,
            'adminBank' => $pendingSub ? $billing->getAdminBankAccount() : null,
        ]);
    }

    /** ①プラン契約：入金待ちレコード作成 + 振込先/金額/期限を通知（メール/画面） */
    public function contractPlan(Request $request, PlanSubscriptionService $planService, BillingManagementService $billing): RedirectResponse
    {
        $shopId = $this->currentShopId();
        if ($shopId === null) {
            return redirect()->route('subscription')->withErrors(['店舗アカウントでログインしてください。']);
        }
        // Premium 契約はお金の操作 → オーナー専用
        $this->assertShopOwner();

        $data = $request->validate([
            'billing_cycle' => ['required', Rule::in([ShopPlanSubscription::CYCLE_MONTHLY, ShopPlanSubscription::CYCLE_YEARLY])],
        ]);

        $sub = $planService->contract($shopId, $data['billing_cycle']);

        // メール通知（デモ環境でメール未設定でも画面遷移は止めない）
        try {
            $email = (string) (auth()->guard('shop')->user()->email ?? '');
            $bank = $billing->getAdminBankAccount();
            if ($email !== '' && $bank !== null) {
                $body = "ミセチョク Premiumプランのお申し込みを受け付けました。\n\n"
                    . "請求書番号: {$sub->invoice_number}\n"
                    . 'お支払い金額: ¥' . number_format((int) $sub->amount) . "（{$sub->cycleLabel()}）\n"
                    . '振込期限: ' . optional($sub->payment_due_date)->format('Y年n月j日') . "\n\n"
                    . "【お振込先（プラン専用口座）】\n"
                    . "{$bank->bank_name} {$bank->branch_name}\n"
                    . "口座番号: {$bank->account_number}\n"
                    . "口座名義: {$bank->account_name}\n\n"
                    . "運営にて入金を確認でき次第、Premium機能が有効になります。\n"
                    . '請求書はプラン設定画面からダウンロードできます。';
                Mail::raw($body, function ($m) use ($email) {
                    $m->to($email)->subject('【ミセチョク】Premiumプラン お振込のご案内');
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Premium plan mail failed: ' . $e->getMessage());
        }

        return redirect()->route('subscription')
            ->with('message', 'Premiumプランのお申し込みを受け付けました。振込先・金額・期限をご確認のうえお振込ください。入金確認後に機能が有効になります。');
    }

    /** 入金前のキャンセル */
    public function cancelPlanContract(PlanSubscriptionService $planService): RedirectResponse
    {
        $shopId = $this->currentShopId();
        if ($shopId !== null) {
            // Premium キャンセルもお金の操作 → オーナー専用
            $this->assertShopOwner();
        }
        $pending = $shopId ? $planService->pendingFor($shopId) : null;
        if ($pending) {
            $planService->cancelPending($pending);
            return redirect()->route('subscription')->with('message', 'お申し込みをキャンセルしました。');
        }
        return redirect()->route('subscription');
    }

    /** 請求書ダウンロード（契約後いつでも） */
    public function downloadPlanInvoice(PlanSubscriptionService $planService, BillingManagementService $billing)
    {
        $shopId = $this->currentShopId();
        $sub = $shopId ? ($planService->pendingFor($shopId) ?? $planService->activeFor($shopId)) : null;
        if ($sub === null) {
            return redirect()->route('subscription')->withErrors(['発行できる請求書がありません。']);
        }

        return $this->planDocumentResponse('invoice', $sub, $billing);
    }

    /** 領収書ダウンロード（入金確認後） */
    public function downloadPlanReceipt(PlanSubscriptionService $planService, BillingManagementService $billing)
    {
        $shopId = $this->currentShopId();
        $sub = null;
        if ($shopId !== null && Schema::hasTable('shop_plan_subscriptions')) {
            $sub = ShopPlanSubscription::query()
                ->where('shop_id', $shopId)
                ->whereNotNull('paid_confirmed_at')
                ->orderByDesc('paid_confirmed_at')
                ->first();
        }
        if ($sub === null) {
            return redirect()->route('subscription')->withErrors(['入金確認後に領収書を発行できます。']);
        }

        return $this->planDocumentResponse('receipt', $sub, $billing);
    }

    /**
     * プラン請求書/領収書のPDFレスポンス（dompdf未導入時は印刷用HTML）。
     * 管理画面からも同じビュー・データ構造で発行する。
     */
    public static function buildPlanDocData(string $type, ShopPlanSubscription $sub, BillingManagementService $billing): array
    {
        $profile = DB::table('shop_profiles')->where('shop_id', $sub->shop_id)->first();
        $shop = DB::table('shops')->where('id', $sub->shop_id)->first();
        $template = app(InvoiceTemplateSettingsService::class)->getForInvoice();
        $bank = $billing->getAdminBankAccount();

        $address = trim(implode(' ', array_filter([
            $profile->zip ?? null ? '〒' . $profile->zip : null,
            $profile->pref ?? null,
            $profile->city ?? null,
            $profile->addr ?? null,
            $profile->building ?? null,
        ])));

        return [
            'type' => $type,
            'number' => $type === 'receipt' ? (string) $sub->receipt_number : (string) $sub->invoice_number,
            'issued_at' => $type === 'receipt' ? $sub->paid_confirmed_at : $sub->invoice_issued_at,
            'due_date' => $sub->payment_due_date,
            'shop_name' => (string) ($profile->shop_name ?? $sub->shop_id),
            'shop_email' => (string) ($shop->email ?? ''),
            'shop_address' => $address,
            'plan_label' => 'Premiumプラン（' . $sub->cycleLabel() . '）',
            'period_label' => ($sub->starts_at && $sub->ends_at)
                ? $sub->starts_at->format('Y/m/d') . ' 〜 ' . $sub->ends_at->format('Y/m/d')
                : '',
            'amount' => (int) $sub->amount,
            'paid_at' => $sub->paid_confirmed_at,
            'issuer_name' => (string) ($template['issuer_name'] ?? 'ミセチョク運営事務局'),
            'issuer_email' => (string) ($template['issuer_email'] ?? ''),
            'footer_text' => (string) ($template['footer_text'] ?? ''),
            'admin_bank' => $bank ? [
                'bank_name' => $bank->bank_name,
                'branch_name' => $bank->branch_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
            ] : null,
        ];
    }

    private function planDocumentResponse(string $type, ShopPlanSubscription $sub, BillingManagementService $billing)
    {
        $doc = self::buildPlanDocData($type, $sub, $billing);
        $view = $type === 'receipt' ? 'billing.plan-receipt' : 'billing.plan-invoice';
        $filename = ($type === 'receipt' ? '領収書_' : '請求書_') . $doc['number'] . '.pdf';

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            // 印刷用HTML（ブラウザの印刷 → PDF保存）
            return view($view, ['doc' => $doc, 'printMode' => true]);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, ['doc' => $doc, 'printMode' => false]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function currentShopId(): ?string
    {
        $manager = auth()->guard('shop')->user();
        return ($manager && !empty($manager->shop_id)) ? (string) $manager->shop_id : null;
    }

    /**
     * 店舗ログインアカウントがオーナー(role=1)であることを保証する。
     * Premium 契約・解約等のお金操作で使用。
     */
    private function assertShopOwner(): void
    {
        $manager = auth()->guard('shop')->user();
        if (!$manager || (int) $manager->role !== \App\Models\ShopManager::ROLE_OWNER) {
            abort(403, 'この操作にはオーナー権限が必要です。');
        }
    }

    private function resolveActor(): array
    {
        if (auth()->guard('member')->check()) {
            return ['cast', (string) auth()->guard('member')->id()];
        }

        if (auth()->guard('shop')->check()) {
            return ['shop_manager', (string) auth()->guard('shop')->id()];
        }

        return [null, null];
    }

    /**
     * アクター種別から、アカウントテーブルと主キーカラムを返す。
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveAccountTable(string $actorType): array
    {
        if ($actorType === 'cast') {
            return ['casts', 'id'];
        }
        if ($actorType === 'shop_manager') {
            return ['shop_managers', 'id'];
        }
        return [null, null];
    }
}
