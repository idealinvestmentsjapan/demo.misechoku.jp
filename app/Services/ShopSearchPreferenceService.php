<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ショップの検索希望条件を扱うサービス。
 *
 * 保存先: shop_search_preferences (shop_id UNIQUE)
 * - 位置情報: max_distance_km のみ（起点は shop_profiles.latitude/longitude 固定）
 * - 希望条件: age_min/max, shift_frequency, work_periods (JSON),
 *           looks_tag_ids (JSON), personality_tag_ids (JSON), night_work_exp
 *
 * 探索拠点はショップ自身の住所緯度経度（cast 側のような mode 選択は無い）。
 */
class ShopSearchPreferenceService
{
    public const DISTANCE_OPTIONS_KM = [0, 1, 3, 5, 10, 20, 30, 50, 100];

    public const SHIFT_FREQUENCY_OPTIONS = ['週1回出勤', '週2回出勤', '週3回以上'];
    public const WORK_PERIOD_OPTIONS = ['morning', 'day', 'night'];
    public const NIGHT_WORK_EXP_OPTIONS = ['none', 'yes', 'any'];

    /**
     * 現在の探索拠点（=自店舗の住所緯度経度）を返す。
     *
     * @return array{lat: float, lng: float, mode: string, label: string}|null
     */
    public function getActiveLocation(?string $shopId = null): ?array
    {
        $shopId ??= $this->currentShopId();
        if ($shopId === null) {
            return null;
        }
        $row = DB::table('shop_profiles')
            ->where('shop_id', $shopId)
            ->select('latitude', 'longitude')
            ->first();
        if (!$row || $row->latitude === null || $row->longitude === null) {
            return null;
        }
        return [
            'lat'   => (float) $row->latitude,
            'lng'   => (float) $row->longitude,
            'mode'  => 'profile',
            'label' => '店舗住所',
        ];
    }

    /**
     * 半径フィルタ（km）。0 は制限なし、null は未設定。
     */
    public function getEffectiveMaxDistanceKm(?string $shopId = null): ?int
    {
        $shopId ??= $this->currentShopId();
        if ($shopId === null) {
            return null;
        }
        $row = $this->loadRow($shopId);
        if (!$row || !isset($row->max_distance_km)) {
            return null;
        }
        return (int) $row->max_distance_km;
    }

    /**
     * Update only the search radius, keeping other preferences untouched.
     */
    public function saveMaxDistanceKm(int $km): void
    {
        $shopId = $this->currentShopId();
        if ($shopId === null || !in_array($km, self::DISTANCE_OPTIONS_KM, true)) {
            return;
        }
        $now = now();
        DB::table('shop_search_preferences')->upsert(
            [[
                'shop_id'         => $shopId,
                'max_distance_km' => $km,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]],
            ['shop_id'],
            ['max_distance_km', 'updated_at']
        );
    }

    /**
     * 詳細検索フォームの希望条件を保存。
     */
    public function savePreferences(array $payload): void
    {
        $shopId = $this->currentShopId();
        if ($shopId === null) {
            return;
        }

        $maxDistanceKm = isset($payload['max_distance_km']) && $payload['max_distance_km'] !== ''
            ? (int) $payload['max_distance_km']
            : null;

        $ageMin = isset($payload['age_min']) && $payload['age_min'] !== '' ? (int) $payload['age_min'] : null;
        $ageMax = isset($payload['age_max']) && $payload['age_max'] !== '' ? (int) $payload['age_max'] : null;

        $shiftFrequency = isset($payload['shift_frequency']) && in_array($payload['shift_frequency'], self::SHIFT_FREQUENCY_OPTIONS, true)
            ? $payload['shift_frequency']
            : null;

        $workPeriods = isset($payload['work_periods']) && is_array($payload['work_periods'])
            ? array_values(array_filter(array_map(
                fn ($v) => is_string($v) ? $v : null,
                $payload['work_periods']
            ), fn ($v) => in_array($v, self::WORK_PERIOD_OPTIONS, true)))
            : null;

        $looksTagIds = isset($payload['looks_tag_ids']) && is_array($payload['looks_tag_ids'])
            ? array_values(array_unique(array_map('intval', $payload['looks_tag_ids'])))
            : null;

        $personalityTagIds = isset($payload['personality_tag_ids']) && is_array($payload['personality_tag_ids'])
            ? array_values(array_unique(array_map('intval', $payload['personality_tag_ids'])))
            : null;

        $nightWorkExp = isset($payload['night_work_exp']) && in_array($payload['night_work_exp'], self::NIGHT_WORK_EXP_OPTIONS, true)
            ? $payload['night_work_exp']
            : null;

        $row = [
            'max_distance_km'     => $maxDistanceKm,
            'age_min'             => $ageMin,
            'age_max'             => $ageMax,
            'shift_frequency'     => $shiftFrequency,
            'work_periods'        => $workPeriods !== null ? json_encode($workPeriods, JSON_UNESCAPED_UNICODE) : null,
            'looks_tag_ids'       => $looksTagIds !== null ? json_encode($looksTagIds) : null,
            'personality_tag_ids' => $personalityTagIds !== null ? json_encode($personalityTagIds) : null,
            'night_work_exp'      => $nightWorkExp,
        ];

        $now = now();
        DB::table('shop_search_preferences')->upsert(
            [array_merge($row, [
                'shop_id'    => $shopId,
                'created_at' => $now,
                'updated_at' => $now,
            ])],
            ['shop_id'],
            array_merge(array_keys($row), ['updated_at'])
        );
    }

    /**
     * 全保存設定を返す。
     */
    public function loadAll(?string $shopId = null): array
    {
        $shopId ??= $this->currentShopId();
        if ($shopId === null) {
            return $this->emptyLoadShape();
        }
        $row = $this->loadRow($shopId);
        if (!$row) {
            return $this->emptyLoadShape();
        }
        return [
            'max_distance_km'     => isset($row->max_distance_km) ? (int) $row->max_distance_km : null,
            'age_min'             => isset($row->age_min) ? (int) $row->age_min : null,
            'age_max'             => isset($row->age_max) ? (int) $row->age_max : null,
            'shift_frequency'     => $row->shift_frequency ?? null,
            'work_periods'        => $this->decodeJsonArray($row->work_periods ?? null),
            'looks_tag_ids'       => array_map('intval', $this->decodeJsonArray($row->looks_tag_ids ?? null)),
            'personality_tag_ids' => array_map('intval', $this->decodeJsonArray($row->personality_tag_ids ?? null)),
            'night_work_exp'      => $row->night_work_exp ?? null,
        ];
    }

    // ===== private =====

    private function currentShopId(): ?string
    {
        $manager = Auth::guard('shop')->user();
        return $manager && !empty($manager->shop_id) ? (string) $manager->shop_id : null;
    }

    private function loadRow(string $shopId): ?\stdClass
    {
        $row = DB::table('shop_search_preferences')
            ->where('shop_id', $shopId)
            ->first();
        return $row ?: null;
    }

    private function decodeJsonArray(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function emptyLoadShape(): array
    {
        return [
            'max_distance_km'     => null,
            'age_min'             => null,
            'age_max'             => null,
            'shift_frequency'     => null,
            'work_periods'        => [],
            'looks_tag_ids'       => [],
            'personality_tag_ids' => [],
            'night_work_exp'      => null,
        ];
    }
}
