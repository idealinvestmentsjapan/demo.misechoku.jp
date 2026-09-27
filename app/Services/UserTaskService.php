<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * キャスト・店舗ユーザー向け「やることリスト」を都度計算するサービス。
 *
 * 永続化しない（テーブル状態から自動判定）。
 * NotificationSpecService::taskCatalog() のキーと整合させる。
 */
class UserTaskService
{
    // -----------------------------------------------------------------
    // キャスト
    // -----------------------------------------------------------------

    /**
     * @return array<int, array{key: string, text: string, url: ?string, urgency: string}>
     */
    public function forCast(string $castId): array
    {
        $tasks = [];

        // (a0) メール未認証
        if ($this->isEmailUnverified('casts', $castId)) {
            $tasks[] = [
                'key'     => 'cast.email_unverified',
                'text'    => 'メールアドレスが未認証です（アカウント設定から再送信できます）',
                'url'     => $this->safeRoute('setting.account'),
                'urgency' => 'normal',
            ];
        }

        // (a) 本人確認書類：承認完了までは常に「未済」としてタスクに出し続ける
        $identityStatus = $this->getCastIdentityStatus($castId);
        if ($identityStatus === 'unsubmitted') {
            $tasks[] = [
                'key'     => 'cast.identity_unsubmitted',
                'text'    => '本人確認書類を提出してください',
                'url'     => $this->safeRoute('cast.mypage.identity'),
                'urgency' => 'high',
            ];
        } elseif ($identityStatus === 'rejected') {
            $tasks[] = [
                'key'     => 'cast.identity_rejected',
                'text'    => '本人確認書類が差戻しされました。再提出してください',
                'url'     => $this->safeRoute('cast.mypage.identity'),
                'urgency' => 'high',
            ];
        } elseif ($identityStatus === 'pending') {
            $tasks[] = [
                'key'     => 'cast.identity_pending',
                'text'    => '本人確認書類を審査中です（承認されるまで一部機能が制限されます）',
                'url'     => $this->safeRoute('cast.mypage.identity'),
                'urgency' => 'normal',
            ];
        }

        // (b) 振込先口座が未登録
        if (!$this->hasBankAccount('casts', $castId)) {
            $tasks[] = [
                'key'     => 'cast.bank_account_unset',
                'text'    => '振込先口座を登録してください（採用ボーナスの振込に必要）',
                'url'     => $this->safeRoute('cast.mypage.index'),
                'urgency' => 'normal',
            ];
        }

        // (c) 未読メッセージ
        $unreadTalks = $this->countUnreadTalks('cast', $castId);
        if ($unreadTalks > 0) {
            $tasks[] = [
                'key'     => 'cast.talk_unread',
                'text'    => "未読メッセージが {$unreadTalks} 件あります",
                'url'     => $this->safeRoute('cast.talk.index'),
                'urgency' => 'normal',
            ];
        }

        // (d) 振込済み案件のキャスト入金確認未完了（5日以上経過）
        $unconfirmedDeposits = $this->countCastUnconfirmedDeposits($castId);
        if ($unconfirmedDeposits > 0) {
            $tasks[] = [
                'key'     => 'cast.deposit_unconfirmed',
                'text'    => "振込済みの採用ボーナスで入金確認が未完了の案件が {$unconfirmedDeposits} 件あります",
                'url'     => $this->safeRoute('cast.mypage.management'),
                'urgency' => 'normal',
            ];
        }

        // (e) 採用済みでボーナス申請が未送信の案件（フローの起点。採用・入金管理から申請する）
        $bonusRequestPending = $this->countCastBonusRequestPending($castId);
        if ($bonusRequestPending > 0) {
            $tasks[] = [
                'key'     => 'cast.bonus_request_pending',
                'text'    => "採用ボーナスの申請ができる案件が {$bonusRequestPending} 件あります",
                'url'     => $this->safeRoute('cast.mypage.management'),
                'urgency' => 'high',
            ];
        }

        return $tasks;
    }

    // -----------------------------------------------------------------
    // 店舗
    // -----------------------------------------------------------------

