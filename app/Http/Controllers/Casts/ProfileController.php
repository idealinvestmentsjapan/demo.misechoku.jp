<?php
namespace App\Http\Controllers\Casts;

use App\Consts\CommonConsts;
use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use App\Services\AdminMasterService;
use App\Services\ProfileViewService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private readonly AdminMasterService $adminMasterService,
        private readonly ProfileViewService $profileViews,
    ) {
    }

    /**
     * ログイン中キャストのプロフィール編集データ
     */
    private function getEditProfileData(string $castId): array
    {
        $row = DB::table('casts')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->where('casts.id', $castId)
            ->select(
                'cast_profiles.nickname',
                'cast_profiles.name',
                'cast_profiles.industry_id',
                'cast_profiles.birthday',
                'cast_profiles.zip',
                'cast_profiles.pref',
                'cast_profiles.city',
                Schema::hasColumn('cast_profiles', 'addr')
                    ? 'cast_profiles.addr'
                    : DB::raw('NULL as addr'),
                Schema::hasColumn('cast_profiles', 'building')
                    ? 'cast_profiles.building'
                    : DB::raw('NULL as building'),
                'cast_profiles.pr',
                'cast_profiles.height',
                'cast_profiles.weight',
                'cast_profiles.bust',
                'cast_profiles.waist',
                'cast_profiles.hip',
                'cast_profiles.profession',
                'cast_profiles.exp',
                Schema::hasColumn('cast_profiles', 'personality_type')
                    ? 'cast_profiles.personality_type'
                    : DB::raw('NULL as personality_type')
            )
            ->first();

        if (!$row) {
            return $this->emptyEditProfileData();
        }

        $birthday = $row->birthday ? Carbon::parse($row->birthday) : null;
        $nightWorkExp = ((int) ($row->exp ?? 0) === 1 ? 'yes' : 'none');
        $looksTags = $this->getCastTagNamesByType($castId, 'looks');
        $personalityTags = $this->getCastTagNamesByType($castId, 'personality');
        $industryNames = $this->resolveDesiredJobByIndustries($castId, $row->industry_id ?? null);

        return [
            'nickname'       => $row->nickname ?? '',
            'name'           => $row->name ?? '',
            'birth_date'     => $birthday ? $birthday->format('Y-m-d') : '',
            'birth_year'     => $birthday ? (string) $birthday->year : (string) date('Y'),
            'birth_month'    => $birthday ? (string) $birthday->month : '1',
            'birth_day'      => $birthday ? (string) $birthday->day : '1',
            'zip'            => $row->zip ?? '',
            'pref'           => $row->pref ?? '東京都',
            'city'           => $row->city ?? '中央区',
            'addr1'          => trim(implode(' ', array_filter([(string) ($row->addr ?? ''), (string) ($row->building ?? '')]))),
            'intro'          => $row->pr ?? '',
            'height'         => $row->height ? (string) $row->height : '',
            'weight'         => $row->weight ? (string) $row->weight : '',
            'bust'           => $row->bust ? (string) $row->bust : '',
            'waist'          => $row->waist ? (string) $row->waist : '',
            'hip'            => $row->hip ? (string) $row->hip : '',
            'industry_names' => $industryNames,
            'desired_job'    => $industryNames, // backward compatible key
            'my_field'       => '',
            'my_inner_skills'=> '',
            'profession'     => $row->profession ?? '',
            'current_job'    => $row->profession ?? '', // backward compatible key
            'exp'            => $nightWorkExp,
            'night_work_exp' => $nightWorkExp, // backward compatible key
            'industry_ids'   => $this->resolveCastIndustryIds($castId, $row->industry_id ?? null),
            'look_tag_ids'   => $this->getCastTagIdsByType($castId, 'looks'),
            'personality_tag_ids' => $this->getCastTagIdsByType($castId, 'personality'),
            'personality_type' => $this->resolvePersonalityType($row->personality_type ?? null),
        ];
    }

    private function emptyEditProfileData(): array
    {
        return [
            'nickname'       => '',
            'name'           => '',
            'birth_date'     => '',
            'birth_year'     => (string) date('Y'),
            'birth_month'    => '1',
            'birth_day'      => '1',
            'zip'            => '',
            'pref'           => '東京都',
            'city'           => '中央区',
            'addr1'          => '',
            'intro'          => '',
            'height'         => '',
            'weight'         => '',
            'bust'           => '',
            'waist'          => '',
            'hip'            => '',
            'industry_names' => '',
            'desired_job'    => '',
            'my_field'       => '',
            'my_inner_skills'=> '',
            'profession'     => '',
            'current_job'    => '',
            'exp'            => 'none',
            'night_work_exp' => 'none',
            'industry_ids'   => [],
            'look_tag_ids'   => [],
            'personality_tag_ids' => [],
            'personality_type' => '',
        ];
    }

    /**
     * プロフィール編集画面表示（cast/profile/edit または shop/profile/edit から呼ばれる）
     */
    public function edit()
    {
        $data = $this->getEditProfileData($this->currentCastId());
        return view('casts.profile.edit', [
            'pageId'      => 'mypage',
            'profile'     => $data,
            'masters'     => $this->adminMasterService->getCastProfileMasters(),
            'prefOptions' => CommonConsts::PREFS,
            'updateRoute' => 'cast.profile.update',
            'editRoute'   => 'cast.profile.edit',
        ]);
    }

    /**
     * プロフィール更新
     *
     * 受け取った全項目を以下のテーブルへ永続化する:
     *   - cast_profiles  ：基本属性（ニックネーム / 生年月日 / 住所 / 身体情報 / 自己紹介 等）
     *   - cast_tag_relations ：ルックスタグ・性格タグ（syncCastTags 経由）
     *   - cast_search_preferences ：希望業種（industry_ids JSON）
     * ホーム表示用画像が 0 枚の場合はバリデーションエラーで戻す。
     */
    public function update(Request $request)
    {
        $request->validate([
            'nickname'     => 'required|string|max:100',
            'name'         => 'nullable|string|max:100',
            'birth_date'   => 'required|date',
            'zip'          => ['nullable', 'regex:/^\d{3}-?\d{4}$/'],
            'pref'         => ['nullable', Rule::in(CommonConsts::PREFS)],
            'city'         => 'nullable|string|max:50',
            'addr1'        => 'nullable|string|max:255',
            'intro'        => 'nullable|string',
            'height'       => 'nullable|integer|min:100|max:250',
            'weight'       => 'nullable|integer|min:30|max:200',
            'bust'         => 'nullable|integer|min:40|max:200',
            'waist'        => 'nullable|integer|min:30|max:200',
            'hip'          => 'nullable|integer|min:40|max:200',
            'my_field'     => 'nullable|string|max:255',
            'my_inner_skills' => 'nullable|string|max:500',
            'profession'   => 'nullable|string',
            'current_job'  => 'nullable|string', // backward compatible
            'exp'          => 'nullable|string|max:20',
            'night_work_exp' => 'nullable|string|max:20', // backward compatible
            'industry_ids'   => 'nullable|array',
            'industry_ids.*' => 'integer|exists:industries,id',
            'look_tag_ids' => 'nullable|array',
            'look_tag_ids.*' => 'integer',
            'personality_tag_ids' => 'nullable|array',
            'personality_tag_ids.*' => 'integer',
        ], [
            'zip.regex' => '郵便番号は 7 桁、または 123-4567 形式で入力してください。',
        ]);
        $tagTable = $this->resolveCastTagMasterTable();
        if ($tagTable !== null) {
            $request->validate([
                'look_tag_ids.*' => 'integer|exists:' . $tagTable . ',id',
                'personality_tag_ids.*' => 'integer|exists:' . $tagTable . ',id',
            ]);
        }

        $castId = $this->currentCastId();

        $imageCount = (int) DB::table('cast_images')
            ->where('cast_id', $castId)
            ->count();

        if ($imageCount < 1) {
            return redirect()->back()
                ->withErrors(['images' => 'ホーム表示用の画像を1枚以上登録してください。'])
                ->withInput();
        }

        $industryIds = collect($request->input('industry_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $profession = $request->input('profession', $request->input('current_job'));
        $exp = $request->input('exp', $request->input('night_work_exp'));

        $castProfileData = [
            'nickname' => $request->input('nickname'),
            'name' => $request->input('name'),
            'birthday' => $request->input('birth_date'),
            'zip' => $this->normalizeZip($request->input('zip')),
            'pref' => $request->input('pref'),
            'city' => $request->input('city'),
            'addr' => $request->input('addr1'),
            'building' => null,
            'pr' => $request->input('intro'),
            'height' => $request->filled('height') ? (int) $request->input('height') : null,
            'weight' => $request->filled('weight') ? (int) $request->input('weight') : null,
            'bust' => $request->filled('bust') ? (int) $request->input('bust') : null,
            'waist' => $request->filled('waist') ? (int) $request->input('waist') : null,
            'hip' => $request->filled('hip') ? (int) $request->input('hip') : null,
            'profession' => $profession,
            'exp' => $exp === 'yes' ? 1 : 0,
            'industry_id' => $industryIds[0] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ];
        DB::table('cast_profiles')->updateOrInsert(
            ['cast_id' => $castId],
            $castProfileData
        );

        // キャストタグ（ルックス・性格）を中間テーブル cast_tag_relations で同期
        $this->syncCastTags($castId, 'looks', $request->input('look_tag_ids', []));
        $this->syncCastTags($castId, 'personality', $request->input('personality_tag_ids', []));

        // 希望業種は cast_search_preferences (industry_ids JSON) に保存
        $now = now();
        DB::table('cast_search_preferences')->upsert(
            [[
                'cast_id'      => $castId,
                'industry_ids' => json_encode(array_values(array_map('intval', $industryIds))),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]],
            ['cast_id'],
            ['industry_ids', 'updated_at']
        );

        return redirect()->route('cast.mypage.index')
            ->with('message', 'プロフィールを更新しました')
            ->withInput([]);
    }

    public function updatePersonalityType(Request $request)
    {
        $validated = $request->validate([
            'personality_type' => ['required', 'regex:/^[LF][CP][IO][HR]$/'],
        ]);

        $castId = $this->currentCastId();
        $payload = [
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('cast_profiles', 'personality_type')) {
            $payload['personality_type'] = $validated['personality_type'];
        }

        $exists = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->exists();

        if ($exists) {
            DB::table('cast_profiles')
                ->where('cast_id', $castId)
                ->update($payload);
        } else {
            $payload['cast_id'] = $castId;
            $payload['created_at'] = now();
            DB::table('cast_profiles')->insert($payload);
        }

        return response()->json([
            'success' => true,
            'personality_type' => $validated['personality_type'],
        ]);
    }

    /**
     * キャスト詳細表示（お店 → キャストを閲覧）
     * ルート: shop.castprofileview.show (/shop/castprofileview/{id})
     *
     * ※ 旧仕様ではキャスト側 (/cast/*) からのお店詳細も同メソッドで扱っていたが、
     *   お店詳細は shops.recruit.show（cast-show partial）に一本化したため、
     *   本メソッドは "お店 → キャスト" 専用となる。
     */
    public function show($id = null) {
        $id = $id ?? 1;

        // お店側 → キャストの情報を表示（閲覧を記録）
        if (auth()->guard('shop')->check()) {
            $this->profileViews->record(
                ProfileView::TYPE_SHOP,
                (string) (auth()->guard('shop')->user()->shop_id ?? ''),
                ProfileView::TYPE_CAST,
                (string) $id
            );
        }
        return view('casts.profile.show', $this->buildCastProfileViewData((string) $id, false));
    }

    /**
     * SNS共有用の公開キャストプロフィール
     */
    public function publicShow(string $id)
    {
        return view('casts.profile.show', $this->buildCastProfileViewData($id, true));
    }

    private function currentCastId(): string
    {
        return (string) auth()->guard('member')->id();
    }

    private function buildCastDetailData(string $castId): array
    {
        $row = DB::table('casts')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->where('casts.id', $castId)
            ->select(
                'casts.id',
                'cast_profiles.nickname',
                'cast_profiles.name',
                'cast_profiles.birthday',
                'cast_profiles.pref',
                'cast_profiles.city',
                'cast_profiles.height',
                'cast_profiles.weight',
                'cast_profiles.bust',
                'cast_profiles.waist',
                'cast_profiles.hip',
                'cast_profiles.profession',
                'cast_profiles.exp',
                'cast_profiles.pr',
                Schema::hasColumn('cast_profiles', 'personality_type')
                    ? 'cast_profiles.personality_type'
                    : DB::raw('NULL as personality_type')
            )
            ->first();

        if (!$row) {
            return $this->buildFallbackCastDetail($castId);
        }

        $birthday = $row->birthday ? Carbon::parse($row->birthday) : null;
        $nightWorkExp = ((int) ($row->exp ?? 0) === 1 ? 'yes' : 'none');
        $looksTags = $this->getCastTagNamesByType($castId, 'looks');
        $personalityTags = $this->getCastTagNamesByType($castId, 'personality');
        $industryNames = $this->resolveDesiredJobByIndustries($castId);

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

        $reviewRows = DB::table('reviews')
            ->leftJoin('review_details', 'reviews.id', '=', 'review_details.review_id')
            ->where('reviews.cast_id', $castId)
            ->groupBy('reviews.id', 'reviews.contents', 'reviews.created_at')
            ->select(
                'reviews.id',
                'reviews.contents',
                DB::raw('AVG(review_details.score) as avg_score')
            )
            ->get();

        $reviews = [];
        foreach ($reviewRows as $review) {
            $reviews[] = [
                'score' => $review->avg_score !== null ? (float) $review->avg_score : 0.0,
                'text' => $review->contents ?? '',
            ];
        }

        // ひとこと（cast_posts.body）：プロフィールヘッダーの吹き出しに表示
        $word = '';
        if (Schema::hasTable('cast_posts')) {
            $word = trim((string) (DB::table('cast_posts')->where('cast_id', $castId)->value('body') ?? ''));
        }

        // 閲覧中の店舗が実際に KEEP 済みか（行が存在 = アクティブ）。
        $isKeptByViewer = false;
        $viewerShopId = (string) (auth()->guard('shop')->user()->shop_id ?? '');
        if ($viewerShopId !== '' && Schema::hasTable('favorites')) {
            $isKeptByViewer = DB::table('favorites')
                ->where('shop_id', $viewerShopId)
                ->where('cast_id', $castId)
                ->where('sender_type', 'shop')
                ->where('action_type', 'KEEP')
                ->exists();
        }

        return [
            'id' => $castId,
            'nickname' => $row->nickname ?? '',
            'name' => $row->name ?? '',
            'age' => $birthday ? $birthday->age : null,
            'birth_year' => $birthday ? (string) $birthday->year : null,
            'birth_month' => $birthday ? (string) $birthday->month : null,
            'birth_day' => $birthday ? (string) $birthday->day : null,
            'images' => $images,
            'img' => $images[0] ?? asset('assets/images/common/no-image.png'),
            'is_applied' => true,
            'is_kept' => $isKeptByViewer,
            // 閲覧数：このキャストのプロフィールが閲覧された回数
            'view_cnt' => $this->profileViews->countFor(ProfileView::TYPE_CAST, $castId),
            'pref' => $row->pref ?? '',
            'city' => $row->city ?? '',
            'height' => $row->height,
            'weight' => $row->weight,
            'bust' => $row->bust,
            'waist' => $row->waist,
            'hip' => $row->hip,
            'pr' => $row->pr ?? '',
            'intro' => $row->pr ?? '',
            'word' => $word,
            'industry_names' => $industryNames,
            'desired_job' => $industryNames, // backward compatible key
            'my_field' => $looksTags !== [] ? implode(' / ', $looksTags) : '',
            'my_inner_skills' => $personalityTags !== [] ? implode(' / ', $personalityTags) : '',
            'looks_tags' => $looksTags,
            'personality_tags' => $personalityTags,
            'personality_type' => $this->resolvePersonalityType($row->personality_type ?? null),
            'profession' => $row->profession ?? '',
            'current_job' => $row->profession ?? '',
            'exp' => $nightWorkExp,
            'night_work_exp' => $nightWorkExp,
            'night_work_label' => $nightWorkExp === 'yes' ? '有り' : '無し',
            'reviews' => $reviews,
        ];
    }

    private function buildCastProfileViewData(string $castId, bool $isPublicShare): array
    {
        abort_unless($this->castExists($castId), 404);

        $cast = $this->buildCastDetailData($castId);
        $displayName = trim((string) ($cast['nickname'] ?? $cast['name'] ?? 'キャスト'));
        $shareText = trim((string) ($cast['intro'] ?? $cast['pr'] ?? ''));

        if ($shareText === '') {
            $shareText = 'ミセチョクのキャストプロフィールです。';
        }

        // 距離（ログインユーザの探索拠点 → キャスト）
        $distanceKm = null;
        $distanceLabel = null;
        $coords = DB::table('cast_profiles')
            ->where('cast_id', $castId)
            ->select('latitude', 'longitude')
            ->first();
        if ($coords && $coords->latitude !== null && $coords->longitude !== null) {
            $userLocation = app(\App\Services\UserLocationService::class);
            $origin = $userLocation->getActiveLocation();
            if ($origin) {
                $distanceKm = $userLocation->distanceKm($origin['lat'], $origin['lng'], (float) $coords->latitude, (float) $coords->longitude);
                $distanceLabel = $distanceKm !== null ? $userLocation->formatDistance($distanceKm) : null;
            }
        }

        return [
            'pageId' => 'cast_detail',
            'cast' => $cast,
            'isPublicShare' => $isPublicShare,
            'showInteractionActions' => !$isPublicShare,
            'shareUrl' => route('share.cast.show', ['id' => $castId]),
            'shareTitle' => $displayName . 'のプロフィール',
            'shareText' => mb_strimwidth($shareText, 0, 80, '…'),
            'distanceKm' => $distanceKm,
            'distanceLabel' => $distanceLabel,
        ];
    }

    private function buildFallbackCastDetail(string $castId): array
    {
        $images = [asset('assets/images/common/no-image.png')];

        return [
            'id' => $castId,
            'nickname' => 'ゲスト',
            'name' => '',
            'age' => null,
            'birth_year' => null,
            'birth_month' => null,
            'birth_day' => null,
            'images' => $images,
            'img' => $images[0],
            'is_applied' => false,
            'is_kept' => false,
            'view_cnt' => 0,
            'pref' => '',
            'city' => '',
            'height' => null,
            'weight' => null,
            'bust' => null,
            'waist' => null,
            'hip' => null,
            'pr' => '',
            'intro' => '',
            'industry_names' => '',
            'desired_job' => '',
            'my_field' => '',
            'my_inner_skills' => '',
            'looks_tags' => [],
            'personality_tags' => [],
            'personality_type' => '',
            'profession' => '',
            'current_job' => '',
            'exp' => 'none',
            'night_work_exp' => 'none',
            'night_work_label' => '無し',
            'reviews' => [],
        ];
    }

    private function castExists(string $castId): bool
    {
        return DB::table('casts')
            ->where('id', $castId)
            ->exists();
    }

    // assetPathForStored() is now provided by ResolvesActor trait.

    private function resolvePersonalityType(?string $columnType): string
    {
        $type = $columnType ?? '';

        return is_string($type) && preg_match('/^[LF][CP][IO][HR]$/', $type) ? $type : '';
    }

    private function syncCastTags(string $castId, string $tagType, array $tagIds): void
    {
        if (!DB::getSchemaBuilder()->hasTable('cast_tag_relations')) {
            return;
        }
        $normalizedType = rtrim($tagType, 's');
        $targetTypes = array_values(array_unique(array_filter([$tagType, $normalizedType])));

        DB::table('cast_tag_relations')
            ->where('cast_id', $castId)
            ->whereIn('tag_type', $targetTypes)
            ->delete();

        $rows = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($tagId) => [
                'cast_id'   => $castId,
                'tag_id'    => $tagId,
                'tag_type'  => $normalizedType,
                'created_at'=> now(),
                'updated_at'=> now(),
            ])
            ->all();

        if (!empty($rows)) {
            DB::table('cast_tag_relations')->insert($rows);
        }
    }

    private function shiftHopeLabel($shift): string
    {
        return match ((int) ($shift ?? 0)) {
            1 => '週1回出勤',
            2 => '週2回出勤',
            3 => '週3回以上',
            default => '週1回出勤',
        };
    }

    private function shiftCode(?string $shiftHope): ?int
    {
        return match ($shiftHope) {
            '週1回出勤' => 1,
            '週2回出勤' => 2,
            '週3回以上' => 3,
            default => null,
        };
    }

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

    private function resolveDesiredJobByIndustries(string $castId, $fallbackIndustryId = null): string
    {
        $names = [];
        $row = DB::table('cast_search_preferences')
            ->where('cast_id', $castId)
            ->value('industry_ids');
        $ids = $row ? (json_decode($row, true) ?: []) : [];
        if (!empty($ids)) {
            $names = DB::table('industries')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all();
        }

        if ($names === []) {
            $single = (int) ($fallbackIndustryId ?? 0);
            if ($single > 0) {
                $name = DB::table('industries')->where('id', $single)->value('name');
                if ($name) {
                    $names = [trim((string) $name)];
                }
            }
        }

        return implode(' / ', array_values(array_unique($names)));
    }

    /**
     * @return array<int, string>
     */
    private function getCastTagNamesByType(string $castId, string $tagType): array
    {
        $tagTable = $this->resolveCastTagMasterTable();
        if ($tagTable === null || !Schema::hasTable('cast_tag_relations')) {
            return [];
        }

        $tagTypes = array_values(array_unique(array_filter([$tagType, rtrim($tagType, 's')])));
        return DB::table('cast_tag_relations as r')
            ->join($tagTable . ' as t', 'r.tag_id', '=', 't.id')
            ->where('r.cast_id', $castId)
            ->whereIn('r.tag_type', $tagTypes)
            ->orderBy('t.id')
            ->pluck('t.name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function getCastTagIdsByType(string $castId, string $tagType): array
    {
        if (!Schema::hasTable('cast_tag_relations')) {
            return [];
        }
        $tagTypes = array_values(array_unique(array_filter([$tagType, rtrim($tagType, 's')])));
        return DB::table('cast_tag_relations')
            ->where('cast_id', $castId)
            ->whereIn('tag_type', $tagTypes)
            ->pluck('tag_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCastTagMasterTable(): ?string
    {
        if (Schema::hasTable('cast_tags')) {
            return 'cast_tags';
        }
        if (Schema::hasTable('tags')) {
            return 'tags';
        }
        return null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveCastIndustryIds(string $castId, $fallbackIndustryId = null): array
    {
        $row = DB::table('cast_search_preferences')
            ->where('cast_id', $castId)
            ->value('industry_ids');
        $ids = $row ? (json_decode($row, true) ?: []) : [];
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
        if ($ids !== []) {
            return $ids;
        }

        $single = (int) ($fallbackIndustryId ?? 0);
        return $single > 0 ? [$single] : [];
    }
}
