<?php

namespace Tests\Feature\Shop;

use App\Models\ShopManager;
use App\Services\BillingManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ### demo function and data for test ###
 *
 * Regression coverage for /shop/mypage/deposit/approve.
 * Purpose: confirm that the shop side "承認する" action actually updates
 * the specific deposit that the shop clicked on. The previous
 * implementation used findLatestDepositForShop() which picked the newest
 * deposit for the shop regardless of which case card was pressed — so
 * with multiple approval-pending cases the "wrong" deposit would move
 * (or the check would silently fail when the latest deposit had already
 * advanced to another status).
 */
class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function approve_updates_the_specific_deposit_passed_via_deposit_id(): void
    {
        $shopId = 's44440001';
        $castId = 'c44440001';
        $manager = $this->makeShopOwner('m44440001', $shopId);

        // Create TWO approval-pending deposits so we can prove that the
        // pressed one — not just "the latest" — is what gets updated.
        $olderDepositId = $this->makePendingDeposit($shopId, $castId, olderBy: 3);
        $newerDepositId = $this->makePendingDeposit($shopId, $castId, olderBy: 0);

        // Cast must have a posted review for the shop, otherwise the
        // service refuses to approve.
        $this->makeReview($shopId, $castId);

        // Click "承認する" on the OLDER card. Modal submits deposit_id
        // + confirm checkboxes as our updated JS/HTML now do.
        $response = $this->actingAs($manager, 'shop')
            ->from(route('shop.mypage.management'))
            ->post(route('shop.mypage.deposit.approve'), [
                'deposit_id'              => $olderDepositId,
                'confirm_review_checked'  => 1,
                'confirm_bonus_condition' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $response->assertSessionMissing('error');

        // Older (clicked) deposit must be advanced to SHOP_APPROVED.
        $this->assertSame(
            BillingManagementService::STATUS_SHOP_APPROVED,
            (int) DB::table('application_deposits')->where('id', $olderDepositId)->value('status'),
            'The deposit the shop actually clicked should be advanced.'
        );

        // Newer deposit must NOT be touched — it was not the one clicked.
        $this->assertSame(
            BillingManagementService::STATUS_CAST_REQUESTED,
            (int) DB::table('application_deposits')->where('id', $newerDepositId)->value('status'),
            'A different deposit must not be affected.'
        );

        // History log entry for the older deposit
        $historyCount = DB::table('application_deposit_histories')
            ->where('application_deposit_id', $olderDepositId)
            ->where('status', BillingManagementService::STATUS_SHOP_APPROVED)
            ->count();
        $this->assertGreaterThanOrEqual(1, $historyCount, 'History row should be appended.');
    }

    /** @test */
    public function approve_falls_back_to_application_id_when_deposit_id_missing(): void
    {
        // If the modal ever submits without deposit_id but with application_id
        // (defensive path), the service should still resolve the correct case.
        $shopId = 's44440002';
        $castId = 'c44440002';
        $manager = $this->makeShopOwner('m44440002', $shopId);

        $depositId = $this->makePendingDeposit($shopId, $castId, olderBy: 0);
        $applicationId = (int) DB::table('application_deposits')
            ->where('id', $depositId)
            ->value('shop_job_application_id');

        $this->makeReview($shopId, $castId);

        $response = $this->actingAs($manager, 'shop')
            ->from(route('shop.mypage.management'))
            ->post(route('shop.mypage.deposit.approve'), [
                'application_id'          => $applicationId,
                'confirm_review_checked'  => 1,
                'confirm_bonus_condition' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertSame(
            BillingManagementService::STATUS_SHOP_APPROVED,
            (int) DB::table('application_deposits')->where('id', $depositId)->value('status')
        );
    }

    /* ---------------- Fixtures ---------------- */

    private function makeShopOwner(string $managerId, string $shopId): ShopManager
    {
        DB::table('shops')->insert([
            'id' => $shopId,
            'email' => $shopId . '@test.example',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shop_managers')->insert([
            'id' => $managerId,
            'shop_id' => $shopId,
            'name' => 'Owner ' . $managerId,
            'email' => $managerId . '@test.example',
            'password' => Hash::make('password'),
            'role' => ShopManager::ROLE_OWNER,
            'status' => ShopManager::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return ShopManager::findOrFail($managerId);
    }

    private function makePendingDeposit(string $shopId, string $castId, int $olderBy): int
    {
        DB::table('casts')->insertOrIgnore([
            'id' => $castId,
            'email' => $castId . '@test.example',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shopJobId = DB::table('shop_jobs')->insertGetId([
            'shop_id' => $shopId,
            'regular_hourly_wage' => 3000,
            'bonus_reward' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appId = DB::table('shop_job_applications')->insertGetId([
            'cast_id' => $castId,
            'shop_job_id' => $shopJobId,
            'status' => 4, // hired
            'hired_bonus_amount' => 50000,
            'result_date' => now()->subDays($olderBy + 1)->toDateString(),
            'created_at' => now()->subDays($olderBy + 1),
            'updated_at' => now()->subDays($olderBy),
        ]);

        return (int) DB::table('application_deposits')->insertGetId([
            'shop_job_application_id' => $appId,
            'status' => BillingManagementService::STATUS_CAST_REQUESTED,
            'is_read' => 0,
            'bonus_amount' => 50000,
            'created_at' => now()->subDays($olderBy),
            'updated_at' => now()->subDays($olderBy),
        ]);
    }

    private function makeReview(string $shopId, string $castId): void
    {
        DB::table('reviews')->insert([
            'cast_id' => $castId,
            'shop_id' => $shopId,
            'contents' => 'Test review.',
            'eva' => 4.5,
            'is_anonymous' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