    /**
     * @return array<int, array{key: string, text: string, url: ?string, urgency: string}>
     */
    public function forShop(string $shopId): array
    {
        $tasks = [];

        // (a0) メール未認証（店舗の場合はマネージャー本人単位で判定）
        $managerId = $this->currentShopManagerId();
        if ($managerId !== null && $this->isEmailUnverified('shop_managers', $managerId)) {
            $tasks[] = [
                'key'     => 'shop.email_unverified',
                'text'    => 'メールアドレスが未認証です（アカウント設定から再送信できます）',
                'url'     => $this->safeRoute('setting.account'),
                'urgency' => 'normal',
            ];
        }

        // (a) 許可書類：全書類が承認されるまで常に「未済」としてタスクに出し続ける
        $licenseStatus = $this->getShopLicenseStatus($shopId);
        $licenseUrl = $this->safeRoute('shop.mypage.index');
        $licenseUrl = $licenseUrl ? $licenseUrl . '#license-section' : null;
        if ($licenseStatus === 'unsubmitted') {
            $tasks[] = [
                'key'     => 'shop.license_unsubmitted',
                'text'    => '営業に必要な許可書類を提出してください',
                'url'     => $licenseUrl,
                'urgency' => 'high',
            ];
        } elseif ($licenseStatus === 'rejected') {
            $tasks[] = [
                'key'     => 'shop.license_rejected',
                'text'    => '許可書類が差戻しされました。再提出してください',
                'url'     => $licenseUrl,
                'urgency' => 'high',
            ];
        } elseif ($licenseStatus === 'pending') {
            $tasks[] = [
                'key'     => 'shop.license_pending',
                'text'    => '許可書類を審査中です（承認されるまで一部機能が制限されます）',
                'url'     => $licenseUrl,
                'urgency' => 'normal',
            ];
        }

        // (b) 振込元口座が未登録
        if (!$this->hasBankAccount('shops', $shopId)) {
            $tasks[] = [
                'key'     => 'shop.bank_account_unset',
                'text'    => '振込元口座を登録してください（採用ボーナスの請求に必要）',
                'url'     => $this->safeRoute('shop.mypage.index'),
                'urgency' => 'normal',
            ];
        }

        // (c) 未読メッセージ
        $unreadTalks = $this->countUnreadTalks('shop', $shopId);
        if ($unreadTalks > 0) {
            $tasks[] = [
                'key'     => 'shop.talk_unread',
                'text'    => "未読メッセージが {$unreadTalks} 件あります",
                'url'     => $this->safeRoute('shop.talk.index'),
                'urgency' => 'normal',
            ];
        }

        // (c2) Premiumプランの振込待ち
        $planPending = app(PlanSubscriptionService::class)->pendingFor($shopId);
        if ($planPending !== null) {
            $due = $planPending->payment_due_date ? $planPending->payment_due_date->format('n月j日') : '';
            $tasks[] = [
                'key'     => 'shop.plan_payment_pending',
                'text'    => 'Premiumプランのお振込をお願いします' . ($due !== '' ? "（期限: {$due}）" : ''),
                'url'     => $this->safeRoute('subscription'),
                'urgency' => 'high',
            ];
        }

        // (d) 入金依頼の承認待ち（店舗にボールがある）
        $pendingApproval = $this->countShopPendingApproval($shopId);
        if ($pendingApproval > 0) {
            $tasks[] = [
                'key'     => 'shop.deposit_pending_approval',
                'text'    => "キャストからの入金依頼が {$pendingApproval} 件、承認待ちです",
                'url'     => $this->safeRoute('shop.mypage.management'),
                'urgency' => 'high',
            ];
        }

        // (e) 請求書が届いて未支払いの案件
        $pendingPayment = $this->countShopPendingPayment($shopId);
        if ($pendingPayment > 0) {
            $tasks[] = [
                'key'     => 'shop.invoice_pending_payment',
                'text'    => "支払い待ちの請求書が {$pendingPayment} 件あります",
                'url'     => $this->safeRoute('shop.mypage.management'),
                'urgency' => 'high',
            ];
        }

        return $tasks;
    }

    // =================================================================
    // 内部ヘルパ
    // =================================================================

    /** @return 'unsubmitted'|'pending'|'approved'|'rejected' */
    private function getCastIdentityStatus(string $castId): string
    {
        if (!Schema::hasTable('cast_identity_documents')) return 'unsubmitted';
        $row = DB::table('cast_identity_documents')
            ->where('cast_id', $castId)
            ->orderByDesc('id')
            ->select('status')
            ->first();
        if (!$row) return 'unsubmitted';
        $s = (int) $row->status;
        return match ($s) {
            2 => 'approved',
            1 => 'pending',
            3 => 'rejected',
            default => 'unsubmitted',
        };
    }

    /** @return 'unsubmitted'|'pending'|'approved'|'rejected' */
    private function getShopLicenseStatus(string $shopId): string
    {
        if (!Schema::hasTable('shop_license_documents')) return 'unsubmitted';
        // 必要な書類のうち、いずれかが未提出ならunsubmitted、差戻しありなら rejected
        $rows = DB::table('shop_license_documents')
            ->where('shop_id', $shopId)
            ->select('type', 'status')
            ->get();
        if ($rows->isEmpty()) return 'unsubmitted';
        $hasRejected = $rows->contains(fn ($r) => (int) $r->status === 3);
        $hasPending  = $rows->contains(fn ($r) => (int) $r->status === 1);
        $allApproved = $rows->every(fn ($r) => (int) $r->status === 2);
        if ($hasRejected) return 'rejected';
        if ($allApproved) return 'approved';
        if ($hasPending)  return 'pending';
        return 'unsubmitted';
    }

