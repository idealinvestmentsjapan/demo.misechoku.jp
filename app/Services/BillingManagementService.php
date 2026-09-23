<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Message;
use App\Support\ShopJobApplicationView;
use App\Models\PaymentTask;
use App\Models\SystemAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class BillingManagementService
{
    public const STATUS_CAST_REQUESTED = 1;
    public const STATUS_SHOP_APPROVED = 2;
    public const STATUS_INVOICE_ISSUED = 3;
    public const STATUS_SHOP_PAYMENT_REPORTED = 4;
    public const STATUS_SHOP_PAYMENT_CONFIRMED = 5;
    public const STATUS_CAST_TRANSFERRED = 6;
    public const STATUS_COMPLETED = 7;

    private const SYSTEM_FEE_RATE = 0.10;
    private const INVOICE_DUE_DAYS = 7;
    /** 銀行振込手数料（円）。マスタ化する場合はここを参照にせずマスタから取得すること */
    private const BANK_FEE_AMOUNT = 220;

    /**
     * Help hires have no hiring bonus. Instead the shop is invoiced 135% of
     * one help hourly wage, the cast receives 50% back, and the platform
     * keeps the remaining 85%. The 10% system fee is NOT applied on top.
     */
    public const HELP_INVOICE_RATE = 1.35;
    public const HELP_CAST_BACK_RATE = 0.50;

    /** Card titles for auto-sent talk messages on deposit status transitions */
    private const BILLING_TALK_TITLES = [
        self::STATUS_CAST_REQUESTED => '入金申請が届きました',
        self::STATUS_SHOP_APPROVED => '入金申請が承認されました',
        self::STATUS_INVOICE_ISSUED => '請求書が発行されました',
        self::STATUS_SHOP_PAYMENT_CONFIRMED => '店舗入金を確認しました',
        self::STATUS_CAST_TRANSFERRED => '採用ボーナスを振込みました',
        self::STATUS_COMPLETED => 'お手続きが完了しました',
    ];

    public function __construct(private readonly BankLookupService $bankLookupService)
    {
    }

    public function normalizeBankAccountData(array $data): array
    {
        $bankCode = preg_replace('/\D+/', '', (string) ($data['bank_code'] ?? '')) ?? '';
        $branchCode = preg_replace('/\D+/', '', (string) ($data['branch_code'] ?? '')) ?? '';
        $bank = $this->bankLookupService->findBankByCode($bankCode);
        $branch = $this->bankLookupService->findBranchByCode($bankCode, $branchCode);
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountType = trim((string) ($data['account_type'] ?? 'ordinary'));

        if ($accountType === 'checking') {
            $accountType = 'current';
        }

        return [
            'bank_code' => $bankCode,
            'bank_name' => trim((string) ($bank['name'] ?? ($data['bank_name'] ?? ''))),
            'bank_name_kana' => trim((string) ($bank['kana'] ?? ($data['bank_name_kana'] ?? ''))),
            'branch_code' => $branchCode,
            'branch_name' => trim((string) ($branch['name'] ?? ($data['branch_name'] ?? ''))),
            'branch_name_kana' => trim((string) ($branch['kana'] ?? ($data['branch_name_kana'] ?? ''))),
            'account_type' => $accountType,
            'account_number' => preg_replace('/\D+/', '', (string) ($data['account_number'] ?? '')) ?? '',
            'account_name' => $accountName,
            'account_holder_name' => $accountName,
        ];
    }

    public function getAdminBankAccount(): ?object
    {
        $holderId = $this->resolveAdminHolderId();

        return $holderId ? $this->getHolderBankAccount(BankAccount::HOLDER_SYSTEM_ACCOUNT, $holderId) : null;
    }

    public function saveAdminBankAccount(array $data): void
    {
        $this->saveHolderBankAccount(BankAccount::HOLDER_SYSTEM_ACCOUNT, $this->resolveAdminHolderId(), $data);
    }

    public function getCastBankAccount(string $castId): ?object
    {
        return $this->getHolderBankAccount(BankAccount::HOLDER_CAST, $castId);
    }

    public function saveCastBankAccount(string $castId, array $data): void
    {
        $this->saveHolderBankAccount(BankAccount::HOLDER_CAST, $castId, $data);
    }

    public function getShopBankAccount(string $shopId): ?object
    {
        return $this->getHolderBankAccount(BankAccount::HOLDER_SHOP, $shopId);
    }

    public function saveShopBankAccount(string $shopId, array $data): void
    {
        $this->saveHolderBankAccount(BankAccount::HOLDER_SHOP, $shopId, $data);
    }

    public function getAdminBillingDashboard(): array
    {
        $deposits = $this->getAllDeposits();
        $depositIds = collect($deposits)->pluck('id')->all();

        $tasksMap = collect();
        if (Schema::hasTable('payment_tasks') && !empty($depositIds)) {
            $tasksMap = DB::table('payment_tasks')
                ->whereIn('application_deposit_id', $depositIds)
                ->get()
                ->keyBy('application_deposit_id');
        }

        foreach ($deposits as &$d) {
            $d['payment_task'] = $tasksMap->get($d['id']);
            // 既存データで店舗入金確認済みなのにタスクがない場合は1件生成（バックフィル）
            if ($d['status_code'] === self::STATUS_SHOP_PAYMENT_CONFIRMED && !$d['payment_task'] && Schema::hasTable('payment_tasks')) {
                $this->ensurePaymentTaskForDeposit($d['id']);
                $d['payment_task'] = $this->getPaymentTaskForDeposit($d['id']);
            }
        }
        unset($d);

        $sevenDaysAgo = Carbon::now()->subDays(7);
        $unconfirmedOver7 = collect($deposits)->filter(function (array $d) use ($sevenDaysAgo) {
            if ($d['status_code'] !== self::STATUS_CAST_TRANSFERRED) {
                return false;
            }
            $transferredAt = $d['cast_transferred_at'] ?? null;
            if (!$transferredAt) {
                return false;
            }
            $t = Carbon::parse($transferredAt);

            return $t->lt($sevenDaysAgo);
        })->count();

        return [
            'deposits' => $deposits,
            'summary' => [
                'cast_request_pending' => collect($deposits)->where('status_code', self::STATUS_CAST_REQUESTED)->count(),
                'invoice_pending' => collect($deposits)->where('status_code', self::STATUS_SHOP_APPROVED)->count(),
                'invoice_workflow_pending' => collect($deposits)->whereIn('status_code', [
                    self::STATUS_CAST_REQUESTED,
                    self::STATUS_SHOP_APPROVED,
                ])->count(),
                'payment_confirmation_pending' => collect($deposits)->where('status_code', self::STATUS_SHOP_PAYMENT_REPORTED)->count(),
                'cast_transfer_pending' => collect($deposits)->where('status_code', self::STATUS_SHOP_PAYMENT_CONFIRMED)->count(),
                'invoice_total' => collect($deposits)->sum('invoice_amount'),
                'unconfirmed_cast_over_7days' => $unconfirmedOver7,
            ],
        ];
    }

    public function getPendingTasks(): array
    {
        return collect($this->getAllDeposits())
            ->filter(fn (array $deposit) => in_array($deposit['status_code'], [
                self::STATUS_CAST_REQUESTED,
                self::STATUS_SHOP_APPROVED,
                self::STATUS_SHOP_PAYMENT_REPORTED,
                self::STATUS_SHOP_PAYMENT_CONFIRMED,
            ], true))
            ->map(function (array $deposit) {
                $deposit['task_title'] = match ($deposit['status_code']) {
                    self::STATUS_CAST_REQUESTED => 'キャスト入金依頼（店舗承認待ち）を確認する',
                    self::STATUS_SHOP_APPROVED => '店舗へ請求書を発行する',
                    self::STATUS_SHOP_PAYMENT_REPORTED => '店舗入金を照合する',
                    self::STATUS_SHOP_PAYMENT_CONFIRMED => 'キャストへの振込を実行する',
                    default => '対応不要',
                };

                $deposit['task_due_date'] = match ($deposit['status_code']) {
                    self::STATUS_CAST_REQUESTED => $deposit['updated_at_label'] ?: now()->format('Y-m-d H:i'),
                    self::STATUS_SHOP_APPROVED => $deposit['invoice_due_date'] ?: now()->addDays(self::INVOICE_DUE_DAYS)->format('Y-m-d'),
                    self::STATUS_SHOP_PAYMENT_REPORTED => $deposit['shop_payment_reported_at'] ?: now()->format('Y-m-d H:i'),
                    self::STATUS_SHOP_PAYMENT_CONFIRMED => $deposit['shop_payment_confirmed_at'] ?: now()->format('Y-m-d H:i'),
                    default => null,
                };

                $deposit['task_actor_label'] = match ($deposit['status_code']) {
                    self::STATUS_CAST_REQUESTED => '運営',
                    self::STATUS_SHOP_APPROVED => '運営',
                    self::STATUS_SHOP_PAYMENT_REPORTED => '運営',
                    self::STATUS_SHOP_PAYMENT_CONFIRMED => '運営',
                    default => 'システム',
                };

                $deposit['task_summary'] = match ($deposit['status_code']) {
                    self::STATUS_CAST_REQUESTED => 'キャストから入金依頼があります。店舗承認後に請求書を発行できます（詳細は請求書発行画面）。',
                    self::STATUS_SHOP_APPROVED => trim((string) ($deposit['bonus_condition'] ?: '店舗承認済みのため、請求書発行へ進めます。')),
                    self::STATUS_SHOP_PAYMENT_REPORTED => '店舗報告金額: ¥' . number_format((int) ($deposit['shop_payment_reported_amount'] ?? 0))
                        . ' / 参照: ' . (($deposit['shop_payment_reference'] ?? '') ?: '未入力'),
                    self::STATUS_SHOP_PAYMENT_CONFIRMED => '振込予定額: ¥' . number_format((int) ($deposit['cast_transfer_amount'] ?? 0))
                        . ' / キャスト口座: ' . (!empty($deposit['has_cast_bank']) ? '登録済み' : '未登録'),
                    default => '',
                };

                $deposit['task_review_summary'] = trim((string) ($deposit['review_comment'] ?? ''));

                $id = (int) ($deposit['id'] ?? 0);
                $deposit['task_url'] = in_array($deposit['status_code'], [self::STATUS_CAST_REQUESTED, self::STATUS_SHOP_APPROVED], true)
                    ? route('admin.invoices.index') . ($id > 0 ? '#invoice-pending-' . $id : '')
                    : route('admin.deposits.index') . ($id > 0 ? '#deposit-' . $id : '');

                return $deposit;
            })
            ->values()
            ->all();
    }

    public function requestDepositForCast(string $castId, array $payload = [], ?int $applicationId = null): array
    {
        if (!$this->getCastBankAccount($castId)) {
            return ['success' => false, 'message' => '入金申請の前に、キャストの振込先口座を登録してください。'];
        }

        $application = $applicationId !== null
            ? $this->getEligibleApplicationForCastById($castId, $applicationId)
            : $this->getLatestEligibleApplicationForCast($castId);

        if (!$application) {
            return ['success' => false, 'message' => '入金申請の対象となる採用済み案件がありません。'];
        }

        $existingDeposit = DB::table('application_deposits')
            ->where('shop_job_application_id', $application->id)
            ->orderByDesc('id')
            ->first();

        if ($existingDeposit) {
            return ['success' => false, 'message' => 'この案件の入金申請はすでに登録済みです。'];
        }

        if (empty($payload['confirm_bonus_condition'])) {
            return ['success' => false, 'message' => 'ボーナス金達成条件を確認したうえで申請してください。'];
        }

        $existingReview = $this->findExistingReviewForApplication($application);
        if (!$existingReview) {
            $reviewValidation = $this->validateReviewPayload($payload);
            if (!$reviewValidation['success']) {
                return $reviewValidation;
            }

            $this->createReviewForApplication($application, $payload);
        }

        $amounts = $this->calculateAmounts($application);
        $depositId = DB::table('application_deposits')->insertGetId($this->filterExistingColumns('application_deposits', [
            'shop_job_application_id' => $application->id,
            'status' => self::STATUS_CAST_REQUESTED,
            'is_read' => false,
            'bonus_amount' => $amounts['bonus_amount'],
            'system_fee_amount' => $amounts['system_fee_amount'],
            'invoice_amount' => $amounts['invoice_amount'],
            'cast_transfer_amount' => $amounts['cast_transfer_amount'],
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->appendHistory($depositId, self::STATUS_CAST_REQUESTED);

        $this->postBillingTalkMessages((string) $application->cast_id, (string) $application->shop_id, self::STATUS_CAST_REQUESTED, [
            'shop' => $this->isHelpApplicationRow($application)
                ? 'キャストからヘルプ勤務完了の入金申請が届きました。採用・入金管理から内容を確認し、承認をお願いします。'
                : 'キャストから採用ボーナスの入金申請が届きました。採用・入金管理から内容を確認し、承認をお願いします。',
        ]);

        return ['success' => true, 'message' => '入金申請を受け付けました。店舗・運営の確認をお待ちください。'];
    }

    public function confirmDepositForShop(string $shopId, array $payload = []): array
    {
        $deposit = $this->findLatestDepositForShop($shopId);

        if (!$deposit || (int) $deposit->status !== self::STATUS_CAST_REQUESTED) {
            return ['success' => false, 'message' => '店舗確認待ちの入金申請がありません。'];
        }

        if (empty($payload['confirm_bonus_condition'])) {
            return ['success' => false, 'message' => '求人に登録したボーナス達成条件を確認してから承認してください。'];
        }

        if (empty($payload['confirm_review_checked'])) {
            return ['success' => false, 'message' => 'キャストのレビュー内容を確認してから承認してください。'];
        }

        if (!$this->findLatestReviewForCastShop((string) $deposit->cast_id, (string) $deposit->shop_id)) {
            return ['success' => false, 'message' => 'キャストのレビューが見つかりません。内容を確認してから再度お試しください。'];
        }

        DB::table('application_deposits')
            ->where('id', $deposit->id)
            ->update($this->filterExistingColumns('application_deposits', [
                'status' => self::STATUS_SHOP_APPROVED,
                'updated_at' => now(),
            ]));

        $this->appendHistory((int) $deposit->id, self::STATUS_SHOP_APPROVED);

        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_SHOP_APPROVED, [
            'cast' => '店舗が入金申請を承認しました。運営が請求書を発行するまでお待ちください。',
        ]);

        return ['success' => true, 'message' => 'ノルマ達成・店舗審査を完了しました。運営による請求書発行をお待ちください。'];
    }

    public function issueInvoice(int $depositId, array $payload = []): array
    {
        $deposit = $this->findDepositById($depositId);
        $adminBank = $this->getAdminBankAccount();

        if (!$deposit) {
            return ['success' => false, 'message' => '対象データが見つかりません。'];
        }

        if ((int) $deposit->status !== self::STATUS_SHOP_APPROVED) {
            return ['success' => false, 'message' => 'この請求は現在のステータスでは発行できません。'];
        }

        if (!$adminBank) {
            return ['success' => false, 'message' => '先に運営口座を登録してください。'];
        }

        if (empty($payload['confirm_shop_approved']) || empty($payload['confirm_admin_bank_ready'])) {
            return ['success' => false, 'message' => '請求書発行前の確認項目を完了してください。'];
        }

        $amounts = $this->calculateAmounts($deposit);
        $issuedAt = now();
        $invoiceNumber = $deposit->invoice_number ?: $this->generateInvoiceNumber((int) $deposit->id, $issuedAt);

        DB::table('application_deposits')
            ->where('id', $depositId)
            ->update($this->filterExistingColumns('application_deposits', [
                'status' => self::STATUS_INVOICE_ISSUED,
                'invoice_number' => $invoiceNumber,
                'bonus_amount' => $amounts['bonus_amount'],
                'system_fee_amount' => $amounts['system_fee_amount'],
                'invoice_amount' => $amounts['invoice_amount'],
                'cast_transfer_amount' => $amounts['cast_transfer_amount'],
                'invoice_issued_at' => $issuedAt,
                'invoice_due_date' => $issuedAt->copy()->addDays(self::INVOICE_DUE_DAYS)->toDateString(),
                'invoice_sent_at' => $issuedAt,
                'updated_at' => $issuedAt,
            ]));

        $this->appendHistory($depositId, self::STATUS_INVOICE_ISSUED);

        $mailSent = false;
        if (!empty($deposit->shop_email)) {
            $mailSent = $this->sendInvoiceMail($depositId, $deposit->shop_email);
        }

        // 店舗マネージャー宛おしらせ：請求書発行
        $this->notifyInvoiceIssued($deposit);

        $dueLabel = $issuedAt->copy()->addDays(self::INVOICE_DUE_DAYS)->format('Y年n月j日');
        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_INVOICE_ISSUED, [
            'cast' => '店舗へ請求書を発行しました。店舗の入金確認後、振込準備に進みます。',
            'shop' => "請求書を発行しました。お支払い期限（{$dueLabel}）までにお振込をお願いします。",
        ]);

        return [
            'success' => true,
            'message' => $mailSent
                ? '請求書を発行し、店舗へ送付しました。'
                : '請求書を発行しました。メール送付は現在のメール設定に依存するため、店舗画面からも確認できるようにしています。',
        ];
    }

    /**
     * 店舗マネージャーに「請求書が発行されました」のおしらせを送る。
     */
    private function notifyInvoiceIssued(object $deposit): void
    {
        try {
            $shopId = null;
            if (!empty($deposit->shop_job_application_id)) {
                $row = \DB::table('shop_job_applications as sja')
                    ->join('shop_jobs as sj', 'sja.shop_job_id', '=', 'sj.id')
                    ->where('sja.id', $deposit->shop_job_application_id)
                    ->value('sj.shop_id');
                $shopId = $row ? (string) $row : null;
            }
            if (!$shopId) return;

            $notifier = app(\App\Services\NotificationService::class);
            $amount = number_format((int) ($deposit->invoice_amount ?? 0));
            $notifier->createForShop(
                $shopId,
                'billing.invoice_issued',
                '請求書が発行されました',
                "請求番号 #{$deposit->id} ・ 金額 ¥{$amount} ・ 期限内のお支払いをお願いします",
                route('shop.mypage.management', ['tab' => 'payment']),
                ['deposit_id' => $deposit->id],
            );
        } catch (\Throwable $e) {
            \Log::warning('Invoice issued notify failed: ' . $e->getMessage());
        }
    }

    /**
     * 手動で請求書を発行（障害時等の回避策。ステータスが「入金依頼確認済み」でなくても発行可能）
     */
    public function issueInvoiceManually(int $depositId, array $payload = []): array
    {
        $deposit = $this->findDepositById($depositId);
        $adminBank = $this->getAdminBankAccount();

        if (!$deposit) {
            return ['success' => false, 'message' => '対象データが見つかりません。'];
        }

        if (!empty($deposit->invoice_number)) {
            return ['success' => false, 'message' => 'この申請はすでに請求書が発行されています。'];
        }

        if (!$adminBank) {
            return ['success' => false, 'message' => '先に運営口座を登録してください。'];
        }

        if (empty($payload['confirm_manual_workaround']) || empty($payload['confirm_admin_bank_ready'])) {
            return ['success' => false, 'message' => '手動発行の確認にチェックを入れてください。'];
        }

        $bonusAmount = (int) ($payload['bonus_amount'] ?? 0);
        $systemFeeAmount = (int) ($payload['system_fee_amount'] ?? 0);
        $invoiceAmount = (int) ($payload['invoice_amount'] ?? 0);
        $castTransferAmount = (int) ($payload['cast_transfer_amount'] ?? 0);

        if ($bonusAmount < 0 || $systemFeeAmount < 0 || $invoiceAmount < 1 || $castTransferAmount < 0) {
            return ['success' => false, 'message' => '金額を正しく入力してください。'];
        }

        if ($bonusAmount + $systemFeeAmount !== $invoiceAmount) {
            return ['success' => false, 'message' => '請求金額合計は、ボーナス額と運営手数料の合計と一致させてください。'];
        }

        $issuedAt = now();
        $invoiceNumber = $this->generateInvoiceNumber((int) $deposit->id, $issuedAt);

        $shopName = trim((string) ($payload['shop_name'] ?? ''));
        $shopAddress = trim((string) ($payload['shop_address'] ?? ''));
        $shopEmail = trim((string) ($payload['shop_email'] ?? ''));
        $castName = trim((string) ($payload['cast_name'] ?? ''));

        $update = [
            'status' => self::STATUS_INVOICE_ISSUED,
            'invoice_number' => $invoiceNumber,
            'bonus_amount' => $bonusAmount,
            'system_fee_amount' => $systemFeeAmount,
            'invoice_amount' => $invoiceAmount,
            'cast_transfer_amount' => $castTransferAmount,
            'invoice_issued_at' => $issuedAt,
            'invoice_due_date' => $issuedAt->copy()->addDays(self::INVOICE_DUE_DAYS)->toDateString(),
            'invoice_sent_at' => $issuedAt,
            'updated_at' => $issuedAt,
            'invoice_display_shop_name' => $shopName !== '' ? $shopName : null,
            'invoice_display_shop_address' => $shopAddress !== '' ? $shopAddress : null,
            'invoice_display_shop_email' => $shopEmail !== '' ? $shopEmail : null,
            'invoice_display_cast_name' => $castName !== '' ? $castName : null,
        ];

        DB::table('application_deposits')
            ->where('id', $depositId)
            ->update($this->filterExistingColumns('application_deposits', $update));

        $this->appendHistory($depositId, self::STATUS_INVOICE_ISSUED);

        $mailSent = false;
        $invoice = $this->getInvoiceData($depositId);
        if ($invoice && ! empty(trim((string) ($invoice['shop_email'] ?? '')))) {
            $mailSent = $this->sendInvoiceMail($depositId, (string) $invoice['shop_email']);
        }

        $dueLabel = $issuedAt->copy()->addDays(self::INVOICE_DUE_DAYS)->format('Y年n月j日');
        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_INVOICE_ISSUED, [
            'cast' => '店舗へ請求書を発行しました。店舗の入金確認後、振込準備に進みます。',
            'shop' => "請求書を発行しました。お支払い期限（{$dueLabel}）までにお振込をお願いします。",
        ]);

        return [
            'success' => true,
            'message' => '手動で請求書を発行しました。' . ($mailSent ? ' 店舗へメール送付済みです。' : ' メール送付は行っていません。'),
        ];
    }

    public function reportShopPayment(string $shopId, array $payload): array
    {
        $deposit = $this->findLatestDepositForShop($shopId);

        if (!$deposit || !in_array((int) $deposit->status, [self::STATUS_INVOICE_ISSUED, self::STATUS_SHOP_PAYMENT_REPORTED], true)) {
            return ['success' => false, 'message' => '入金報告できる請求書がありません。'];
        }

        $reportedAt = Carbon::parse($payload['reported_at']);

        DB::table('application_deposits')
            ->where('id', $deposit->id)
            ->update($this->filterExistingColumns('application_deposits', [
                'status' => self::STATUS_SHOP_PAYMENT_REPORTED,
                'shop_payment_reported_at' => $reportedAt,
                'shop_payment_reported_amount' => (int) $payload['reported_amount'],
                'shop_payment_reference' => $payload['reference'] ?? null,
                'updated_at' => now(),
            ]));

        if ((int) $deposit->status === self::STATUS_INVOICE_ISSUED) {
            $this->appendHistory((int) $deposit->id, self::STATUS_SHOP_PAYMENT_REPORTED);
        }

        return ['success' => true, 'message' => '入金報告を受け付けました。運営による着金照合をお待ちください。'];
    }

    public function confirmShopPayment(int $depositId, array $payload): array
    {
        $deposit = $this->findDepositById($depositId);

        if (!$deposit) {
            return ['success' => false, 'message' => '対象データが見つかりません。'];
        }

        if ((int) $deposit->status !== self::STATUS_SHOP_PAYMENT_REPORTED) {
            return ['success' => false, 'message' => 'この入金報告は現在のステータスでは照合できません。'];
        }

        if (
            empty($payload['confirm_amount_checked'])
            || empty($payload['confirm_report_checked'])
            || empty($payload['confirm_bank_checked'])
        ) {
            return ['success' => false, 'message' => '店舗入金照合前の確認項目を完了してください。'];
        }

        $confirmedAmount = (int) $payload['confirmed_amount'];
        $expectedAmount = (int) ($deposit->invoice_amount ?? 0);
        $reportedAmount = (int) ($deposit->shop_payment_reported_amount ?? 0);

        if ($confirmedAmount !== $expectedAmount || $reportedAmount !== $expectedAmount) {
            return ['success' => false, 'message' => '請求金額と報告金額が一致しません。店舗へ再確認してください。'];
        }

        DB::table('application_deposits')
            ->where('id', $depositId)
            ->update($this->filterExistingColumns('application_deposits', [
                'status' => self::STATUS_SHOP_PAYMENT_CONFIRMED,
                'shop_payment_confirmed_at' => now(),
                'updated_at' => now(),
            ]));

        $this->appendHistory($depositId, self::STATUS_SHOP_PAYMENT_CONFIRMED);

        $this->ensurePaymentTaskForDeposit($depositId);

        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_SHOP_PAYMENT_CONFIRMED, [
            'cast' => $this->isHelpApplicationRow($deposit)
                ? '店舗からの入金を確認しました。ヘルプ勤務分の報酬の振込準備に入ります。'
                : '店舗からの入金を確認しました。採用ボーナスの振込準備に入ります。',
            'shop' => 'お振込を確認しました。お支払いの手続きは完了です。',
        ]);

        return ['success' => true, 'message' => '店舗からの入金を確認しました。キャストへの振込準備に進めます。'];
    }

    /**
     * 店舗入金確認済みの deposit に対して PaymentTask を1件のみ生成（UNIQUE で二重防止）
     * 振込額 = 店舗入金額 - プラットフォーム手数料 - 銀行振込手数料（手入力禁止のため自動計算のみ）
     */
    public function ensurePaymentTaskForDeposit(int $depositId): void
    {
        if (!Schema::hasTable('payment_tasks')) {
            return;
        }

        $deposit = $this->findDepositById($depositId);
        if (!$deposit || (int) $deposit->status !== self::STATUS_SHOP_PAYMENT_CONFIRMED) {
            return;
        }

        $existing = DB::table('payment_tasks')->where('application_deposit_id', $depositId)->first();
        if ($existing) {
            return;
        }

        $shopReceived = (int) ($deposit->invoice_amount ?? 0);
        $platformFee = (int) ($deposit->system_fee_amount ?? 0);
        $bankFee = self::BANK_FEE_AMOUNT;
        $payout = max(0, $shopReceived - $platformFee - $bankFee);

        DB::table('payment_tasks')->insert([
            'application_deposit_id' => $depositId,
            'status' => PaymentTask::STATUS_READY,
            'shop_received_amount' => $shopReceived,
            'platform_fee_amount' => $platformFee,
            'bank_fee_amount' => $bankFee,
            'payout_amount' => $payout,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** 入金管理IDに紐づく PaymentTask を1件取得（支払済・無効含む） */
    public function getPaymentTaskForDeposit(int $depositId): ?object
    {
        if (!Schema::hasTable('payment_tasks')) {
            return null;
        }

        return DB::table('payment_tasks')->where('application_deposit_id', $depositId)->first();
    }

    /**
     * 振込作業を開始（振込中に変更し他担当をロック）
     * 支払準備中のみ実行可能。
     */
    public function startTransfer(int $depositId, ?string $operatorId = null): array
    {
        if (!Schema::hasTable('payment_tasks')) {
            return ['success' => false, 'message' => '振込タスクテーブルが存在しません。'];
        }

        $task = $this->getPaymentTaskForDeposit($depositId);
        if (!$task) {
            return ['success' => false, 'message' => '振込タスクが見つかりません。'];
        }
        if ((int) $task->status !== PaymentTask::STATUS_READY) {
            return ['success' => false, 'message' => '支払準備中のタスクのみ振込開始できます。'];
        }

        DB::table('payment_tasks')
            ->where('id', $task->id)
            ->update([
                'status' => PaymentTask::STATUS_TRANSFERRING,
                'operator_id' => $operatorId,
                'updated_at' => now(),
            ]);

        return ['success' => true, 'message' => '振込作業を開始しました。証跡画像のアップロードとチェック完了後に「支払済」を実行してください。'];
    }

    /**
     * 振込完了処理（証跡画像・チェックリスト・振込作業完了日必須）。支払済は不可逆。
     */
    public function completeTransfer(int $depositId, array $payload, ?string $evidenceFilePath = null): array
    {
        if (!Schema::hasTable('payment_tasks')) {
            return ['success' => false, 'message' => '振込タスクテーブルが存在しません。'];
        }

        $deposit = $this->findDepositById($depositId);
        if (!$deposit) {
            return ['success' => false, 'message' => '対象データが見つかりません。'];
        }

        $task = $this->getPaymentTaskForDeposit($depositId);
        if (!$task) {
            return ['success' => false, 'message' => '振込タスクが見つかりません。'];
        }
        if ((int) $task->status !== PaymentTask::STATUS_TRANSFERRING) {
            return ['success' => false, 'message' => '振込中のタスクのみ完了できます。'];
        }

        if (empty($payload['checklist_confirmed_account']) || empty($payload['checklist_confirmed_amount'])) {
            return ['success' => false, 'message' => '振込先名義・口座番号と振込金額の両方にチェックを入れてください。'];
        }
        if (empty($payload['transferred_at'])) {
            return ['success' => false, 'message' => '振込作業完了日を入力してください。'];
        }
        if (!$evidenceFilePath) {
            return ['success' => false, 'message' => '振込完了画面のスクリーンショット（証跡画像）をアップロードしてください。'];
        }

        $transferredAt = Carbon::parse($payload['transferred_at']);
        $now = now();

        DB::transaction(function () use ($depositId, $task, $evidenceFilePath, $payload, $transferredAt, $now) {
            DB::table('payment_tasks')
                ->where('id', $task->id)
                ->update([
                    'status' => PaymentTask::STATUS_PAID,
                    'transferred_at' => $transferredAt,
                    'completed_at' => $now,
                    'evidence_file_path' => $evidenceFilePath,
                    'checklist_confirmed_account' => true,
                    'checklist_confirmed_amount' => true,
                    'updated_at' => $now,
                ]);

            DB::table('application_deposits')
                ->where('id', $depositId)
                ->update($this->filterExistingColumns('application_deposits', [
                    'status' => self::STATUS_CAST_TRANSFERRED,
                    'cast_transferred_at' => $transferredAt,
                    'cast_transfer_reference' => $payload['reference'] ?? null,
                    'cast_transfer_note' => $payload['note'] ?? null,
                    'updated_at' => $now,
                ]));
        });

        $this->appendHistory($depositId, self::STATUS_CAST_TRANSFERRED);

        // キャスト宛おしらせ：振込実行通知
        $this->notifyCastTransferred($depositId);

        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_CAST_TRANSFERRED, [
            'cast' => $this->castTransferredTalkText($deposit),
        ]);

        return ['success' => true, 'message' => 'キャストへの振込を記録しました。キャストの入金確認をお待ちください。'];
    }

    /**
     * キャストに「振込完了しました」のおしらせを送る。
     */
    private function notifyCastTransferred(int $depositId): void
    {
        try {
            $row = \DB::table('application_deposits as ad')
                ->join('shop_job_applications as sja', 'ad.shop_job_application_id', '=', 'sja.id')
                ->where('ad.id', $depositId)
                ->select('sja.cast_id', 'ad.cast_transfer_amount')
                ->first();
            if (!$row || empty($row->cast_id)) return;

            $amount = number_format((int) ($row->cast_transfer_amount ?? 0));
            app(\App\Services\NotificationService::class)->createForCast(
                (string) $row->cast_id,
                'billing.cast_transferred',
                '採用ボーナスを振込みました',
                "金額 ¥{$amount} を登録口座へ振込みました。入金をご確認ください。",
                \Route::has('cast.mypage.management') ? route('cast.mypage.management') : null,
                ['deposit_id' => $depositId],
            );
        } catch (\Throwable $e) {
            \Log::warning('Cast transferred notify failed: ' . $e->getMessage());
        }
    }

    /**
     * 振込タスクを無効化（組戻し・口座誤り時）。既存レコードの上書きはせず、口座修正後に別IDで新規タスクを発行する運用。
     */
    public function invalidatePaymentTask(int $depositId, string $reason = ''): array
    {
        if (!Schema::hasTable('payment_tasks')) {
            return ['success' => false, 'message' => '振込タスクテーブルが存在しません。'];
        }

        $task = $this->getPaymentTaskForDeposit($depositId);
        if (!$task) {
            return ['success' => false, 'message' => '振込タスクが見つかりません。'];
        }
        if ((int) $task->status === PaymentTask::STATUS_PAID) {
            return ['success' => false, 'message' => '支払済のタスクは無効化できません。要返金フラグで対応してください。'];
        }

        DB::table('payment_tasks')
            ->where('id', $task->id)
            ->update([
                'status' => PaymentTask::STATUS_INVALID,
                'updated_at' => now(),
            ]);

        return ['success' => true, 'message' => '振込タスクを無効にしました。口座修正後、新規タスクで再発行してください。'];
    }

    /** 要返金フラグを立てる（支払後にレビュー不正等が判明した場合） */
    public function setPaymentTaskRefundRequired(int $depositId): array
    {
        if (!Schema::hasTable('payment_tasks')) {
            return ['success' => false, 'message' => '振込タスクテーブルが存在しません。'];
        }

        $task = $this->getPaymentTaskForDeposit($depositId);
        if (!$task) {
            return ['success' => false, 'message' => '振込タスクが見つかりません。'];
        }

        DB::table('payment_tasks')
            ->where('id', $task->id)
            ->update(['refund_required' => true, 'updated_at' => now()]);

        return ['success' => true, 'message' => '要返金フラグを設定しました。'];
    }

    public function executeCastTransfer(int $depositId, array $payload, ?string $evidenceFilePath = null): array
    {
        $deposit = $this->findDepositById($depositId);

        if (!$deposit) {
            return ['success' => false, 'message' => '対象データが見つかりません。'];
        }

        if ((int) $deposit->status !== self::STATUS_SHOP_PAYMENT_CONFIRMED) {
            return ['success' => false, 'message' => 'このデータはまだキャスト振込へ進めません。'];
        }

        if (!$this->getCastBankAccount((string) $deposit->cast_id)) {
            return ['success' => false, 'message' => 'キャストの口座情報が未登録です。'];
        }

        if (
            empty($payload['confirm_transfer_amount'])
            || empty($payload['confirm_account_name'])
            || empty($payload['confirm_transfer_executed'])
            || empty($payload['confirm_receipt_checked'])
        ) {
            return ['success' => false, 'message' => 'キャスト振込記録前の確認項目を完了してください。'];
        }

        // AD-109（completeTransfer）と同じく証跡画像必須（証跡なしの振込記録を防止）
        if (!$evidenceFilePath) {
            return ['success' => false, 'message' => '振込完了画面のスクリーンショット（証跡画像）をアップロードしてください。'];
        }

        $transferredAt = Carbon::parse($payload['transferred_at']);
        $now = now();
        DB::transaction(function () use ($depositId, $payload, $evidenceFilePath, $transferredAt, $now) {
            DB::table('application_deposits')
                ->where('id', $depositId)
                ->update($this->filterExistingColumns('application_deposits', [
                    'status' => self::STATUS_CAST_TRANSFERRED,
                    'cast_transferred_at' => $transferredAt,
                    'cast_transfer_reference' => $payload['reference'] ?? null,
                    'cast_transfer_note' => $payload['note'] ?? null,
                    'cast_transfer_evidence_path' => $evidenceFilePath,
                    'updated_at' => $now,
                ]));

            if (Schema::hasTable('payment_tasks')) {
                $task = $this->getPaymentTaskForDeposit($depositId);
                if ($task) {
                    DB::table('payment_tasks')->where('id', $task->id)->update([
                        'status' => PaymentTask::STATUS_PAID,
                        'transferred_at' => $transferredAt,
                        'completed_at' => $now,
                        'evidence_file_path' => $evidenceFilePath,
                        'checklist_confirmed_account' => true,
                        'checklist_confirmed_amount' => true,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        $this->appendHistory($depositId, self::STATUS_CAST_TRANSFERRED);
        $this->notifyCastTransferred($depositId);

        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_CAST_TRANSFERRED, [
            'cast' => $this->castTransferredTalkText($deposit),
        ]);

        return ['success' => true, 'message' => 'キャストへの振込手続きを記録しました。キャストの入金確認待ちです。'];
    }

    public function confirmCastReceipt(string $castId): array
    {
        $deposit = $this->findLatestDepositForCast($castId);

        if (!$deposit || (int) $deposit->status !== self::STATUS_CAST_TRANSFERRED) {
            return ['success' => false, 'message' => '確認待ちの振込データがありません。'];
        }

        DB::table('application_deposits')
            ->where('id', $deposit->id)
            ->update($this->filterExistingColumns('application_deposits', [
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]));

        $this->appendHistory((int) $deposit->id, self::STATUS_COMPLETED);

        $completedText = $this->isHelpApplicationRow($deposit)
            ? 'ヘルプ勤務分のお手続きがすべて完了しました。ご利用ありがとうございました。'
            : '採用ボーナスのお手続きがすべて完了しました。ご利用ありがとうございました。';
        $this->postBillingTalkMessages((string) $deposit->cast_id, (string) $deposit->shop_id, self::STATUS_COMPLETED, [
            'cast' => $completedText,
            'shop' => $completedText,
        ]);

        return ['success' => true, 'message' => '入金確認を記録しました。今回の請求・振込フローは完了です。'];
    }

    public function getShopPaymentPageData(string $shopId): array
    {
        $deposits = collect($this->getAllDeposits())
            ->where('shop_id', $shopId)
            ->values();

        $current = $deposits->first();
        $bank = $this->normalizeBankAccount($this->getShopBankAccount($shopId));
        $approvalTarget = ($current && (int) ($current['status_code'] ?? 0) === self::STATUS_CAST_REQUESTED)
            ? $this->buildShopApprovalTarget($current)
            : null;

        return [
            'current' => $current,
            'flow' => $current['flow'] ?? $this->flowState(0),
            'summary' => [
                'unpaid_total' => $deposits
                    ->whereIn('status_code', [
                        self::STATUS_INVOICE_ISSUED,
                        self::STATUS_SHOP_PAYMENT_REPORTED,
                    ])
                    ->sum('invoice_amount'),
                'next_settlement' => $current['invoice_due_date'] ?? null,
            ],
            'invoices' => $deposits
                ->filter(fn (array $deposit) => !empty($deposit['invoice_number']))
                ->map(fn (array $deposit) => [
                    'id' => $deposit['id'],
                    'title' => '請求書 ' . $deposit['invoice_number'],
                    'amount' => $deposit['invoice_amount'],
                    'status' => in_array($deposit['status_code'], [
                        self::STATUS_SHOP_PAYMENT_CONFIRMED,
                        self::STATUS_CAST_TRANSFERRED,
                        self::STATUS_COMPLETED,
                    ], true) ? 'paid' : 'pending',
                    'date' => $deposit['invoice_issued_at'],
                    'invoice_url' => $this->getSignedInvoiceUrl($deposit['id']),
                    'invoice_pdf_url' => $this->getSignedInvoicePdfUrl($deposit['id']),
                ])
                ->values()
                ->all(),
            'bank' => $bank,
            'payment_form' => [
                'reported_amount' => $current['invoice_amount'] ?? '',
                'reported_at' => $current['shop_payment_reported_at_form'] ?? now()->format('Y-m-d\TH:i'),
                'reference' => $current['shop_payment_reference'] ?? '',
            ],
            'approval_target' => $approvalTarget,
            'can_report_payment' => $current && in_array($current['status_code'], [
                self::STATUS_INVOICE_ISSUED,
                self::STATUS_SHOP_PAYMENT_REPORTED,
            ], true),
        ];
    }

    public function getCastPaymentPageData(string $castId): array
    {
        $deposits = collect($this->getAllDeposits())
            ->where('cast_id', $castId)
            ->values();

        $current = $deposits->first();
        $bank = $this->normalizeBankAccount($this->getCastBankAccount($castId));
        $eligibleApplication = $this->getLatestEligibleApplicationForCast($castId);
        $hasExistingDeposit = $eligibleApplication
            ? DB::table('application_deposits')
                ->where('shop_job_application_id', $eligibleApplication->id)
                ->exists()
            : false;
        $requestTarget = $eligibleApplication ? $this->buildCastDepositRequestTarget($eligibleApplication) : null;

        $requestDisabledReason = null;
        if (!$bank['exists']) {
            $requestDisabledReason = '振込先口座を登録すると入金申請できます。';
        } elseif (!$eligibleApplication) {
            $requestDisabledReason = '採用済み案件が確定すると入金申請できます。';
        } elseif ($hasExistingDeposit) {
            $requestDisabledReason = 'この案件の入金申請はすでに登録済みです。';
        }

        return [
            'current' => $current,
            'flow' => $current['flow'] ?? $this->flowState(0),
            'payments' => $deposits->map(fn (array $deposit) => [
                'title' => !empty($deposit['invoice_number'])
                    ? '請求・入金フロー ' . $deposit['invoice_number']
                    : ((($deposit['job_kind'] ?? '') === 'help') ? 'ヘルプ勤務の入金申請' : 'ボーナス入金申請'),
                'status_label' => $deposit['status_label'],
                'status_class' => in_array($deposit['status_code'], [self::STATUS_CAST_TRANSFERRED, self::STATUS_COMPLETED], true)
                    ? 'status-paid'
                    : 'status-pending',
                'date' => $deposit['updated_at_label'],
                'amount' => $deposit['cast_transfer_amount'],
                'link' => !empty($deposit['invoice_number']) ? $this->getSignedInvoiceUrl($deposit['id']) : null,
            ])->all(),
            'bank' => $bank,
            'can_request' => $requestDisabledReason === null,
            'request_disabled_reason' => $requestDisabledReason,
            'request_target' => $requestTarget,
        ];
    }

    public function getInvoiceData(int $depositId): ?array
    {
        $deposit = $this->findDepositById($depositId);
        $adminBank = $this->getAdminBankAccount();

        if (!$deposit || !$adminBank) {
            return null;
        }

        $amounts = $this->calculateAmounts($deposit);
        $issuedAt = $deposit->invoice_issued_at ? Carbon::parse($deposit->invoice_issued_at) : now();
        $dueDate = $deposit->invoice_due_date
            ? Carbon::parse($deposit->invoice_due_date)
            : $issuedAt->copy()->addDays(self::INVOICE_DUE_DAYS);

        $template = app(InvoiceTemplateSettingsService::class)->getForInvoice();

        $defaultAddress = trim(implode(' ', array_filter([
            $deposit->shop_pref,
            $deposit->shop_city,
            $deposit->shop_addr2,
            $deposit->shop_addr3,
        ])));

        return array_merge($template, [
            'deposit_id' => (int) $deposit->id,
            'invoice_number' => $deposit->invoice_number ?: $this->generateInvoiceNumber((int) $deposit->id, $issuedAt),
            'issued_at' => $issuedAt,
            'due_date' => $dueDate,
            'shop_name' => $this->invoiceDisplayString($deposit, 'invoice_display_shop_name', (string) ($deposit->shop_name ?? '')),
            'shop_email' => $this->invoiceDisplayString($deposit, 'invoice_display_shop_email', (string) ($deposit->shop_email ?? '')),
            'shop_address' => $this->invoiceDisplayString($deposit, 'invoice_display_shop_address', $defaultAddress),
            'cast_name' => $this->invoiceDisplayString($deposit, 'invoice_display_cast_name', $this->castName($deposit)),
            'bonus_amount' => $amounts['bonus_amount'],
            'system_fee_amount' => $amounts['system_fee_amount'],
            'invoice_amount' => $amounts['invoice_amount'],
            'cast_transfer_amount' => $amounts['cast_transfer_amount'],
            'admin_bank' => [
                'bank_name' => $adminBank->bank_name,
                'branch_name' => $adminBank->branch_name,
                'account_type_label' => $this->accountTypeLabel($adminBank->account_type),
                'account_number' => $adminBank->account_number,
                'account_holder_name' => $adminBank->account_holder_name ?? '',
                'account_name' => $adminBank->account_name,
            ],
        ]);
    }

    /**
     * 帳票の体裁のみ（数値・実データなし）。テンプレートDL・プレビュー用。
     */
    public function getInvoiceTemplateShellData(): array
    {
        $template = app(InvoiceTemplateSettingsService::class)->getForInvoice();

        return array_merge($template, [
            'template_only' => true,
            'deposit_id' => 0,
            'invoice_number' => '',
            'issued_at' => null,
            'due_date' => null,
            'shop_name' => '',
            'shop_email' => '',
            'shop_address' => '',
            'cast_name' => '',
            'bonus_amount' => 0,
            'system_fee_amount' => 0,
            'invoice_amount' => 0,
            'cast_transfer_amount' => 0,
            'admin_bank' => [
                'bank_name' => '（運営口座情報設定を反映）',
                'branch_name' => '（支店名）',
                'account_type_label' => '（普通／当座）',
                'account_number' => '（口座番号）',
                'account_holder_name' => '',
                'account_name' => '（口座名義）',
            ],
        ]);
    }

    /**
     * @deprecated 互換のため getInvoiceTemplateShellData と同等
     */
    public function getSampleInvoiceData(): array
    {
        return $this->getInvoiceTemplateShellData();
    }

    /**
     * 請求書宛先の上書きカラム（存在し値あり）を優先
     */
    private function invoiceDisplayString(object $deposit, string $column, string $fallback): string
    {
        if (! Schema::hasColumn('application_deposits', $column)) {
            return $fallback;
        }

        $v = trim((string) ($deposit->{$column} ?? ''));

        return $v !== '' ? $v : $fallback;
    }

    public function getSignedInvoiceUrl(int $depositId, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'billing.invoices.show',
            $expiresAt ?: now()->addDays(30),
            ['deposit' => $depositId]
        );
    }

    /** 店舗向け：請求書PDFの署名付きダウンロードURL */
    public function getSignedInvoicePdfUrl(int $depositId, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'billing.invoices.pdf',
            $expiresAt ?: now()->addDays(30),
            ['deposit' => $depositId]
        );
    }

    public function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_CAST_REQUESTED => '申請中',
            self::STATUS_SHOP_APPROVED => '請求待ち',
            self::STATUS_INVOICE_ISSUED => '店舗へ請求中',
            self::STATUS_SHOP_PAYMENT_REPORTED => '店舗入金確認中',
            self::STATUS_SHOP_PAYMENT_CONFIRMED => '店舗入金確認済',
            self::STATUS_CAST_TRANSFERRED => 'キャスト振込済',
            self::STATUS_COMPLETED => '完了',
            default => '未申請',
        };
    }

    public function flowState(int $status): array
    {
        return match ($status) {
            self::STATUS_CAST_REQUESTED => ['cast' => '申請中', 'shop' => '承認待ち', 'admin' => '提出待ち'],
            self::STATUS_SHOP_APPROVED => ['cast' => '申請中', 'shop' => '請求待ち', 'admin' => '店舗承認済'],
            self::STATUS_INVOICE_ISSUED => ['cast' => 'お振込準備中', 'shop' => 'お支払い待ち', 'admin' => '店舗へ請求中'],
            self::STATUS_SHOP_PAYMENT_REPORTED => ['cast' => 'お振込準備中', 'shop' => '入金報告済', 'admin' => '店舗入金確認中'],
            self::STATUS_SHOP_PAYMENT_CONFIRMED => ['cast' => 'お振込準備中', 'shop' => 'お支払い完了', 'admin' => '店舗入金確認済'],
            self::STATUS_CAST_TRANSFERRED => ['cast' => 'お振込手続き中', 'shop' => 'お支払い完了', 'admin' => 'キャスト振込済'],
            self::STATUS_COMPLETED => ['cast' => '完了', 'shop' => '完了', 'admin' => '完了'],
            default => ['cast' => '未申請', 'shop' => '未稼働', 'admin' => '未稼働'],
        };
    }

    public function getAllDeposits(): array
    {
        return $this->baseDepositQuery()
            ->orderByDesc('application_deposits.id')
            ->get()
            ->map(fn (object $row) => $this->mapDepositRow($row))
            ->all();
    }

    /**
     * キャスト/店舗ごとの請求・振込フロー実績サマリーを返す
     *
     * @return array<string, array{
     *     total: int,
     *     invoice_issued: int,
     *     payment_reported: int,
     *     payment_confirmed: int,
     *     cast_transferred: int,
     *     completed: int,
     *     latest_status_label: string,
     *     latest_updated_at: string|null
     * }>
     */
    public function getOperationSummaryByEntity(string $entityType): array
    {
        $key = $entityType === 'shop' ? 'shop_id' : 'cast_id';
        $groups = collect($this->getAllDeposits())->groupBy($key);

        return $groups->map(function ($rows) {
            $latest = collect($rows)->sortByDesc(function (array $row) {
                return $row['id'] ?? 0;
            })->first();

            return [
                'total' => collect($rows)->count(),
                'invoice_issued' => collect($rows)->filter(fn (array $row) => (int) ($row['status_code'] ?? 0) >= self::STATUS_INVOICE_ISSUED)->count(),
                'payment_reported' => collect($rows)->filter(fn (array $row) => (int) ($row['status_code'] ?? 0) >= self::STATUS_SHOP_PAYMENT_REPORTED)->count(),
                'payment_confirmed' => collect($rows)->filter(fn (array $row) => (int) ($row['status_code'] ?? 0) >= self::STATUS_SHOP_PAYMENT_CONFIRMED)->count(),
                'cast_transferred' => collect($rows)->filter(fn (array $row) => (int) ($row['status_code'] ?? 0) >= self::STATUS_CAST_TRANSFERRED)->count(),
                'completed' => collect($rows)->filter(fn (array $row) => (int) ($row['status_code'] ?? 0) >= self::STATUS_COMPLETED)->count(),
                'latest_status_label' => (string) ($latest['status_label'] ?? '未申請'),
                'latest_updated_at' => $latest['updated_at_label'] ?? null,
            ];
        })->all();
    }

    /**
     * 店舗ごとのバッヂ情報（優良店バッヂなど）を返す
     *
     * 現状の判定対象は「優良店バッヂ」のみ。今後別バッヂを追加する場合は本メソッドで集約する。
     * - 対象期間: 過去3ヶ月
     * - 対象: application_deposits に紐づく shop_jobs.shop_id = 指定店舗
     * - 付与条件:
     *   1) 対象期間内に1件以上の application_deposits がある
     *   2) すべてのレコードで status >= STATUS_SHOP_PAYMENT_CONFIRMED（店舗入金確認済み）
     *   3) 各レコードについて、請求〜入金確認までの所要日数が一定以内
     *      - invoice_issued_at 〜 shop_payment_confirmed_at が 10 日以内
     */
    public function getShopBadges(string $shopId): array
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $rows = $this->baseDepositQuery()
            ->where('shops.id', $shopId)
            ->where('application_deposits.created_at', '>=', $threeMonthsAgo)
            ->get([
                'application_deposits.status',
                'application_deposits.invoice_issued_at',
                'application_deposits.shop_payment_confirmed_at',
            ]);

        if ($rows->isEmpty()) {
            return [
                'good_payer' => false,
            ];
        }

        $allStatusOk = $rows->every(function ($row) {
            return (int) $row->status >= self::STATUS_SHOP_PAYMENT_CONFIRMED;
        });

        if (!$allStatusOk) {
            return [
                'good_payer' => false,
            ];
        }

        $allSpeedOk = $rows->every(function ($row) {
            if (empty($row->invoice_issued_at) || empty($row->shop_payment_confirmed_at)) {
                return false;
            }
            $issued = Carbon::parse($row->invoice_issued_at);
            $confirmed = Carbon::parse($row->shop_payment_confirmed_at);
            $diffDays = $issued->diffInDays($confirmed, false);

            return $diffDays >= 0 && $diffDays <= 10;
        });

        return [
            'good_payer' => $allSpeedOk,
        ];
    }

    private function baseDepositQuery()
    {
        return DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shops', 'shop_jobs.shop_id', '=', 'shops.id')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->join('casts', 'shop_job_applications.cast_id', '=', 'casts.id')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->leftJoin('bank_accounts as cast_bank_accounts', function ($join) {
                $join->on('casts.id', '=', 'cast_bank_accounts.holder_id')
                    ->where('cast_bank_accounts.holder_type', '=', BankAccount::HOLDER_CAST);
            })
            ->leftJoin('bank_accounts as shop_bank_accounts', function ($join) {
                $join->on('shops.id', '=', 'shop_bank_accounts.holder_id')
                    ->where('shop_bank_accounts.holder_type', '=', BankAccount::HOLDER_SHOP);
            })
            ->select(array_merge(
                [
                    'application_deposits.id',
                    'application_deposits.shop_job_application_id',
                    'application_deposits.status',
                    'application_deposits.is_read',
                    'application_deposits.created_at',
                    'application_deposits.updated_at',
                ],
                $this->optionalDepositSelects(),
                $this->shopJobJoinSelectsForBilling(),
            ));
    }

    /**
     * shop_jobs JOIN 時に取得するボーナス・時給関連カラム（存在するもののみ）
     *
     * @return list<string>
     */
    private function shopJobExtraColumnsForApplicationBilling(): array
    {
        $cols = ['shop_jobs.shop_id'];
        foreach ([
            'regular_hourly_wage',
            'hourly_wage_regular',
            'help_hourly_wage',
            'bonus_reward',
            'noruma_reward',
            'noruma_cond',
            'bonus_condition',
            'norma_day',
            'normal_time',
            'norma_hours',
            'hours_day',
        ] as $c) {
            if (Schema::hasColumn('shop_jobs', $c)) {
                $cols[] = 'shop_jobs.' . $c;
            }
        }

        return $cols;
    }

    /**
     * @return array<int, string|\Illuminate\Database\Query\Expression>
     */
    private function shopJobJoinSelectsForBilling(): array
    {
        return array_merge(
            [
                'shop_job_applications.id as application_id',
                'shop_job_applications.cast_id',
                'shop_job_applications.result_date',
                'shops.email as shop_email',
                'shop_profiles.shop_name',
                'shop_profiles.pref as shop_pref',
                'shop_profiles.city as shop_city',
                'cast_profiles.nickname as cast_nickname',
                'cast_profiles.name as cast_full_name',
                'casts.email as cast_email',
                DB::raw('cast_bank_accounts.id as cast_bank_id'),
                DB::raw('shop_bank_accounts.id as shop_bank_id'),
            ],
            $this->shopProfileAddressSelectsForBilling(),
            $this->shopJobApplicationColumnsForBilling(),
            $this->shopJobExtraColumnsForApplicationBilling()
        );
    }

    /**
     * @return list<string>
     */
    private function shopJobApplicationColumnsForBilling(): array
    {
        $cols = [];
        foreach ([
            'talk_job_kind',
            'hired_regular_hourly_wage',
            'hired_bonus_amount',
            'hired_bonus_condition',
            'applied_help_hourly_wage',
            'applied_bonus_reward',
            'applied_bonus_condition',
            'applied_norma_day',
            'applied_norma_hours',
        ] as $c) {
            if (Schema::hasColumn('shop_job_applications', $c)) {
                $cols[] = 'shop_job_applications.' . $c;
            }
        }

        return $cols;
    }

    /**
     * shop_profiles の住所列差分（addr/building or addr2/addr3）を吸収する。
     *
     * @return array<int, string|\Illuminate\Database\Query\Expression>
     */
    private function shopProfileAddressSelectsForBilling(): array
    {
        $selects = [];

        if (Schema::hasColumn('shop_profiles', 'addr')) {
            $selects[] = 'shop_profiles.addr as shop_addr2';
            $selects[] = Schema::hasColumn('shop_profiles', 'building')
                ? 'shop_profiles.building as shop_addr3'
                : DB::raw("'' as shop_addr3");

            return $selects;
        }

        $selects[] = Schema::hasColumn('shop_profiles', 'addr2')
            ? 'shop_profiles.addr2 as shop_addr2'
            : DB::raw("'' as shop_addr2");
        $selects[] = Schema::hasColumn('shop_profiles', 'addr3')
            ? 'shop_profiles.addr3 as shop_addr3'
            : DB::raw("'' as shop_addr3");

        return $selects;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function mergeShopJobBonusMetaFromRow(object $row, array $meta): array
    {
        if (Schema::hasColumn('shop_jobs', 'bonus_condition') && property_exists($row, 'bonus_condition')) {
            $bc = trim((string) ($row->bonus_condition ?? ''));
            if ($bc !== '') {
                $meta['bonus_condition'] = $bc;
            }
        }

        $days = null;
        if (Schema::hasColumn('shop_jobs', 'norma_day') && property_exists($row, 'norma_day') && $row->norma_day !== null && $row->norma_day !== '') {
            $days = (int) $row->norma_day;
        } elseif (Schema::hasColumn('shop_jobs', 'normal_time') && property_exists($row, 'normal_time') && $row->normal_time !== null && $row->normal_time !== '') {
            $days = (int) $row->normal_time;
        }
        if ($days !== null) {
            $meta['working_days'] = (string) $days;
            $meta['bonus_total_working_days'] = (string) $days;
        }

        $hours = null;
        if (Schema::hasColumn('shop_jobs', 'norma_hours') && property_exists($row, 'norma_hours') && $row->norma_hours !== null && $row->norma_hours !== '') {
            $hours = (int) $row->norma_hours;
        } elseif (Schema::hasColumn('shop_jobs', 'hours_day') && property_exists($row, 'hours_day') && $row->hours_day !== null && $row->hours_day !== '') {
            $hours = (int) $row->hours_day;
        }
        if ($hours !== null) {
            $meta['working_hours'] = (string) $hours;
            $meta['bonus_total_working_hours'] = (string) $hours;
        }

        return $meta;
    }

    /**
     * 応募時スナップショット（applied_*）でメタを上書き
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function mergeBonusMetaFromApplicationApplied(object $application, array $meta): array
    {
        if (Schema::hasColumn('shop_job_applications', 'applied_norma_day')
            && property_exists($application, 'applied_norma_day')
            && $application->applied_norma_day !== null
            && $application->applied_norma_day !== '') {
            $d = (int) $application->applied_norma_day;
            $meta['working_days'] = (string) $d;
            $meta['bonus_total_working_days'] = (string) $d;
        }
        if (Schema::hasColumn('shop_job_applications', 'applied_norma_hours')
            && property_exists($application, 'applied_norma_hours')
            && $application->applied_norma_hours !== null
            && $application->applied_norma_hours !== '') {
            $h = (int) $application->applied_norma_hours;
            $meta['working_hours'] = (string) $h;
            $meta['bonus_total_working_hours'] = (string) $h;
        }
        if (Schema::hasColumn('shop_job_applications', 'applied_bonus_condition')
            && property_exists($application, 'applied_bonus_condition')
            && trim((string) ($application->applied_bonus_condition ?? '')) !== '') {
            $meta['bonus_condition'] = trim((string) $application->applied_bonus_condition);
        }

        return $meta;
    }

    private function findDepositById(int $depositId): ?object
    {
        return $this->baseDepositQuery()
            ->where('application_deposits.id', $depositId)
            ->first();
    }

    private function findLatestDepositForShop(string $shopId): ?object
    {
        return $this->baseDepositQuery()
            ->where('shops.id', $shopId)
            ->orderByDesc('application_deposits.id')
            ->first();
    }

    private function findLatestDepositForCast(string $castId): ?object
    {
        return $this->baseDepositQuery()
            ->where('casts.id', $castId)
            ->orderByDesc('application_deposits.id')
            ->first();
    }

    private function getLatestEligibleApplicationForCast(string $castId): ?object
    {
        return DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shops', 'shop_jobs.shop_id', '=', 'shops.id')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->where('shop_job_applications.cast_id', $castId)
            ->where('shop_job_applications.status', 4)
            ->orderByDesc('shop_job_applications.id')
            ->select(array_merge(
                ['shop_job_applications.*'],
                $this->shopJobExtraColumnsForApplicationBilling(),
                ['shop_profiles.shop_name']
            ))
            ->first();
    }

    /**
     * 指定IDの採用済み応募を取得（キャスト本人のものに限定）
     */
    public function getEligibleApplicationForCastById(string $castId, int $applicationId): ?object
    {
        $row = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shops', 'shop_jobs.shop_id', '=', 'shops.id')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->where('shop_job_applications.id', $applicationId)
            ->where('shop_job_applications.cast_id', $castId)
            ->where('shop_job_applications.status', 4)
            ->select(array_merge(
                ['shop_job_applications.*'],
                $this->shopJobExtraColumnsForApplicationBilling(),
                ['shop_profiles.shop_name']
            ))
            ->first();

        return $row ?: null;
    }

    /**
     * 指定応募の入金申請用ターゲット（採用時点のボーナス焼き付けを含む）を返す
     */
    public function getRequestTargetByApplicationId(string $castId, int $applicationId): ?array
    {
        $application = $this->getEligibleApplicationForCastById($castId, $applicationId);
        return $application ? $this->buildCastDepositRequestTarget($application) : null;
    }

    /**
     * 指定店舗の採用済み応募の入金申請用ターゲットを返す（そのキャストの最新1件）
     */
    public function getRequestTargetByCastAndShopId(string $castId, string $shopId): ?array
    {
        $application = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shops', 'shop_jobs.shop_id', '=', 'shops.id')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->where('shop_job_applications.cast_id', $castId)
            ->where('shop_jobs.shop_id', $shopId)
            ->where('shop_job_applications.status', 4)
            ->orderByDesc('shop_job_applications.id')
            ->select(array_merge(
                ['shop_job_applications.*'],
                $this->shopJobExtraColumnsForApplicationBilling(),
                ['shop_profiles.shop_name']
            ))
            ->first();
        return $application ? $this->buildCastDepositRequestTarget($application) : null;
    }

    /**
     * レビューのみ投稿し、ボーナス条件達成確認用の request_target を返す
     */
    public function submitReviewOnly(string $castId, int $applicationId, array $payload): array
    {
        $application = $this->getEligibleApplicationForCastById($castId, $applicationId);
        if (!$application) {
            return ['success' => false, 'message' => '対象の採用案件が見つかりません。'];
        }
        $existingDeposit = DB::table('application_deposits')
            ->where('shop_job_application_id', $application->id)
            ->exists();
        if ($existingDeposit) {
            return ['success' => false, 'message' => 'この案件の入金申請はすでに登録済みです。'];
        }
        $existingReview = $this->findExistingReviewForApplication($application);
        if ($existingReview) {
            $target = $this->buildCastDepositRequestTarget($application);
            return ['success' => true, 'message' => 'レビューはすでに投稿済みです。', 'request_target' => $target];
        }
        $validation = $this->validateReviewPayload($payload);
        if (!$validation['success']) {
            return $validation;
        }
        $this->createReviewForApplication($application, $payload);
        $requestTarget = $this->buildCastDepositRequestTarget($application);
        return ['success' => true, 'message' => 'レビューを投稿しました。', 'request_target' => $requestTarget];
    }

    private function buildCastDepositRequestTarget(object $application): array
    {
        $reviewContents = $this->orderedReviewContentsQuery()
            ->get(['id', DB::raw($this->reviewContentColumn() . ' as name')])
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
            ])
            ->all();

        $meta = $this->decodeJobMeta($application->noruma_cond ?? null);
        $meta = $this->mergeShopJobBonusMetaFromRow($application, $meta);
        $meta = $this->mergeBonusMetaFromApplicationApplied($application, $meta);
        $bonusAmount = $this->resolveApplicationBonusAmount($application);
        $bonusCondition = $this->resolveApplicationBonusCondition($application, $meta);
        $bonusMeta = [
            'bonus_amount' => $bonusAmount,
            'working_days' => isset($meta['working_days']) ? (string) $meta['working_days'] : '',
            'working_hours' => isset($meta['working_hours']) ? (string) $meta['working_hours'] : '',
            'extra_condition' => $bonusCondition,
        ];
        $existingReview = $this->findExistingReviewForApplication($application);
        $existingReviewDetails = [];

        if ($existingReview) {
            $existingReviewDetails = $this->orderReviewContentJoin(
                DB::table('review_details')
                    ->join('review_contents', 'review_details.' . $this->reviewDetailContentColumn(), '=', 'review_contents.id')
                    ->where('review_details.review_id', $existingReview->id)
            )
                ->get([
                    DB::raw('review_contents.' . $this->reviewContentColumn() . ' as name'),
                    'review_details.score',
                ])
                ->map(fn (object $row) => [
                    'name' => $row->name,
                    'score' => (float) $row->score,
                ])
                ->all();
        }

        $jobKind = trim((string) ($application->talk_job_kind ?? ''));
        $helpHourlyWage = $jobKind === 'help' ? $this->resolveHelpHourlyWage($application) : 0;

        return [
            'application_id' => (int) $application->id,
            'shop_id' => $application->shop_id,
            'shop_name' => $application->shop_name ?: $application->shop_id,
            'job_kind' => $jobKind,
            'help_hourly_wage' => $helpHourlyWage,
            'help_invoice_amount' => $helpHourlyWage > 0 ? (int) round($helpHourlyWage * self::HELP_INVOICE_RATE) : 0,
            'bonus_amount' => $bonusAmount,
            'bonus_condition' => $bonusCondition,
            'bonus_meta' => $bonusMeta,
            'review_contents' => $reviewContents,
            'review_exists' => $existingReview !== null,
            'review_comment' => $existingReview->contents ?? '',
            'review_posted_at' => !empty($existingReview->created_at)
                ? Carbon::parse($existingReview->created_at)->format('Y-m-d H:i')
                : null,
            'review_average' => isset($existingReview->eva) ? (float) $existingReview->eva : null,
            'review_details' => $existingReviewDetails,
            'hired_hourly_wage' => ShopJobApplicationView::wageAtHire($application),
        ];
    }

    private function findExistingReviewForApplication(object $application): ?object
    {
        return $this->findLatestReviewForCastShop((string) $application->cast_id, (string) $application->shop_id);
    }

    /** 採用時点の焼き付けがあればそれを、なければ求人から取得 */
    private function resolveApplicationBonusAmount(object $application): int
    {
        if ($this->isHelpApplicationRow($application)) {
            return (int) round($this->resolveHelpHourlyWage($application) * self::HELP_CAST_BACK_RATE);
        }
        if (isset($application->hired_bonus_amount) && $application->hired_bonus_amount !== null && $application->hired_bonus_amount !== '') {
            return (int) $application->hired_bonus_amount;
        }
        if (property_exists($application, 'applied_bonus_reward') && $application->applied_bonus_reward !== null) {
            return (int) $application->applied_bonus_reward;
        }

        return (int) (
            $application->bonus_reward
            ?? $application->noruma_reward
            ?? $application->regular_hourly_wage
            ?? 0
        );
    }

    /** 採用時点の焼き付けがあればそれを、なければ求人metaから取得 */
    private function resolveApplicationBonusCondition(object $application, array $meta): string
    {
        if (isset($application->hired_bonus_condition) && $application->hired_bonus_condition !== null && trim((string) $application->hired_bonus_condition) !== '') {
            return trim((string) $application->hired_bonus_condition);
        }
        if (property_exists($application, 'applied_bonus_condition') && $application->applied_bonus_condition !== null && trim((string) $application->applied_bonus_condition) !== '') {
            return trim((string) $application->applied_bonus_condition);
        }

        return trim((string) ($meta['bonus_condition'] ?? ''));
    }

    private function findLatestReviewForCastShop(string $castId, string $shopId): ?object
    {
        return DB::table('reviews')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->orderByDesc('id')
            ->first();
    }

    private function buildShopApprovalTarget(array $deposit): array
    {
        $review = $this->findLatestReviewForCastShop((string) $deposit['cast_id'], (string) $deposit['shop_id']);
        $reviewDetails = [];

        if ($review) {
            $reviewDetails = $this->orderReviewContentJoin(
                DB::table('review_details')
                ->join('review_contents', 'review_details.' . $this->reviewDetailContentColumn(), '=', 'review_contents.id')
                ->where('review_details.review_id', $review->id)
            )
                ->get([
                    DB::raw('review_contents.' . $this->reviewContentColumn() . ' as name'),
                    'review_details.score',
                ])
                ->map(fn (object $row) => [
                    'name' => $row->name,
                    'score' => (float) $row->score,
                ])
                ->all();
        }

        $meta = $this->decodeJobMeta($deposit['noruma_cond'] ?? null);
        $bonusConditionLine = trim((string) ($deposit['bonus_condition'] ?? $meta['bonus_condition'] ?? ''));

        return [
            'review_id' => $review->id ?? null,
            'application_id' => $deposit['application_id'],
            'cast_name' => $deposit['cast_name'],
            'job_kind' => trim((string) ($deposit['job_kind'] ?? '')),
            'help_hourly_wage' => (int) ($deposit['help_hourly_wage'] ?? 0),
            'invoice_amount' => (int) ($deposit['invoice_amount'] ?? 0),
            'bonus_amount' => (int) ($deposit['bonus_amount'] ?? 0),
            'bonus_condition' => $bonusConditionLine,
            'requested_at' => $deposit['updated_at_label'] ?? null,
            'review_comment' => $review->contents ?? '',
            'review_average' => isset($review->eva) ? (float) $review->eva : null,
            'review_posted_at' => !empty($review->created_at)
                ? Carbon::parse($review->created_at)->format('Y-m-d H:i')
                : null,
            'review_details' => $reviewDetails,
        ];
    }

    private function validateReviewPayload(array $payload): array
    {
        $comment = trim((string) ($payload['review_comment'] ?? ''));
        $requiredContentIds = $this->orderedReviewContentsQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $scores = collect($payload['review_scores'] ?? [])
            ->mapWithKeys(fn ($score, $contentId) => [(int) $contentId => (int) $score])
            ->filter(fn ($score, $contentId) => $contentId > 0 && $score >= 1 && $score <= 5);

        if ($comment === '') {
            return ['success' => false, 'message' => '入金申請の前にレビューコメントを入力してください。'];
        }

        if (empty($requiredContentIds)) {
            return ['success' => false, 'message' => 'レビュー設問マスタが未設定です。管理者にお問い合わせください。'];
        }

        foreach ($requiredContentIds as $contentId) {
            if (!$scores->has($contentId)) {
                return ['success' => false, 'message' => 'レビュー評価をすべて入力してから申請してください。'];
            }
        }

        return ['success' => true];
    }

    private function createReviewForApplication(object $application, array $payload): void
    {
        $scores = collect($payload['review_scores'] ?? [])
            ->mapWithKeys(fn ($score, $contentId) => [(int) $contentId => (int) $score])
            ->filter(fn ($score, $contentId) => $contentId > 0 && $score >= 1 && $score <= 5);

        $reviewId = DB::table('reviews')->insertGetId([
            'cast_id' => $application->cast_id,
            'shop_id' => $application->shop_id,
            'contents' => trim((string) ($payload['review_comment'] ?? '')),
            'eva' => round($scores->avg() ?? 0, 1),
            'created_at' => now(),
        ]);

        $detailRows = $scores->map(fn ($score, $contentId) => [
            'review_id' => $reviewId,
            $this->reviewDetailContentColumn() => (int) $contentId,
            'score' => $score,
        ])->values()->all();

        if (!empty($detailRows)) {
            DB::table('review_details')->insert($detailRows);
        }

        // レビュー投稿を店舗マネージャーに通知（インボックス＋Push）
        try {
            $castName = (string) ($application->cast_name ?? DB::table('cast_profiles')->where('cast_id', $application->cast_id)->value('nickname') ?? '');
            $displayName = $castName !== '' ? $castName : 'キャスト';
            app(\App\Services\NotificationService::class)->createForShop(
                (string) $application->shop_id,
                'review.posted',
                'キャストからレビューが届きました',
                "{$displayName}さんが勤務レビューを投稿しました。",
                url('/shop/mypage/reviews'),
                ['cast_id' => (string) $application->cast_id, 'shop_id' => (string) $application->shop_id, 'review_id' => $reviewId]
            );
        } catch (\Throwable $e) {
            \Log::warning('Review notify failed: ' . $e->getMessage());
        }
    }

    private function mapDepositRow(object $row): array
    {
        $amounts = $this->calculateAmounts($row);
        $status = (int) $row->status;
        $meta = $this->decodeJobMeta($row->noruma_cond ?? null);
        $meta = $this->mergeShopJobBonusMetaFromRow($row, $meta);
        $review = $this->findLatestReviewForCastShop((string) $row->cast_id, (string) $row->shop_id);
        $reviewDetails = [];

        if ($review) {
            $reviewDetails = $this->orderReviewContentJoin(
                DB::table('review_details')
                ->join('review_contents', 'review_details.' . $this->reviewDetailContentColumn(), '=', 'review_contents.id')
                ->where('review_details.review_id', $review->id)
            )
                ->get([
                    DB::raw('review_contents.' . $this->reviewContentColumn() . ' as name'),
                    'review_details.score',
                ])
                ->map(fn (object $detail) => [
                    'name' => $detail->name,
                    'score' => (float) $detail->score,
                ])
                ->all();
        }

        return [
            'id' => (int) $row->id,
            'shop_job_application_id' => isset($row->shop_job_application_id) ? (int) $row->shop_job_application_id : null,
            'status_code' => $status,
            'status_label' => $this->statusLabel($status),
            'job_kind' => trim((string) ($row->talk_job_kind ?? '')),
            'help_hourly_wage' => $this->isHelpApplicationRow($row) ? $this->resolveHelpHourlyWage($row) : 0,
            'shop_id' => $row->shop_id,
            'shop_name' => $row->shop_name ?: $row->shop_id,
            'shop_address' => trim(implode(' ', array_filter([
                $row->shop_pref ?? '',
                $row->shop_city ?? '',
                $row->shop_addr2 ?? '',
                $row->shop_addr3 ?? '',
            ]))),
            'shop_email' => $row->shop_email,
            'cast_id' => $row->cast_id,
            'cast_name' => $this->castName($row),
            'cast_email' => $row->cast_email,
            'invoice_number' => $row->invoice_number,
            'bonus_amount' => $amounts['bonus_amount'],
            'system_fee_amount' => $amounts['system_fee_amount'],
            'invoice_amount' => $amounts['invoice_amount'],
            'cast_transfer_amount' => $amounts['cast_transfer_amount'],
            'invoice_issued_at' => $this->formatDateTime($row->invoice_issued_at),
            'invoice_due_date' => $row->invoice_due_date ? Carbon::parse($row->invoice_due_date)->format('Y-m-d') : null,
            'shop_payment_reported_at' => $this->formatDateTime($row->shop_payment_reported_at),
            'shop_payment_reported_at_form' => $row->shop_payment_reported_at
                ? Carbon::parse($row->shop_payment_reported_at)->format('Y-m-d\TH:i')
                : null,
            'shop_payment_reported_amount' => (int) ($row->shop_payment_reported_amount ?? 0),
            'shop_payment_reference' => $row->shop_payment_reference,
            'shop_payment_confirmed_at' => $this->formatDateTime($row->shop_payment_confirmed_at),
            'cast_transferred_at' => $this->formatDateTime($row->cast_transferred_at),
            'cast_transfer_reference' => $row->cast_transfer_reference,
            'cast_transfer_note' => $row->cast_transfer_note,
            'completed_at' => $this->formatDateTime($row->completed_at),
            'flow' => $this->flowState($status),
            'next_action' => $this->nextActionLabel($status),
            'has_cast_bank' => !empty($row->cast_bank_id),
            'has_shop_bank' => !empty($row->shop_bank_id),
            'updated_at_label' => $this->formatDateTime($row->updated_at),
            'noruma_cond' => $row->noruma_cond ?? null,
            'bonus_condition' => trim((string) ($meta['bonus_condition'] ?? '')),
            'review_comment' => $review->contents ?? '',
            'review_average' => isset($review->eva) ? (float) $review->eva : null,
            'review_posted_at' => !empty($review->created_at)
                ? Carbon::parse($review->created_at)->format('Y-m-d H:i')
                : null,
            'review_details' => $reviewDetails,
        ];
    }

    private function calculateAmounts(object $row): array
    {
        if ($this->isHelpApplicationRow($row)) {
            $helpWage = $this->resolveHelpHourlyWage($row);
            $castBack = (int) round($helpWage * self::HELP_CAST_BACK_RATE);
            $bonusAmount = (int) ($row->bonus_amount ?? $castBack);
            $invoiceAmount = (int) ($row->invoice_amount ?? round($helpWage * self::HELP_INVOICE_RATE));
            $systemFeeAmount = (int) ($row->system_fee_amount ?? max(0, $invoiceAmount - $bonusAmount));
            $castTransferAmount = (int) ($row->cast_transfer_amount ?? $bonusAmount);

            return [
                'bonus_amount' => $bonusAmount,
                'system_fee_amount' => $systemFeeAmount,
                'invoice_amount' => $invoiceAmount,
                'cast_transfer_amount' => $castTransferAmount,
            ];
        }

        $bonusAmount = (int) ($row->bonus_amount
            ?? $row->hired_bonus_amount
            ?? $row->applied_bonus_reward
            ?? $row->bonus_reward
            ?? $row->noruma_reward
            ?? $row->regular_hourly_wage
            ?? $row->hourly_wage_regular
            ?? 0);

        $systemFeeAmount = (int) ($row->system_fee_amount ?? round($bonusAmount * self::SYSTEM_FEE_RATE));
        $invoiceAmount = (int) ($row->invoice_amount ?? ($bonusAmount + $systemFeeAmount));
        $castTransferAmount = (int) ($row->cast_transfer_amount ?? $bonusAmount);

        return [
            'bonus_amount' => $bonusAmount,
            'system_fee_amount' => $systemFeeAmount,
            'invoice_amount' => $invoiceAmount,
            'cast_transfer_amount' => $castTransferAmount,
        ];
    }

    /** talk_job_kind = 'help' judged from application/deposit joined row */
    private function isHelpApplicationRow(object $row): bool
    {
        return trim((string) ($row->talk_job_kind ?? '')) === 'help';
    }

    /** Help hourly wage: application-time snapshot first, then current job value */
    private function resolveHelpHourlyWage(object $row): int
    {
        foreach (['applied_help_hourly_wage', 'help_hourly_wage'] as $col) {
            if (!property_exists($row, $col) || $row->{$col} === null) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $row->{$col});
            if ($digits !== '') {
                return (int) $digits;
            }
        }

        return 0;
    }

    /**
     * Post audience-scoped auto messages into the cast-shop talk when the
     * deposit status changes. Each row is visible only to its target role
     * (sender_type 3 = cast, 4 = shop) so the shared is_read flag never
     * conflicts between the two viewers. Failures never block the transition.
     *
     * @param array{cast?: string, shop?: string} $texts
     */
    private function postBillingTalkMessages(?string $castId, ?string $shopId, int $status, array $texts): void
    {
        if (!$castId || !$shopId || !Schema::hasTable('messages')) {
            return;
        }

        try {
            $senderByAudience = [
                'cast' => Message::SENDER_SYSTEM_TO_CAST,
                'shop' => Message::SENDER_SYSTEM_TO_SHOP,
            ];

            foreach ($senderByAudience as $audience => $senderType) {
                $text = trim((string) ($texts[$audience] ?? ''));
                if ($text === '') {
                    continue;
                }

                Message::create([
                    'cast_id' => $castId,
                    'shop_id' => $shopId,
                    'sender_type' => $senderType,
                    'type' => Message::TYPE_BILLING_SYSTEM,
                    'content' => json_encode([
                        'kind' => 'billing_deposit',
                        'deposit_status' => $status,
                        'title' => self::BILLING_TALK_TITLES[$status] ?? '入金手続きの進捗',
                        'text' => $text,
                    ], JSON_UNESCAPED_UNICODE),
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Billing talk message post failed: ' . $e->getMessage());
        }
    }

    private function castTransferredTalkText(?object $deposit): string
    {
        $amount = (int) ($deposit->cast_transfer_amount ?? 0);
        $label = ($deposit !== null && $this->isHelpApplicationRow($deposit)) ? 'ヘルプ勤務分の報酬' : '採用ボーナス';

        return ($amount > 0 ? $label . ' ¥' . number_format($amount) . ' の振込が完了しました。' : $label . 'の振込が完了しました。')
            . '入金をご確認のうえ、採用・入金管理から受取確認をお願いします。';
    }

    private function appendHistory(int $depositId, int $status): void
    {
        DB::table('application_deposit_histories')->insert([
            'application_deposit_id' => $depositId,
            'status' => $status,
            'status_date' => now(),
            'created_at' => now(),
        ]);
    }

    private function sendInvoiceMail(int $depositId, string $shopEmail): bool
    {
        $invoice = $this->getInvoiceData($depositId);

        if (!$invoice || !view()->exists('emails.shop-invoice-issued')) {
            return false;
        }

        try {
            Mail::send('emails.shop-invoice-issued', [
                'invoice' => $invoice,
                'invoiceUrl' => $this->getSignedInvoiceUrl($depositId),
            ], function ($message) use ($shopEmail, $invoice) {
                $message->to($shopEmail)
                    ->subject('【ミセチョク】請求書を発行しました: ' . $invoice['invoice_number']);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send invoice mail.', [
                'deposit_id' => $depositId,
                'shop_email' => $shopEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function generateInvoiceNumber(int $depositId, Carbon $issuedAt): string
    {
        return sprintf('INV-%s-%04d', $issuedAt->format('Ym'), $depositId);
    }

    private function castName(object $deposit): string
    {
        return $deposit->cast_nickname ?: ($deposit->cast_full_name ?: $deposit->cast_id);
    }

    private function nextActionLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_CAST_REQUESTED => '店舗承認待ち',
            self::STATUS_SHOP_APPROVED => '請求書発行',
            self::STATUS_INVOICE_ISSUED => '店舗の入金報告待ち',
            self::STATUS_SHOP_PAYMENT_REPORTED => '着金照合',
            self::STATUS_SHOP_PAYMENT_CONFIRMED => 'キャスト振込',
            self::STATUS_CAST_TRANSFERRED => 'キャスト確認待ち',
            self::STATUS_COMPLETED => '完了',
            default => '未着手',
        };
    }

    private function normalizeBankAccount(?object $bank): array
    {
        return [
            'exists' => $bank !== null,
            'bank_code' => $bank->bank_code ?? '',
            'bank_name' => $bank->bank_name ?? '',
            'bank_name_kana' => $bank->bank_name_kana ?? '',
            'branch_code' => $bank->branch_code ?? '',
            'branch_name' => $bank->branch_name ?? '',
            'branch_name_kana' => $bank->branch_name_kana ?? '',
            'account_type' => $bank->account_type ?? 'ordinary',
            'account_number' => $bank->account_number ?? '',
            'account_holder_name' => $bank->account_holder_name ?? $bank->account_name ?? '',
            'account_name' => $bank->account_name ?? '',
            'account_type_label' => $this->accountTypeLabel($bank->account_type ?? 'ordinary'),
        ];
    }

    private function accountTypeLabel(string $accountType): string
    {
        return match ($accountType) {
            'current', 'checking' => '当座',
            default => '普通',
        };
    }

    private function formatDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function decodeJobMeta(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function orderedReviewContentsQuery()
    {
        $query = DB::table('review_contents');

        if (Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'del_flg')) {
            $query->where('del_flg', 0);
        } elseif (Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('id');
    }

    private function orderReviewContentJoin($query)
    {
        if (Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'sort_order')) {
            $query->orderBy('review_contents.sort_order');
        }

        return $query->orderBy('review_contents.id');
    }

    private function reviewContentColumn(): string
    {
        return Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'content')
            ? 'content'
            : 'name';
    }

    private function reviewDetailContentColumn(): string
    {
        if (Schema::hasTable('review_details') && Schema::hasColumn('review_details', 'val')) {
            return 'val';
        }

        return 'review_content_id';
    }

    private function filterExistingColumns(string $table, array $payload): array
    {
        if (!Schema::hasTable($table)) {
            return $payload;
        }

        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    private function bankAccountSelectColumns(string $table): array
    {
        $columns = [
            'id',
            'holder_type',
            'holder_id',
            'bank_code',
            'bank_name',
            'bank_name_kana',
            'branch_code',
            'branch_name',
            'branch_name_kana',
            'account_type',
            'account_number',
            'account_name',
        ];

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'account_holder_name')) {
            $columns[] = 'account_holder_name';
        } else {
            $columns[] = DB::raw('account_name as account_holder_name');
        }

        return $columns;
    }

    private function normalizeBankAccountRecord(?object $account): ?object
    {
        if (!$account) {
            return null;
        }

        $account->account_holder_name = $account->account_holder_name ?? $account->account_name ?? '';
        $account->account_type = $account->account_type === 'checking'
            ? 'current'
            : $account->account_type;

        return $account;
    }

    private function getHolderBankAccount(string $holderType, string $holderId): ?object
    {
        $account = DB::table('bank_accounts')
            ->select($this->bankAccountSelectColumns('bank_accounts'))
            ->where('holder_type', $holderType)
            ->where('holder_id', $holderId)
            ->first();

        return $this->normalizeBankAccountRecord($account);
    }

    private function saveHolderBankAccount(string $holderType, string $holderId, array $data): void
    {
        $normalized = $this->normalizeBankAccountData($data);

        DB::table('bank_accounts')->updateOrInsert(
            [
                'holder_type' => $holderType,
                'holder_id' => $holderId,
            ],
            $this->filterExistingColumns('bank_accounts', [
                'bank_code' => $normalized['bank_code'],
                'bank_name' => $normalized['bank_name'],
                'bank_name_kana' => $normalized['bank_name_kana'],
                'branch_code' => $normalized['branch_code'],
                'branch_name' => $normalized['branch_name'],
                'branch_name_kana' => $normalized['branch_name_kana'],
                'account_type' => $normalized['account_type'],
                'account_number' => $normalized['account_number'],
                'account_name' => $normalized['account_name'],
                'updated_at' => now(),
                'created_at' => now(),
            ])
        );
    }

    private function resolveAdminHolderId(): string
    {
        $authenticatedUserId = auth()->guard('admin')->id();

        if (!empty($authenticatedUserId)) {
            return (string) $authenticatedUserId;
        }

        return (string) DB::table('system_accounts')
            ->where('role', SystemAccount::ROLE_ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    private function optionalDepositSelects(): array
    {
        $optionalColumns = [
            'invoice_number',
            'bonus_amount',
            'system_fee_amount',
            'invoice_amount',
            'cast_transfer_amount',
            'invoice_issued_at',
            'invoice_due_date',
            'invoice_sent_at',
            'shop_payment_reported_at',
            'shop_payment_reported_amount',
            'shop_payment_reference',
            'shop_payment_confirmed_at',
            'cast_transferred_at',
            'cast_transfer_reference',
            'cast_transfer_note',
            'completed_at',
            'invoice_display_shop_name',
            'invoice_display_shop_address',
            'invoice_display_shop_email',
            'invoice_display_cast_name',
        ];

        return array_map(function (string $column) {
            if (Schema::hasTable('application_deposits') && Schema::hasColumn('application_deposits', $column)) {
                return 'application_deposits.' . $column;
            }

            return DB::raw('NULL as ' . $column);
        }, $optionalColumns);
    }
}
