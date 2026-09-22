<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

/**
 * 後方互換のための薄いファサード。
 * 認証中ロール（キャスト/ショップ）に応じて、
 *   - CastSearchPreferenceService
 *   - ShopSearchPreferenceService
 * へ処理を振り分ける。
 *
 * 新規コードはこの class ではなく、対象ロールのサービスを直接 inject すること。
 *
 * @deprecated 個別サービスを使用してください
 */
class UserLocationService
{
    public const SESSION_KEY = CastSearchPreferenceService::SESSION_KEY;
    public const MODE_CURRENT = CastSearchPreferenceService::MODE_CURRENT;
    public const MODE_PASSPORT = CastSearchPreferenceService::MODE_PASSPORT;
    public const MODE_PROFILE = CastSearchPreferenceService::MODE_PROFILE;
    public const ALL_MODES = CastSearchPreferenceService::ALL_MODES;
    public const DISTANCE_OPTIONS_KM = CastSearchPreferenceService::DISTANCE_OPTIONS_KM;

    public function __construct(
        private readonly CastSearchPreferenceService $cast,
        private readonly ShopSearchPreferenceService $shop,
    ) {
    }

    public function getActiveLocation(): ?array
    {
        return $this->isCast() ? $this->cast->getActiveLocation() : $this->shop->getActiveLocation();
    }

    public function getEffectiveMaxDistanceKm(): ?int
    {
        return $this->isCast() ? $this->cast->getEffectiveMaxDistanceKm() : $this->shop->getEffectiveMaxDistanceKm();
    }

    public function setManualLocation(string $mode, float $lat, float $lng, string $label = ''): void
    {
        // 端末 GPS / パスポート位置の一時保存はキャストのみで使用
        $this->cast->setManualLocation($mode, $lat, $lng, $label);
    }

    public function clear(): void
    {
        $this->cast->clearSessionLocation();
    }

    /**
     * Update only the search radius for the current role.
     */
    public function saveMaxDistanceKm(int $km): void
    {
        if ($this->isCast()) {
            $this->cast->saveMaxDistanceKm($km);
        } else {
            $this->shop->saveMaxDistanceKm($km);
        }
    }

    /**
     * キャストの位置設定（mode / passport / max_km）を保存。
     * ショップから呼び出された場合は何もしない。
     */
    public function saveSearchSettings(array $payload): void
    {
        if ($this->isCast()) {
            $this->cast->saveLocationSettings($payload);
        }
    }

    /**
     * 詳細検索フォームの希望条件を保存。ロールに応じて振り分け。
     */
    public function saveSearchPreferences(array $payload): void
    {
        if ($this->isCast()) {
            $this->cast->savePreferences($payload);
        } else {
            $this->shop->savePreferences($payload);
        }
    }

    /**
     * MyPage 表示用：位置情報＋住所情報を返す（cast/shop 共通フォーマット）。
     */
    public function loadProfileSettings(): ?array
    {
        if ($this->isCast()) {
            return $this->cast->loadAll();
        }
        // ショップ側は cast 側のキー命名を踏襲し、不要キーは未設定で返す
        $shop = $this->shop->loadAll();
        return [
            'mode'              => '',
            'passport_address'  => null,
            'passport_latitude' => null,
            'passport_longitude'=> null,
            'passport_label'    => '',
            'max_distance_km'   => $shop['max_distance_km'],
            'profile_location'  => $this->shop->getActiveLocation(),
            'profile_address'   => '',
            'has_address'       => true,
        ];
    }

    /**
     * 詳細検索フォーム用に希望条件をロード。
     */
    public function loadSearchPreferences(): array
    {
        if ($this->isCast()) {
            $all = $this->cast->loadAll();
            return [
                'shift_frequency' => $all['shift_frequency'],
                'work_periods'    => $all['work_periods'],
                'hourly_wage_min' => $all['hourly_wage_min'],
                'industry_ids'    => $all['industry_ids'],
            ];
        }
        return $this->shop->loadAll();
    }

    /**
     * 自プロフィール住所をジオコーディングし latitude/longitude を埋める（キャストのみ）。
     */
    public function geocodeAndSaveProfileLocation(GeocodingService $geocoding): ?array
    {
        if ($this->isCast()) {
            return $this->cast->geocodeAndSaveProfileLocation($geocoding);
        }
        return null;
    }

    // ===== 距離・幾何計算（ロール非依存・静的ユーティリティ） =====

    public function distanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }
        $earthRadiusKm = 6371.0;
        $latFrom = deg2rad((float) $lat1);
        $latTo = deg2rad((float) $lat2);
        $deltaLat = deg2rad((float) $lat2 - (float) $lat1);
        $deltaLng = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }

    public function formatDistance(?float $km): string
    {
        if ($km === null) {
            return '—';
        }
        if ($km < 1.0) {
            return number_format((int) round($km * 1000)) . ' m';
        }
        if ($km < 10.0) {
            return number_format($km, 1) . ' km';
        }
        return number_format((int) round($km)) . ' km';
    }

    public function haversineSqlExpression(string $latColumn, string $lngColumn, float $originLat, float $originLng): array
    {
        $expr = "(6371 * 2 * ASIN(SQRT("
            . "POWER(SIN(RADIANS(? - {$latColumn}) / 2), 2)"
            . " + COS(RADIANS({$latColumn})) * COS(RADIANS(?))"
            . " * POWER(SIN(RADIANS(? - {$lngColumn}) / 2), 2)"
            . ")))";
        return [$expr, [$originLat, $originLat, $originLng]];
    }

    // ===== private =====

    private function isCast(): bool
    {
        $cast = Auth::guard('member')->user();
        return $cast && !empty($cast->id);
    }
}
