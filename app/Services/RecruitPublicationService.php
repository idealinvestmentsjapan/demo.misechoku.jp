<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encapsulates shop_jobs publication toggling and its preconditions.
 *
 * Extracted from Shops\RecruitmentController::toggleStatus() (2026-08-02).
 * Publication requires the shop's entertainment license slot (風営許可証 or
 * 深夜酒類届出 — either is fine) to be approved by admin. See
 * DocumentReviewService::shopLicenseFullyApproved / GATE_LICENSE_KEY.
 *
 * The service supports both legacy schema shapes:
 *   - Horizontal: shop_jobs has `regular_status` / `trial_status` / `help_status`
 *   - Vertical (single row): shop_jobs.status is the only publish flag
 */
class RecruitPublicationService
{
    public const MESSAGE_LICENSE_REQUIRED = '求人を公開するには、風営許可証または深夜酒類届出のいずれかを提出し、運営の承認が必要です。';

    public function __construct(private readonly DocumentReviewService $documentReviewService) {}

    /** Whether the schema uses per-job-type status columns. */
    public function isHorizontalSchema(): bool
    {
        return Schema::hasTable('shop_jobs') && Schema::hasColumn('shop_jobs', 'regular_status');
    }

    /**
     * Toggle the publication of a horizontal-schema job type (regular/trial/help).
     *
     * @return array{success:bool, message:string, next:int|null}
     */
    public function toggleHorizontal(string $shopId, int $jobType): array
    {
        if (!$this->isHorizontalSchema()) {
            return ['success' => false, 'message' => '対応スキーマではありません。', 'next' => null];
        }

        $col = match ($jobType) {
            2 => 'trial_status',
            3 => 'help_status',
            default => 'regular_status',
        };
        if (!Schema::hasColumn('shop_jobs', $col)) {
            return ['success' => false, 'message' => '求人設定を更新できません。', 'next' => null];
        }

        $row = DB::table('shop_jobs')->where('shop_id', $shopId)->first();
        if (!$row) {
            return ['success' => false, 'message' => '求人情報が見つかりません。', 'next' => null];
        }

        $current = (int) ($row->{$col} ?? 0);
        $next    = $current === 1 ? 0 : 1;

        if ($next === 1 && !$this->documentReviewService->shopLicenseFullyApproved($shopId)) {
            return ['success' => false, 'message' => self::MESSAGE_LICENSE_REQUIRED, 'next' => null];
        }

        DB::table('shop_jobs')->where('shop_id', $shopId)->update([
            $col => $next,
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => $next === 1 ? '求人を公開しました' : '求人を非公開にしました',
            'next'    => $next,
        ];
    }
}
