<?php

namespace Tests\Feature\Discovery;

use App\Models\ShopManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DISCOVERY (shop home) cast list ordering: Tier A > B > C.
 *
 * Feature-level test that hits the actual /shop/home endpoint and inspects
 * the order casts appear in the returned view items.
 */
class TierRankingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tier_a_casts_appear_before_tier_b_and_c(): void
    {
        // Base shop + owner login
        $manager = $this->makeShopManager('m11110001', 's11110001');

        // Cast A: declared today as a candidate work date (Tier A)
        $this->makeCastWithProfile('c11110001', 'Alice', [
            'latitude' => 35.68, 'longitude' => 139.76,
        ], now()->subMinutes(3));
        DB::table('availability_dates')->insert([
            'owner_type' => 'cast',
            'owner_id' => 'c11110001',
            'available_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cast B: recent login + location, no declaration (Tier B)
        $this->makeCastWithProfile('c11110002', 'Bob', [
            'latitude' => 35.68, 'longitude' => 139.76,
        ], now()->subMinutes(30));

        // Cast C: no location, week-old login (Tier C)
        $this->makeCastWithProfile('c11110003', 'Carol', [], now()->subDays(7));

        $res = $this->actingAs($manager, 'shop')->get(route('shop.home'));
        $res->assertOk();

        // Grab the items collection the view received.
        $items = $res->viewData('items');
        $this->assertIsArray($items);

        // Filter to our test casts (in case seed data slips in) and check order.
        $ourItems = array_values(array_filter($items, fn ($it) => in_array($it['id'], ['c11110001', 'c11110002', 'c11110003'])));
        $this->assertNotEmpty($ourItems);
        $this->assertSame('c11110001', $ourItems[0]['id'], 'Alice (Tier A) must be first');
        $this->assertSame('A', $ourItems[0]['tier']);

        // Bob (B) should come before Carol (C) if both are present.
        $bIndex = null;
        $cIndex = null;
        foreach ($ourItems as $i => $it) {
            if ($it['id'] === 'c11110002') $bIndex = $i;
            if ($it['id'] === 'c11110003') $cIndex = $i;
        }
        if ($bIndex !== null && $cIndex !== null) {
            $this->assertLessThan($cIndex, $bIndex, 'Bob (Tier B) must come before Carol (Tier C)');
        }
    }

    private function makeCastWithProfile(string $castId, string $nickname, array $profileExtra, $lastLogin): void
    {
        DB::table('casts')->insert([
            'id' => $castId,
            'email' => $castId . '@test.example',
            'password' => Hash::make('password'),
            'status' => 1,
            'last_login_at' => $lastLogin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cast_profiles')->insert(array_merge([
            'cast_id' => $castId,
            'nickname' => $nickname,
            'created_at' => now(),
            'updated_at' => now(),
        ], $profileExtra));
    }

    private function makeShopManager(string $managerId, string $shopId): ShopManager
    {
        DB::table('shops')->insert([
            'id' => $shopId,
            'email' => $shopId . '@test.example',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shop_profiles')->insert([
            'shop_id' => $shopId,
            'shop_name' => 'Test Shop',
            'pref' => 'Tokyo',
            'city' => 'Chuo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shop_managers')->insert([
            'id' => $managerId,
            'shop_id' => $shopId,
            'name' => 'Owner',
            'email' => $managerId . '@test.example',
            'password' => Hash::make('password'),
            'role' => ShopManager::ROLE_OWNER,
            'status' => ShopManager::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return ShopManager::findOrFail($managerId);
    }
}
