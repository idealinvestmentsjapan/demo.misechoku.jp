<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use App\Services\UserLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ログインユーザの探索拠点（現在地 or パスポートモード）を保存／解除する。
 */
class LocationController extends Controller
{
    public function __construct(
        private readonly UserLocationService $userLocation,
        private readonly GeocodingService $geocodingService,
    ) {
    }

    /**
     * POST /setting/location
     *
     * Modes:
     *  - current  : browser geolocation {lat, lng}
     *  - passport : address (or pre-resolved lat/lng from suggest) is geocoded then saved
     *  - profile  : fall back to the profile address (geocoded on demand)
     *
     * The chosen mode is also persisted to cast_search_preferences so that a
     * previously saved DB mode does not shadow the modal selection.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:current,passport,profile'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:80'],
            'max_distance_km' => ['nullable', 'integer', 'in:0,1,3,5,10,20,30,50,100'],
        ]);

        $mode = (string) $data['mode'];
        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        $label = (string) ($data['label'] ?? '');
        $address = trim((string) ($data['address'] ?? ''));
        $maxKm = isset($data['max_distance_km'])
            ? (int) $data['max_distance_km']
            : (int) ($this->userLocation->getEffectiveMaxDistanceKm() ?? 0);

        if ($mode === UserLocationService::MODE_PROFILE) {
            $this->userLocation->clear();
            $this->userLocation->saveSearchSettings([
                'mode' => UserLocationService::MODE_PROFILE,
                'max_distance_km' => $maxKm,
            ]);
            $resolved = $this->userLocation->getActiveLocation();
            if (!$resolved) {
                // Profile coordinates missing: try geocoding the profile address once.
                $this->userLocation->geocodeAndSaveProfileLocation($this->geocodingService);
                $resolved = $this->userLocation->getActiveLocation();
            }
            if (!$resolved) {
                return response()->json([
                    'success' => false,
                    'message' => 'プロフィール住所が未登録のため拠点にできません。プロフィール編集から住所を登録してください。',
                ], 422);
            }
            return response()->json(['success' => true, 'location' => $resolved]);
        }

        if ($mode === UserLocationService::MODE_PASSPORT && (!isset($lat, $lng))) {
            if ($address === '') {
                return response()->json([
                    'success' => false,
                    'message' => '住所または駅名を入力してください。',
                ], 422);
            }
            $coords = $this->geocodingService->fromAddress($address);
            if (!$coords) {
                return response()->json([
                    'success' => false,
                    'message' => '指定の住所から緯度経度を取得できませんでした。別の表現で試してください。',
                ], 422);
            }
            $lat = (float) $coords['latitude'];
            $lng = (float) $coords['longitude'];
            if ($label === '') {
                $label = $address;
            }
        }

        if (!isset($lat, $lng)) {
            return response()->json([
                'success' => false,
                'message' => '緯度／経度が指定されていません。',
            ], 422);
        }

        $this->userLocation->setManualLocation($mode, $lat, $lng, $label);
        $this->userLocation->saveSearchSettings([
            'mode' => $mode,
            'max_distance_km' => $maxKm,
            'passport_address' => $address !== '' ? $address : ($label !== '' ? $label : null),
            'passport_latitude' => $lat,
            'passport_longitude' => $lng,
            'passport_label' => $label !== '' ? $label : null,
        ]);

        $resolved = $this->userLocation->getActiveLocation();
        return response()->json([
            'success' => true,
            'location' => $resolved,
        ]);
    }

    /**
     * DELETE /setting/location
     *
     * Reset to the profile-address origin (also clears the persisted DB mode,
     * otherwise a saved passport/current mode would survive the reset).
     */
    public function destroy(): JsonResponse
    {
        $this->userLocation->clear();
        $this->userLocation->saveSearchSettings([
            'mode' => UserLocationService::MODE_PROFILE,
            'max_distance_km' => (int) ($this->userLocation->getEffectiveMaxDistanceKm() ?? 0),
        ]);
        return response()->json(['success' => true, 'location' => $this->userLocation->getActiveLocation()]);
    }

    /**
     * POST /setting/location/radius
     *
     * Update only the search radius (works for both cast and shop).
     */
    public function updateRadius(Request $request): JsonResponse
    {
        $data = $request->validate([
            'max_distance_km' => ['required', 'integer', 'in:0,1,3,5,10,20,30,50,100'],
        ]);

        $this->userLocation->saveMaxDistanceKm((int) $data['max_distance_km']);

        return response()->json([
            'success' => true,
            'max_distance_km' => $this->userLocation->getEffectiveMaxDistanceKm(),
            'location' => $this->userLocation->getActiveLocation(),
        ]);
    }

    /**
     * GET /api/geocoding/lookup?q=...
     *
     * 住所・駅名を緯度経度に解決する（プレビュー用、保存はしない）。
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $address = trim((string) $data['q']);
        if ($address === '') {
            return response()->json([
                'success' => false,
                'message' => '住所または駅名を入力してください。',
            ], 422);
        }

        $coords = $this->geocodingService->fromAddress($address);
        if (!$coords) {
            return response()->json([
                'success' => false,
                'message' => '指定の住所から緯度経度を取得できませんでした。別の表現で試してください。',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'label' => $address,
            'latitude' => (float) $coords['latitude'],
            'longitude' => (float) $coords['longitude'],
        ]);
    }

    /**
     * GET /api/geocoding/suggest?q=...
     *
     * 入力に応じて住所候補を最大 8 件返す（オートサジェスト用）。
     */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $candidates = $this->geocodingService->searchCandidates((string) $data['q'], 8);

        return response()->json([
            'success' => true,
            'candidates' => $candidates,
        ]);
    }
}
