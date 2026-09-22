<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * キャストの検索希望条件を扱うサービス。
 *
 * 保存先: cast_search_preferences (cast_id UNIQUE)
 * - 位置情報: mode (profile/passport/current) / passport_* / max_distance_km
 * - 希望条件: shift_frequency / work_periods (JSON) / hourly_wage_min / industry_ids (JSON)
 *
 * 探索拠点の解決順:
 *   1. cast_search_preferences.mode に従う:
 *      - 'profile'  : cast_profiles.latitude/longitude
 *      - 'passport' : cast_search_preferences.passport_latitude/longitude
 *      - 'current'  : セッションに保存された端末 geolocation
 *   2. 設定が無ければセッションの一時保存にフォールバック
 *   3. それでも解決できなければ自プロフィールの住所緯度経度
 *   4. 解決不能 → null
 */
class CastSearchPreferenceService
{
    public const SESSION_KEY = 'user_location';
    public const MODE_CURRENT = 'current';
    public const MODE_PASSPORT = 'passport';
    public const MODE_PROFILE = 'profile';

    public const ALL_MODES = [self::MODE_PROFILE, self::MODE_PASSPORT, self::MODE_CURRENT];

    /** プルダウン用：選択肢（半径 km）。0 は「制限なし」。 */
    public const DISTANCE_OPTIONS_KM = [0, 1, 3, 5, 10, 20, 30, 50, 100];

    /** 出勤頻度の選択肢 */
    public const SHIFT_FREQUENCY_OPTIONS = ['週1回出勤', '週2回出勤', '週3回以上'];

    /** 勤務時間帯の選択肢 */
    public const WORK_PERIOD_OPTIONS = ['morning', 'day', 'night'];

    /**
     * 現在の探索拠点を返す。
     *
     * @return array{lat: float, lng: float, mode: string, label: string}|null
     */
    public function getActiveLocation(?string $castId = null): ?array
    {
        $castId ??= $this->currentCastId();
        if ($castId === null) {
            return null;
        }

        $persisted = $this->resolvePersistedOrigin($castId);
        if ($persisted) {
            return $persisted;
        }

        $session = (array) session(self::SESSION_KEY, []);
        if (
            isset($session['lat'], $session['lng']) &&
            is_numeric($session['lat']) && is_numeric($session['lng'])
        ) {
            return [
                'lat'   => (float) $session['lat'],
                'lng'   => (float) $session['lng'],
                'mode'  => (string) ($session['mode'] ?? self::MODE_CURRENT),
                'label' => (string) ($session['label'] ?? '指定位置'),
            ];
        }

        $profile = $this->resolveProfileLocation($castId);
        if ($profile) {
            return $profile + ['mode' => self::MODE_PROFILE, 'label' => 'プロフィール住所'];
        }

        return null;
    }

    /**
     * 半径フィルタ（km）。0 は「制限なし」、null は「未設定」。
     */
    public function getEffectiveMaxDistanceKm(?string $castId = null): ?int
    {
        $castId ??= $this->currentCastId();
        if ($castId === null) {
            return null;
        }
        $row = $this->loadRow($castId);
        if (!$row || !isset($row->max_distance_km)) {
            return null;
        }
        return (int) $row->max_distance_km;
    }

    /**
     * 位置設定をセッションに保存（端末 geolocation 用）。
     */
    public function setManualLocation(string $mode, float $lat, float $lng, string $label = ''): void
    {
        if (!in_array($mode, [self::MODE_CURRENT, self::MODE_PASSPORT], true)) {
            $mode = self::MODE_CURRENT;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }
        session([
            self::SESSION_KEY => [
                'mode'   => $mode,
                'lat'    => $lat,
                'lng'    => $lng,
                'label'  => $label !== '' ? $label : ($mode === self::MODE_CURRENT ? '現在地' : '指定位置'),
                'set_at' => time(),
            ],
        ]);
    }

