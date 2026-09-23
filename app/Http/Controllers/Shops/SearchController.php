<?php

namespace App\Http\Controllers\Shops;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Common\SearchController as BaseSearchController;
use App\Services\SearchScoringService;
use App\Services\UserLocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchController extends BaseSearchController
{
    use ResolvesActor;

    private const SORT_OPTIONS = [
        'hitokoto'  => 'ひとこと更新が新しい順',
        'distance'  => '距離が近い順',
        'age_asc'   => '年齢が若い順',
        'new'       => '新着登録順',
        'relevance' => 'おすすめ（マッチ度が高い順）',
    ];

    public function index(Request $request)
    {
        // 店舗側は {tab} ルートパラメータが無いためクエリで受ける（?tab=keep）
        $tab = (string) $request->query('tab', 'list');
        $tab = in_array($tab, ['list', 'keep'], true) ? $tab : 'list';
        $activeTab = 'pane-' . $tab;
        $savedPreferences = app(\App\Services\ShopSearchPreferenceService::class)->loadAll();
        $this->applySavedSearchDefaults($request, $savedPreferences, array_combine(
            ['age_min', 'age_max', 'shift_frequency', 'work_periods', 'looks_tag_ids', 'personality_tag_ids', 'night_work_exp'],
            ['age_min', 'age_max', 'shift_frequency', 'work_periods', 'looks_tag_ids', 'personality_tag_ids', 'night_work_exp']
        ));

        $sort = (string) $request->query('sort', 'hitokoto');
        if (!array_key_exists($sort, self::SORT_OPTIONS)) {
            $sort = 'hitokoto';
        }

        $items = $tab === 'list' ? $this->buildSearchItems($request, $sort) : [];

        $data = [
            'items'                  => $items,
            'activeTab'              => $activeTab,
            'searchTab'              => $tab,
            'sort'                   => $sort,
            'sortOptions'            => self::SORT_OPTIONS,
            'savedPreferences'       => $savedPreferences,
            'castTagsByCategory'     => $this->loadCastTagsByCategory(),
            'searchLocationSettings' => app(UserLocationService::class)->loadProfileSettings(),
        ];

        if ($tab === 'keep') {
            $data += $this->buildKeepPaneData();
        }

        return $this->renderIndex($data);
    }

    private function loadCastTagsByCategory(): array
    {
        if (!Schema::hasTable('cast_tags')) {
            return ['looks' => [], 'personality' => []];
        }
        $rows = DB::table('cast_tags')
            ->where('del_flg', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'category', 'name']);
        $byCat = ['looks' => [], 'personality' => []];
        foreach ($rows as $row) {
            $cat = (string) ($row->category ?? '');
            if (isset($byCat[$cat])) {
                $byCat[$cat][] = $row;
            }
        }
        return $byCat;
    }

    /**
     * 統合済み検索結果（タイムライン＋一覧）を生成する。
     */
    private function buildSearchItems(Request $request, string $sort): array
    {
        $keyword = $request->query('keyword');
        $keyword = is_string($keyword) ? trim($keyword) : '';
        $normalizedKeyword = $this->normalizeSearchText($keyword);
        $industries = $request->query('industry', []);
        $industries = is_array($industries) ? array_values(array_filter($industries, 'is_string')) : (is_string($industries) ? [$industries] : []);

        $hasCastPostsTable = Schema::hasTable('cast_posts');
        $castPostsHasBody = $hasCastPostsTable && Schema::hasColumn('cast_posts', 'body');
        $castPostsHasUpdatedAt = $hasCastPostsTable && Schema::hasColumn('cast_posts', 'updated_at');
        $castPostsHasCreatedAt = $hasCastPostsTable && Schema::hasColumn('cast_posts', 'created_at');
        $useCastPostsForHitokoto = $hasCastPostsTable && ($castPostsHasBody || $castPostsHasUpdatedAt || $castPostsHasCreatedAt);

        $rows = DB::table('casts')
            ->join('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id');

        $select = [
            'casts.id',
            'casts.created_at as cast_created_at',
            'cast_profiles.nickname',
            'cast_profiles.name',
            'cast_profiles.birthday',
            'cast_profiles.exp',
            'cast_profiles.pref',
            'cast_profiles.city',
            'cast_profiles.pr',
            'cast_profiles.latitude',
            'cast_profiles.longitude',
            DB::raw("(SELECT ci.image_path FROM cast_images ci WHERE ci.cast_id = casts.id ORDER BY ci.is_main DESC, ci.main_order IS NULL, ci.main_order, ci.id LIMIT 1) as main_image_path"),
            'cast_profiles.updated_at as profile_updated_at',
        ];

        if ($useCastPostsForHitokoto) {
            if ($castPostsHasBody) {
                $select[] = DB::raw("(SELECT cp.body FROM cast_posts cp WHERE cp.cast_id = casts.id ORDER BY COALESCE(cp.updated_at, cp.created_at) DESC, cp.id DESC LIMIT 1) as hitokoto_body");
            } else {
                $select[] = DB::raw('NULL as hitokoto_body');
            }
            if ($castPostsHasUpdatedAt) {
                $select[] = DB::raw("(SELECT cp.updated_at FROM cast_posts cp WHERE cp.cast_id = casts.id ORDER BY COALESCE(cp.updated_at, cp.created_at) DESC, cp.id DESC LIMIT 1) as hitokoto_updated_at");
            } else {
                $select[] = DB::raw('NULL as hitokoto_updated_at');
            }
            if ($castPostsHasCreatedAt) {
                $select[] = DB::raw("(SELECT cp.created_at FROM cast_posts cp WHERE cp.cast_id = casts.id ORDER BY COALESCE(cp.updated_at, cp.created_at) DESC, cp.id DESC LIMIT 1) as hitokoto_created_at");
            } else {
                $select[] = DB::raw('NULL as hitokoto_created_at');
            }
        } else {
            $select[] = DB::raw('NULL as hitokoto_body');
            $select[] = DB::raw('NULL as hitokoto_updated_at');
            $select[] = DB::raw('NULL as hitokoto_created_at');
        }

        $rows->select($select);

        $this->applySort($rows, $sort, $castPostsHasUpdatedAt);

        if (!empty($industries)) {
            $rows->join('cast_search_preferences', 'cast_search_preferences.cast_id', '=', 'casts.id')
                ->join('industries', function ($j) {
                    $j->whereRaw('JSON_CONTAINS(cast_search_preferences.industry_ids, CAST(industries.id AS JSON))');
                })
                ->whereIn('industries.name', $industries)
                ->distinct();
        }

        // 距離フィルタ：新クエリ（location_mode）が優先、無ければ MyPage 永続設定
        $userLocation = app(UserLocationService::class);
        $persistedOrigin = $userLocation->getActiveLocation();
        $persistedMaxKm = (int) ($userLocation->getEffectiveMaxDistanceKm() ?? 0);

        $locationMode = (string) $request->query('location_mode', '');
        if (!in_array($locationMode, ['none', 'profile', 'passport', 'current'], true)) {
            $locationMode = '';
        }
        $queryDistanceKm = (int) $request->query('distance_km', 0);

        $origin = $locationMode === 'none' ? null : $persistedOrigin;
        if ($locationMode === 'profile') {
            $settings = $userLocation->loadProfileSettings();
            $profileLoc = $settings['profile_location'] ?? null;
            $origin = (is_array($profileLoc) && isset($profileLoc['lat'], $profileLoc['lng']))
                ? ['lat' => (float) $profileLoc['lat'], 'lng' => (float) $profileLoc['lng']]
                : null;
        } elseif ($locationMode === 'passport') {
            $pLat = $request->query('passport_lat');
            $pLng = $request->query('passport_lng');
            $origin = (is_numeric($pLat) && is_numeric($pLng))
                ? ['lat' => (float) $pLat, 'lng' => (float) $pLng]
                : null;
        } elseif ($locationMode === 'current') {
            $cLat = $request->query('current_lat');
            $cLng = $request->query('current_lng');
            $origin = (is_numeric($cLat) && is_numeric($cLng))
                ? ['lat' => (float) $cLat, 'lng' => (float) $cLng]
                : null;
        }

        $legacyHasFilter = $queryDistanceKm > 0
            && in_array((string) $request->query('location_type'), ['current', 'geo'], true);
        $distanceKmLimit = ($locationMode !== '' && $queryDistanceKm > 0)
            ? $queryDistanceKm
            : ($legacyHasFilter ? $queryDistanceKm : $persistedMaxKm);
        $useDistance = $origin && $distanceKmLimit > 0;

        $allRows = $rows->get();

        // --- スコアリング用コンテキストの一括ロード ---
        $scoring = app(SearchScoringService::class);
        $prefs   = app(\App\Services\ShopSearchPreferenceService::class)->loadAll();

        $keywordTokens = $scoring->tokenize($normalizedKeyword);
        $castIds = $allRows->pluck('id')->map(fn ($v) => (string) $v)->values()->all();
        $castTagsByCastId  = $scoring->loadCastTagsByCastIds($castIds);
        $castPrefsByCastId = $scoring->loadCastPrefsByCastIds($castIds);

        $scoringContext = [
            'keywordTokens'    => $keywordTokens,
            'normalize'        => fn (string $s) => $this->normalizeSearchText($s),
            'prefs'            => $prefs,
            'castTagsByCastId' => $castTagsByCastId,
            'castPrefsByCastId'=> $castPrefsByCastId,
        ];

        // キーワード絞り込み: 入力をトークン化し、各トークンが少なくとも1つのフィールドに含まれること
        // （AND-of-tokens / OR-of-fields）。ヒット順位はスコアで決定。
        $filters = $request->only(['age_min', 'age_max', 'shift_frequency', 'work_periods', 'looks_tag_ids', 'personality_tag_ids', 'night_work_exp']);
        $filterService = app(\App\Services\SearchFilterService::class);

        // Candidate-date filter: only casts who declared the selected date
        $availabilityService = app(\App\Services\AvailabilityService::class);
        $availableOn = $availabilityService->normalizeFilterDate($request->query('available_on'));
        $availableCastIdSet = $availableOn !== null
            ? array_fill_keys($availabilityService->ownerIdsAvailableOn(\App\Models\AvailabilityDate::OWNER_CAST, $availableOn), true)
            : null;

        $items = $allRows
            ->filter(function ($row) use ($keywordTokens, $filters, $castTagsByCastId, $castPrefsByCastId, $filterService, $availableCastIdSet) {
                if ($availableCastIdSet !== null && !isset($availableCastIdSet[(string) $row->id])) {
                    return false;
                }
                if (!$filterService->matchesCast($row, $filters, $castTagsByCastId[$row->id] ?? [], $castPrefsByCastId[$row->id] ?? [])) {
                    return false;
                }
                if ($keywordTokens === []) {
                    return true;
                }
                $haystack = $this->normalizeSearchText(implode(' ', array_filter([
                    $row->nickname,
                    $row->name,
                    $row->pref,
                    $row->city,
                    $row->pr,
                    $row->hitokoto_body ?? null,
                ])));
                foreach ($keywordTokens as $token) {
                    if (!str_contains($haystack, $token)) {
                        return false;
                    }
                }
                return true;
            })
            ->map(function ($row) use ($userLocation, $origin, $scoring, $scoringContext) {
                $birthday = $row->birthday ? Carbon::parse($row->birthday) : null;
                $hitokotoTs = null;
                if (!empty($row->hitokoto_updated_at)) {
                    $hitokotoTs = Carbon::parse($row->hitokoto_updated_at);
                } elseif (!empty($row->hitokoto_created_at)) {
                    $hitokotoTs = Carbon::parse($row->hitokoto_created_at);
                }
                if ($hitokotoTs === null && !empty($row->profile_updated_at)) {
                    $hitokotoTs = Carbon::parse($row->profile_updated_at);
                }

                // 「ひとこと」は cast_posts の最新投稿が第一。
                // 投稿がまだ無いキャストは自己紹介 (cast_profiles.pr) の冒頭を代替表示することで
                // 一覧のスキマ（何も表示されない行）を無くす。
                $hitokotoBody = (string) ($row->hitokoto_body ?? '');
                if ($hitokotoBody === '') {
                    $pr = trim((string) ($row->pr ?? ''));
                    if ($pr !== '') {
                        // 1行に整えて、長すぎたら省略（吹き出しに収まるサイズ）
                        $prOneLine = preg_replace('/\s+/u', ' ', $pr) ?? $pr;
                        $hitokotoBody = mb_strlen($prOneLine) > 80
                            ? mb_substr($prOneLine, 0, 80) . '…'
                            : $prOneLine;
                    }
                }

                $distanceKm = $origin
                    ? $userLocation->distanceKm(
                        $origin['lat'], $origin['lng'],
                        $row->latitude !== null ? (float) $row->latitude : null,
                        $row->longitude !== null ? (float) $row->longitude : null
                    )
                    : null;

                // 一致度スコア（店舗の保存条件 + キーワード重み付け）
                $sc = $scoring->scoreCastRow($row, $scoringContext);

                return [
                    'id'                  => $row->id,
                    'name'                => $this->castDisplayName($row),
                    'age'                 => $birthday?->age,
                    'img'                 => $this->getCastImages((string) $row->id)[0] ?? asset('assets/images/common/no-image.png'),
                    'pref'                => $row->pref ?? '',
                    'city'                => $row->city ?? '',
                    'pr'                  => (string) ($row->pr ?? ''),
                    'hitokoto'            => $hitokotoBody,
                    'hitokoto_updated_at' => $hitokotoTs?->locale('ja')->diffForHumans(),
                    'hitokoto_ts'         => $hitokotoTs?->getTimestamp(),
                    'distance_km'         => $distanceKm,
                    'distance_label'      => $distanceKm !== null ? $userLocation->formatDistance($distanceKm) : null,
                    'match_score'         => $sc['score'],
                    'match_count'         => $sc['matched'],
                    'match_total'         => $sc['total'],
                    'match_reasons'       => $sc['reasons'],
                    'match_summary'       => $sc['total'] > 0
                        ? "条件 {$sc['matched']}/{$sc['total']} 件一致"
                        : null,
                ];
            })
            ->filter(function ($item) use ($useDistance, $distanceKmLimit) {
                if (!$useDistance) {
                    return true;
                }
                $km = $item['distance_km'] ?? null;
                if ($km === null) {
                    return true;
                }
                return $km <= $distanceKmLimit;
            })
            ->values()
            ->all();

        // 'relevance' / 'distance' は PHP 側で再ソート（距離は SQL 後に計算されるため）。
        // ほかは DB 側の ORDER BY に従う。
        if ($sort === 'relevance') {
            usort($items, function ($a, $b) {
                if ($a['match_score'] !== $b['match_score']) {
                    return $b['match_score'] <=> $a['match_score'];
                }
                // 同点はひとこと更新の新しい順
                return ($b['hitokoto_ts'] ?? 0) <=> ($a['hitokoto_ts'] ?? 0);
            });
        } elseif ($sort === 'distance') {
            usort($items, function ($a, $b) {
                $ka = $a['distance_km'] ?? null;
                $kb = $b['distance_km'] ?? null;
                // 距離不明（位置未登録）は最後尾。同士はひとこと更新の新しい順
                if ($ka === null && $kb === null) {
                    return ($b['hitokoto_ts'] ?? 0) <=> ($a['hitokoto_ts'] ?? 0);
                }
                if ($ka === null) return 1;
                if ($kb === null) return -1;
                return $ka <=> $kb;
            });
        }

        // hitokoto_ts は内部用なので返却前に落とす
        foreach ($items as &$it) {
            unset($it['hitokoto_ts']);
        }
        unset($it);

        // KEEP/LIKE 初期状態を付与
        $items = $this->attachFavoriteStates($items, 'cast');

        return $items;
    }

    public function savePreferences(\Illuminate\Http\Request $request, \App\Services\ShopSearchPreferenceService $prefs)
    {
        $data = $request->validate([
            'max_distance_km' => ['nullable', 'integer', 'in:0,1,3,5,10,20,30,50,100'],
            'age_min'         => ['nullable', 'integer', 'min:18', 'max:99'],
            'age_max'         => ['nullable', 'integer', 'min:18', 'max:99'],
            'shift_frequency' => ['nullable', 'string', 'in:週1回出勤,週2回出勤,週3回以上'],
            'work_periods'    => ['nullable', 'array'],
            'work_periods.*'  => ['string', 'in:morning,day,night'],
            'looks_tag_ids'   => ['nullable', 'array'],
            'looks_tag_ids.*' => ['integer'],
            'personality_tag_ids'   => ['nullable', 'array'],
            'personality_tag_ids.*' => ['integer'],
            'night_work_exp'  => ['nullable', 'string', 'in:none,yes,any'],
        ]);
        $prefs->savePreferences($data);
        return response()->json(['success' => true, 'preferences' => $prefs->loadAll()]);
    }

    private function applySort($rows, string $sort, bool $castPostsHasUpdatedAt): void
    {
        switch ($sort) {
            case 'new':
                $rows->orderByDesc('casts.created_at')->orderByDesc('casts.id');
                break;
            case 'age_asc':
                $rows->orderByDesc('cast_profiles.birthday')->orderByDesc('casts.id');
                break;
            case 'hitokoto':
            case 'distance': // 距離は SQL 後に PHP で再ソート。SQL 側は既定並びで取得
            default:
                if ($castPostsHasUpdatedAt) {
                    // ひとこと最終更新が新しい順。未投稿のキャストは最後に回す。
                    $rows->orderByRaw('(SELECT MAX(cp.updated_at) FROM cast_posts cp WHERE cp.cast_id = casts.id) IS NULL')
                        ->orderByRaw('(SELECT MAX(cp.updated_at) FROM cast_posts cp WHERE cp.cast_id = casts.id) DESC')
                        ->orderByDesc('cast_profiles.updated_at')
                        ->orderByDesc('casts.id');
                } else {
                    $rows->orderByDesc('cast_profiles.updated_at')->orderByDesc('casts.id');
                }
                break;
        }
    }

    private function getCastImages(string $castId): array
    {
        $images = DB::table('cast_images')
            ->where('cast_id', $castId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('image_path')
            ->map(fn ($path) => $this->assetPathForStored($path))
            ->filter()
            ->values()
            ->all();

        if (empty($images)) {
            $images[] = asset('assets/images/common/no-image.png');
        }

        return $images;
    }

    private function castDisplayName(object $row): string
    {
        return (string) ($row->nickname ?: $row->name ?: 'キャスト');
    }

    // assetPathForStored() is now provided by ResolvesActor trait.
}
