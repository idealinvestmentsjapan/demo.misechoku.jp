<?php

namespace App\Services;

use App\Models\ShopPlanSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Premiumプラン（店舗向け）の契約・入金確認・機能ゲート。
 *
 * 運用フロー:
 *   ①店舗が契約 → 入金待ちレコード作成 + 振込先/金額/期限を案内（請求書発行可）
 *   ②店舗が振込
 *   ③運営がネットバンキング明細を目視確認 → 管理画面で「入金確認済み」
 *   → システムが Premium 機能を開放（領収書発行可）
 */
class PlanSubscriptionService
{
    /** プラン料金（税込） */
    public const PRICES = [
        ShopPlanSubscription::CYCLE_MONTHLY => 29000,
        ShopPlanSubscription::CYCLE_YEARLY  => 290000,
    ];

    /** 振込期限（契約から日数） */
    public const PAYMENT_DUE_DAYS = 7;

    /** スカウト（既存やりとりが無いキャストへの新規送信）の1日上限 */
    public const SCOUT_LIMIT_FREE    = 5;
    public const SCOUT_LIMIT_PREMIUM = 30;

    private function tableReady(): bool
    {
        return Schema::hasTable('shop_plan_subscriptions');
    }

    /* ============================================================
       照会
       ============================================================ */

    /** 有効な Premium 契約（期限切れは遅延評価で expired に落とす） */
    public function activeFor(string $shopId): ?ShopPlanSubscription
    {
        if ($shopId === '' || !$this->tableReady()) {
            return null;
        }

        $sub = ShopPlanSubscription::query()
            ->where('shop_id', $shopId)
            ->where('status', ShopPlanSubscription::STATUS_ACTIVE)
            ->orderByDesc('ends_at')
            ->first();

        if ($sub === null) {
            return null;
        }

        if ($sub->ends_at !== null && $sub->ends_at->isPast()) {
            $sub->update(['status' => ShopPlanSubscription::STATUS_EXPIRED]);
            return null;
        }

        return $sub;
    }

    /** 入金待ちの契約 */
    public function pendingFor(string $shopId): ?ShopPlanSubscription
    {
        if ($shopId === '' || !$this->tableReady()) {
            return null;
        }

        return ShopPlanSubscription::query()
            ->where('shop_id', $shopId)
            ->where('status', ShopPlanSubscription::STATUS_PENDING_PAYMENT)
            ->orderByDesc('id')
            ->first();
    }

    public function isPremium(string $shopId): bool
    {
        return $this->activeFor($shopId) !== null;
    }