    public function clearSessionLocation(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * 位置設定（mode/passport/max_km）を DB に保存。
     */
    public function saveLocationSettings(array $payload): void
    {
        $castId = $this->currentCastId();
        if ($castId === null) {
            return;
        }
        $mode = (string) ($payload['mode'] ?? '');
        if (!in_array($mode, self::ALL_MODES, true)) {
            return;
        }

        $row = [
            'mode'            => $mode,
            'max_distance_km' => isset($payload['max_distance_km']) ? (int) $payload['max_distance_km'] : 0,
        ];
        if ($mode === self::MODE_PASSPORT) {
            $row['passport_address']   = $payload['passport_address']   ?? null;
            $row['passport_latitude']  = $payload['passport_latitude']  ?? null;
            $row['passport_longitude'] = $payload['passport_longitude'] ?? null;
            $row['passport_label']     = $payload['passport_label']     ?? null;
        } else {
            $row['passport_address']   = null;
            $row['passport_latitude']  = null;
            $row['passport_longitude'] = null;
            $row['passport_label']     = null;
        }

        $this->upsert($castId, $row);
    }

    /**
     * Update only the search radius, keeping mode / passport fields untouched.
     */
    public function saveMaxDistanceKm(int $km): void
    {
        $castId = $this->currentCastId();
        if ($castId === null || !in_array($km, self::DISTANCE_OPTIONS_KM, true)) {
            return;
        }
        $this->upsert($castId, ['max_distance_km' => $km]);
    }

    /**
     * 詳細検索フォームの「希望条件」を DB に保存。
     */
    public function savePreferences(array $payload): void
    {
        $castId = $this->currentCastId();
        if ($castId === null) {
            return;
        }

        $shiftFrequency = isset($payload['shift_frequency']) && in_array($payload['shift_frequency'], self::SHIFT_FREQUENCY_OPTIONS, true)
            ? $payload['shift_frequency']
            : null;

        $workPeriods = isset($payload['work_periods']) && is_array($payload['work_periods'])
            ? array_values(array_filter(array_map(
                fn ($v) => is_string($v) ? $v : null,
                $payload['work_periods']
            ), fn ($v) => in_array($v, self::WORK_PERIOD_OPTIONS, true)))
            : null;

        $industryIds = isset($payload['industry_ids']) && is_array($payload['industry_ids'])
            ? array_values(array_unique(array_map('intval', $payload['industry_ids'])))
            : null;

        $hourlyWageMin = isset($payload['hourly_wage_min']) && $payload['hourly_wage_min'] !== '' && $payload['hourly_wage_min'] !== null
            ? (int) $payload['hourly_wage_min']
            : null;

        $row = [
            'shift_frequency' => $shiftFrequency,
            'work_periods'    => $workPeriods !== null ? json_encode($workPeriods, JSON_UNESCAPED_UNICODE) : null,
            'hourly_wage_min' => $hourlyWageMin,
            'industry_ids'    => $industryIds !== null ? json_encode($industryIds) : null,
        ];

        $this->upsert($castId, $row);
    }

    /**
     * 全保存設定を返す（位置 + 希望条件）。
     *
     * @return array
     */
    public function loadAll(?string $castId = null): array
    {
        $castId ??= $this->currentCastId();
        if ($castId === null) {
            return $this->emptyLoadShape();
        }

        $profile = $this->loadProfileRow($castId);
        $row = $this->loadRow($castId);

        $profileLocation = ($profile && $profile->latitude !== null && $profile->longitude !== null)
            ? ['lat' => (float) $profile->latitude, 'lng' => (float) $profile->longitude]
            : null;

        $addressText = $profile ? trim(
            ((string) ($profile->pref ?? ''))
            . ((string) ($profile->city ?? ''))
            . ((string) ($profile->addr ?? ''))
        ) : '';

        $workPeriods  = $this->decodeJsonArray($row->work_periods ?? null);
        $industryIds  = array_map('intval', $this->decodeJsonArray($row->industry_ids ?? null));

        return [
            // 位置
            'mode'               => (string) ($row->mode ?? ''),
            'passport_address'   => $row->passport_address ?? null,
            'passport_latitude'  => isset($row->passport_latitude)  ? (float) $row->passport_latitude  : null,
            'passport_longitude' => isset($row->passport_longitude) ? (float) $row->passport_longitude : null,
            'passport_label'     => (string) ($row->passport_label ?? ''),
            'max_distance_km'    => isset($row->max_distance_km) ? (int) $row->max_distance_km : null,
            'profile_location'   => $profileLocation,
            'profile_address'    => $addressText,
            'has_address'        => $addressText !== '',
            // 希望条件
            'shift_frequency'    => $row->shift_frequency ?? null,
            'work_periods'       => $workPeriods,
            'hourly_wage_min'    => isset($row->hourly_wage_min) ? (int) $row->hourly_wage_min : null,
            'industry_ids'       => $industryIds,
        ];
    }

    /**
     * 自プロフィール住所をジオコーディングして cast_profiles.latitude/longitude を更新する。
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocodeAndSaveProfileLocation(GeocodingService $geocoding): ?array
    {
        $castId = $this->currentCastId();
        if ($castId === null) {
            return null;
        }
        $row = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->select('pref', 'city', 'addr')
            ->first();
        if (!$row) {
            return null;
        }
        $address = trim(((string) ($row->pref ?? '')) . ((string) ($row->city ?? '')) . ((string) ($row->addr ?? '')));
        if ($address === '') {
            return null;
        }
        $coords = $geocoding->fromAddress($address);
        if (!$coords) {
            return null;
        }
        DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->update([
                'latitude'  => $coords['latitude'],
                'longitude' => $coords['longitude'],
            ]);
        return ['lat' => (float) $coords['latitude'], 'lng' => (float) $coords['longitude']];
    }

    // ===== private =====

    private function currentCastId(): ?string
    {
        $cast = Auth::guard('member')->user();
        return $cast && !empty($cast->id) ? (string) $cast->id : null;
    }

    private function loadRow(string $castId): ?\stdClass
    {
        $row = DB::table('cast_search_preferences')
            ->where('cast_id', $castId)
            ->first();
        return $row ?: null;
    }

    private function loadProfileRow(string $castId): ?\stdClass
    {
        $row = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->select('pref', 'city', 'addr', 'latitude', 'longitude')
            ->first();
        return $row ?: null;
    }

    private function upsert(string $castId, array $row): void
    {
        $now = now();
        DB::table('cast_search_preferences')->upsert(
            [array_merge($row, [
                'cast_id'    => $castId,
                'created_at' => $now,
                'updated_at' => $now,
            ])],
            ['cast_id'],
            array_merge(array_keys($row), ['updated_at'])
        );
    }

    private function resolvePersistedOrigin(string $castId): ?array
    {
        $row = $this->loadRow($castId);
        if (!$row) {
            return null;
        }
        $mode = (string) ($row->mode ?? '');

        if ($mode === self::MODE_PROFILE) {
            $profile = $this->resolveProfileLocation($castId);
            if ($profile) {
                return $profile + ['mode' => self::MODE_PROFILE, 'label' => 'プロフィール住所'];
            }
            return null;
        }
        if ($mode === self::MODE_PASSPORT) {
            if ($row->passport_latitude !== null && $row->passport_longitude !== null) {
                return [
                    'lat'   => (float) $row->passport_latitude,
                    'lng'   => (float) $row->passport_longitude,
                    'mode'  => self::MODE_PASSPORT,
                    'label' => $row->passport_label !== '' ? (string) $row->passport_label : '指定位置',
                ];
            }
            return null;
        }
        if ($mode === self::MODE_CURRENT) {
            $session = (array) session(self::SESSION_KEY, []);
            if (
                isset($session['lat'], $session['lng']) &&
                is_numeric($session['lat']) && is_numeric($session['lng'])
            ) {
                return [
                    'lat'   => (float) $session['lat'],
                    'lng'   => (float) $session['lng'],
                    'mode'  => self::MODE_CURRENT,
                    'label' => '現在地',
                ];
            }
            return null;
        }
        return null;
    }

    private function resolveProfileLocation(string $castId): ?array
    {
        $row = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->select('latitude', 'longitude')
            ->first();
        if ($row && $row->latitude !== null && $row->longitude !== null) {
            return ['lat' => (float) $row->latitude, 'lng' => (float) $row->longitude];
        }
        return null;
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
            'mode'               => '',
            'passport_address'   => null,
            'passport_latitude'  => null,
            'passport_longitude' => null,
            'passport_label'     => '',
            'max_distance_km'    => null,
            'profile_location'   => null,
            'profile_address'    => '',
            'has_address'        => false,
            'shift_frequency'    => null,
            'work_periods'       => [],
            'hourly_wage_min'    => null,
            'industry_ids'       => [],
        ];
    }
}
