<?php

namespace App\Services;

use App\Models\CastIdentityDocument;
use App\Models\ShopLicenseDocument;
use App\Models\SupportInquiry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 管理画面サイドバー（オペレーション）の未対応バッジ・ヘッダー通知の集計
 */
class AdminOperationalSummaryService
{
    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly DocumentReviewService $documentReviewService
    ) {
    }

    /**
     * ルート名 => 未対応件数（0 のときはバッジ非表示）
     *
     * @return array<string, int>
     */
    public function getOperationBadgeCounts(): array
    {
        $dashboard = $this->billingManagementService->getAdminBillingDashboard();
        $s = $dashboard['summary'];
        $v = $this->documentReviewService->getAdminVerificationData()['summary'];

        // Badges must count ONLY items that require admin action right now.
        // For invoices, that is SHOP_APPROVED (waiting for admin to issue). CAST_REQUESTED
        // is "shop approval pending" and the invoice page itself labels those as
        // "運営の対応は不要", so they must not inflate the "要対応" badge.
        // Plan pending count is folded into the 入金確認 badge because plans are
        // now confirmed on the same screen (kind=plan rows in the unified list).
        $planPending = $this->getPendingPlanPaymentCount();

        return [
            'admin.invoices.index' => (int) ($s['invoice_pending'] ?? 0),
            'admin.deposits.confirmations' => (int) $s['payment_confirmation_pending'] + $planPending,
            'admin.deposits.transfers' => (int) $s['cast_transfer_pending'],
            // Legacy key kept in case some caller still references it.
            'admin.deposits.index' => (int) $s['payment_confirmation_pending'] + (int) $s['cast_transfer_pending'] + $planPending,
            // Legacy plans badge left as 0 so the removed sidebar entry does not resurface via badges.
            'admin.plans.index' => 0,
            'admin.verification.index' => (int) $v['cast_pending'] + (int) $v['shop_pending'],
            'admin.support-inquiries.index' => $this->getPendingInquiryCount(),
            'admin.user_reports.index' => $this->getPendingUserReportCount(),
        ];
    }

    private function getPendingPlanPaymentCount(): int
    {
        if (!Schema::hasTable('shop_plan_subscriptions')) {
            return 0;
        }
        return (int) DB::table('shop_plan_subscriptions')
            ->where('status', 1) // 1 = PENDING_PAYMENT (see ShopPlanSubscription)
            ->count();
    }

    private function getPendingUserReportCount(): int
    {
        if (!Schema::hasTable('user_reports')) {
            return 0;
        }
        // UserReport::STATUS_PENDING = 0; use literal to avoid a hard dependency here.
        return (int) DB::table('user_reports')
            ->where('status', 0)
            ->count();
    }

    /**
     * オペレーション各メニューに対応する「実績」（累計の完了系件数）
     *
     * @return array<string, int>
     */
    public function getOperationAchievementCounts(): array
    {
        // Deposits are split into two screens (入金確認 / キャスト振込) — surface the same
        // "completed deposit flows total" on each so the operation-achievement badge
        // consistently reflects the running total.
        $depositTotal = $this->countDepositFlowsCompletedTotal();
        return [
            'admin.invoices.index' => $this->countInvoicesIssuedTotal(),
            'admin.deposits.index' => $depositTotal,
            'admin.deposits.confirmations' => $depositTotal,
            'admin.deposits.transfers' => $depositTotal,
            'admin.verification.index' => $this->countVerificationProcessedTotal(),
            'admin.support-inquiries.index' => $this->countInquiriesResolvedTotal(),
        ];
    }

    private function countInvoicesIssuedTotal(): int
    {
        if (! Schema::hasTable('application_deposits')) {
            return 0;
        }

        return (int) DB::table('application_deposits')
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->count();
    }

    private function countDepositFlowsCompletedTotal(): int
    {
        if (! Schema::hasTable('application_deposits')) {
            return 0;
        }

        return (int) DB::table('application_deposits')
            ->where('status', BillingManagementService::STATUS_COMPLETED)
            ->count();
    }

    private function countVerificationProcessedTotal(): int
    {
        $cast = 0;
        $shop = 0;

        if (Schema::hasTable('cast_identity_documents')) {
            $cast = (int) CastIdentityDocument::query()
                ->whereIn('status', [
                    CastIdentityDocument::STATUS_APPROVED,
                    CastIdentityDocument::STATUS_REJECTED,
                ])
                ->count();
        }

        if (Schema::hasTable('shop_license_documents')) {
            $shop = (int) ShopLicenseDocument::query()
                ->whereIn('status', [
                    ShopLicenseDocument::STATUS_APPROVED,
                    ShopLicenseDocument::STATUS_REJECTED,
                ])
                ->count();
        }

        return $cast + $shop;
    }

    private function countInquiriesResolvedTotal(): int
    {
        if (!Schema::hasTable('support_inquiries')) {
            return 0;
        }

        return (int) SupportInquiry::query()
            ->whereIn('status', [SupportInquiry::STATUS_RESOLVED, SupportInquiry::STATUS_DISMISSED])
            ->count();
    }

    /**
     * レイアウト用：通知一覧（先頭 N 件）と総件数を一度に取得
     *
     * @return array{items: array<int, array{title: string, time_label: string, icon: string, class: string, url: string}>, total_count: int}
     */
    public function getNotificationsForLayout(int $listLimit = 30): array
    {
        $all = $this->buildNotifications(500);

        return [
            'items' => array_slice($all, 0, $listLimit),
            'total_count' => count($all),
        ];
    }

    /**
     * ヘッダー「お知らせ」用：ログイン中 admin 宛の個人通知（notifications テーブル）。
     *
     * @return array{items: array<int, array<string, mixed>>, unread: int}
     */
    public function getInboxForLayout(int $limit = 20): array
    {
        $adminId = (string) (auth()->guard('admin')->id() ?? '');
        if ($adminId === '' || ! Schema::hasTable('notifications')) {
            return ['items' => [], 'unread' => 0];
        }

        $rows = DB::table('notifications')
            ->where('user_type', 'admin')
            ->where('user_id', $adminId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'url', 'read_at', 'created_at']);

        $unread = (int) DB::table('notifications')
            ->where('user_type', 'admin')
            ->where('user_id', $adminId)
            ->whereNull('read_at')
            ->count();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'url' => $row->url ?: route('admin.dashboard'),
                'is_unread' => $row->read_at === null,
                'time_label' => $row->created_at ? Carbon::parse($row->created_at)->format('m/d H:i') : '',
            ];
        }

        return ['items' => $items, 'unread' => $unread];
    }

    /**
     * @return array<int, array{title: string, time_label: string, icon: string, class: string, url: string, sort: int}>
     */
    private function buildNotifications(int $maxBillingTasks): array
    {
        $items = [];
        $dashboard = $this->billingManagementService->getAdminBillingDashboard();
        $summary = $dashboard['summary'];

        foreach (array_slice($this->billingManagementService->getPendingTasks(), 0, $maxBillingTasks) as $task) {
            $items[] = $this->mapBillingTaskToNotification($task);
        }

        foreach ($this->documentReviewService->getDashboardTasks() as $task) {
            $items[] = $this->mapDocumentTaskToNotification($task);
        }

        if ((int) ($summary['unconfirmed_cast_over_7days'] ?? 0) > 0) {
            $n = (int) $summary['unconfirmed_cast_over_7days'];
            $items[] = [
                'title' => 'キャストの入金確認が7日以上未完了の案件が' . $n . '件あります',
                'time_label' => '要フォロー',
                'icon' => 'fa-triangle-exclamation',
                'class' => 'is-danger',
                'url' => route('admin.deposits.transfers'),
                'sort' => 920,
            ];
        }

        foreach ($this->buildInquiryNotifications() as $row) {
            $items[] = $row;
        }

        usort($items, fn ($a, $b) => ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));

        return array_map(fn (array $row) => [
            'title' => $row['title'],
            'time_label' => $row['time_label'],
            'icon' => $row['icon'],
            'class' => $row['class'],
            'url' => $row['url'],
        ], $items);
    }

    private function getPendingInquiryCount(): int
    {
        if (!Schema::hasTable('support_inquiries')) {
            return 0;
        }

        return (int) SupportInquiry::query()
            ->where('status', SupportInquiry::STATUS_NEW)
            ->count();
    }

    /**
     * @return array<int, array{title: string, time_label: string, icon: string, class: string, url: string, sort: int}>
     */
    private function buildInquiryNotifications(): array
    {
        if (!Schema::hasTable('support_inquiries')) {
            return [];
        }

        return SupportInquiry::query()
            ->where('status', SupportInquiry::STATUS_NEW)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (SupportInquiry $row) {
                $created = Carbon::parse($row->created_at ?? now());
                $fromName = trim((string) ($row->from_name ?? $row->name ?? ''));
                $subject = trim((string) ($row->subject ?? ''));

                return [
                    'title' => '[問合せ] ' . $fromName . ' — ' . $subject,
                    'time_label' => $created->diffForHumans(),
                    'icon' => 'fa-comments',
                    'class' => 'is-warning',
                    'url' => route('admin.support-inquiries.index'),
                    'sort' => 400,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, time_label: string, icon: string, class: string, url: string, sort: int}
     */
    private function mapBillingTaskToNotification(array $task): array
    {
        $id = (int) ($task['id'] ?? 0);
        $shop = trim((string) ($task['shop_name'] ?? ''));
        $cast = trim((string) ($task['cast_name'] ?? ''));
        $label = $shop !== '' ? $shop : ($cast !== '' ? $cast : '案件');
        $title = trim((string) ($task['task_title'] ?? '対応')) . '：' . $label;

        $dueRaw = $task['task_due_date'] ?? null;
        $due = $dueRaw ? Carbon::parse($dueRaw) : null;
        $now = Carbon::now();

        $class = 'is-gold';
        $sort = 300;
        $timeLabel = '期限未設定';

        if ($due) {
            $timeLabel = '期限 ' . $due->format('n/j H:i') . '（' . $due->diffForHumans() . '）';

            if ($due->lt($now)) {
                $class = 'is-danger';
                $sort = 900;
            } elseif ($due->lte($now->copy()->addDays(3))) {
                $class = 'is-warning';
                $sort = 700;
            }
        }

        // Route the task URL to the appropriate operation screen based on status:
        //   確認フェーズ（照合待ち・報告前）→ 入金確認
        //   振込フェーズ（confirmed 以降）    → キャスト振込
        //   その他                             → 入金確認（デフォルト）
        $statusCode = (int) ($task['status_code'] ?? 0);
        $defaultUrl = route(
            $statusCode >= BillingManagementService::STATUS_SHOP_PAYMENT_CONFIRMED
                ? 'admin.deposits.transfers'
                : 'admin.deposits.confirmations'
        );

        return [
            'title' => $title,
            'time_label' => $timeLabel,
            'icon' => $this->billingTaskIcon($statusCode),
            'class' => $class,
            'url' => $task['task_url'] ?? $defaultUrl,
            'sort' => $sort,
        ];
    }

    private function billingTaskIcon(int $statusCode): string
    {
        return match ($statusCode) {
            BillingManagementService::STATUS_CAST_REQUESTED => 'fa-inbox',
            BillingManagementService::STATUS_SHOP_APPROVED => 'fa-file-invoice',
            BillingManagementService::STATUS_SHOP_PAYMENT_REPORTED => 'fa-money-bill-wave',
            BillingManagementService::STATUS_SHOP_PAYMENT_CONFIRMED => 'fa-money-bill-wave',
            default => 'fa-triangle-exclamation',
        };
    }

    /**
     * @param array<string, mixed> $task
     * @return array{title: string, time_label: string, icon: string, class: string, url: string, sort: int}
     */
    private function mapDocumentTaskToNotification(array $task): array
    {
        $target = (string) ($task['target'] ?? '');
        $category = (string) ($task['category'] ?? '');
        $title = '[' . $category . '] ' . $target . ' — ' . (string) ($task['status'] ?? '');

        $urgency = (string) ($task['urgency'] ?? 'normal');
        $class = match ($urgency) {
            'critical' => 'is-danger',
            'high' => 'is-warning',
            default => 'is-gold',
        };
        $sort = match ($urgency) {
            'critical' => 850,
            'high' => 650,
            default => 350,
        };

        return [
            'title' => $title,
            'time_label' => (string) ($task['date'] ?? '-'),
            'icon' => ($task['cat_id'] ?? '') === 'kyc' ? 'fa-id-card' : 'fa-file',
            'class' => $class,
            'url' => (string) ($task['url'] ?? route('admin.verification.index')),
            'sort' => $sort,
        ];
    }
}
