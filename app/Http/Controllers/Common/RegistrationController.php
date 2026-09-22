<?php

namespace App\Http\Controllers\Common;

use App\Consts\CommonConsts;
use App\Http\Controllers\Controller;
use App\Models\Cast;
use App\Models\ShopManager;
use App\Models\CastIdentityDocument;
use App\Services\AdminMasterService;
use App\Services\DocumentReviewService;
use App\Services\ShopProfileLocationSyncService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly AdminMasterService $adminMasterService,
        private readonly ShopProfileLocationSyncService $shopProfileLocationSyncService,
        private readonly DocumentReviewService $documentReviewService,
    ) {
    }

    public function showCast(): View
    {
        return view('common.register', array_merge($this->buildViewData('cast'), [
            'masters' => $this->adminMasterService->getCastProfileMasters(),
        ]));
    }

    public function showShop(): View
    {
        return view('common.register', array_merge($this->buildViewData('shop'), [
            'masters' => $this->adminMasterService->getShopProfileMasters(),
        ]));
    }

    public function storeCast(Request $request): RedirectResponse
    {
        $rules = [
            'nickname' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'zip' => ['required', 'regex:/^\d{3}-?\d{4}$/'],
            'pref' => ['required', Rule::in(CommonConsts::PREFS)],
            'city' => ['required', 'string', 'max:100'],
            'addr1' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:casts,email'],
            'profile_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'height' => ['nullable', 'integer', 'min:100', 'max:250'],
            'weight' => ['nullable', 'integer', 'min:30', 'max:150'],
            'bust' => ['nullable', 'integer', 'min:50', 'max:120'],
            'waist' => ['nullable', 'integer', 'min:40', 'max:120'],
            'hip' => ['nullable', 'integer', 'min:50', 'max:120'],
            'profession' => ['nullable', 'string', 'max:1000'],
            'current_job' => ['nullable', 'string', 'max:1000'], // backward compatible
            'exp' => ['nullable', 'string', 'in:none,yes'],
            'night_work_exp' => ['nullable', 'string', 'in:none,yes'], // backward compatible
            'industry_ids' => ['nullable', 'array'],
            'industry_ids.*' => ['integer'],
            'look_tag_ids' => ['nullable', 'array'],
            'look_tag_ids.*' => ['integer'],
            'personality_tag_ids' => ['nullable', 'array'],
            'personality_tag_ids.*' => ['integer'],
            // 本人確認書類（任意・後で登録可能）
            'identity_skip' => ['nullable'],
            'identity_category' => ['nullable', 'string', 'in:photo_id,non_photo_id'],
            'identity_type' => ['nullable', 'string'],
            'identity_front_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'identity_back_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'identity_expired_at' => ['nullable', 'date'],
            'identity_address_type' => ['nullable', 'string'],
            'identity_address_front_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ];
        if (Schema::hasTable('industries')) {
            $rules['industry_ids.*'][] = 'exists:industries,id';
        }
        if (Schema::hasTable('cast_tags')) {
            $rules['look_tag_ids.*'][] = 'exists:cast_tags,id';
            $rules['personality_tag_ids.*'][] = 'exists:cast_tags,id';
        }

        $request->validate($rules, [
            'zip.required' => '郵便番号を入力してください。',
            'zip.regex' => '郵便番号は 7 桁、または 123-4567 形式で入力してください。',
        ]);

        // プロフィール詳細・本人確認書類は「あとで登録」可。
        // フォーム側でスキップが指定されていれば各セクションの入力は無視する。
        $profileSkipped = (bool) $request->boolean('profile_skip');
        $identitySkipped = (bool) $request->boolean('identity_skip');

        $this->validateIdentityUpload($request, $identitySkipped);

        $member = DB::transaction(function () use ($request, $profileSkipped, $identitySkipped) {
            $castId = $this->nextSequentialId('casts', 'c');

            DB::table('casts')->insert([
                'id' => $castId,
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'status' => 1,
                'identity_status' => 1,
                'last_login_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $exp = $profileSkipped
                ? 'none'
                : ($request->filled('exp') ? $request->input('exp') : ($request->input('night_work_exp') ?? 'none'));
            $industryIds = $profileSkipped
                ? []
                : array_values(array_unique(array_map('intval', (array) $request->input('industry_ids', []))));

            $profilePayload = [
                'cast_id' => $castId,
                'nickname' => $request->input('nickname'),
                'name' => $request->input('name'),
                'birthday' => $request->input('birth_date'),
                'zip' => $this->normalizeZip($request->input('zip')),
                'pref' => $request->input('pref'),
                'city' => $request->input('city'),
                'addr' => $request->input('addr1'),
                'building' => null,
                'tel' => $request->input('phone'),
                'exp' => $exp === 'yes' ? 1 : 0,
                'pr' => $profileSkipped ? null : $request->input('intro'),
                'height' => !$profileSkipped && $request->filled('height') ? (int) $request->input('height') : null,
                'weight' => !$profileSkipped && $request->filled('weight') ? (int) $request->input('weight') : null,
                'bust' => !$profileSkipped && $request->filled('bust') ? (int) $request->input('bust') : null,
                'waist' => !$profileSkipped && $request->filled('waist') ? (int) $request->input('waist') : null,
                'hip' => !$profileSkipped && $request->filled('hip') ? (int) $request->input('hip') : null,
                'profession' => $profileSkipped ? null : $request->input('profession', $request->input('current_job')),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('cast_profiles', 'industry_id')) {
                $profilePayload['industry_id'] = $industryIds[0] ?? null;
            }
            DB::table('cast_profiles')->insert($profilePayload);
            $this->syncCastIndustries($castId, $industryIds);

            // プロフィール画像（必須1枚）を保存
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $dir = public_path('uploads/casts/gallery');
                File::ensureDirectoryExists($dir);
                $name = $file->hashName();
                $file->move($dir, $name);
                $path = 'uploads/casts/gallery/' . $name;

                DB::table('cast_images')->insert([
                    'cast_id'       => $castId,
                    'image_path'    => $path,
                    'status'        => 0,
                    'is_main'       => 1,
                    'main_order'    => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

            }

            if (!$profileSkipped) {
                $this->syncCastTags($castId, 'looks', $request->input('look_tag_ids', []));
                $this->syncCastTags($castId, 'personality', $request->input('personality_tag_ids', []));
            }

            if (!$identitySkipped) {
                $this->saveIdentityDocuments($request, $castId);
            }

            return Cast::query()->findOrFail($castId);
        });

        auth()->guard('shop')->logout();
        auth()->guard('member')->login($member);
        $request->session()->regenerate();

        // メール認証リンクを送信（未認証状態でスタート）
        \App\Http\Controllers\Auth\EmailVerificationController::dispatchVerifyMail(
            'cast', (string) $member->id, (string) $member->email
        );

        return redirect()
            ->route('cast.tutorial')
            ->with('registration_completed', 'cast')
            ->with('message', 'キャストアカウントを登録しました。ご登録のメールアドレス宛に認証リンクをお送りしましたのでご確認ください。');
    }

    public function storeShop(Request $request): RedirectResponse
    {
        $shopRules = [
            'company_name' => ['required', 'string', 'max:100'],
            'shop_name' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'regex:/^\d{3}-?\d{4}$/'],
            'pref' => ['required', Rule::in(CommonConsts::PREFS)],
            'city' => ['required', 'string', 'max:100'],
            'addr' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:shop_managers,email'],
            'plan' => ['required', 'in:basic'],
            'shop_profile_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
            'word' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:2000'],
            'industry_ids' => ['nullable', 'array'],
            'industry_ids.*' => ['integer'],
        ];
        if (Schema::hasTable('industries')) {
            $shopRules['industry_ids.*'][] = 'exists:industries,id';
        }
        $request->validate($shopRules, [
            'zip.required' => '郵便番号を入力してください。',
            'zip.regex' => '郵便番号は 7 桁、または 123-4567 形式で入力してください。',
        ]);

        $manager = DB::transaction(function () use ($request) {
            $shopId = $this->nextSequentialId('shops', 's');
            $managerId = $this->nextSequentialId('shop_managers', 'm');

            DB::table('shops')->insert([
                'id' => $shopId,
                'email' => $request->input('email'),
                'status' => 1,
                'license_status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $industryIds = array_values(array_unique(array_map('intval', (array) $request->input('industry_ids', []))));

            $shopProfilePayload = [
                'shop_id' => $shopId,
                'shop_name' => $request->input('shop_name'),
                'zip' => $this->normalizeZip($request->input('zip')),
                'pref' => $request->input('pref'),
                'city' => $request->input('city'),
                'addr' => $request->input('addr'),
                'building' => $request->input('building'),
                'tel' => $request->input('phone'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('shop_profiles', 'industry_id')) {
                $shopProfilePayload['industry_id'] = $industryIds[0] ?? null;
            }
            DB::table('shop_profiles')->insert($shopProfilePayload);
            $this->syncShopIndustries($shopId, $industryIds);

            if (Schema::hasTable('shop_posts') && $request->filled('word')) {
                DB::table('shop_posts')->insert([
                    'shop_id'    => $shopId,
                    'body'       => (string) $request->input('word'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('shop_managers')->insert([
                'id' => $managerId,
                'shop_id' => $shopId,
                'name' => $request->input('contact_name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'role' => 1,
                'status' => 1,
                'last_login_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 店舗登録と同時に、求人票（本入・体入・ヘルプを想定したベースレコード）を1件作成しておく。
            // 各勤務形態は regular_status / trial_status / help_status を 0 (非公開) で初期化し、
            // 詳細はマイページの求人編集から後で設定する。
            if (DB::getSchemaBuilder()->hasTable('shop_jobs')) {
                DB::table('shop_jobs')->insert([
                    'shop_id'        => $shopId,
                    'regular_status' => 0,
                    'trial_status'   => 0,
                    'help_status'    => 0,
                    'has_help'       => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // 店舗プロフィール画像（必須1枚）を保存
            if ($request->hasFile('shop_profile_image')) {
                $file = $request->file('shop_profile_image');
                $dir = public_path('uploads/shops/gallery');
                File::ensureDirectoryExists($dir);
                $name = $file->hashName();
                $file->move($dir, $name);
                $path = 'uploads/shops/gallery/' . $name;

                DB::table('shop_images')->insert([
                    'shop_id'    => $shopId,
                    'image_path' => $path,
                    'type'       => null,
                    'is_main'    => 1,
                    'main_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (DB::getSchemaBuilder()->hasColumn('shop_profiles', 'main_image_path')) {
                    DB::table('shop_profiles')
                        ->where('shop_id', $shopId)
                        ->update(['main_image_path' => $path]);
                }
            }

            return ShopManager::query()->findOrFail($managerId);
        });

        auth()->guard('member')->logout();
        auth()->guard('shop')->login($manager);
        $request->session()->regenerate();

        // 緯度経度・最寄駅は外部 API（国土地理院 / HeartRails）への HTTP 通信を伴うため、
        // レスポンスを返してから（=ユーザーがリダイレクト先に到達した後）バックグラウンドで実行する。
        // PHP-FPM 環境では fastcgi_finish_request により、ユーザーは API 完了を待たずに次画面へ遷移できる。
        if (Schema::hasColumn('shop_profiles', 'latitude')) {
            $shopId = $manager->shop_id;
            $fullAddr = $this->shopProfileLocationSyncService->buildFullAddressLineForGeocode($request);
            $service = $this->shopProfileLocationSyncService;
            app()->terminating(function () use ($service, $shopId, $fullAddr) {
                try {
                    $service->persistResolvedLocation($shopId, $fullAddr);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('shop registration geocode failed: ' . $e->getMessage(), [
                        'shop_id' => $shopId,
                    ]);
                }
            });
        }

        // メール認証リンクを送信
        \App\Http\Controllers\Auth\EmailVerificationController::dispatchVerifyMail(
            'shop', (string) $manager->id, (string) $manager->email
        );

        return redirect()
            ->route('shop.tutorial')
            ->with('registration_completed', 'shop')
            ->with('message', '店舗アカウントを登録しました。ご登録のメールアドレス宛に認証リンクをお送りしましたのでご確認ください。許可証の提出は「許可証提出ページ」からどうぞ。');
    }

    private function buildViewData(string $role): array
    {
        $isCast = $role === 'cast';

        return [
            'role' => $role,
            'pageId' => 'register',
            'title' => $isCast ? 'キャスト新規登録' : '店舗新規登録',
            'bodyClass' => $isCast ? 'page-auth-register page-auth-register-cast' : 'page-auth-register page-auth-register-shop',
            'eyebrow' => '',
            'heroTitle' => $isCast ? 'キャスト登録' : '店舗登録',
            'heroDescription' => '',
            'benefits' => [],
            'formAction' => $isCast ? route('cast.register.store') : route('shop.register.store'),
            'submitLabel' => '登録する',
            'alternateUrl' => $isCast ? route('shop.register') : route('cast.register'),
            'alternateLabel' => $isCast ? '店舗' : 'キャスト',
            'loginUrl' => $isCast ? route('cast.login') : route('shop.login'),
            'prefOptions' => CommonConsts::PREFS,
        ];
    }

    private function nextSequentialId(string $table, string $prefix): string
    {
        $lastId = DB::table($table)
            ->where('id', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id');

        $nextNumber = $lastId
            ? ((int) substr($lastId, 1)) + 1
            : 1;

        return $prefix . str_pad((string) $nextNumber, 8, '0', STR_PAD_LEFT);
    }

    /**
     * 新規登録時に提出された本人確認書類のバリデーション。
     * パターンA: 顔写真付き身分証 1枚 / パターンB: 顔写真なし身分証＋住所確認書類 の2枚を許容。
     */
    private function validateIdentityUpload(Request $request, bool $skipped): void
    {
        if ($skipped) {
            return;
        }
        if (!$request->hasFile('identity_front_file') && !$request->hasFile('identity_address_front_file')) {
            return; // 何も提出していなければスキップ扱い
        }

        $category = (string) $request->input('identity_category');
        $allowedTypes = CastIdentityDocument::allowedTypesFor($category);

        $messages = [];
        if (!in_array($category, [CastIdentityDocument::CATEGORY_PHOTO_ID, CastIdentityDocument::CATEGORY_NON_PHOTO_ID], true)) {
            $messages['identity_category'] = ['本人確認書類の種別を選択してください。'];
        } elseif (!$request->hasFile('identity_front_file')) {
            $messages['identity_front_file'] = ['本人確認書類の表面ファイルを添付してください。'];
        } elseif (!in_array((string) $request->input('identity_type'), $allowedTypes, true)) {
            $messages['identity_type'] = ['選択した書類種別がカテゴリと一致しません。'];
        }

        if ($category === CastIdentityDocument::CATEGORY_NON_PHOTO_ID) {
            $addressTypes = CastIdentityDocument::allowedTypesFor(CastIdentityDocument::CATEGORY_ADDRESS_PROOF);
            if (!$request->hasFile('identity_address_front_file')) {
                $messages['identity_address_front_file'] = ['住所確認書類の表面ファイルを添付してください。'];
            } elseif (!in_array((string) $request->input('identity_address_type'), $addressTypes, true)) {
                $messages['identity_address_type'] = ['住所確認書類の種別を選択してください。'];
            }
        }

        if (!empty($messages)) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }
    }

    /**
     * バリデーション通過後に本人確認書類を保存する。
     */
    private function saveIdentityDocuments(Request $request, string $castId): void
    {
        if (!$request->hasFile('identity_front_file')) {
            return;
        }
        $category = (string) $request->input('identity_category');

        $this->documentReviewService->uploadCastIdentityDocument(
            $castId,
            (string) $request->input('identity_type'),
            $request->file('identity_front_file'),
            $request->file('identity_back_file'),
            $request->input('identity_expired_at'),
            $category
        );

        if ($category === CastIdentityDocument::CATEGORY_NON_PHOTO_ID && $request->hasFile('identity_address_front_file')) {
            $this->documentReviewService->uploadCastIdentityDocument(
                $castId,
                (string) $request->input('identity_address_type'),
                $request->file('identity_address_front_file'),
                null,
                null,
                CastIdentityDocument::CATEGORY_ADDRESS_PROOF
            );
        }
    }

    private function syncCastTags(string $castId, string $tagType, array $tagIds): void
    {
        if (!DB::getSchemaBuilder()->hasTable('cast_tag_relations')) {
            return;
        }
        $normalizedType = rtrim($tagType, 's');
        $targetTypes = array_values(array_unique(array_filter([$tagType, $normalizedType])));
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds))));
        DB::table('cast_tag_relations')
            ->where('cast_id', $castId)
            ->whereIn('tag_type', $targetTypes)
            ->delete();
        foreach ($tagIds as $tagId) {
            DB::table('cast_tag_relations')->insert([
                'cast_id' => $castId,
                'tag_id' => $tagId,
                'tag_type' => $normalizedType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<int, int> $industryIds */
    private function syncCastIndustries(string $castId, array $industryIds): void
    {
        $industryIds = array_values(array_unique(array_filter(array_map('intval', $industryIds))));
        $now = now();
        DB::table('cast_search_preferences')->upsert(
            [[
                'cast_id'      => $castId,
                'industry_ids' => json_encode($industryIds),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]],
            ['cast_id'],
            ['industry_ids', 'updated_at']
        );
    }

    /** @param array<int, int> $industryIds */
    private function syncShopIndustries(string $shopId, array $industryIds): void
    {
        $industryIds = array_values(array_unique(array_filter(array_map('intval', $industryIds))));
        $industryId = $industryIds[0] ?? null;
        DB::table('shop_profiles')
            ->where('shop_id', $shopId)
            ->update(['industry_id' => $industryId ?: null]);
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
}