    private function hasBankAccount(string $holderType, string $holderId): bool
    {
        if (!Schema::hasTable('bank_accounts')) return true; // 不明時は表示しない
        return DB::table('bank_accounts')
            ->where('holder_type', $holderType)
            ->where('holder_id', $holderId)
            ->exists();
    }

    /**
     * @param 'cast'|'shop' $side
     */
    private function countUnreadTalks(string $side, string $userId): int
    {
        if (!Schema::hasTable('messages')) return 0;
        $col = $side === 'cast' ? 'cast_id' : 'shop_id';
        $readCol = $side === 'cast' ? 'cast_read_at' : 'shop_read_at';
        if (!Schema::hasColumn('messages', $col)) return 0;

        // 自分（cast/shop）あてに来た最新メッセージで未読のもの
        $q = DB::table('messages')
            ->where($col, $userId);
        // メッセージの送信元が自分ではないものに絞る（あれば）
        if (Schema::hasColumn('messages', 'sender_type')) {
            $q->where('sender_type', $side === 'cast' ? 'shop' : 'cast');
        }
        if (Schema::hasColumn('messages', $readCol)) {
            $q->whereNull($readCol);
        }
        return (int) $q->count();
    }

    /**
     * キャスト：振込実行後、本人の入金確認が未済（CAST_TRANSFERRED status=6）
     */
    private function countCastUnconfirmedDeposits(string $castId): int
    {
        if (!Schema::hasTable('application_deposits') || !Schema::hasTable('shop_job_applications')) {
            return 0;
        }
        return (int) DB::table('application_deposits as ad')
            ->join('shop_job_applications as sja', 'ad.shop_job_application_id', '=', 'sja.id')
            ->where('sja.cast_id', $castId)
            ->where('ad.status', 6) // CAST_TRANSFERRED
            ->count();
    }

    /**
     * キャスト：採用済み（application.status IN 4=HIRED / 6=HIRED_FULLTIME）で
     * 未だ入金申請（application_deposits）が作られていない案件の件数。
     * これがフローの起点（STATUS_CAST_REQUESTED を作るタスク）。
     */
    private function countCastBonusRequestPending(string $castId): int
    {
        if (!Schema::hasTable('shop_job_applications')) {
            return 0;
        }
        $q = DB::table('shop_job_applications as sja')
            ->where('sja.cast_id', $castId)
            ->whereIn('sja.status', [4, 6]);

        if (Schema::hasTable('application_deposits')) {
            $q->leftJoin('application_deposits as ad', 'ad.shop_job_application_id', '=', 'sja.id')
              ->whereNull('ad.id');
        }
        return (int) $q->count('sja.id');
    }

    /**
     * 店舗：キャスト入金依頼が来て承認待ち（status=1）
     */
    private function countShopPendingApproval(string $shopId): int
    {
        if (!Schema::hasTable('application_deposits') || !Schema::hasTable('shop_job_applications') || !Schema::hasTable('shop_jobs')) {
            return 0;
        }
        return (int) DB::table('application_deposits as ad')
            ->join('shop_job_applications as sja', 'ad.shop_job_application_id', '=', 'sja.id')
            ->join('shop_jobs as sj', 'sja.shop_job_id', '=', 'sj.id')
            ->where('sj.shop_id', $shopId)
            ->where('ad.status', 1)
            ->count();
    }

    /**
     * 店舗：請求書発行済みで未支払いの案件（status=3=INVOICE_ISSUED のみ）
     *
     * status=4=SHOP_PAYMENT_REPORTED は「店舗が振込報告済み → 運営照合待ち」で
     * 店舗側にボールがないため除外する（フローでは運営タスク）。
     */
    private function countShopPendingPayment(string $shopId): int
    {
        if (!Schema::hasTable('application_deposits') || !Schema::hasTable('shop_job_applications') || !Schema::hasTable('shop_jobs')) {
            return 0;
        }
        return (int) DB::table('application_deposits as ad')
            ->join('shop_job_applications as sja', 'ad.shop_job_application_id', '=', 'sja.id')
            ->join('shop_jobs as sj', 'sja.shop_job_id', '=', 'sj.id')
            ->where('sj.shop_id', $shopId)
            ->where('ad.status', 3)
            ->count();
    }

    private function isEmailUnverified(string $table, string $userId): bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'email_verified_at')) {
            return false;
        }
        $verifiedAt = DB::table($table)->where('id', $userId)->value('email_verified_at');
        return empty($verifiedAt);
    }

    private function currentShopManagerId(): ?string
    {
        try {
            $u = auth()->guard('shop')->user();
            return $u ? (string) $u->getKey() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeRoute(string $name, array $params = []): ?string
    {
        try {
            return \Route::has($name) ? route($name, $params) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
