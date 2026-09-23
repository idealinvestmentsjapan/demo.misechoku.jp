<?php

namespace App\Http\Controllers\Casts;

use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use App\Services\ProfileViewService;
use App\Support\RecruitCatchOverlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * キャスト向け：店舗の求人情報表示
 */
class RecruitmentController extends Controller
{
    /**
     * 店舗プロフィール表示（GALLERY / JOB / SHOP 統合）
     * ルートの {id} は以下どちらも許容する:
     * - 数値ID (1, 2, 3, ...)
     * - 店舗ID文字列 (s00000001 など)
     *
     * キャスト向け画面では公開中の求人だけを表示する。
     */
    public function show(Request $request, $id)
    {
        $numericId = $this->normalizeRouteIdToNumeric($id);
        $data = $this->getRecruitDataFromDatabase($numericId, true);
        abort_if(empty($data['recruit']), 404);

        // キャストによる店舗プロフィール閲覧を記録
        if (auth()->guard('member')->check()) {
            app(ProfileViewService::class)->record(
                ProfileView::TYPE_CAST,
                (string) auth()->guard('member')->id(),
                ProfileView::TYPE_SHOP,
                's' . str_pad((string) $numericId, 8, '0', STR_PAD_LEFT)
            );
        }

        $initialJobPanel = $request->query('job', '');
        $initialJobPanel = in_array($initialJobPanel, ['fulltime', 'help'], true) ? $initialJobPanel : '';

        return view('shops.recruit.show', $this->buildRecruitViewData($data, $numericId, true, false, $initialJobPanel));
    }

    /**
     * SNS共有用の公開求人情報詳細
     */
    public function publicShow(int $id)
    {
        $data = $this->getRecruitDataFromDatabase($id, true);
        abort_if(empty($data['recruit']), 404);

        return view('shops.recruit.show', $this->buildRecruitViewData($data, $id, true, true));
    }

    /**
     * 求人情報＋店舗情報をDBから取得して画面用に整形
     * ルートの {id} は内部の shop_id を 1,2.. とした表示用として扱い、
     * 実テーブルの文字列ID形式へ変換する。
     */
    private function getRecruitDataFromDatabase(int $shopNumericId, bool $publishedOnly = false): array
    {
        $shopId = 's' . str_pad((string) $shopNumericId, 8, '0', STR_PAD_LEFT);

        if ($this->shopJobsHorizontalSchema()) {
            $bundle = $this->getRecruitDataHorizontalForCast($shopId);
            if (empty($bundle['recruit'])) {
                return [
                    'recruit' => [],
                    'recruit_trial' => [],
                    'recruit_help' => [],
                    'shop' => null,
                ];
            }
            if ($publishedOnly && (int) ($bundle['recruit']['regular_status'] ?? 0) !== 1) {
                return [
                    'recruit' => [],
                    'recruit_trial' => [],
                    'recruit_help' => [],
                    'shop' => null,
                ];
            }

            return $bundle;
        }

        $q = DB::table('shops')
            ->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->where('shops.id', $shopId)
            ->select(
                'shops.id',
                'shops.status as shop_status',
                'shop_profiles.shop_name',
                DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.main_order IS NULL, si.main_order, si.id LIMIT 1) as main_image_path"),
                'shop_profiles.opened_on',
                'shop_profiles.pref',
                'shop_profiles.city',
                'shop_jobs.id as shop_job_id',
                'shop_jobs.working_day',
                'shop_jobs.working_hours',
                'shop_jobs.regular_holiday',
                'shop_jobs.qualification',
            );
        if (Schema::hasColumn('shop_profiles', 'addr')) {
            $q->addSelect('shop_profiles.addr as addr2');
            if (Schema::hasColumn('shop_profiles', 'building')) {
                $q->addSelect('shop_profiles.building as addr3');
            } else {
                $q->addSelect(DB::raw("'' as addr3"));
            }
        } else {
            if (Schema::hasColumn('shop_profiles', 'addr2')) {
                $q->addSelect('shop_profiles.addr2');
            } else {
                $q->addSelect(DB::raw("'' as addr2"));
            }
            if (Schema::hasColumn('shop_profiles', 'addr3')) {
                $q->addSelect('shop_profiles.addr3');
            } else {
                $q->addSelect(DB::raw("'' as addr3"));
            }
        }
        if (Schema::hasColumn('shop_profiles', 'station1')) {
            $q->addSelect('shop_profiles.station1');
        } else {
            $q->addSelect(DB::raw("'' as station1"));
        }

        if (Schema::hasColumn('shop_jobs', 'salary')) {
            $q->addSelect('shop_jobs.salary');
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_remarks')) {
            $q->addSelect('shop_jobs.bonus_remarks');
        }
        if (Schema::hasColumn('shop_jobs', 'norma_day')) {
            $q->addSelect('shop_jobs.norma_day');
        }
        if (Schema::hasColumn('shop_jobs', 'norma_hours')) {
            $q->addSelect('shop_jobs.norma_hours');
        }
        if (Schema::hasColumn('shop_profiles', 'industry_id')) {
            $q->addSelect('shop_profiles.industry_id');
        }

