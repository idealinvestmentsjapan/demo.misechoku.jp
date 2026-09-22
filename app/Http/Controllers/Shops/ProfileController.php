<?php
namespace App\Http\Controllers\Shops;

use App\Consts\CommonConsts;
use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Services\AdminMasterService;
use App\Services\ShopProfileLocationSyncService;
use App\Support\ShopBusinessHours;
use Illuminate\Http\Request;
use App\Http\Requests\Shops\UploadImageRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private readonly AdminMasterService $adminMasterService,
        private readonly ShopProfileLocationSyncService $shopProfileLocationSyncService,
    ) {
    }

    /**
     * 編集画面表示
     */
    public function edit() {
        return view('shops.profile.edit', [
            'pageId' => 'mypage',
            'shopData' => $this->buildShopEditData($this->currentShopId()),
            'masters' => $this->adminMasterService->getShopProfileMasters(),
            'prefOptions' => CommonConsts::PREFS,
            'hasProfileAddr' => Schema::hasColumn('shop_profiles', 'addr'),
            'hasProfileBusinessHours' => Schema::hasColumn('shop_profiles', 'open_time'),
            'hasShopStationsTable' => Schema::hasTable('shop_stations'),
            'hasProfileTel' => Schema::hasColumn('shop_profiles', 'tel'),
        ]);
    }

    /**
     * プロフィール基本情報の更新
     * (旧 api/update_mypage.php, api/update_shop.php の機能を統合)
     */
    public function update(Request $request) {
        $rules = [
            'shop_name' => 'required|string|max:100',
            'industry_label' => 'nullable|string|max:60',
            'zip' => ['nullable', 'regex:/^\d{3}-?\d{4}$/'],
            'pref' => ['required', 'string', Rule::in(CommonConsts::PREFS)],
            'city' => 'nullable|string|max:100',
            'addr1' => 'nullable|string|max:255',
            'addr' => 'nullable|string|max:255',
            'building' => 'nullable|string|max:255',
            'tel' => 'nullable|string|max:20',
            'industry_ids' => 'nullable|array|max:1',
            'industry_ids.*' => 'integer|exists:industries,id',
            'atmosphere_tag_ids'   => 'nullable|array',
            'atmosphere_tag_ids.*' => 'integer|exists:shop_tags,id',
            'facility_tag_ids'     => 'nullable|array',
            'facility_tag_ids.*'   => 'integer|exists:shop_tags,id',
            'business_open' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'business_close' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'business_close_last' => 'nullable|boolean',
            'stations' => 'nullable|array',
            'stations.*' => 'nullable|string|max:255',
        ];
        $request->validate($rules, [
            'zip.regex' => '郵便番号は 7 桁、または 123-4567 形式で入力してください。',
        ]);

        $shopId = $this->currentShopId();
        $imageCount = (int) DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->count();

        if ($imageCount < 1) {
            return response()->json([
                'success' => false,
                'message' => 'ホーム表示用の画像を1枚以上登録してください。',
            ], 422);
        }

        $existingProfile = DB::table('shop_profiles')->where('shop_id', $shopId)->first();
        $addressChanged = $this->profileAddressChanged($existingProfile, $request);

        // 業種は 1 店舗 1 つ。複数送信されても先頭のみ採用する。
        $industryIds = collect($request->input('industry_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(1)
            ->values()
            ->all();

        $profileRow = [
            'shop_name' => $request->input('shop_name'),
            'zip' => $this->normalizeZip($request->input('zip')),
            'pref' => $request->input('pref'),
            'city' => $request->input('city'),
            'industry_id' => $industryIds[0] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ];
        if (Schema::hasColumn('shop_profiles', 'industry_label')) {
            $label = trim((string) $request->input('industry_label', ''));
            $profileRow['industry_label'] = $label !== '' ? $label : null;
        }

        if (Schema::hasColumn('shop_profiles', 'addr')) {
            $profileRow['addr'] = trim((string) $request->input('addr', ''));
            $profileRow['building'] = trim((string) $request->input('building', ''));
        } elseif (Schema::hasColumn('shop_profiles', 'addr2')) {
            $profileRow['addr2'] = $request->input('addr1');
        }

        if (Schema::hasColumn('shop_profiles', 'tel')) {
            $profileRow['tel'] = trim((string) $request->input('tel', '')) ?: null;
        }

        if (Schema::hasColumn('shop_profiles', 'open_time')) {
            $bh = ShopBusinessHours::normalizeFromRequest(
                (string) $request->input('business_open', ''),
                $request->boolean('business_close_last'),
                (string) $request->input('business_close', '')
            );
            $profileRow['open_time'] = $bh['open_time'];
            $profileRow['close_is_last'] = $bh['close_is_last'];
            $profileRow['close_time'] = $bh['close_time'];
        }

        $stationsFilledFromApi = false;
        if (
            $addressChanged
            && Schema::hasColumn('shop_profiles', 'latitude')
            && Schema::hasColumn('shop_profiles', 'longitude')
        ) {
            $fullAddress = $this->shopProfileLocationSyncService->buildFullAddressLineForGeocode($request);
            $resolved = $this->shopProfileLocationSyncService->resolveFromAddressLine($fullAddress);
            $profileRow['latitude'] = $resolved['latitude'];
            $profileRow['longitude'] = $resolved['longitude'];
            if (Schema::hasTable('shop_stations')) {
                $this->shopProfileLocationSyncService->replaceShopStationsRows($shopId, $resolved['station_rows']);
                $stationsFilledFromApi = true;
            }
        }

        DB::table('shop_profiles')->updateOrInsert(
            ['shop_id' => $shopId],
            $profileRow
        );

        if (Schema::hasTable('shop_stations') && !$stationsFilledFromApi) {
            $this->syncShopStations($shopId, $request->input('stations', []));
        }

        $this->syncShopIndustries($shopId, $industryIds);
        $this->syncShopProfileTags($shopId, 'atmosphere', $request->input('atmosphere_tag_ids', []));
        $this->syncShopProfileTags($shopId, 'facility',   $request->input('facility_tag_ids', []));

        return redirect()
            ->route('shop.mypage.index')
            ->with('message', 'プロフィールを更新しました');
    }

    /**
     * 住所入力中の最寄り駅候補を非同期で返す（保存はしない）
     */
    public function suggestStations(Request $request)
    {
        if (!Schema::hasTable('shop_stations')) {
            return response()->json(['stations' => []]);
        }

        $fullAddress = $this->shopProfileLocationSyncService->buildFullAddressLineForGeocode($request);
        if ($fullAddress === '') {
            return response()->json(['stations' => []]);
        }

        $resolved = $this->shopProfileLocationSyncService->resolveFromAddressLine($fullAddress);
        $stations = collect($resolved['station_rows'] ?? [])
            ->pluck('station_name')
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values()
            ->all();

        return response()->json(['stations' => $stations]);
    }

    /**
     * shop_tag_relations を新スキーマ (shop_tags target='shop') と同期する.
     */

    private function profileAddressChanged(?object $existing, Request $request): bool
    {
        if (!$existing) {
            return true;
        }
        $pref = trim((string) $request->input('pref', ''));
        $city = trim((string) $request->input('city', ''));
        $p0 = trim((string) ($existing->pref ?? ''));
        $c0 = trim((string) ($existing->city ?? ''));
        if ($p0 !== $pref || $c0 !== $city) {
            return true;
        }
        if (Schema::hasColumn('shop_profiles', 'addr')) {
            return trim((string) ($existing->addr ?? '')) !== trim((string) $request->input('addr', ''))
                || trim((string) ($existing->building ?? '')) !== trim((string) $request->input('building', ''));
        }
        $incoming = trim((string) $request->input('addr1', ''));
        $existingStreet = trim(trim((string) ($existing->addr2 ?? '')) . ' ' . trim((string) ($existing->addr3 ?? '')));

        return $existingStreet !== $incoming;
    }

    /**
     * @param  array<int, mixed>  $lines
     */
    private function syncShopStations(string $shopId, array $lines): void
    {
        DB::table('shop_stations')->where('shop_id', $shopId)->delete();
        $order = 0;
        foreach ($lines as $line) {
            $t = is_string($line) ? trim($line) : '';
            if ($t === '') {
                continue;
            }
            DB::table('shop_stations')->insert([
                'shop_id' => $shopId,
                'station_name' => $t,
                'sort_order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $order++;
        }
    }

    private function syncShopProfileTags(string $shopId, string $category, array $tagIds): void
    {
        if (!Schema::hasTable('shop_tag_relations') || !Schema::hasTable('shop_tags')) {
            return;
        }

        DB::table('shop_tag_relations')
            ->where('shop_id', $shopId)
            ->where('tag_type', $category)
            ->delete();

        $ids = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($ids)) {
            return;
        }

        $validIds = DB::table('shop_tags')
            ->where('target', 'shop')
            ->where('category', $category)
            ->where('del_flg', 0)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (empty($validIds)) {
            return;
        }

        $rows = array_map(fn ($tagId) => [
            'shop_id'    => $shopId,
            'tag_id'     => $tagId,
            'tag_type'   => $category,
            'created_at' => now(),
            'updated_at' => now(),
        ], $validIds);
        DB::table('shop_tag_relations')->insert($rows);
    }

    /**
     * 画像アップロード（DB: shop_images に保存）
     */
    public function uploadImage(UploadImageRequest $request)
    {
        if (!$request->hasFile('image')) {
            return response()->json(['success' => false, 'message' => 'ファイルが見つかりません'], 400);
        }

        try {
            $dir = public_path('uploads/shops/gallery');
            File::ensureDirectoryExists($dir);
            $file = $request->file('image');
            $name = $file->hashName();
            $file->move($dir, $name);
            $path = 'uploads/shops/gallery/' . $name;
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => '画像の保存に失敗しました。'], 500);
        }

        $slotIndex = (int) $request->input('slot_index', -1);

        $shopId = $this->currentShopId();
        $maxOrder = DB::table('shop_images')->where('shop_id', $shopId)->max('main_order');
        $mainOrder = $maxOrder !== null ? $maxOrder + 1 : 0;
        $isMain = $slotIndex === 0 ? 1 : 0;

        $id = DB::table('shop_images')->insertGetId([
            'shop_id'    => $shopId,
            'image_path' => $path,
            'type'       => null,
            'is_main'    => $isMain,
            'main_order' => $mainOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderedIds = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($imageId) => (int) $imageId)
            ->all();

        $orderedIds = array_values(array_filter($orderedIds, fn ($imageId) => $imageId !== (int) $id));
        $slotIndex = max(0, min($slotIndex, count($orderedIds)));
        array_splice($orderedIds, $slotIndex, 0, [(int) $id]);

        // 並び順の同期は副次処理。万一 sync で例外が出ても shop_images の登録自体は完了しているので、
        // ユーザーには成功として返し、サーバ側にはログを残す。
        try {
            $this->syncShopImageOrder($shopId, $orderedIds);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('syncShopImageOrder failed after upload: ' . $e->getMessage(), [
                'shop_id' => $shopId,
                'image_id' => $id,
            ]);
        }

        return response()->json([
            'success' => true,
            'path'    => asset($path),
            'id'      => $id,
        ]);
    }

    /**
     * 画像削除（DB: shop_images から削除しストレージも削除）
     */
    public function deleteImage(Request $request, $id)
    {
        $shopId = $this->currentShopId();

        $currentCount = (int) DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->count();

        $row = DB::table('shop_images')
            ->where('id', $id)
            ->where('shop_id', $shopId)
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => '画像が見つかりません'], 404);
        }

        if ($currentCount <= 1) {
            return response()->json(['success' => false, 'message' => '店舗画像は1枚以上必要です。最低1枚は残してください。'], 422);
        }

        $fullPath = str_starts_with($row->image_path ?? '', 'uploads/')
            ? public_path($row->image_path)
            : storage_path('app/' . $row->image_path);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
        DB::table('shop_images')->where('id', $id)->delete();

        $orderedIds = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($imageId) => (int) $imageId)
            ->all();

        $this->syncShopImageOrder($shopId, $orderedIds);

        return response()->json(['success' => true, 'message' => '画像を削除しました']);
    }

    /**
     * 画像の並び替え順序の保存
     * (旧 api/update_image_order.php の機能を統合)
     */
    public function updateOrder(Request $request) {
        $imageOrder = $request->input('images');

        if (!is_array($imageOrder)) {
            return response()->json(['success' => false, 'message' => 'データが不正です'], 400);
        }

        $shopId = $this->currentShopId();
        $orderedIds = array_values(array_unique(array_map('intval', $imageOrder)));
        $this->syncShopImageOrder($shopId, $orderedIds);

        return response()->json(['success' => true, 'message' => '並び順を保存しました']);
    }

    private function syncShopImageOrder(string $shopId, array $orderedIds): void
    {
        $existingImages = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->get(['id', 'image_path']);

        $existingIds = $existingImages->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orderedIds = array_values(array_intersect($orderedIds, $existingIds));

        foreach ($existingIds as $imageId) {
            if (!in_array($imageId, $orderedIds, true)) {
                $orderedIds[] = $imageId;
            }
        }

        DB::transaction(function () use ($shopId, $orderedIds, $existingImages) {
            DB::table('shop_images')
                ->where('shop_id', $shopId)
                ->update([
                    'is_main' => 0,
                    'updated_at' => now(),
                ]);

            foreach ($orderedIds as $index => $imageId) {
                DB::table('shop_images')
                    ->where('shop_id', $shopId)
                    ->where('id', $imageId)
                    ->update([
                        'main_order' => $index,
                        'is_main' => $index === 0 ? 1 : 0,
                        'updated_at' => now(),
                    ]);
            }

            // shop_profiles.main_image_path は旧スキーマのみのカラムなので、
            // カラムが存在する環境だけ更新する。
            if (Schema::hasColumn('shop_profiles', 'main_image_path')) {
                $mainImageId = $orderedIds[0] ?? null;
                $mainImagePath = $mainImageId
                    ? optional($existingImages->firstWhere('id', $mainImageId))->image_path
                    : null;

                DB::table('shop_profiles')
                    ->where('shop_id', $shopId)
                    ->update([
                        'main_image_path' => $mainImagePath,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function buildShopEditData(string $shopId): array
    {
        $row = DB::table('shop_profiles')
            ->where('shop_id', $shopId)
            ->first();

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $shopTagIds = $this->fetchSelectedShopTagIds($shopId);

        $addrOut = '';
        $addrStreet = '';
        $building = '';
        if ($row && Schema::hasColumn('shop_profiles', 'addr')) {
            $addrStreet = (string) ($row->addr ?? '');
            $building = (string) ($row->building ?? '');
            $addrOut = trim(implode(' ', array_filter([$addrStreet, $building])));
        } elseif ($row) {
            $addrOut = trim(implode(' ', array_filter([$row->addr2 ?? null, $row->addr3 ?? null])));
        }

        $stations = [''];
        if (Schema::hasTable('shop_stations')) {
            $names = DB::table('shop_stations')
                ->where('shop_id', $shopId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('station_name')
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->values()
                ->all();
            $stations = !empty($names) ? $names : [''];
        }

        $data = [
            'shop_name' => $row ? ($row->shop_name ?? '') : '',
            'word' => $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '',
            'overview' => '',
            'zip' => $row ? ($row->zip ?? '') : '',
            'pref' => $row ? (($row->pref ?? '') !== '' ? (string) $row->pref : '東京都') : '東京都',
            'city' => $row ? ($row->city ?? '') : '',
            'addr1' => $addrOut,
            'addr' => $addrStreet,
            'building' => $building,
            'tel' => ($row && Schema::hasColumn('shop_profiles', 'tel')) ? (string) ($row->tel ?? '') : '',
            'industry_ids' => $this->resolveShopIndustryIds($shopId, $row ? ($row->industry_id ?? null) : null),
            'industry_label' => ($row && Schema::hasColumn('shop_profiles', 'industry_label')) ? (string) ($row->industry_label ?? '') : '',
            'atmosphere_tag_ids' => $shopTagIds['atmosphere'],
            'facility_tag_ids'   => $shopTagIds['facility'],
            'stations' => $stations,
            'business_open' => '',
            'business_close' => '',
            'business_close_last' => false,
        ];

        if ($row && Schema::hasColumn('shop_profiles', 'open_time')) {
            $data['business_open'] = ShopBusinessHours::formatTimeHhmm($row->open_time ?? null);
            $data['business_close_last'] = (bool) (int) ($row->close_is_last ?? 0);
            $data['business_close'] = ShopBusinessHours::formatTimeHhmm($row->close_time ?? null);
        }

        return $data;
    }

    /**
     * 店舗プロフィール用に選択中の shop_tag_relations を新スキーマで取得.
     *
     * @return array{atmosphere: array<int,int>, facility: array<int,int>}
     */
    private function fetchSelectedShopTagIds(string $shopId): array
    {
        $result = ['atmosphere' => [], 'facility' => []];
        if (!Schema::hasTable('shop_tag_relations') || !Schema::hasTable('shop_tags')) {
            return $result;
        }

        $rows = DB::table('shop_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_id', $shopId)
            ->where('t.target', 'shop')
            ->whereIn('t.category', ['atmosphere', 'facility'])
            ->where('t.del_flg', 0)
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->select('t.id', 't.category')
            ->get();

        foreach ($rows as $r) {
            $cat = (string) $r->category;
            if (isset($result[$cat])) {
                $result[$cat][] = (int) $r->id;
            }
        }

        return $result;
    }

    private function currentShopId(): string
    {
        return (string) auth()->guard('shop')->user()->shop_id;
    }

    // assetPathForStored() is now provided by ResolvesActor trait.

    private function normalizeZip(?string $zip): ?string
    {
        if ($zip === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $zip);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) !== 7) {
            return trim($zip);
        }

        return substr($digits, 0, 3) . '-' . substr($digits, 3);
    }

    /**
     * @return array<int, int>
     */
    private function resolveShopIndustryIds(string $shopId, $fallbackIndustryId = null): array
    {
        $single = (int) ($fallbackIndustryId ?? 0);
        if ($single <= 0) {
            $single = (int) DB::table('shop_profiles')
                ->where('shop_id', $shopId)
                ->value('industry_id');
        }
        return $single > 0 ? [$single] : [];
    }

    /**
     * @param array<int, int> $industryIds
     */
    private function syncShopIndustries(string $shopId, array $industryIds): void
    {
        $industryId = $industryIds[0] ?? null;
        DB::table('shop_profiles')
            ->where('shop_id', $shopId)
            ->update(['industry_id' => $industryId ? (int) $industryId : null]);
    }
}
