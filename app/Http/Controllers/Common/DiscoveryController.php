<?php
// app/Http/Controllers/Common/DiscoveryController.php
//
// DISCOVERY（縦スワイプの発見画面）用コントローラ。
// キャスト側は /cast/home（求人カード一覧）、店舗側は /shop/home（キャストカード一覧）で
// 同じ view (shops.home.index) を使う。以前は Shops\HomeController に置いて
// キャスト側からも呼んでいたが、namespace が実態と乖離していたため Common へ移設 (2026-08-02)。
//
// TODO: getHomeCasts() / getHomeRecruits() のクエリロジックは将来 DiscoveryService に切り出す。
// 今はまず controller の置き場所だけ Common に揃える段階。

namespace App\Http\Controllers\Common;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\ProfileView;
use App\Services\ProfileViewService;
use App\Services\UserLocationService;
use App\Support\RecruitCatchOverlay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscoveryController extends Controller
{
    use ResolvesActor;

    // ==============================================================
    // Tier ランキング設定（getHomeCasts の並び順に影響）
    //
    // Tier A: 「今すぐ入れる」宣言中 → 距離昇順 → 宣言時刻の新しい順
    // Tier B: 直近ログイン & 位置あり → スコア = 距離*重み + 経過時間*重み
    // Tier C: それ以外 → 元の ID 順の逆
    //
    // 将来 A/B テストや管理画面から調整したくなったら config/discovery.php
    // に移設予定。今はまずマジックナンバーを排除するのが目的。
    // ==============================================================

    /** キャストが「オンライン中」と見なされるログイン後の分数（Tier B チップ判定） */
    private const ONLINE_NOW_WINDOW_MINUTES = 30;

    /** Tier B の対象となる「直近ログイン」の時間窓（時間単位） */
    private const TIER_B_RECENCY_HOURS = 24;

    /** Tier B スコアの距離側の重み（km あたり） */
    private const TIER_B_DISTANCE_WEIGHT = 1.0;

    /** Tier B スコアの経過時間側の重み（時間あたり） */
    private const TIER_B_RECENCY_WEIGHT = 0.5;

    /** Tier B スコア計算時、距離未算出キャストに用いる仮の距離（km） */
    private const TIER_B_UNKNOWN_DISTANCE_KM = 30.0;

    /** Tier A で距離不明キャストを末尾に沈めるためのソートキー値 */
    private const DISTANCE_UNKNOWN_SORT_KEY = 99999.0;

    /** DISCOVERY 画面に表示するカードの上限件数 */
    private const MAX_HOME_ITEMS = 20;

    /** Tier 分類前に取得するキャストの上限件数（並び替え後上位 MAX_HOME_ITEMS を返す） */
    private const CANDIDATES_FETCH_LIMIT = 60;

    public function __construct(
        private readonly UserLocationService $userLocation,
        private readonly ProfileViewService $profileViews,
    ) {
    }

    public function index()
    {
        $this->cleanupStaleImageReferences();

        $isCastPortal = request()->is('cast/*');

        if ($isCastPortal) {
            $recruits = $this->getHomeRecruits();
            return view('shops.home.index', [
                'pageId' => 'home',
                'items' => $recruits,
                'itemType' => 'recruit',
                'helpBand' => $this->buildCastHelpBand(),
            ]);
        }

        $casts = $this->getHomeCasts();
        return view('shops.home.index', [
            'pageId' => 'home',
            'items' => $casts,
            'itemType' => 'cast',
            'helpBand' => $this->buildShopHelpBandEntry(),
        ]);
    }

    /**
     * Shop-side entry ribbon leading to /shop/help-recruitment.
     * Shows currently active dates as a short summary when present.
     *
     * @return array{kind:string, dates:list<string>, has_dates:bool}
     */
    private function buildShopHelpBandEntry(): array
    {
        $shopId = (string) (auth()->guard('shop')->user()->shop_id ?? '');
        if ($shopId === '') {
            return ['kind' => 'shop-entry', 'dates' => [], 'has_dates' => false];
        }

        $dates = app(\App\Services\AvailabilityService::class)
            ->getDates(\App\Models\AvailabilityDate::OWNER_SHOP, $shopId);

        return [
            'kind' => 'shop-entry',
            'dates' => array_map(
                static fn ($d) => \App\Services\AvailabilityService::shortLabel($d),
                array_slice($dates, 0, 3)
            ),
            'has_dates' => $dates !== [],
        ];
    }

    /**
     * Cast-side horizontal band of shops with help recruitment today/tomorrow.
     *
     * @return array{kind:string, shops:list<array{id:string,name:string,image:string,wage_label:string,date_label:string}>}
     */
    private function buildCastHelpBand(): array
    {
        if (!Schema::hasTable('availability_dates')) {
            return ['kind' => 'cast-band', 'shops' => []];
        }

        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::today()->addDay()->toDateString();

        $rows = DB::table('availability_dates')
            ->where('owner_type', \App\Models\AvailabilityDate::OWNER_SHOP)
            ->whereIn('available_on', [$today, $tomorrow])
            ->orderBy('available_on')
            ->get(['owner_id', 'available_on']);

        if ($rows->isEmpty()) {
            return ['kind' => 'cast-band', 'shops' => []];
        }

        // Group by shop_id → keep earliest date only for display
        $shopDateMap = [];
        foreach ($rows as $r) {
            $sid = (string) $r->owner_id;
            if (!isset($shopDateMap[$sid])) {
                $shopDateMap[$sid] = (string) $r->available_on;
            }
        }
        $shopIds = array_keys($shopDateMap);
        if ($shopIds === []) {
            return ['kind' => 'cast-band', 'shops' => []];
        }

        $shopRows = DB::table('shops')->whereIn('id', $shopIds)->pluck('shop_name', 'id');

        // Wage lookup from shop_jobs (main row)
        $wageRows = collect();
        if (Schema::hasTable('shop_jobs')) {
            $cols = ['shop_id'];
            foreach (['help_hourly_wage', 'help_hourly_wage_max'] as $c) {
                if (Schema::hasColumn('shop_jobs', $c)) {
                    $cols[] = $c;
                }
            }
            $q = DB::table('shop_jobs')->whereIn('shop_id', $shopIds)->select($cols);
            if (Schema::hasColumn('shop_jobs', 'job_type') && !Schema::hasColumn('shop_jobs', 'regular_status')) {
                $q->where('job_type', 1);
            }
            $wageRows = $q->get()->keyBy('shop_id');
        }

        $imgRows = collect();
        if (Schema::hasTable('shop_images')) {
            $imgRows = DB::table('shop_images')
                ->whereIn('shop_id', $shopIds)
                ->orderBy('main_order')
                ->orderBy('id')
                ->get()
                ->groupBy('shop_id')
                ->map(fn ($rs) => $rs->first());
        }

        $shops = [];
        foreach ($shopDateMap as $sid => $date) {
            $wageRow = $wageRows->get($sid);
            $wageMin = $wageRow && !empty($wageRow->help_hourly_wage) ? (int) $wageRow->help_hourly_wage : 0;
            $wageMax = $wageRow && !empty($wageRow->help_hourly_wage_max) ? (int) $wageRow->help_hourly_wage_max : 0;

            $wageLabel = '';
            if ($wageMin > 0 && $wageMax > 0 && $wageMax > $wageMin) {
                $wageLabel = '¥' . number_format($wageMin) . '〜' . number_format($wageMax);
            } elseif ($wageMin > 0) {
                $wageLabel = '¥' . number_format($wageMin);
            }

            $img = $imgRows->get($sid);
            $imgUrl = $img && !empty($img->image_path)
                ? (str_starts_with((string) $img->image_path, 'http') ? (string) $img->image_path : asset('storage/' . $img->image_path))
                : asset('assets/images/common/no-image.png');

            $shops[] = [
                'id' => (string) $sid,
                'name' => (string) ($shopRows[$sid] ?? '店舗'),
                'image' => $imgUrl,
                'wage_label' => $wageLabel,
                'date_label' => $date === $today ? '本日' : '明日',
            ];
        }

        return ['kind' => 'cast-band', 'shops' => array_slice($shops, 0, 12)];
    }

    private function getHomeCasts(): array
    {
        $select = [
            'casts.id',
            'casts.last_login_at',
            'cast_profiles.nickname',
            'cast_profiles.name',
            'cast_profiles.birthday',
            'cast_profiles.pref',
            'cast_profiles.city',
            'cast_profiles.pr',
            'cast_profiles.exp',
            'cast_profiles.profession',
            'cast_profiles.industry_id',
            'cast_profiles.latitude',
            'cast_profiles.longitude',
            DB::raw("(SELECT ci.image_path FROM cast_images ci WHERE ci.cast_id = casts.id ORDER BY ci.is_main DESC, ci.main_order IS NULL, ci.main_order, ci.id LIMIT 1) as main_image_path"),
        ];

        // WHERE で予めランクを付けておくとページング時に楽になるが、
        // ここでは Tier 判定に必要な情報を全部拾って PHP 側で並び替える方針
        // （20 件と少ないため）。将来 100+ になったら SQL 側で ORDER BY を組む
        $rows = DB::table('casts')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->select($select)
            ->orderBy('casts.id')
            ->limit(self::CANDIDATES_FETCH_LIMIT)   // Tier 分類後に上位 MAX_HOME_ITEMS を返すため広めに取得
            ->get();

        // プロフィールタグ（ルックス/内面）を一括取得。
        // 場所（pref/city）は位置チップで別表示するため、タグには含めない
        $tagNamesByCast = [];
        $castIdsForTags = $rows->pluck('id')->all();
        if ($castIdsForTags !== [] && Schema::hasTable('cast_tag_relations')) {
            $tagTable = Schema::hasTable('cast_tags') ? 'cast_tags' : (Schema::hasTable('tags') ? 'tags' : null);
            if ($tagTable !== null) {
                DB::table('cast_tag_relations as r')
                    ->join($tagTable . ' as t', 'r.tag_id', '=', 't.id')
                    ->whereIn('r.cast_id', $castIdsForTags)
                    ->select('r.cast_id', 't.name')
                    ->get()
                    ->each(function ($tr) use (&$tagNamesByCast) {
                        $tagNamesByCast[(string) $tr->cast_id][] = (string) $tr->name;
                    });
            }
        }

        $origin = $this->userLocation->getActiveLocation();

        // プロフィール閲覧数（profile_views テーブルから集計）
        $viewCounts = $this->profileViews->countForMany(
            ProfileView::TYPE_CAST,
            $rows->pluck('id')->all()
        );

        $keptCastIds = [];
        if (Schema::hasTable('favorites') && auth()->guard('shop')->check()) {
            $shopId = (string) (auth()->guard('shop')->user()->shop_id ?? '');
            if ($shopId !== '') {
                $keptCastIds = DB::table('favorites')
                    ->where('shop_id', $shopId)
                    ->where('action_type', Favorite::ACTION_KEEP)
                    ->where('sender_type', Favorite::SENDER_SHOP)
                    ->whereNotNull('cast_id')
                    ->pluck('cast_id')
                    ->all();
            }
        }
        $keptCastMap = array_fill_keys($keptCastIds, true);

        $maxDistanceKm = (int) ($this->userLocation->getEffectiveMaxDistanceKm() ?? 0);

        // 希望業種（industry_id → 業種名）
        $industryNames = Schema::hasTable('industries')
            ? DB::table('industries')->pluck('name', 'id')
            : collect();

        // 各キャストのパスポートモード設定（設定していれば「設定位置ラベル」を表示に使う）
        $passportLabelByCast = [];
        if (Schema::hasTable('cast_search_preferences')) {
            DB::table('cast_search_preferences')
                ->whereIn('cast_id', $castIdsForTags)
                ->where('mode', 'passport')
                ->select('cast_id', 'passport_label')
                ->get()
                ->each(function ($r) use (&$passportLabelByCast) {
                    $label = trim((string) ($r->passport_label ?? ''));
                    if ($label !== '') {
                        $passportLabelByCast[(string) $r->cast_id] = $label;
                    }
                });
        }

        // Tier 分類（各パラメータはクラス定数で管理。上部の定数ブロック参照）
        $now = Carbon::now();
        $todayStr = Carbon::today()->toDateString();
        $availDatesByCast = app(\App\Services\AvailabilityService::class)
            ->datesByOwner(\App\Models\AvailabilityDate::OWNER_CAST, $rows->pluck('id')->map(fn ($id) => (string) $id)->all());

        $items = [];
        foreach ($rows as $row) {
            $birthday = $row->birthday ? Carbon::parse($row->birthday) : null;
            $images = $this->getCastImages($row->id, $row->main_image_path);
            $lat = $row->latitude  !== null ? (float) $row->latitude  : null;
            $lng = $row->longitude !== null ? (float) $row->longitude : null;
            $distanceKm = $origin
                ? $this->userLocation->distanceKm($origin['lat'], $origin['lng'], $lat, $lng)
                : null;
            // 距離フィルタ：拠点設定があり、かつ半径>0 の場合のみ適用。
            // 距離不明（lat/lng 未登録）は除外しない（情報不足を理由にスキップする方針もあるが、表示機会を残す）。
            if ($origin && $maxDistanceKm > 0 && $distanceKm !== null && $distanceKm > $maxDistanceKm) {
                continue;
            }
            $passportLabel = $passportLabelByCast[(string) $row->id] ?? null;

            // --- Tier / チップ判定（候補日ベース：本日が候補日なら Tier A） ---
            $castAvailDates = $availDatesByCast[(string) $row->id] ?? [];
            $availActive = in_array($todayStr, $castAvailDates, true);
            $availDateLabels = array_map(
                fn ($d) => \App\Services\AvailabilityService::shortLabel($d),
                $castAvailDates
            );

            $lastLogin = $row->last_login_at ? Carbon::parse($row->last_login_at) : null;
            $minutesSinceLogin = $lastLogin ? (int) $lastLogin->diffInMinutes($now, false) : null;
            $isOnlineNow = $minutesSinceLogin !== null
                && $minutesSinceLogin >= 0
                && $minutesSinceLogin <= self::ONLINE_NOW_WINDOW_MINUTES;

            if ($availActive) {
                $tier = 'A';
                // 距離不明は末尾扱いにするため大きな値を割当
                $sortKey1 = $distanceKm !== null ? (float) $distanceKm : self::DISTANCE_UNKNOWN_SORT_KEY;
                // More declared dates first as a tiebreak (motivated casts)
                $sortKey2 = -count($castAvailDates);
            } elseif ($lat !== null && $lng !== null
                && $lastLogin !== null
                && $lastLogin->diffInHours($now, false) <= self::TIER_B_RECENCY_HOURS
                && $lastLogin->diffInHours($now, false) >= 0
            ) {
                $tier = 'B';
                $hoursSinceLogin = max(0.0, (float) $lastLogin->diffInHours($now, false));
                $distanceForScore = $distanceKm !== null ? (float) $distanceKm : self::TIER_B_UNKNOWN_DISTANCE_KM;
                $sortKey1 = $distanceForScore * self::TIER_B_DISTANCE_WEIGHT
                          + $hoursSinceLogin * self::TIER_B_RECENCY_WEIGHT;
                $sortKey2 = $lastLogin ? -$lastLogin->getTimestamp() : 0;
            } else {
                $tier = 'C';
                $sortKey1 = 0;
                $sortKey2 = -((int) $row->id); // 元の ID 順の逆（新しい登録が上）
            }

            $items[] = [
                'id' => $row->id,
                'name' => $row->nickname ?: ($row->name ?: 'ゲスト'),
                'age' => $birthday ? $birthday->age : null,
                'tags' => $this->buildCastTags($row, $tagNamesByCast[(string) $row->id] ?? []),
                'view_count' => $viewCounts[$row->id] ?? 0,
                'images' => $images,
                'is_kept' => isset($keptCastMap[$row->id]),
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'industry_name' => $row->industry_id !== null ? (string) ($industryNames[$row->industry_id] ?? '') : '',
                'night_work_label' => ((int) ($row->exp ?? 0) === 1) ? '経験あり' : '未経験',
                // パスポートモード時：設定位置ラベル / 通常時：登録住所(pref+city)
                'location_label' => $passportLabel ?: trim(($row->pref ?? '') . ' ' . ($row->city ?? '')),
                'location_mode' => $passportLabel ? 'passport' : 'profile',
                'distance_km' => $distanceKm,
                'distance_label' => $distanceKm !== null ? $this->userLocation->formatDistance($distanceKm) : null,
                // ↓ Tier / チップ表示用（view で参照）
                'tier' => $tier,
                'availability_active' => $availActive,
                'availability_date_labels' => $availDateLabels,
                'is_online_now' => $isOnlineNow,
                '_sort_tier'  => ['A' => 0, 'B' => 1, 'C' => 2][$tier] ?? 2,
                '_sort_key1'  => $sortKey1,
                '_sort_key2'  => $sortKey2,
            ];
        }

        // Tier A → B → C の順で並べ、各 Tier 内は sortKey1(ASC) → sortKey2(ASC) で決定的に整列
        usort($items, function ($a, $b) {
            if ($a['_sort_tier'] !== $b['_sort_tier']) {
                return $a['_sort_tier'] <=> $b['_sort_tier'];
            }
            if ($a['_sort_key1'] !== $b['_sort_key1']) {
                return $a['_sort_key1'] <=> $b['_sort_key1'];
            }
            return $a['_sort_key2'] <=> $b['_sort_key2'];
        });

        // 内部ソートキーは view に不要
        foreach ($items as &$it) {
            unset($it['_sort_tier'], $it['_sort_key1'], $it['_sort_key2']);
        }
        unset($it);

        return array_slice($items, 0, self::MAX_HOME_ITEMS);
    }

    /**
     * キャスト向けホーム：求人票ベースの一覧（ボーナス金・時給など重要情報を表示するため）
     */
    private function getHomeRecruits(): array
    {
        $horizontal = Schema::hasTable('shop_jobs') && Schema::hasColumn('shop_jobs', 'regular_status');
        $useJobType = Schema::hasColumn('shop_jobs', 'job_type') && !$horizontal;
        $hasReviews = Schema::hasTable('reviews');

        $q = DB::table('shops')
            ->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->join('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id');

        if ($horizontal) {
            $q->where('shop_jobs.regular_status', 1);
        } else {
            $q->where('shop_jobs.status', 1);
            if ($useJobType) {
                $q->where('shop_jobs.job_type', 1);
            }
        }

        if ($hasReviews) {
            $q->leftJoin(
                DB::raw('(SELECT shop_id, ROUND(AVG(eva), 1) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY shop_id) AS shop_reviews'),
                'shop_reviews.shop_id', '=', 'shops.id'
            );
        }

        $selectFields = [
            'shops.id',
            'shop_jobs.id as shop_job_id',
            'shop_profiles.shop_name',
            'shop_profiles.pref',
            'shop_profiles.city',
            'shop_profiles.industry_id',
            'shop_profiles.latitude',
            'shop_profiles.longitude',
            DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.main_order IS NULL, si.main_order, si.id LIMIT 1) as main_image_path"),
        ];
        if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
            $selectFields[] = 'shop_jobs.hourly_wage_regular';
        }
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $selectFields[] = 'shop_jobs.regular_hourly_wage';
        }
        if (Schema::hasColumn('shop_jobs', 'has_trial')) {
            $selectFields[] = 'shop_jobs.has_trial';
        }
        if (Schema::hasColumn('shop_jobs', 'has_help')) {
            $selectFields[] = 'shop_jobs.has_help';
        }
        if (Schema::hasColumn('shop_jobs', 'trial_hourly_wage')) {
            $selectFields[] = 'shop_jobs.trial_hourly_wage';
        }
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage')) {
            $selectFields[] = 'shop_jobs.help_hourly_wage';
        }
        if (Schema::hasColumn('shop_jobs', 'trial_status')) {
            $selectFields[] = 'shop_jobs.trial_status';
        }
        if (Schema::hasColumn('shop_jobs', 'help_status')) {
            $selectFields[] = 'shop_jobs.help_status';
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
            $selectFields[] = 'shop_jobs.noruma_reward';
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $selectFields[] = 'shop_jobs.bonus_reward';
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_cond')) {
            $selectFields[] = 'shop_jobs.noruma_cond';
        }
        if (Schema::hasColumn('shop_jobs', 'catch_copy')) {
            $selectFields[] = 'shop_jobs.catch_copy';
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_condition')) {
            $selectFields[] = 'shop_jobs.bonus_condition';
        }
        if ($hasReviews) {
            $selectFields[] = DB::raw('COALESCE(shop_reviews.avg_rating, 0) AS avg_rating');
            $selectFields[] = DB::raw('COALESCE(shop_reviews.review_count, 0) AS review_count');
        }
        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $selectFields[] = 'shop_jobs.pr';
        }

        $rows = $q->select($selectFields)
            ->orderBy('shops.id')
            ->limit(20)
            ->get();

        $industryByShop = $this->resolveIndustryLabelsByShopIds($rows->pluck('id')->unique()->values()->all());

        $shopIds = $rows->pluck('id')->unique()->values()->all();
        $trialByShop = collect();
        $helpByShop = collect();

        // 優良店バッヂ：過去3ヶ月の請求がすべて確認済みかつ10日以内に入金された店舗
        $premiumShopIds = [];
        if ($shopIds !== [] && Schema::hasTable('application_deposits') && Schema::hasTable('shop_job_applications')) {
            $threeMonthsAgo = now()->subMonths(3);
            $disqualified = DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->join('shop_jobs as sj_badge', 'shop_job_applications.shop_job_id', '=', 'sj_badge.id')
                ->whereIn('sj_badge.shop_id', $shopIds)
                ->where('application_deposits.created_at', '>=', $threeMonthsAgo)
                ->where('application_deposits.status', '<', 5)
                ->pluck('sj_badge.shop_id')
                ->unique()
                ->flip()
                ->all();
            $confirmed = DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->join('shop_jobs as sj_badge', 'shop_job_applications.shop_job_id', '=', 'sj_badge.id')
                ->whereIn('sj_badge.shop_id', $shopIds)
                ->where('application_deposits.created_at', '>=', $threeMonthsAgo)
                ->where('application_deposits.status', '>=', 5)
                ->pluck('sj_badge.shop_id')
                ->unique()
                ->flip()
                ->all();
            foreach ($confirmed as $sid => $v) {
                if (!isset($disqualified[$sid])) {
                    $premiumShopIds[$sid] = true;
                }
            }
        }

        if ($useJobType && $shopIds !== []) {
            $trialByShop = DB::table('shop_jobs')
                ->whereIn('shop_id', $shopIds)
                ->where('job_type', 2)
                ->get()
                ->keyBy('shop_id');
            $helpByShop = DB::table('shop_jobs')
                ->whereIn('shop_id', $shopIds)
                ->where('job_type', 3)
                ->get()
                ->keyBy('shop_id');
        }

        // 店舗プロフィールの閲覧数（profile_views テーブルから集計）
        $viewCounts = $this->profileViews->countForMany(
            ProfileView::TYPE_SHOP,
            $rows->pluck('id')->all()
        );

        $keptShopIds = [];
        if (Schema::hasTable('favorites') && auth()->guard('member')->check()) {
            $castId = (string) auth()->guard('member')->id();
            if ($castId !== '') {
                $keptShopIds = DB::table('favorites')
                    ->where('cast_id', $castId)
                    ->where('action_type', Favorite::ACTION_KEEP)
                    ->where('sender_type', Favorite::SENDER_CAST)
                    ->whereNotNull('shop_id')
                    ->pluck('shop_id')
                    ->all();
            }
        }
        $keptShopMap = array_fill_keys($keptShopIds, true);

        // Dated help recruitment declarations per shop
        $todayStr = Carbon::today()->toDateString();
        $helpDatesByShop = app(\App\Services\AvailabilityService::class)
            ->datesByOwner(\App\Models\AvailabilityDate::OWNER_SHOP, array_map('strval', $shopIds));

        $items = [];
        foreach ($rows as $row) {
            // 画面からは「DBの店舗ID（例: s00000001）」でアクセスできるようにする
            $numericId = $this->toNumericShopId($row->id);
            $images = $this->getShopImages($row->id, $row->main_image_path);
            $norumaRaw = Schema::hasColumn('shop_jobs', 'noruma_cond') ? ($row->noruma_cond ?? null) : null;
            $meta = $this->decodeRecruitMeta($norumaRaw);
            if (Schema::hasColumn('shop_jobs', 'catch_copy') && trim((string) ($row->catch_copy ?? '')) !== '') {
                $meta['catch_copy'] = trim((string) $row->catch_copy);
            }
            if (Schema::hasColumn('shop_jobs', 'bonus_condition') && trim((string) ($row->bonus_condition ?? '')) !== '') {
                $meta['bonus_condition'] = trim((string) $row->bonus_condition);
            }

            $trialRow = $useJobType ? ($trialByShop[$row->id] ?? null) : null;
            $helpRow = $useJobType ? ($helpByShop[$row->id] ?? null) : null;

            if ($horizontal) {
                // 店舗プレビューと同様：trial_status / help_status が 0 でも登録時給は表示（スワイプ用）
                $trialHourly = null;
                if (isset($row->trial_hourly_wage) && $row->trial_hourly_wage !== null && $row->trial_hourly_wage !== '' && (int) $row->trial_hourly_wage > 0) {
                    $trialHourly = (int) $row->trial_hourly_wage;
                }
                $helpHourly = null;
                if (isset($row->help_hourly_wage) && $row->help_hourly_wage !== null && $row->help_hourly_wage !== '' && (int) $row->help_hourly_wage > 0) {
                    $helpHourly = (int) $row->help_hourly_wage;
                }
            } else {
                $trialHourly = $trialRow && !empty($trialRow->status) && !empty($trialRow->trial_hourly_wage)
                    ? (int) $trialRow->trial_hourly_wage
                    : (!empty($row->has_trial) && isset($row->trial_hourly_wage) ? (int) $row->trial_hourly_wage : null);

                $helpHourly = $helpRow && !empty($helpRow->status) && !empty($helpRow->help_hourly_wage)
                    ? (int) $helpRow->help_hourly_wage
                    : (!empty($row->has_help) && isset($row->help_hourly_wage) ? (int) $row->help_hourly_wage : null);
            }

            $mainBonus = 0;
            if (isset($row->bonus_reward) && $row->bonus_reward !== null && $row->bonus_reward !== '') {
                $mainBonus = (int) $row->bonus_reward;
            } elseif (isset($row->noruma_reward) && $row->noruma_reward !== null && $row->noruma_reward !== '') {
                $mainBonus = (int) $row->noruma_reward;
            }

            if ($horizontal) {
                $bonusTrial = $mainBonus;
                $bonusHelp = $mainBonus;
            } else {
                $bonusTrial = ($trialRow && isset($trialRow->noruma_reward) && (int) $trialRow->noruma_reward > 0)
                    ? (int) $trialRow->noruma_reward
                    : $mainBonus;
                $bonusHelp = ($helpRow && isset($helpRow->noruma_reward) && (int) $helpRow->noruma_reward > 0)
                    ? (int) $helpRow->noruma_reward
                    : $mainBonus;
            }

            $regularWage = 0;
            if (isset($row->regular_hourly_wage) && $row->regular_hourly_wage !== null && $row->regular_hourly_wage !== '') {
                $regularWage = (int) $row->regular_hourly_wage;
            } elseif (isset($row->hourly_wage_regular) && $row->hourly_wage_regular !== null && $row->hourly_wage_regular !== '') {
                $regularWage = (int) $row->hourly_wage_regular;
            }

            $offerFulltime = $regularWage > 0;
            $offerTrial = $trialHourly !== null && $trialHourly > 0;
            $offerHelp = $helpHourly !== null && $helpHourly > 0;

            $shopJobId = isset($row->shop_job_id) ? (int) $row->shop_job_id : 0;
            $managerOverlay = $this->buildManagerImageOverlay($meta, $mainBonus);

            $bonusLines = [
                ['label' => '体入', 'amount' => $bonusTrial, 'offered' => $offerTrial],
                ['label' => 'ヘルプ', 'amount' => $bonusHelp, 'offered' => $offerHelp],
                ['label' => '本入', 'amount' => $mainBonus, 'offered' => $offerFulltime],
            ];

            $items[] = [
                // ルート用には文字列ID（例: s00000001）をそのまま渡す
                'id' => $row->id,
                // 必要に応じて数値IDを併用したい場合に備えて保持
                'numeric_id' => $numericId,
                'shop_job_id' => $shopJobId,
                'name' => $row->shop_name ?: '店舗',
                'images' => $images,
                'hourly_wage_regular' => $regularWage,
                'trial_hourly_wage' => $trialHourly,
                'help_hourly_wage' => $helpHourly,
                'noruma_reward' => $mainBonus,
                'bonus_condition' => $meta['bonus_condition'] ?? '',
                'catch_copy' => $meta['catch_copy'] ?? '',
                'tags' => $this->buildHomeRecruitDiscoveryTags($row->id, $shopJobId),
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'nearest_station' => '',
                'view_count' => $viewCounts[$row->id] ?? 0,
                'industry_name' => $industryByShop[$row->id] ?? null,
                'rating' => $hasReviews ? (float) ($row->avg_rating ?? 0) : 0.0,
                'review_count' => $hasReviews ? (int) ($row->review_count ?? 0) : 0,
                'is_premium' => isset($premiumShopIds[$row->id]),
                'is_kept' => isset($keptShopMap[$row->id]),
                'available_active' => in_array($todayStr, $helpDatesByShop[(string) $row->id] ?? [], true),
                'help_date_labels' => array_map(
                    fn ($d) => \App\Services\AvailabilityService::shortLabel($d),
                    $helpDatesByShop[(string) $row->id] ?? []
                ),
                'recruit_bonus_lines' => $bonusLines,
                'signup_bonus_range' => $this->discoverySignupBonusRange($bonusLines),
                'trial_hourly_range' => $this->discoveryHourlyPair($trialHourly, $meta, 'trial'),
                'help_hourly_range' => $this->discoveryHourlyPair($helpHourly, $meta, 'help'),
                'manager_overlay' => $managerOverlay,
                'distance_km' => null,
                'distance_label' => null,
                '_lat' => $row->latitude !== null ? (float) $row->latitude : null,
                '_lng' => $row->longitude !== null ? (float) $row->longitude : null,
            ];
        }

        // メイン最寄り駅を一括取得して各レコードに付与
        $shopIds = array_values(array_unique(array_map(fn ($it) => (string) ($it['id'] ?? ''), $items)));
        $shopIds = array_values(array_filter($shopIds, fn ($v) => $v !== ''));
        $mainStationByShop = $this->fetchMainStationByShopIds($shopIds);
        foreach ($items as &$item) {
            $item['nearest_station'] = $mainStationByShop[(string) ($item['id'] ?? '')] ?? '';
        }
        unset($item);

        // 探索拠点が設定されていれば各レコードに距離を付与
        $origin = $this->userLocation->getActiveLocation();
        $maxDistanceKm = (int) ($this->userLocation->getEffectiveMaxDistanceKm() ?? 0);
        if ($origin) {
            foreach ($items as &$item) {
                $km = $this->userLocation->distanceKm($origin['lat'], $origin['lng'], $item['_lat'] ?? null, $item['_lng'] ?? null);
                $item['distance_km'] = $km;
                $item['distance_label'] = $km !== null ? $this->userLocation->formatDistance($km) : null;
                unset($item['_lat'], $item['_lng']);
            }
            unset($item);
            if ($maxDistanceKm > 0) {
                $items = array_values(array_filter($items, function ($item) use ($maxDistanceKm) {
                    $km = $item['distance_km'] ?? null;
                    return $km === null || $km <= $maxDistanceKm;
                }));
            }
        } else {
            foreach ($items as &$item) {
                unset($item['_lat'], $item['_lng']);
            }
            unset($item);
        }

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
     * 求人スワイプ用：体入／ヘルプ時給の表示レンジ（noruma_cond JSON に trial_hourly_min 等があれば優先）
     *
     * @return array{lo: int, hi: int}|null
     */
    private function discoveryHourlyPair(?int $baseWage, array $meta, string $role): ?array
    {
        if ($baseWage === null || $baseWage <= 0) {
            return null;
        }
        $minKey = $role . '_hourly_min';
        $maxKey = $role . '_hourly_max';
        $lo = (isset($meta[$minKey]) && (int) $meta[$minKey] > 0) ? (int) $meta[$minKey] : $baseWage;
        $hi = (isset($meta[$maxKey]) && (int) $meta[$maxKey] > 0) ? (int) $meta[$maxKey] : $baseWage;
        if ($hi < $lo) {
            [$lo, $hi] = [$hi, $lo];
        }

        return ['lo' => $lo, 'hi' => $hi];
    }

    /**
     * 入店祝い金レンジ（体入・ヘルプ・本入で提示している金額の min〜max）
     *
     * @param array<int, array{label: string, amount: int, offered: bool}> $lines
     *
     * @return array{lo: int, hi: int}|null
     */
    private function discoverySignupBonusRange(array $lines): ?array
    {
        $amounts = [];
        foreach ($lines as $ln) {
            if (!empty($ln['offered']) && (int) ($ln['amount'] ?? 0) > 0) {
                $amounts[] = (int) $ln['amount'];
            }
        }
        if ($amounts === []) {
            return null;
        }
        $lo = min($amounts);
        $hi = max($amounts);

        return ['lo' => $lo, 'hi' => $hi];
    }

    /**
     * 求人カード画像上のキャッチ表示（ディスカバリー用：catch_copy のみ。pr・ボーナス条件バッジは出さない）
     *
     * @return array{show: bool, line1_html: string, line2: string, badge: string}
     */
    private function buildManagerImageOverlay(array $meta, int $norumaReward): array
    {
        return RecruitCatchOverlay::buildFromMeta($meta, $norumaReward, true);
    }

    private function toNumericShopId(string $shopId): int
    {
        if (!str_starts_with($shopId, 's')) {
            return is_numeric($shopId) ? (int) $shopId : 0;
        }

        return (int) ltrim(substr($shopId, 1), '0') ?: 0;
    }

    private function decodeRecruitMeta(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, string> $shopIds
     * @return array<string, string>
     */
    private function resolveIndustryLabelsByShopIds(array $shopIds): array
    {
        $shopIds = array_values(array_filter($shopIds, fn ($id) => is_string($id) && $id !== ''));
        if ($shopIds === [] || !Schema::hasTable('industries')) {
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

        $map = [];
        foreach ($rows as $row) {
            $shopId = (string) ($row->shop_id ?? '');
            if ($shopId === '') {
                continue;
            }
            $label = $hasLabel ? trim((string) ($row->industry_label ?? '')) : '';
            $masterName = trim((string) ($row->name ?? ''));
            $name = $label !== '' ? $label : $masterName;
            if ($name === '') {
                continue;
            }
            $map[$shopId] ??= [];
            if (!in_array($name, $map[$shopId], true)) {
                $map[$shopId][] = $name;
            }
        }

        return collect($map)
            ->map(fn ($names) => implode(' / ', $names))
            ->all();
    }

    /**
     * 求人タグ・店舗タグ（DB登録の shop_tags 系）だけを並べる。一覧で最大 {@see $max} 件まで。
     */
    private function buildHomeRecruitDiscoveryTags(string $shopId, int $shopJobId, int $max = 6): array
    {
        $merged = [];

        if ($shopJobId > 0 && Schema::hasTable('shop_job_tag_relations') && Schema::hasTable('shop_tags')) {
            $qj = DB::table('shop_job_tag_relations as r')
                ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
                ->where('r.shop_job_id', $shopJobId)
                ->where('t.target', 'job')
                ->whereIn('t.category', ['work_style', 'welcome', 'benefit'])
                ->orderBy('t.sort_order')
                ->orderBy('t.id');
            if (Schema::hasColumn('shop_tags', 'del_flg')) {
                $qj->where('t.del_flg', 0);
            }
            foreach ($qj->pluck('t.name') as $n) {
                $this->pushUniqueDiscoveryTag($merged, (string) $n, $max);
                if (count($merged) >= $max) {
                    return $merged;
                }
            }
        }

        if (Schema::hasTable('shop_tag_relations') && Schema::hasTable('shop_tags')) {
            $qs = DB::table('shop_tag_relations as r')
                ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
                ->where('r.shop_id', $shopId)
                ->where('t.target', 'shop')
                ->whereIn('t.category', ['atmosphere', 'facility'])
                ->orderBy('t.sort_order')
                ->orderBy('t.id');
            if (Schema::hasColumn('shop_tags', 'del_flg')) {
                $qs->where('t.del_flg', 0);
            }
            foreach ($qs->pluck('t.name') as $n) {
                $this->pushUniqueDiscoveryTag($merged, (string) $n, $max);
                if (count($merged) >= $max) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    /** @param list<string> $merged */
    private function pushUniqueDiscoveryTag(array &$merged, string $name, int $max): void
    {
        $t = trim($name);
        if ($t === '' || count($merged) >= $max) {
            return;
        }
        if (in_array($t, $merged, true)) {
            return;
        }
        $merged[] = $t;
    }

    private function buildRecruitCardTags(object $row, array $meta): array
    {
        $tags = [];
        if (!empty($row->pref)) {
            $tags[] = $row->pref;
        }
        if (!empty($row->city)) {
            $tags[] = $row->city;
        }
        $wage = 0;
        if (isset($row->regular_hourly_wage) && $row->regular_hourly_wage !== null && $row->regular_hourly_wage !== '') {
            $wage = (int) $row->regular_hourly_wage;
        } elseif (isset($row->hourly_wage_regular) && $row->hourly_wage_regular !== null && $row->hourly_wage_regular !== '') {
            $wage = (int) $row->hourly_wage_regular;
        }
        if ($wage >= 3000) {
            $tags[] = '高時給';
        }
        $bonus = 0;
        if (isset($row->bonus_reward) && $row->bonus_reward !== null && $row->bonus_reward !== '') {
            $bonus = (int) $row->bonus_reward;
        } elseif (isset($row->noruma_reward) && $row->noruma_reward !== null && $row->noruma_reward !== '') {
            $bonus = (int) $row->noruma_reward;
        }
        if ($bonus > 0) {
            $tags[] = 'ボーナスあり';
        }
        $catch = $meta['catch_copy'] ?? '';
        if ($catch !== '') {
            $tags[] = mb_strimwidth(trim($catch), 0, 12, '…');
        }

        return array_slice(array_unique($tags), 0, 5);
    }

    private function getHomeShops(): array
    {
        $latestPostSub = DB::table('shop_posts')
            ->select('shop_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('shop_id');

        $rows = DB::table('shops')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoinSub($latestPostSub, 'sp_latest', 'shops.id', '=', 'sp_latest.shop_id')
            ->leftJoin('shop_posts', 'shop_posts.id', '=', 'sp_latest.latest_id')
            ->select(
                'shops.id',
                'shop_profiles.shop_name',
                'shop_profiles.pref',
                'shop_profiles.city',
                DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.main_order IS NULL, si.main_order, si.id LIMIT 1) as main_image_path"),
                'shop_posts.body as shop_post_body'
            )
            ->orderBy('shops.id')
            ->limit(20)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $images = $this->getShopImages($row->id, $row->main_image_path);
            $items[] = [
                'id' => $row->id,
                'name' => $row->shop_name ?: '店舗',
                'age' => null,
                'tags' => $this->buildShopTags($row),
                'view_count' => 0,
                'rating' => 0,
                'images' => $images,
            ];
        }

        return $items;
    }

    private function getCastImages(string $castId, ?string $mainImagePath): array
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

    private function getShopImages(string $shopId, ?string $mainImagePath): array
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

    /**
     * カード下部のタグ。場所（pref/city）は位置チップと重複するため入れず、
     * 「経験の有無・職業・ルックス/内面タグ」など判断材料になる情報を優先する。
     *
     * @param array<int, string> $profileTags cast_tag_relations 由来のタグ名
     */
    private function buildCastTags(object $row, array $profileTags = []): array
    {
        $tags = [];
        $tags[] = ((int) ($row->exp ?? 0)) === 1 ? 'ナイトワーク経験あり' : '未経験';
        if (!empty($row->profession)) {
            $tags[] = mb_strimwidth(trim((string) $row->profession), 0, 12, '…');
        }
        foreach ($profileTags as $t) {
            if (count($tags) >= 4) {
                break;
            }
            $t = trim($t);
            if ($t !== '' && !in_array($t, $tags, true)) {
                $tags[] = $t;
            }
        }

        return $tags !== [] ? array_slice($tags, 0, 4) : ['プロフィール登録中'];
    }

    private function buildShopTags(object $row): array
    {
        $tags = [];
        if (!empty($row->pref)) {
            $tags[] = $row->pref;
        }
        if (!empty($row->city)) {
            $tags[] = $row->city;
        }
        $hitokoto = $row->shop_post_body ?? null;
        if (!empty($hitokoto)) {
            $tags[] = mb_strimwidth(trim((string) $hitokoto), 0, 18, '…');
        }

        return !empty($tags) ? array_slice($tags, 0, 3) : ['店舗情報登録中'];
    }

    // assetPathForStored() is now provided by the ResolvesActor trait.

    private function cleanupStaleImageReferences(): void
    {
        if (Schema::hasColumn('cast_profiles', 'main_image_path')) {
            DB::table('cast_profiles')
                ->whereNotNull('main_image_path')
                ->get(['cast_id'])
                ->each(function ($row) {
                    $hasImages = DB::table('cast_images')
                        ->where('cast_id', $row->cast_id)
                        ->exists();

                    if (!$hasImages) {
                        DB::table('cast_profiles')
                            ->where('cast_id', $row->cast_id)
                            ->update([
                                'main_image_path' => null,
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        if (Schema::hasColumn('shop_profiles', 'main_image_path')) {
            DB::table('shop_profiles')
                ->whereNotNull('main_image_path')
                ->get(['shop_id'])
                ->each(function ($row) {
                    $hasImages = DB::table('shop_images')
                        ->where('shop_id', $row->shop_id)
                        ->exists();

                    if (!$hasImages) {
                        DB::table('shop_profiles')
                            ->where('shop_id', $row->shop_id)
                            ->update([
                                'main_image_path' => null,
                                'updated_at' => now(),
                            ]);
                    }
                });
        }
    }
}