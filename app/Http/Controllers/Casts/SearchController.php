<?php

namespace App\Http\Controllers\Casts;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Common\SearchController as BaseSearchController;
use App\Services\AdminMasterService;
use App\Services\SearchScoringService;
use App\Services\UserLocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchController extends BaseSearchController
{
    use ResolvesActor;

    private const SORT_OPTIONS = [
        'hitokoto'  => 'ひとこと更新が新しい順',
        'distance'  => '距離が近い順',
        'name'      => '店舗名順',
        'wage'      => '時給が高い順',
        'reward'    => 'ボーナスが高い順',
        'new'       => '新着登録順',
        'relevance' => 'おすすめ（マッチ度が高い順）',
    ];

    public function __construct(private readonly AdminMasterService $adminMasterService)
    {
    }

    public function index(Request $request, ?string $tab = 'list')
    {
        if ($tab === 'search') {
            $tab = 'list';
        }
        $tab = in_array($tab, ['list', 'ai', 'keep'], true) ? $tab : 'list';
        $activeTab = 'pane-' . $tab;
        $savedPreferences = app(\App\Services\CastSearchPreferenceService::class)->loadAll();
        $this->applySavedSearchDefaults($request, $savedPreferences, [
            'industry_ids' => 'industry_ids', 'hourly_wage' => 'hourly_wage_min',
            'shift_frequency' => 'shift_frequency', 'work_periods' => 'work_periods',
        ]);

        $sort = (string) $request->query('sort', 'relevance');
        if (!array_key_exists($sort, self::SORT_OPTIONS)) {
            $sort = 'relevance';
        }

        $items = $tab === 'list' ? $this->buildSearchItems($request, $sort) : [];
        $personalityType = $this->currentCastPersonalityType();

        $data = [
            'items'                  => $items,
            'personalityType'        => $personalityType,
            'activeTab'              => $activeTab,
            'searchTab'              => $tab,
            'sort'                   => $sort,
            'sortOptions'            => self::SORT_OPTIONS,
            'detailSearchOptions'    => $this->buildDetailSearchOptions(),
            'savedPreferences'       => $savedPreferences,
            'searchLocationSettings' => app(UserLocationService::class)->loadProfileSettings(),
        ];

        if ($tab === 'keep') {
            $data += $this->buildKeepPaneData();
        }

        return $this->renderIndex($data);
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
        // Detail search modal posts industry_ids[] (master IDs). Prefer these over legacy industry[] (names).
        $industryIds = $this->normalizeIdFilters($request->query('industry_ids', []));
        $areas = $request->query('area', []);
        $areas = is_array($areas) ? array_values(array_filter($areas, 'is_string')) : (is_string($areas) ? [$areas] : []);
        $hourlyWage = (int) $request->query('hourly_wage', 0);
        $reward = (int) $request->query('reward', 0);
        $jobTagFilters = [
            'work_style' => $this->normalizeIdFilters($request->query('work_style_tag_ids', [])),
            'welcome'    => $this->normalizeIdFilters($request->query('welcome_tag_ids', [])),
            'benefit'    => $this->normalizeIdFilters($request->query('benefit_tag_ids', [])),
        ];
        $shopTagFilters = [
            'atmosphere' => $this->normalizeIdFilters($request->query('atmosphere_tag_ids', [])),
            'facility'   => $this->normalizeIdFilters($request->query('facility_tag_ids', [])),
        ];

        // 各ショップごとに「ひとこと」の最新行（id 最大）を 1 件だけ参照する。
        // shop_posts.shop_id は UNIQUE 制約ありだが、安全のため MAX(id) でサブクエリ化。
        $latestShopPost = DB::table('shop_posts')
            ->select('shop_id', DB::raw('MAX(id) as latest_id'))
            ->whereNotNull('body')
            ->where('body', '<>', '')
            ->groupBy('shop_id');

        $rows = DB::table('shops')
            ->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->leftJoinSub($latestShopPost, 'latest_post', function ($join) {
                $join->on('shops.id', '=', 'latest_post.shop_id');
            })
            ->leftJoin('shop_posts', 'shop_posts.id', '=', 'latest_post.latest_id');

        $jobSelect = [];
        if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
            $jobSelect[] = 'shop_jobs.hourly_wage_regular';
        }
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $jobSelect[] = 'shop_jobs.regular_hourly_wage';
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
            $jobSelect[] = 'shop_jobs.noruma_reward';
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $jobSelect[] = 'shop_jobs.bonus_reward';
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_cond')) {
            $jobSelect[] = 'shop_jobs.noruma_cond';
        }
        // ひとこと（shop_posts）が無いとき、キャッチコピー / 店長メッセージを代替表示するために select
        if (Schema::hasColumn('shop_jobs', 'catch_copy')) {
            $jobSelect[] = 'shop_jobs.catch_copy';
        }
        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $jobSelect[] = 'shop_jobs.pr as shop_job_pr';
        }

        // 「本日すぐ入れます」宣言（列があるときのみ include）
        if (Schema::hasColumn('shop_profiles', 'available_until')) {
            $jobSelect[] = 'shop_profiles.available_until';
        }

        $rows = $rows->select(array_merge(
            [
                'shops.id',
                'shop_jobs.id as shop_job_id',
                'shop_profiles.shop_name',
                'shop_profiles.pref',
                'shop_profiles.city',
                'shop_profiles.latitude',
                'shop_profiles.longitude',
                DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.main_order IS NULL, si.main_order, si.id LIMIT 1) as main_image_path"),
                'shop_profiles.updated_at as profile_updated_at',
                'shops.created_at as shop_created_at',
                'shop_posts.body as shop_post_body',
                'shop_posts.updated_at as shop_post_updated_at',
                'shop_posts.created_at as shop_post_created_at',
            ],
            $jobSelect
        ));

        $this->applySort($rows, $sort);

        if (!empty($industryIds)) {
            $rows->whereIn('shop_profiles.industry_id', $industryIds);
        } elseif (!empty($industries)) {
            $rows->join('industries', 'shop_profiles.industry_id', '=', 'industries.id')
                ->whereIn('industries.name', $industries);
        }

        // スコアリングサービスとキーワードトークンを準備
        $scoring = app(SearchScoringService::class);
        $keywordTokens = $scoring->tokenize($normalizedKeyword);
        $scoringContext = [
            'keywordTokens' => $keywordTokens,
            'normalize'     => fn (string $s) => $this->normalizeSearchText($s),
        ];

        $allRows = $rows->get();
        $availabilityFilters = $request->only(['shift_frequency', 'work_periods']);
        $shopAvailability = ($request->filled('shift_frequency') || $request->filled('work_periods'))
            ? \App\Models\ShopSearchPreference::whereIn('shop_id', $allRows->pluck('id'))->get(['shop_id', 'shift_frequency', 'work_periods'])->keyBy('shop_id')
            : collect();
        $filterService = app(\App\Services\SearchFilterService::class);
        $items = $allRows
            ->filter(function ($row) use ($keywordTokens, $areas, $hourlyWage, $reward, $jobTagFilters, $shopTagFilters, $availabilityFilters, $shopAvailability, $filterService) {
                if (!$filterService->matchesAvailability($availabilityFilters, $shopAvailability->get($row->id)?->toArray() ?? [])) {
                    return false;
                }
                // キーワード絞り込み: 全トークンが少なくとも1つのフィールドに含まれること
                if ($keywordTokens !== []) {
                    $haystack = $this->normalizeSearchText(implode(' ', array_filter([
                        $row->shop_name,
                        $row->pref,
                        $row->city,
                        $row->shop_post_body ?? null,
                    ])));
                    foreach ($keywordTokens as $token) {
                        if (!str_contains($haystack, $token)) {
                            return false;
                        }
                    }
                }

                $areaLabel = $this->formatAreaLabel($row->pref ?? '', $row->city ?? '');
                if (!empty($areas) && !in_array($areaLabel, $areas, true)) {
                    return false;
                }

                if ($hourlyWage > 0 && $this->searchRowHourlyWage($row) < $hourlyWage) {
                    return false;
                }

                if ($reward > 0 && $this->searchRowReward($row) < $reward) {
                    return false;
                }

                if (!$this->matchesShopJobTagFilters((int) ($row->shop_job_id ?? 0), $jobTagFilters)) {
                    return false;
                }

                if (!$this->matchesShopProfileTagFilters((string) $row->id, $shopTagFilters)) {
                    return false;
                }

                return true;
            })
            ->map(function ($row) use ($scoring, $scoringContext) {
                $hitokotoUpdatedAt = $row->shop_post_updated_at
                    ? Carbon::parse($row->shop_post_updated_at)
                    : ($row->shop_post_created_at ? Carbon::parse($row->shop_post_created_at) : null);

                $sc = $scoring->scoreShopRow($row, $scoringContext);

                // 「ひとこと」は shop_posts の最新投稿が第一。
                // 未投稿の店舗は shop_jobs.catch_copy → shop_jobs.pr を代替表示することで
                // 一覧のスキマ（何も出ない行）を無くす。
                $hitokotoBody = (string) ($row->shop_post_body ?? '');
                if ($hitokotoBody === '') {
                    $fallback = trim((string) ($row->catch_copy ?? '')) ?: trim((string) ($row->shop_job_pr ?? ''));
                    if ($fallback !== '') {
                        $fallbackOneLine = preg_replace('/\s+/u', ' ', $fallback) ?? $fallback;
                        $hitokotoBody = mb_strlen($fallbackOneLine) > 80
                            ? mb_substr($fallbackOneLine, 0, 80) . '…'
                            : $fallbackOneLine;
                    }
                }

                $availActive = false;
                if (!empty($row->available_until ?? null)) {
                    try {
                        $availActive = Carbon::parse($row->available_until)->isFuture();
                    } catch (\Throwable) {
                        $availActive = false;
                    }
                }

                return [
                    'id'                  => $row->id,
                    'shop_name'           => (string) ($row->shop_name ?: 'ショップ'),
                    'pref'                => $row->pref ?? '',
                    'city'                => $row->city ?? '',
                    'catch'               => (string) ($row->shop_post_body ?? ''),
                    'overview'            => '',
                    'main_img'            => $this->getShopImages((string) $row->id)[0] ?? asset('assets/images/common/no-image.png'),
                    'available_active'    => $availActive,
                    'hitokoto'            => $hitokotoBody,
                    'hitokoto_updated_at' => $hitokotoUpdatedAt?->locale('ja')->diffForHumans(),
                    'hitokoto_ts'         => $hitokotoUpdatedAt?->getTimestamp(),
                    'hourly_wage'         => $this->searchRowHourlyWage($row),
                    'reward'              => $this->searchRowReward($row),
                    'latitude'            => $row->latitude !== null ? (float) $row->latitude : null,
                    'longitude'           => $row->longitude !== null ? (float) $row->longitude : null,
                    'match_score'         => $sc['score'],
                    'match_reasons'       => $sc['reasons'],
                ];
            })
            ->values()
            ->all();

        $items = $this->enrichCastSearchShopCards($items);

        // 距離計算＋距離フィルタ。
        // 新クエリ（location_mode/passport_*/current_*）が指定されていればそれを最優先。
        // それ以外は MyPage 永続設定にフォールバック。
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
            // プロフィール住所を基準にする：永続設定の profile_location をそのまま採用
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

        // 後方互換：旧 location_type=current/geo + distance_km(5/10/...) 形式も受け付ける
        $legacyHasFilter = $queryDistanceKm > 0
            && in_array((string) $request->query('location_type'), ['current', 'geo'], true);
        $effectiveMaxKm = ($locationMode !== '' && $queryDistanceKm > 0)
            ? $queryDistanceKm
            : ($legacyHasFilter ? $queryDistanceKm : $persistedMaxKm);

        foreach ($items as &$item) {
            $km = $origin
                ? $userLocation->distanceKm($origin['lat'], $origin['lng'], $item['latitude'] ?? null, $item['longitude'] ?? null)
                : null;
            $item['distance_km'] = $km;
            $item['distance_label'] = $km !== null ? $userLocation->formatDistance($km) : null;
            unset($item['latitude'], $item['longitude']);
        }
        unset($item);

        if ($origin && $effectiveMaxKm > 0) {
            $items = array_values(array_filter($items, function ($it) use ($effectiveMaxKm) {
                $km = $it['distance_km'] ?? null;
                // 距離不明は除外しない（lat/lng 未登録の店舗は表示機会を残す）
                if ($km === null) {
                    return true;
                }
                return $km <= $effectiveMaxKm;
            }));
        }

        // Premium店舗の優先表示（AIレコメンド＝relevance ソートで最上位グループに）
        $premiumMap = app(\App\Services\PlanSubscriptionService::class)
            ->premiumShopIdMap(array_column($items, 'id'));
        foreach ($items as &$item) {
            $item['is_premium'] = isset($premiumMap[(string) $item['id']]);
        }
        unset($item);

        // 'relevance' / 'distance' は PHP 側で再ソート（距離は SQL 後に計算されるため）
        if ($sort === 'relevance') {
            usort($items, function ($a, $b) {
                // Premiumプラン店舗を優先表示（同グループ内はマッチ度順）
                if (($a['is_premium'] ?? false) !== ($b['is_premium'] ?? false)) {
                    return ($b['is_premium'] ?? false) <=> ($a['is_premium'] ?? false);
                }
                if (($a['match_score'] ?? 0) !== ($b['match_score'] ?? 0)) {
                    return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
                }
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

        // 内部用キーは返却前に落とす
        foreach ($items as &$it) {
            unset($it['hitokoto_ts']);
        }
        unset($it);

        // KEEP/LIKE 初期状態を付与
        $items = $this->attachFavoriteStates($items, 'shop');

        return $items;
    }

    /**
     * 一覧カード用に業種・レビュー評価・優良店フラグを付与する。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function enrichCastSearchShopCards(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            if (!empty($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }
        $ids = array_values(array_unique($ids));

        $industryByShop = $this->fetchShopIndustryLabelsByIds($ids);
        $ratingByShop = $this->fetchShopRatingAggregatesByIds($ids);
        $stationByShop = $this->fetchMainStationByShopIds($ids);

        foreach ($items as &$item) {
            $id = (string) ($item['id'] ?? '');
            $item['industry_label'] = $industryByShop[$id] ?? '';
            $agg = $ratingByShop[$id] ?? ['avg' => null, 'cnt' => 0];
            $item['rating_avg'] = $agg['avg'];
            $item['rating_display'] = $agg['avg'] !== null ? number_format((float) $agg['avg'], 1) : null;
            $item['is_excellent'] = $agg['avg'] !== null
                && (float) $agg['avg'] >= 4.5
                && (int) $agg['cnt'] >= 2;
            $item['nearest_station'] = $stationByShop[$id] ?? '';
        }
        unset($item);

        return $items;
    }

    /**
     * 各 shop_id のメイン最寄り駅（sort_order が最小のレコード）を返す。
     *
     * @param  array<int, string>  $shopIds
     * @return array<string, string> shop_id => station_name
     */
    private function fetchMainStationByShopIds(array $shopIds): array
    {
        if ($shopIds === [] || !Schema::hasTable('shop_stations')) {
            return [];
        }

        // shop_id ごとに sort_order / id が最小の 1 件だけ取得
        $rows = DB::table('shop_stations')
            ->whereIn('shop_id', $shopIds)
            ->orderBy('shop_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['shop_id', 'station_name']);

        $out = [];
        foreach ($rows as $row) {
            $sid = (string) $row->shop_id;
            if (!isset($out[$sid])) {
                $name = trim((string) $row->station_name);
                if ($name !== '') {
                    $out[$sid] = $name;
                }
            }
        }
        return $out;
    }

    /**
     * @param  array<int, string>  $shopIds
     * @return array<string, string> shop_id => 代表業種名（先頭の1件）
     */
    private function fetchShopIndustryLabelsByIds(array $shopIds): array
    {
        if ($shopIds === []) {
            return [];
        }

        $hasLabel = Schema::hasColumn('shop_profiles', 'industry_label');

        $selectCols = ['shop_profiles.shop_id as shop_id', 'industries.name as name'];
        if ($hasLabel) {
            $selectCols[] = 'shop_profiles.industry_label as industry_label';
        }

        $rows = DB::table('shop_profiles')
            ->leftJoin('industries', 'shop_profiles.industry_id', '=', 'industries.id')
            ->whereIn('shop_profiles.shop_id', $shopIds)
            ->get($selectCols);

        $out = [];
        foreach ($rows as $r) {
            $sid = (string) $r->shop_id;
            if (isset($out[$sid])) {
                continue;
            }
            $label = $hasLabel ? trim((string) ($r->industry_label ?? '')) : '';
            $masterName = trim((string) ($r->name ?? ''));
            $out[$sid] = $label !== '' ? $label : $masterName;
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $shopIds
     * @return array<string, array{avg: ?float, cnt: int}>
     */
    private function fetchShopRatingAggregatesByIds(array $shopIds): array
    {
        if ($shopIds === [] || !Schema::hasTable('reviews') || !Schema::hasTable('review_details')) {
            return [];
        }

        if (!Schema::hasColumn('reviews', 'shop_id')) {
            return [];
        }

        $rows = DB::table('reviews')
            ->join('review_details', 'reviews.id', '=', 'review_details.review_id')
            ->whereIn('reviews.shop_id', $shopIds)
            ->whereNotNull('reviews.shop_id')
            ->groupBy('reviews.shop_id')
            ->select(
                'reviews.shop_id',
                DB::raw('AVG(review_details.score) as avg_score'),
                DB::raw('COUNT(review_details.id) as detail_count')
            )
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->shop_id] = [
                'avg' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null,
                'cnt' => (int) $r->detail_count,
            ];
        }

        return $out;
    }

    /**
     * 並び替え条件をクエリに反映する。
     */
    private function applySort($rows, string $sort): void
    {
        switch ($sort) {
            case 'new':
                $rows->orderByDesc('shops.created_at')->orderByDesc('shops.id');
                break;
            case 'name':
                $rows->orderBy('shop_profiles.shop_name')->orderBy('shops.id');
                break;
            case 'wage':
                $wageCols = [];
                if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
                    $wageCols[] = 'shop_jobs.regular_hourly_wage';
                }
                if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
                    $wageCols[] = 'shop_jobs.hourly_wage_regular';
                }
                if ($wageCols !== []) {
                    $rows->orderByRaw('COALESCE(' . implode(', ', $wageCols) . ', 0) DESC')
                        ->orderByDesc('shops.id');
                } else {
                    $rows->orderByDesc('shops.id');
                }
                break;
            case 'reward':
                $rewardCols = [];
                if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
                    $rewardCols[] = 'shop_jobs.bonus_reward';
                }
                if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
                    $rewardCols[] = 'shop_jobs.noruma_reward';
                }
                if ($rewardCols !== []) {
                    $rows->orderByRaw('COALESCE(' . implode(', ', $rewardCols) . ', 0) DESC')
                        ->orderByDesc('shops.id');
                } else {
                    $rows->orderByDesc('shops.id');
                }
                break;
            case 'hitokoto':
            default:
                // ひとこと最終更新が新しい順。未投稿のショップは最後に回す。
                $rows->orderByRaw('shop_posts.updated_at IS NULL')
                    ->orderByDesc('shop_posts.updated_at')
                    ->orderByDesc('shop_profiles.updated_at')
                    ->orderByDesc('shops.id');
                break;
        }
    }

    private function buildDetailSearchOptions(): array
    {
        $recruitmentMasters = $this->adminMasterService->getRecruitmentMasters();
        $profileMasters = $this->adminMasterService->getShopProfileMasters();

        return [
            'industries'   => $profileMasters['industries'] ?? collect(),
            'areas'        => $this->fetchAreaOptions(),
            'hourly_wages' => $this->fetchNumericOptions(
                Schema::hasColumn('shop_jobs', 'regular_hourly_wage') ? 'regular_hourly_wage' : 'hourly_wage_regular'
            ),
            'rewards'      => $this->fetchNumericOptions(
                Schema::hasColumn('shop_jobs', 'bonus_reward') ? 'bonus_reward' : 'noruma_reward'
            ),
            'work_style'   => $recruitmentMasters['work_style'] ?? collect(),
            'welcome'      => $recruitmentMasters['welcome'] ?? collect(),
            'benefit'      => $recruitmentMasters['benefit'] ?? collect(),
            'atmosphere'   => $profileMasters['atmosphere'] ?? collect(),
            'facility'     => $profileMasters['facility'] ?? collect(),
        ];
    }

    private function fetchAreaOptions(): Collection
    {
        if (!Schema::hasTable('shop_profiles')) {
            return collect();
        }

        return DB::table('shop_profiles')
            ->select('pref', 'city')
            ->orderBy('pref')
            ->orderBy('city')
            ->get()
            ->map(function ($row) {
                $label = $this->formatAreaLabel($row->pref ?? '', $row->city ?? '');

                return $label === '' ? null : (object) ['name' => $label];
            })
            ->filter()
            ->unique('name')
            ->values();
    }

    private function fetchNumericOptions(string $column): Collection
    {
        if (!Schema::hasTable('shop_jobs') || !Schema::hasColumn('shop_jobs', $column)) {
            return collect();
        }

        return DB::table('shop_jobs')
            ->whereNotNull($column)
            ->pluck($column)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sort()
            ->values();
    }

    private function searchRowHourlyWage(object $row): int
    {
        if (property_exists($row, 'regular_hourly_wage') && $row->regular_hourly_wage !== null && $row->regular_hourly_wage !== '') {
            return (int) $row->regular_hourly_wage;
        }

        return (int) ($row->hourly_wage_regular ?? 0);
    }

    private function searchRowReward(object $row): int
    {
        if (property_exists($row, 'bonus_reward') && $row->bonus_reward !== null && $row->bonus_reward !== '') {
            return (int) $row->bonus_reward;
        }

        return (int) ($row->noruma_reward ?? 0);
    }

    private function formatAreaLabel(?string $pref, ?string $city): string
    {
        return trim(implode(' ', array_filter([(string) $pref, (string) $city])));
    }

    private function normalizeIdFilters(array|string|null $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function decodeMeta(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 求人票のタグ（shop_job_tag_relations）でフィルタリング.
     *
     * @param array<string, array<int,int>> $filters
     */
    private function matchesShopJobTagFilters(int $shopJobId, array $filters): bool
    {
        $hasFilter = false;
        foreach ($filters as $ids) {
            if (!empty($ids)) {
                $hasFilter = true;
                break;
            }
        }
        if (!$hasFilter) {
            return true;
        }

        if ($shopJobId <= 0 || !Schema::hasTable('shop_job_tag_relations')) {
            return false;
        }

        foreach ($filters as $category => $ids) {
            if (empty($ids)) {
                continue;
            }
            $hit = DB::table('shop_job_tag_relations')
                ->where('shop_job_id', $shopJobId)
                ->where('tag_type', $category)
                ->whereIn('tag_id', $ids)
                ->exists();
            if (!$hit) {
                return false;
            }
        }

        return true;
    }

    /**
     * 店舗プロフィールのタグ（shop_tag_relations）でフィルタリング.
     *
     * @param array<string, array<int,int>> $filters
     */
    private function matchesShopProfileTagFilters(string $shopId, array $filters): bool
    {
        $hasFilter = false;
        foreach ($filters as $ids) {
            if (!empty($ids)) {
                $hasFilter = true;
                break;
            }
        }
        if (!$hasFilter) {
            return true;
        }

        if (!Schema::hasTable('shop_tag_relations')) {
            return false;
        }

        foreach ($filters as $category => $ids) {
            if (empty($ids)) {
                continue;
            }
            $hit = DB::table('shop_tag_relations')
                ->where('shop_id', $shopId)
                ->where('tag_type', $category)
                ->whereIn('tag_id', $ids)
                ->exists();
            if (!$hit) {
                return false;
            }
        }

        return true;
    }

    private function getShopImages(string $shopId): array
    {
        $images = DB::table('shop_images')
            ->where('shop_id', $shopId)
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

    // assetPathForStored() is now provided by ResolvesActor trait.

    public function savePreferences(\Illuminate\Http\Request $request, \App\Services\CastSearchPreferenceService $prefs)
    {
        $data = $request->validate([
            'shift_frequency' => ['nullable', 'string', 'in:週1回出勤,週2回出勤,週3回以上'],
            'work_periods'    => ['nullable', 'array'],
            'work_periods.*'  => ['string', 'in:morning,day,night'],
            'hourly_wage_min' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'industry_ids'    => ['nullable', 'array'],
            'industry_ids.*'  => ['integer', 'exists:industries,id'],
        ]);
        $prefs->savePreferences($data);
        return response()->json(['success' => true, 'preferences' => $prefs->loadAll()]);
    }

    private function currentCastPersonalityType(): ?string
    {
        $castId = (string) auth()->guard('member')->id();
        if ($castId === '') {
            return null;
        }

        $row = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->select(
                Schema::hasColumn('cast_profiles', 'personality_type')
                    ? 'personality_type'
                    : DB::raw('NULL as personality_type'),
                Schema::hasColumn('cast_profiles', 'memo')
                    ? 'memo'
                    : DB::raw('NULL as memo')
            )
            ->first();

        if (!$row) {
            return null;
        }

        $memo = $this->decodeMeta($row->memo ?? null);
        $type = $row->personality_type ?? ($memo['personality_type'] ?? null);

        return is_string($type) && preg_match('/^[LF][CP][IO][HR]$/', $type) ? $type : null;
    }
}