        if (Schema::hasColumn('shop_jobs', 'regular_status')) {
            $q->addSelect('shop_jobs.regular_status');
        }
        if (Schema::hasColumn('shop_jobs', 'trial_status')) {
            $q->addSelect('shop_jobs.trial_status');
        }
        if (Schema::hasColumn('shop_jobs', 'help_status')) {
            $q->addSelect('shop_jobs.help_status');
        }
        if (Schema::hasColumn('shop_jobs', 'status')) {
            $q->addSelect('shop_jobs.status as recruit_status');
        }
        if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
            $q->addSelect('shop_jobs.hourly_wage_regular');
        }
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $q->addSelect('shop_jobs.regular_hourly_wage');
        }
        if (Schema::hasColumn('shop_jobs', 'has_trial')) {
            $q->addSelect('shop_jobs.has_trial');
        }
        if (Schema::hasColumn('shop_jobs', 'trial_hourly_wage')) {
            $q->addSelect('shop_jobs.trial_hourly_wage');
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $q->addSelect('shop_jobs.bonus_reward');
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
            $q->addSelect('shop_jobs.noruma_reward');
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_cond')) {
            $q->addSelect('shop_jobs.noruma_cond');
        }
        if (Schema::hasColumn('shop_jobs', 'catch_copy')) {
            $q->addSelect('shop_jobs.catch_copy');
        }
        if (Schema::hasColumn('shop_jobs', 'job_content')) {
            $q->addSelect('shop_jobs.job_content');
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_condition')) {
            $q->addSelect('shop_jobs.bonus_condition');
        }
        if (Schema::hasColumn('shop_jobs', 'atmosphere')) {
            $q->addSelect('shop_jobs.atmosphere');
        }
        if (Schema::hasColumn('shop_jobs', 'has_help')) {
            $q->addSelect('shop_jobs.has_help');
        }
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage')) {
            $q->addSelect('shop_jobs.help_hourly_wage');
        }
        if (Schema::hasColumn('shop_jobs', 'pr')) {
            $q->addSelect('shop_jobs.pr');
        }

        $row = $q->first();

        if (!$row) {
            return [
                'recruit' => [],
                'recruit_trial' => [],
                'recruit_help' => [],
                'shop' => null,
            ];
        }

        $horizontal = Schema::hasColumn('shop_jobs', 'regular_status');
        $publishedGate = true;
        if ($publishedOnly) {
            if ($horizontal) {
                $publishedGate = (int) ($row->regular_status ?? 0) === 1;
            } else {
                $publishedGate = (int) ($row->recruit_status ?? 0) === 1;
            }
        }

        if (!$publishedGate) {
            return [
                'recruit' => [],
                'recruit_trial' => [],
                'recruit_help' => [],
                'shop' => null,
            ];
        }

        $address = trim(($row->pref ?? '') . ($row->city ?? '') . $this->streetAddressFromProfileRow($row));
        $meta = Schema::hasColumn('shop_jobs', 'noruma_cond')
            ? $this->decodeMeta($row->noruma_cond ?? null)
            : [];
        $shopJobId = isset($row->shop_job_id) ? (int) $row->shop_job_id : 0;
        $jobTagNames = $this->resolveJobTagNames($shopJobId);
        $shopTagNames = $this->resolveShopTagNames($shopId);
        $managerMessage = Schema::hasColumn('shop_jobs', 'pr')
            ? (string) ($row->pr ?? '')
            : '';

        $trialWage = null;
        if ($horizontal) {
            if (isset($row->trial_hourly_wage) && $row->trial_hourly_wage !== null && $row->trial_hourly_wage !== '') {
                $trialWage = (int) $row->trial_hourly_wage;
            }
        } elseif (!empty($row->has_trial) && isset($row->trial_hourly_wage)) {
            $trialWage = (int) $row->trial_hourly_wage;
        }

        $helpWage = null;
        if ($horizontal) {
            if (isset($row->help_hourly_wage) && $row->help_hourly_wage !== null && $row->help_hourly_wage !== '') {
                $helpWage = (int) $row->help_hourly_wage;
            }
        } elseif (Schema::hasColumn('shop_jobs', 'has_help') && Schema::hasColumn('shop_jobs', 'help_hourly_wage')) {
            $helpWage = !empty($row->has_help) && isset($row->help_hourly_wage)
                ? (int) $row->help_hourly_wage : null;
        }

        $regularWage = 0;
        if (isset($row->regular_hourly_wage) && $row->regular_hourly_wage !== null && $row->regular_hourly_wage !== '') {
            $regularWage = (int) $row->regular_hourly_wage;
        } elseif (isset($row->hourly_wage_regular) && $row->hourly_wage_regular !== null && $row->hourly_wage_regular !== '') {
            $regularWage = (int) $row->hourly_wage_regular;
        }

        $bonusReward = 0;
        if (isset($row->bonus_reward) && $row->bonus_reward !== null && $row->bonus_reward !== '') {
            $bonusReward = (int) $row->bonus_reward;
        } elseif (isset($row->noruma_reward) && $row->noruma_reward !== null && $row->noruma_reward !== '') {
            $bonusReward = (int) $row->noruma_reward;
        }

        $catchFromCol = Schema::hasColumn('shop_jobs', 'catch_copy') ? trim((string) ($row->catch_copy ?? '')) : '';
        $jobContentCol = Schema::hasColumn('shop_jobs', 'job_content') ? trim((string) ($row->job_content ?? '')) : '';
        $bonusCondCol = Schema::hasColumn('shop_jobs', 'bonus_condition') ? trim((string) ($row->bonus_condition ?? '')) : '';

        $normaDayVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_day') && isset($row->norma_day) && $row->norma_day !== null && $row->norma_day !== '') {
            $normaDayVal = (int) $row->norma_day;
        }
        $normaHoursVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_hours') && isset($row->norma_hours) && $row->norma_hours !== null && $row->norma_hours !== '') {
            $normaHoursVal = (int) $row->norma_hours;
        }
        $bonusWorkingDays = $normaDayVal !== null ? (string) $normaDayVal : (string) ($meta['bonus_total_working_days'] ?? $meta['bonus_working_days'] ?? '');
        $bonusWorkingHours = $normaHoursVal !== null ? (string) $normaHoursVal : (string) ($meta['bonus_total_working_hours'] ?? $meta['bonus_working_hours'] ?? '');
        $bonusRemarksCol = Schema::hasColumn('shop_jobs', 'bonus_remarks') ? trim((string) ($row->bonus_remarks ?? '')) : '';

        $salaryText = '';
        if (Schema::hasColumn('shop_jobs', 'salary')) {
            $salaryText = (string) ($row->salary ?? '');
        } elseif ($bonusRemarksCol !== '') {
            $salaryText = $bonusRemarksCol;
        }

        $recruit = [
            'store_name'         => $row->shop_name,
            'open_date'          => $row->opened_on ? date('Y年n月j日', strtotime($row->opened_on)) : null,
            'address'            => $address,
            'map_embed_src'      => null,
            'nearest_station'    => $this->resolveNearestStation($shopId, $row->station1 ?? null),
            'hourly_wage_regular'=> $regularWage,
            'regular_hourly_wage'=> $regularWage,
            'trial_hourly_wage'  => $trialWage,
            'help_hourly_wage'   => $helpWage,
            'noruma_reward'      => $bonusReward,
            'bonus_reward'       => $bonusReward,
            'bonus_remarks'      => $bonusRemarksCol,
            'bonus_other_conditions' => $bonusCondCol !== '' ? $bonusCondCol : trim((string) ($meta['bonus_other_conditions'] ?? $meta['bonus_condition'] ?? '')),
            'bonus_total_working_days' => $bonusWorkingDays,
            'bonus_total_working_hours' => $bonusWorkingHours,
            'bonus_working_days' => $bonusWorkingDays,
            'bonus_working_hours' => $bonusWorkingHours,
            'bonus_condition'    => $bonusCondCol !== '' ? $bonusCondCol : ($meta['bonus_condition'] ?? ''),
            'salary_text'        => $salaryText,
            'working_hours'      => Schema::hasColumn('shop_jobs', 'working_hours') ? ($row->working_hours ?? null) : ($meta['working_hours'] ?? null),
            'working_days'       => Schema::hasColumn('shop_jobs', 'working_day') ? ($row->working_day ?? null) : ($meta['working_days'] ?? null),
            'regular_holiday'    => Schema::hasColumn('shop_jobs', 'regular_holiday') ? ($row->regular_holiday ?? null) : ($meta['regular_holiday'] ?? null),
            'job_content'        => $jobContentCol !== '' ? $jobContentCol : ($meta['job_content'] ?? ''),
            'store_atmosphere'   => Schema::hasColumn('shop_jobs', 'atmosphere') ? ($row->atmosphere ?? '') : '',
            'qualification'      => Schema::hasColumn('shop_jobs', 'qualification') ? ($row->qualification ?? '18歳以上（高校生不可）') : ($meta['qualification'] ?? '18歳以上（高校生不可）'),
            'catch_copy'         => $catchFromCol !== '' ? $catchFromCol : ($meta['catch_copy'] ?? ''),
            'message'            => $managerMessage,
            'regular_status'     => $horizontal ? (int) ($row->regular_status ?? 0) : null,
            'trial_status'       => $horizontal ? (int) ($row->trial_status ?? 0) : null,
            'help_status'        => $horizontal ? (int) ($row->help_status ?? 0) : null,
            'status'             => $horizontal
                ? ((int) ($row->regular_status ?? 0) === 1 ? 'active' : 'inactive')
                : (((int) ($row->recruit_status ?? 0) === 1) ? 'active' : 'inactive'),
            'benefits'           => [],
            'selected_benefits'  => $jobTagNames['benefit'],
            'store_features'     => [
                '働き方・給与'   => $jobTagNames['work_style'],
                '歓迎条件'       => $jobTagNames['welcome'],
                '待遇・サポート' => $jobTagNames['benefit'],
                '店内の雰囲気・客層' => $shopTagNames['atmosphere'],
                '設備・アクセス' => $shopTagNames['facility'],
            ],
        ];
        $recruit['catch_hero_overlay'] = RecruitCatchOverlay::buildFromMeta(
            [
                'catch_copy' => $recruit['catch_copy'] ?? '',
                'bonus_condition' => $recruit['bonus_condition'] ?? '',
                'bonus_other_conditions' => $recruit['bonus_other_conditions'] ?? '',
            ],
            (int) ($recruit['noruma_reward'] ?? 0)
        );

        $mainImg = $this->imageUrl($row->main_image_path);
        $subImagesRows = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->get();
        $subImages = [];
        foreach ($subImagesRows as $img) {
            $subImages[] = $this->imageUrl($img->image_path);
        }
        if (empty($subImages)) {
            $subImages[] = $mainImg;
        }

        $galleryImages = [];
        if ($mainImg) {
            $galleryImages[] = $mainImg;
        }
        foreach ($subImages as $path) {
            if ($path && !in_array($path, $galleryImages, true)) {
                $galleryImages[] = $path;
            }
        }

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $shopHitokoto = $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '';

        $industryName = $this->resolveShopIndustryName($shopId, $row->industry_id ?? null);
        $shopTagGroups = $this->resolveShopInfoTagGroups($shopId);

        $shop = [
            'name'       => $row->shop_name,
            'word'       => $shopHitokoto,
            'main_img'   => $mainImg,
            'area'       => trim(($row->pref ?? '') . ' ' . ($row->city ?? '')),
            'concept'    => '',
            'review_avg' => 0,
            'review_cnt' => 0,
            'sub_images' => $subImages,
            'gallery_images' => $galleryImages,
            'zip' => $row->zip ?? '',
            'pref' => $row->pref ?? '',
            'city' => $row->city ?? '',
            'addr1' => $this->streetAddressFromProfileRow($row),
            'industry_name' => $industryName,
            'nearest_station' => $this->resolveNearestStation($shopId, $row->station1 ?? null),
            'tag_groups' => $shopTagGroups,
            'available_active' => $this->resolveShopAvailableActive($shopId),
            'help_date_labels' => $this->resolveShopHelpDateLabels($shopId),
        ];

        return [
            'recruit' => $recruit,
            'recruit_trial' => $recruit,
            'recruit_help' => $recruit,
            'shop' => $shop,
        ];
    }

    private function buildRecruitViewData(array $data, int $id, bool $forCast, bool $isPublicShare, string $initialJobPanel = ''): array
    {
        $shopName = $data['shop']['name'] ?? $data['recruit']['store_name'] ?? '店舗';

        // プロフィール画面に表示する閲覧数（この店舗のプロフィールが閲覧された回数）
        $likeShopId = 's' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
        if (is_array($data['shop'] ?? null) && !isset($data['shop']['view_cnt'])) {
            $data['shop']['view_cnt'] = app(ProfileViewService::class)
                ->countFor(ProfileView::TYPE_SHOP, $likeShopId);
        }
        // 優良店バッヂ（過去3ヶ月の請求をすべて期日内に入金した店舗）— キャストにも見せる
        if (is_array($data['shop'] ?? null) && !isset($data['shop']['is_premium'])) {
            try {
                $badges = app(\App\Services\BillingManagementService::class)->getShopBadges($likeShopId);
                $data['shop']['is_premium'] = !empty($badges['good_payer']);
            } catch (\Throwable) {
                $data['shop']['is_premium'] = false;
            }
        }

        // レビュー概要 + 明細（キャストからも詳細を閲覧できるようにする）
        if (is_array($data['shop'] ?? null) && !isset($data['shop']['review_avg'])) {
            $data['shop']['review_avg'] = 0.0;
            $data['shop']['review_count'] = 0;
            $data['shop']['reviews'] = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('reviews')) {
                    $reviewRows = DB::table('reviews')
                        ->where('shop_id', $likeShopId)
                        ->orderByDesc('created_at')
                        ->select('id', 'contents', 'eva', 'created_at')
                        ->limit(50)
                        ->get();
                    $data['shop']['review_count'] = $reviewRows->count();
                    $scored = $reviewRows->filter(fn ($r) => $r->eva !== null);
                    $data['shop']['review_avg'] = $scored->isNotEmpty()
                        ? round((float) $scored->avg('eva'), 1)
                        : 0.0;
                    $data['shop']['reviews'] = $reviewRows->take(5)->map(fn ($r) => [
                        'score' => $r->eva !== null ? round((float) $r->eva, 1) : null,
                        'text'  => trim((string) ($r->contents ?? '')),
                        'date'  => $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('Y/m/d') : '',
                    ])->values()->all();
                }
            } catch (\Throwable) {
                // レビューが取得できなくてもプロフィール表示は継続
            }
        }

        $shareUrl = route('share.recruit.show', ['id' => $id]);
        $shareText = trim((string) ($data['recruit']['catch_copy'] ?? ''));
        if ($shareText === '') {
            $shareText = trim((string) ($data['recruit']['message'] ?? ''));
        }

        if ($shareText === '') {
            $shareText = 'ミセチョクの求人情報です。';
        }

        // 距離（ログインユーザの探索拠点 → 店舗）
        $distanceKm = null;
        $distanceLabel = null;
        $shopId = 's' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
        $shopCoords = DB::table('shop_profiles')
            ->where('shop_id', $shopId)
            ->select('latitude', 'longitude')
            ->first();
        if ($shopCoords && $shopCoords->latitude !== null && $shopCoords->longitude !== null) {
            $userLocation = app(\App\Services\UserLocationService::class);
            $origin = $userLocation->getActiveLocation();
            if ($origin) {
                $distanceKm = $userLocation->distanceKm($origin['lat'], $origin['lng'], (float) $shopCoords->latitude, (float) $shopCoords->longitude);
                $distanceLabel = $distanceKm !== null ? $userLocation->formatDistance($distanceKm) : null;
            }
        }

        return [
            'pageId' => 'job_info',
            'recruit' => $data['recruit'],
            'recruit_trial' => $data['recruit_trial'] ?? $data['recruit'],
            'recruit_help' => $data['recruit_help'] ?? $data['recruit'],
            'usesJobTypes' => $this->shopJobsUseMultipleTypesForCast(),
            'horizontalShopJobs' => $this->shopJobsHorizontalSchema(),
            'initial_job_panel' => $initialJobPanel,
            'shop' => $data['shop'],
            'forCast' => $forCast,
            'isPublicShare' => $isPublicShare,
            'shareUrl' => $shareUrl,
            'shareTitle' => $shopName . 'の求人情報',
            'shareText' => mb_strimwidth($shareText, 0, 80, '…'),
            'distanceKm' => $distanceKm,
            'distanceLabel' => $distanceLabel,
        ];
    }

    private function imageUrl(?string $path): string
    {
        if (empty($path)) {
            return asset('assets/images/common/no-image.png');
        }
        if (str_starts_with($path, 'uploads/')) {
            return asset($path);
        }
        if (str_starts_with($path, 'public/')) {
            return asset('storage/' . substr($path, 7));
        }

        return asset(ltrim($path, '/'));
    }

    private function resolveShopIndustryName(string $shopId, mixed $fallbackIndustryId = null): ?string
    {
        // industry_label が登録されていれば最優先で採用
        if (Schema::hasColumn('shop_profiles', 'industry_label')) {
            $label = trim((string) DB::table('shop_profiles')
                ->where('shop_id', $shopId)
                ->value('industry_label'));
            if ($label !== '') {
                return $label;
            }
        }

        if (!Schema::hasTable('industries')) {
            return null;
        }

        $names = DB::table('shop_profiles')
            ->join('industries', 'shop_profiles.industry_id', '=', 'industries.id')
            ->where('shop_profiles.shop_id', $shopId)
            ->pluck('industries.name')
            ->filter()
            ->values()
            ->all();

        if ($names === [] && $fallbackIndustryId !== null && $fallbackIndustryId !== '') {
            $name = DB::table('industries')
                ->where('id', (int) $fallbackIndustryId)
                ->value('name');
            if (!empty($name)) {
                $names = [$name];
            }
        }

        if ($names === []) {
            return null;
        }

        return implode(' / ', array_values(array_unique(array_map('strval', $names))));
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
     * 求人票（shop_jobs.id）に紐づく shop_tags(target='job') を category 別に名前で取得.
     *
     * @return array{work_style: array<int,string>, welcome: array<int,string>, benefit: array<int,string>}
     */
    private function resolveJobTagNames(int $shopJobId): array
    {
        $resolved = ['work_style' => [], 'welcome' => [], 'benefit' => []];
        if ($shopJobId <= 0
            || !Schema::hasTable('shop_job_tag_relations')
            || !Schema::hasTable('shop_tags')
        ) {
            return $resolved;
        }

        $rows = DB::table('shop_job_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_job_id', $shopJobId)
            ->where('t.target', 'job')
            ->where('t.del_flg', 0)
            ->whereIn('t.category', ['work_style', 'welcome', 'benefit'])
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->select('t.category', 't.name')
            ->get();

        foreach ($rows as $r) {
            $cat = (string) $r->category;
            if (isset($resolved[$cat])) {
                $resolved[$cat][] = (string) $r->name;
            }
        }

        return $resolved;
    }

    /**
     * 店舗（shops.id）に紐づく shop_tags(target='shop') を category 別に名前で取得.
     *
     * @return array{atmosphere: array<int,string>, facility: array<int,string>}
     */
    private function resolveShopTagNames(string $shopId): array
    {
        $resolved = ['atmosphere' => [], 'facility' => []];
        if (!Schema::hasTable('shop_tag_relations') || !Schema::hasTable('shop_tags')) {
            return $resolved;
        }

        $rows = DB::table('shop_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_id', $shopId)
            ->where('t.target', 'shop')
            ->where('t.del_flg', 0)
            ->whereIn('t.category', ['atmosphere', 'facility'])
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->select('t.category', 't.name')
            ->get();

        foreach ($rows as $r) {
            $cat = (string) $r->category;
            if (isset($resolved[$cat])) {
                $resolved[$cat][] = (string) $r->name;
            }
        }

        return $resolved;
    }

    private function shopJobsHorizontalSchema(): bool
    {
        return Schema::hasTable('shop_jobs') && Schema::hasColumn('shop_jobs', 'regular_status');
    }

    private function shopJobsUseMultipleTypesForCast(): bool
    {
        if (!Schema::hasTable('shop_jobs')) {
            return false;
        }
        if ($this->shopJobsHorizontalSchema()) {
            return true;
        }

        return Schema::hasColumn('shop_jobs', 'job_type');
    }

    /**
     * 店舗側 getRecruitDataHorizontal と同型のペイロード（キャスト用 URL 生成は imageUrl を使用）。
     *
     * @return array{recruit: array, recruit_trial: array, recruit_help: array, shop: array|null}
     */
    private function getRecruitDataHorizontalForCast(string $shopId): array
    {
        $row = DB::table('shops')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->where('shops.id', $shopId)
            ->select('shops.id', 'shop_profiles.*', 'shop_jobs.*', 'shop_jobs.id as primary_job_id')
            ->first();

        if (!$row) {
            return [
                'recruit' => [],
                'recruit_trial' => [],
                'recruit_help' => [],
                'shop' => null,
            ];
        }

        $meta = [];
        if (Schema::hasColumn('shop_jobs', 'noruma_cond') && !empty($row->noruma_cond)) {
            $meta = $this->decodeMeta($row->noruma_cond);
        }

        $industryName = $this->resolveShopIndustryName($shopId, $row->industry_id ?? null);
        $shopTagGroups = $this->resolveShopInfoTagGroups($shopId);

        $catchCopy = Schema::hasColumn('shop_jobs', 'catch_copy')
            ? (string) ($row->catch_copy ?? '')
            : (string) ($meta['catch_copy'] ?? '');
        $jobContent = Schema::hasColumn('shop_jobs', 'job_content')
            ? (string) ($row->job_content ?? '')
            : (string) ($meta['job_content'] ?? '');

        $regularWage = 0;
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $regularWage = (int) ($row->regular_hourly_wage ?? 0);
        } elseif (isset($row->hourly_wage_regular)) {
            $regularWage = (int) $row->hourly_wage_regular;
        }

        $bonusReward = 0;
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $bonusReward = (int) ($row->bonus_reward ?? 0);
        } elseif (isset($row->noruma_reward)) {
            $bonusReward = (int) $row->noruma_reward;
        }

        $bonusRemarks = Schema::hasColumn('shop_jobs', 'bonus_remarks')
            ? (string) ($row->bonus_remarks ?? '')
            : (string) ($row->noruma_reward2 ?? '');

        $normaDayVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_day') && $row->norma_day !== null) {
            $normaDayVal = (int) $row->norma_day;
        } elseif ($row && Schema::hasColumn('shop_jobs', 'normal_time') && $row->normal_time !== null) {
            $normaDayVal = (int) $row->normal_time;
        }
        $normaHoursVal = null;
        if (Schema::hasColumn('shop_jobs', 'norma_hours') && $row->norma_hours !== null) {
            $normaHoursVal = (int) $row->norma_hours;
        } elseif ($row && Schema::hasColumn('shop_jobs', 'hours_day') && $row->hours_day !== null) {
            $normaHoursVal = (int) $row->hours_day;
        }

        $bonusWorkingDays = $normaDayVal !== null ? (string) $normaDayVal : (string) ($meta['bonus_total_working_days'] ?? $meta['bonus_working_days'] ?? '');
        $bonusWorkingHours = $normaHoursVal !== null ? (string) $normaHoursVal : (string) ($meta['bonus_total_working_hours'] ?? $meta['bonus_working_hours'] ?? '');

        $bonusExtraCondition = '';
        if (Schema::hasColumn('shop_jobs', 'bonus_condition') && $row) {
            $bonusExtraCondition = trim((string) ($row->bonus_condition ?? ''));
        }
        if ($bonusExtraCondition === '') {
            $bonusExtraCondition = trim((string) ($meta['bonus_other_conditions'] ?? $meta['bonus_condition'] ?? ''));
        }

        $regStat = Schema::hasColumn('shop_jobs', 'regular_status')
            ? (int) ($row->regular_status ?? 0)
            : ($row ? ((int) ($row->status ?? 0) === 1 ? 1 : 0) : 0);
        $trialStat = Schema::hasColumn('shop_jobs', 'trial_status')
            ? (int) ($row->trial_status ?? 0)
            : 0;
        $helpStat = Schema::hasColumn('shop_jobs', 'help_status')
            ? (int) ($row->help_status ?? 0)
            : 0;

        $workingHours = Schema::hasColumn('shop_jobs', 'working_hours') ? ($row->working_hours ?? '') : ($meta['working_hours'] ?? '');
        $workingDays = Schema::hasColumn('shop_jobs', 'working_day') ? ($row->working_day ?? '') : ($meta['working_days'] ?? '');
        $regularHoliday = Schema::hasColumn('shop_jobs', 'regular_holiday') ? ($row->regular_holiday ?? '') : ($meta['regular_holiday'] ?? '');
        $qualification = Schema::hasColumn('shop_jobs', 'qualification') ? ($row->qualification ?? '') : ($meta['qualification'] ?? '');

        $message = Schema::hasColumn('shop_jobs', 'pr') && $row
            ? (string) ($row->pr ?? '')
            : '';

        $primaryJobId = isset($row->primary_job_id) ? (int) $row->primary_job_id : 0;
        $primaryJobTagIds = $primaryJobId ? $this->getShopJobTagIdsByCategory($primaryJobId) : ['work_style' => [], 'welcome' => [], 'benefit' => []];
        $primaryJobTagNames = $this->resolveShopJobTagNames($primaryJobTagIds);

        $subImages = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('image_path')
            ->map(fn ($path) => $this->imageUrl($path))
            ->filter()
            ->values()
            ->all();

        $mainImage = $subImages[0] ?? $this->imageUrl($row->main_image_path ?? null);
        if (empty($subImages) && $mainImage) {
            $subImages[] = $mainImage;
        }

        $galleryImages = [];
        if ($mainImage) {
            $galleryImages[] = $mainImage;
        }
        foreach ($subImages as $path) {
            if ($path && !in_array($path, $galleryImages, true)) {
                $galleryImages[] = $path;
            }
        }

        $trialWage = ($row && !empty($row->trial_hourly_wage)) ? (int) $row->trial_hourly_wage : null;
        $helpWage = ($row && !empty($row->help_hourly_wage)) ? (int) $row->help_hourly_wage : null;

        $salaryText = Schema::hasColumn('shop_jobs', 'salary')
            ? (string) ($row->salary ?? '')
            : $bonusRemarks;

        $base = [
            'store_name' => $row->shop_name ?? '店舗',
            'open_date' => !empty($row->opened_on) ? date('Y年n月j日', strtotime($row->opened_on)) : null,
            'address' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null, $this->streetAddressFromProfileRow($row)]))),
            'map_embed_src' => null,
            'nearest_station' => $this->resolveNearestStation($shopId, $row->station1 ?? null),
            'hourly_wage_regular' => $regularWage,
            'regular_hourly_wage' => $regularWage,
            'trial_hourly_wage' => $trialWage,
            'help_hourly_wage' => $helpWage,
            'help_job_content' => '',
            'noruma_reward' => $bonusReward,
            'bonus_reward' => $bonusReward,
            'bonus_remarks' => $bonusRemarks,
            'bonus_condition' => $bonusExtraCondition,
            'bonus_other_conditions' => $bonusExtraCondition,
            'bonus_total_working_days' => $bonusWorkingDays,
            'bonus_total_working_hours' => $bonusWorkingHours,
            'bonus_working_days' => $bonusWorkingDays,
            'bonus_working_hours' => $bonusWorkingHours,
            'salary_text' => $salaryText,
            'working_hours' => $workingHours,
            'working_days' => $workingDays,
            'regular_holiday' => $regularHoliday,
            'job_content' => $jobContent,
            'store_atmosphere' => '',
            'qualification' => $qualification ?: '18歳以上（高校生不可）',
            'catch_copy' => $catchCopy,
            'message' => $message,
            'regular_status' => $regStat,
            'trial_status' => $trialStat,
            'help_status' => $helpStat,
            'selected_benefits' => $primaryJobTagNames['benefit'],
            'store_features' => [
                '働き方・給与'   => $primaryJobTagNames['work_style'],
                '歓迎条件'       => $primaryJobTagNames['welcome'],
                '待遇・サポート' => $primaryJobTagNames['benefit'],
            ],
            'work_style_tag_ids' => $primaryJobTagIds['work_style'],
            'welcome_tag_ids'    => $primaryJobTagIds['welcome'],
            'benefit_tag_ids'    => $primaryJobTagIds['benefit'],
            'status' => $regStat === 1 ? 'active' : 'inactive',
            'updated_at' => $row && !empty($row->updated_at) ? date('Y.m.d', strtotime($row->updated_at)) : null,
        ];
        $base['catch_hero_overlay'] = RecruitCatchOverlay::buildFromMeta(
            [
                'catch_copy' => $base['catch_copy'] ?? '',
                'bonus_condition' => $base['bonus_condition'] ?? '',
                'bonus_other_conditions' => $base['bonus_other_conditions'] ?? '',
            ],
            (int) ($base['noruma_reward'] ?? 0)
        );

        $trialOut = $base;
        $trialOut['status'] = $trialStat === 1 ? 'active' : 'inactive';
        $helpOut = $base;
        $helpOut['status'] = $helpStat === 1 ? 'active' : 'inactive';

        $shopPost = DB::table('shop_posts')
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $shopHitokoto = $shopPost && isset($shopPost->body) ? (string) $shopPost->body : '';

        return [
            'recruit' => $base,
            'recruit_trial' => $trialOut,
            'recruit_help' => $helpOut,
            'shop' => [
                'name' => $row->shop_name ?? '店舗',
                'word' => $shopHitokoto,
                'main_img' => $mainImage,
                'area' => trim(implode(' ', array_filter([$row->pref ?? null, $row->city ?? null]))),
                'concept' => '',
                'review_avg' => 0,
                'review_cnt' => 0,
                'sub_images' => $subImages,
                'gallery_images' => $galleryImages,
                'zip' => $row->zip ?? '',
                'pref' => $row->pref ?? '',
                'city' => $row->city ?? '',
                'addr1' => $this->streetAddressFromProfileRow($row),
                'industry_name' => $industryName,
                'nearest_station' => $this->resolveNearestStation($shopId, $row->station1 ?? null),
                // 店舗プロフィールの営業時間（求人個別の working_hours は使わない）
                'business_hours_shop' => \App\Support\ShopBusinessHours::formatDisplay(
                    $row->open_time ?? null,
                    isset($row->close_is_last) ? (int) $row->close_is_last : 0,
                    $row->close_time ?? null
                ),
                'tag_groups' => $shopTagGroups,
                'available_active' => $this->resolveShopAvailableActive($shopId),
                'help_date_labels' => $this->resolveShopHelpDateLabels($shopId),
            ],
        ];
    }

    /**
     * Return true if the shop declared help recruitment for today.
     */
    private function resolveShopAvailableActive(string $shopId): bool
    {
        return app(\App\Services\AvailabilityService::class)->isAvailableOn(
            \App\Models\AvailabilityDate::OWNER_SHOP,
            $shopId,
            \Carbon\Carbon::today()->toDateString()
        );
    }

    /** Short labels ('本日', '9/28', ...) of the shop's dated help recruitment. */
    private function resolveShopHelpDateLabels(string $shopId): array
    {
        return array_map(
            fn ($d) => \App\Services\AvailabilityService::shortLabel($d),
            app(\App\Services\AvailabilityService::class)
                ->getDates(\App\Models\AvailabilityDate::OWNER_SHOP, $shopId)
        );
    }

    private function resolveNearestStation(string $shopId, ?string $legacyStation): string
    {
        if (Schema::hasTable('shop_stations')) {
            $name = DB::table('shop_stations')
                ->where('shop_id', $shopId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('station_name');
            $name = trim((string) $name);
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) $legacyStation);
    }

    private function streetAddressFromProfileRow(?object $row): string
    {
        if (!$row) {
            return '';
        }

        $addr = trim((string) ($row->addr ?? ''));
        $building = trim((string) ($row->building ?? ''));
        if ($addr !== '' || $building !== '') {
            return trim($addr . ' ' . $building);
        }

        return trim((string) ($row->addr2 ?? '') . ' ' . (string) ($row->addr3 ?? ''));
    }

    /**
     * @return array<int, array{label: string, tags: array<int, string>}>
     */
    private function resolveShopInfoTagGroups(string $shopId): array
    {
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('shop_tag_relations') || !$schema->hasTable('shop_tags')) {
            return [];
        }

        $definitions = [
            ['label' => '店内の雰囲気・客層', 'category' => 'atmosphere'],
            ['label' => '設備・アクセス',     'category' => 'facility'],
        ];

        $groups = [];
        foreach ($definitions as $def) {
            $names = DB::table('shop_tag_relations as r')
                ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
                ->where('r.shop_id', $shopId)
                ->where('r.tag_type', $def['category'])
                ->where('t.target', 'shop')
                ->where('t.category', $def['category'])
                ->where('t.del_flg', 0)
                ->orderBy('t.sort_order')
                ->orderBy('t.id')
                ->pluck('t.name')
                ->filter()
                ->values()
                ->all();
            if (!empty($names)) {
                $groups[] = ['label' => $def['label'], 'tags' => $names];
            }
        }

        return $groups;
    }

    /**
     * @return array{work_style: array<int,int>, welcome: array<int,int>, benefit: array<int,int>}
     */
    private function getShopJobTagIdsByCategory(int $shopJobId): array
    {
        $result = ['work_style' => [], 'welcome' => [], 'benefit' => []];
        if ($shopJobId <= 0
            || !Schema::hasTable('shop_job_tag_relations')
            || !Schema::hasTable('shop_tags')
        ) {
            return $result;
        }

        $rows = DB::table('shop_job_tag_relations as r')
            ->join('shop_tags as t', 'r.tag_id', '=', 't.id')
            ->where('r.shop_job_id', $shopJobId)
            ->where('t.target', 'job')
            ->where('t.del_flg', 0)
            ->whereIn('t.category', ['work_style', 'welcome', 'benefit'])
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

    /**
     * @param array{work_style: array<int,int>, welcome: array<int,int>, benefit: array<int,int>} $idsByCategory
     * @return array{work_style: array<int,string>, welcome: array<int,string>, benefit: array<int,string>}
     */
    private function resolveShopJobTagNames(array $idsByCategory): array
    {
        $resolved = ['work_style' => [], 'welcome' => [], 'benefit' => []];
        if (!Schema::hasTable('shop_tags')) {
            return $resolved;
        }

        foreach ($resolved as $cat => $_) {
            $ids = $this->normalizeTagIds($idsByCategory[$cat] ?? []);
            if (empty($ids)) {
                continue;
            }
            $resolved[$cat] = DB::table('shop_tags')
                ->where('target', 'job')
                ->where('category', $cat)
                ->whereIn('id', $ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('name')
                ->all();
        }

        return $resolved;
    }

    private function normalizeTagIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * ルートの {id} を内部で扱う数値IDに正規化する
     * - "s00000001" → 1
     * - "1" / 1     → 1
     */
    private function normalizeRouteIdToNumeric($id): int
    {
        if (is_int($id) || ctype_digit((string) $id)) {
            return (int) $id;
        }

        $id = (string) $id;
        if (str_starts_with($id, 's')) {
            return (int) ltrim(substr($id, 1), '0') ?: 0;
        }

        return 0;
    }
}