    /**
     * 複数店舗のうち Premium 有効な店舗IDだけを返す（検索の優先表示用）。
     *
     * @param  array<int, string>  $shopIds
     * @return array<string, true>  shop_id => true
     */
    public function premiumShopIdMap(array $shopIds): array
    {
        $shopIds = array_values(array_filter(array_map('strval', $shopIds)));
        if ($shopIds === [] || !$this->tableReady()) {
            return [];
        }

        return ShopPlanSubscription::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', ShopPlanSubscription::STATUS_ACTIVE)
            ->where('ends_at', '>=', now())
            ->pluck('shop_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();
    }

    /* ============================================================
       契約 → 入金確認
       ============================================================ */

    /**
     * プラン契約（入金待ちレコード作成 + 請求書番号採番）。
     * 既に入金待ち or 有効な契約がある場合はそれを返す。
     */
    public function contract(string $shopId, string $cycle): ShopPlanSubscription
    {
        $cycle = $cycle === ShopPlanSubscription::CYCLE_YEARLY
            ? ShopPlanSubscription::CYCLE_YEARLY
            : ShopPlanSubscription::CYCLE_MONTHLY;

        if ($existing = $this->pendingFor($shopId)) {
            return $existing;
        }
        if ($active = $this->activeFor($shopId)) {
            return $active;
        }

        $now = now();

        $sub = ShopPlanSubscription::create([
            'shop_id' => $shopId,
            'plan' => ShopPlanSubscription::PLAN_PREMIUM,
            'billing_cycle' => $cycle,
            'amount' => self::PRICES[$cycle],
            'status' => ShopPlanSubscription::STATUS_PENDING_PAYMENT,
            'invoice_issued_at' => $now,
            'payment_due_date' => $now->copy()->addDays(self::PAYMENT_DUE_DAYS)->toDateString(),
        ]);

        $sub->update(['invoice_number' => $this->generateInvoiceNumber((int) $sub->id, $now)]);

        return $sub->refresh();
    }

    /** 入金前のキャンセル */
    public function cancelPending(ShopPlanSubscription $sub): void
    {
        if ((int) $sub->status === ShopPlanSubscription::STATUS_PENDING_PAYMENT) {
            $sub->update(['status' => ShopPlanSubscription::STATUS_CANCELED]);
        }
    }

    /**
     * 運営の入金確認 → Premium 有効化 + 領収書番号採番。
     */
    public function confirmPayment(ShopPlanSubscription $sub, string $adminId): ShopPlanSubscription
    {
        if ((int) $sub->status !== ShopPlanSubscription::STATUS_PENDING_PAYMENT) {
            return $sub;
        }

        $now = now();
        $ends = $sub->billing_cycle === ShopPlanSubscription::CYCLE_YEARLY
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $sub->update([
            'status' => ShopPlanSubscription::STATUS_ACTIVE,
            'paid_confirmed_at' => $now,
            'confirmed_by' => $adminId,
            'starts_at' => $now,
            'ends_at' => $ends,
            'receipt_number' => $this->generateReceiptNumber((int) $sub->id, $now),
        ]);

        return $sub->refresh();
    }

    public function generateInvoiceNumber(int $id, Carbon $issuedAt): string
    {
        return sprintf('PLN-%s-%04d', $issuedAt->format('Ym'), $id);
    }

    public function generateReceiptNumber(int $id, Carbon $paidAt): string
    {
        return sprintf('RCT-%s-%04d', $paidAt->format('Ym'), $id);
    }

    /* ============================================================
       スカウト上限（既存キャストとのやりとりは除外）
       ============================================================ */

    public function scoutLimitFor(string $shopId): int
    {
        return $this->isPremium($shopId) ? self::SCOUT_LIMIT_PREMIUM : self::SCOUT_LIMIT_FREE;
    }

    /**
     * 店舗→キャストの送信が「スカウト（新規開拓）」かどうか。
     * 双方向いずれかのメッセージが1通でもあれば既存のやりとりとみなす。
     */
    public function isScout(string $shopId, string $castId): bool
    {
        return !DB::table('messages')
            ->where('shop_id', $shopId)
            ->where('cast_id', $castId)
            ->exists();
    }

    /**
     * 本日のスカウト送信数。
     * 「会話の最初のメッセージが今日・店舗発」の会話数を数える。
     */
    public function scoutCountToday(string $shopId): int
    {
        $firsts = DB::table('messages')
            ->selectRaw('cast_id, MIN(created_at) as first_at')
            ->where('shop_id', $shopId)
            ->groupBy('cast_id')
            ->havingRaw('MIN(created_at) >= ?', [now()->startOfDay()->toDateTimeString()])
            ->get();

        if ($firsts->isEmpty()) {
            return 0;
        }

        // 最初のメッセージが店舗発（sender_type=2）の会話のみカウント
        $count = 0;
        foreach ($firsts as $row) {
            $firstSender = DB::table('messages')
                ->where('shop_id', $shopId)
                ->where('cast_id', $row->cast_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->value('sender_type');
            if ((int) $firstSender === 2) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * スカウト送信可否チェック。
     *
     * @return array{allowed: bool, used: int, limit: int, is_premium: bool}
     */
    public function checkScoutQuota(string $shopId): array
    {
        $isPremium = $this->isPremium($shopId);
        $limit = $isPremium ? self::SCOUT_LIMIT_PREMIUM : self::SCOUT_LIMIT_FREE;
        $used = $this->scoutCountToday($shopId);

        return [
            'allowed' => $used < $limit,
            'used' => $used,
            'limit' => $limit,
            'is_premium' => $isPremium,
        ];
    }

    /* ============================================================
       閲覧キャスト一覧（Premium機能）
       ============================================================ */

    /**
     * この店舗（プロフィール/求人）を閲覧したキャストの一覧。
     *
     * @return array<int, object{cast_id: string, view_count: int, last_viewed_at: string}>
     */
    public function recentViewersFor(string $shopId, int $limit = 50): array
    {
        if (!Schema::hasTable('profile_views')) {
            return [];
        }

        return DB::table('profile_views')
            ->selectRaw('viewer_id as cast_id, COUNT(*) as view_count, MAX(created_at) as last_viewed_at')
            ->where('target_type', 'shop')
            ->where('target_id', $shopId)
            ->where('viewer_type', 'cast')
            ->groupBy('viewer_id')
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
