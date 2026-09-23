<?php

namespace App\Http\Controllers\Casts;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Rules\KouzaMeig;
use App\Services\BillingManagementService;
use App\Services\DocumentReviewService;
use App\Services\GeocodingService;
use App\Services\UserLocationService;
use App\Support\ShopJobApplicationView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MypageController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private readonly BillingManagementService $billingManagementService,
        private readonly DocumentReviewService $documentReviewService
    )
    {
    }

    /**
     * キャスト用マイページ（プロフィール確認＝shop/castprofileview と同じ内容）
     */
    public function index()
    {
        $cast = $this->getCastFromDatabase($this->currentCastId());
        $castForProfile = $cast;
        $castForProfile['reviews'] = [];
        // ギャラリー用：id + url（削除APIで id を使用）
        $subImages = [];
        foreach (array_values($cast['images']) as $i => $img) {
            $subImages[] = [
                'id'  => is_array($img) ? ($img['id'] ?? $i + 1) : $i + 1,
                'url' => is_array($img) ? ($img['url'] ?? '') : $img,
            ];
        }
        $searchLocationSettings = app(UserLocationService::class)->loadProfileSettings();
        $availabilityDates = app(\App\Services\AvailabilityService::class)
            ->getDates(\App\Models\AvailabilityDate::OWNER_CAST, $this->currentCastId());

        return view('casts.mypage.index', [
            'pageId'       => 'mypage',
            'cast'         => $castForProfile,
            'isOwn'        => true,
            'subImages'    => $subImages,
            'searchLocationSettings' => $searchLocationSettings,
            'searchLocationDistanceOptions' => UserLocationService::DISTANCE_OPTIONS_KM,
            'availabilityDates' => $availabilityDates,
        ]);
    }

    /**
     * 位置情報フィルタ（住所/パスポート/現在地 + 半径）の保存
     */
    public function updateSearchLocation(Request $request, UserLocationService $userLocation, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:profile,passport,current'],
            'passport_address' => ['nullable', 'string', 'max:255'],
            'passport_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'passport_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'max_distance_km' => ['nullable', 'integer', 'in:0,1,3,5,10,20,30,50,100'],
        ]);

        $payload = [
            'mode' => $data['mode'],
            'max_distance_km' => $data['max_distance_km'] ?? 0,
        ];

        if ($data['mode'] === 'passport') {
            $address = trim((string) ($data['passport_address'] ?? ''));
            $lat = isset($data['passport_lat']) ? (float) $data['passport_lat'] : null;
            $lng = isset($data['passport_lng']) ? (float) $data['passport_lng'] : null;
            if (($lat === null || $lng === null) && $address !== '') {
                $coords = $geocoding->fromAddress($address);
                if (!$coords) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定の住所から緯度経度を取得できませんでした。別の表現で試してください。',
                    ], 422);
                }
                $lat = (float) $coords['latitude'];
                $lng = (float) $coords['longitude'];
            }
            if ($lat === null || $lng === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'パスポートモードでは住所または緯度経度が必要です。',
                ], 422);
            }
            $payload['passport_address'] = $address !== '' ? $address : null;
            $payload['passport_latitude'] = $lat;
            $payload['passport_longitude'] = $lng;
            $payload['passport_label'] = $address !== '' ? $address : '指定位置';
        }

        if ($data['mode'] === 'current') {
            $lat = isset($data['current_lat']) ? (float) $data['current_lat'] : null;
            $lng = isset($data['current_lng']) ? (float) $data['current_lng'] : null;
            if ($lat !== null && $lng !== null) {
                $userLocation->setManualLocation(UserLocationService::MODE_CURRENT, $lat, $lng, '現在地');
            }
        }

        // プロフィール住所モード：緯度経度が未取得なら住所からジオコーディングして latitude/longitude を埋める。
        if ($data['mode'] === 'profile') {
            $current = $userLocation->loadProfileSettings();
            if ($current && empty($current['profile_location']) && !empty($current['has_address'])) {
                $userLocation->geocodeAndSaveProfileLocation($geocoding);
            }
        }

        $userLocation->saveSearchSettings($payload);

        return response()->json([
            'success' => true,
            'settings' => $userLocation->loadProfileSettings(),
        ]);
    }

    /**
     * Candidate-date availability declaration (up to 5 dates, 30 days ahead).
     * POST /cast/mypage/availability with dates[] = ['Y-m-d', ...]
     */
    public function declareAvailability(Request $request)
    {
        $validated = $request->validate([
            'dates' => ['required', 'array', 'max:' . \App\Services\AvailabilityService::MAX_DATES],
            'dates.*' => ['date_format:Y-m-d'],
        ]);

        $service = app(\App\Services\AvailabilityService::class);
        $result = $service->setDates(
            \App\Models\AvailabilityDate::OWNER_CAST,
            $this->currentCastId(),
            $validated['dates']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'dates' => $service->getDates(\App\Models\AvailabilityDate::OWNER_CAST, $this->currentCastId()),
        ]);
    }

    /**
     * Clear all declared candidate dates.
     */
    public function clearAvailability()
    {
        app(\App\Services\AvailabilityService::class)
            ->clearDates(\App\Models\AvailabilityDate::OWNER_CAST, $this->currentCastId());

        return response()->json(['success' => true]);
    }

    /**
     * 採用・入金管理（採用状況と請求・入金を1画面に統合）
     */
    public function employment()
    {
        $castId = $this->currentCastId();

        $employmentQuery = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shops', 'shop_jobs.shop_id', '=', 'shops.id')
            ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->where('shop_job_applications.cast_id', $castId)
            ->orderByDesc('shop_job_applications.updated_at')
            ->select(
                'shop_job_applications.id as application_id',
                'shop_job_applications.status',
                'shop_job_applications.result_date',
                'shops.id as shop_id',
                'shop_profiles.shop_name'
            );

        foreach ([
            'hired_regular_hourly_wage',
            'applied_bonus_reward',
            'applied_bonus_remarks',
            'applied_bonus_condition',
            'applied_norma_day',
            'applied_norma_hours',
            'applied_regular_hourly_wage',
        ] as $col) {
            if (Schema::hasColumn('shop_job_applications', $col)) {
                $employmentQuery->addSelect('shop_job_applications.' . $col);
            }
        }

        $employments = $employmentQuery
            ->get()
            ->map(function ($row) {
                $status = $this->mapApplicationStatus((int) $row->status);
                $bonusLines = [];
                $norma = ShopJobApplicationView::bonusNormaSummaryAtApplication($row);
                if ($norma !== '') {
                    $bonusLines[] = 'ボーナス条件（応募時）: ' . $norma;
                }
                $br = ShopJobApplicationView::bonusRewardAtApplication($row);
                if ($br !== null && $br > 0) {
                    $bonusLines[] = 'ボーナス金額（応募時）: ¥' . number_format($br);
                }
                $remarks = property_exists($row, 'applied_bonus_remarks') && $row->applied_bonus_remarks !== null
                    ? trim((string) $row->applied_bonus_remarks)
                    : '';
                if ($remarks !== '') {
                    $bonusLines[] = '補足: ' . $remarks;
                }
                $cond = ShopJobApplicationView::bonusConditionAtApplication($row);
                if ($cond !== '') {
                    $bonusLines[] = '達成条件: ' . $cond;
                }
                $hiredWage = ShopJobApplicationView::wageAtHire($row);

                return [
                    'application_id' => (int) $row->application_id,
                    'status_code' => (int) $row->status,
                    'shop_id' => (string) $row->shop_id,
                    'shop_name' => $row->shop_name ?: $row->shop_id,
                    'status_label' => $status['label'],
                    'status_class' => $status['class'],
                    'applied_at' => !empty($row->result_date)
                        ? Carbon::parse($row->result_date)->format('Y-m-d')
                        : null,
                    'link' => route('cast.talk.room', ['id' => $row->shop_id, 'talk_topic' => 'other', 'initiate' => 1]),
                    'bonus_at_apply_lines' => $bonusLines,
                    'hired_hourly_wage_display' => $hiredWage,
                ];
            })
            ->all();

        $paymentData = $this->billingManagementService->getCastPaymentPageData($castId);

        // 採用→入金 を一気通貫で見せるため、application と deposit を結合した cases を組み立てる
        $allDeposits = collect($this->billingManagementService->getAllDeposits())
            ->where('cast_id', $castId)
            ->keyBy('shop_job_application_id');

        $hiredCases = [];
        $ongoingApplications = [];
        $rejectedApplications = [];
        foreach ($employments as $emp) {
            $code = (int) ($emp['status_code'] ?? 0);
            // ステータスコード:
            //   1=チャット中 / 2=面談調整 / 3=面談確定 / 4=採用 / 5=不採用 / 6=本採用 / 7=本入店不採用
            if (in_array($code, [4, 6], true)) {
                $deposit = $allDeposits->get($emp['application_id']);
                $hiredCases[] = $this->buildCastEmploymentCase($emp, $deposit ? (array) $deposit : null);
            } elseif (in_array($code, [5, 7], true)) {
                $rejectedApplications[] = $emp;
            } else {
                $ongoingApplications[] = $emp;
            }
        }

        // 進行中（未完了）の案件が上、完了済みは下に
        usort($hiredCases, function (array $a, array $b) {
            $aDone = (int) ($a['is_completed'] ?? 0);
            $bDone = (int) ($b['is_completed'] ?? 0);
            if ($aDone !== $bDone) {
                return $aDone <=> $bDone; // 未完了→完了
            }
            return ($b['progress_index'] ?? 0) <=> ($a['progress_index'] ?? 0);
        });

        // 獲得ボーナス金合計（mypage から移設したバッジ用）
        $bonusTotal = 0;
        if (Schema::hasTable('application_deposits') && Schema::hasTable('shop_job_applications')) {
            $bonusTotal = (int) DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->where('shop_job_applications.cast_id', $castId)
                ->whereNotNull('application_deposits.bonus_amount')
                ->sum('application_deposits.bonus_amount');
        }

        return view('casts.mypage.employment', [
            'pageId' => 'mypage',
            'employments' => $employments, // 旧データ：互換用に保持
            'hiredCases' => $hiredCases,
            'ongoingApplications' => $ongoingApplications,
            'rejectedApplications' => $rejectedApplications,
            'payments' => $paymentData['payments'],
            'depositFlow' => $paymentData['flow'],
            'castBank' => $paymentData['bank'],
            'currentDeposit' => $paymentData['current'],
            'canRequestDeposit' => $paymentData['can_request'],
            'requestDisabledReason' => $paymentData['request_disabled_reason'],
            'requestTarget' => $paymentData['request_target'],
            'bonusTotal' => $bonusTotal,
        ]);
    }

    /**
     * 採用済み案件 1 件分の「採用→入金」一気通貫ケースを組み立てる。
     *
     * @param  array        $emp     mapApplicationStatus 済みの employment 配列
     * @param  array|null   $deposit getAllDeposits の deposit 配列（null = 入金フロー未開始）
     */
    private function buildCastEmploymentCase(array $emp, ?array $deposit): array
    {
        // パイプライン定義：採用 → ボーナス申請 → 店舗承認 → 請求書発行 → 店舗入金 → 振込実行 → 受領完了
        // 進捗インデックス（数字が大きいほど先へ進んでいる）
        //   0: 採用された（deposit 未作成）
        //   1: ボーナス申請（deposit status=1）
        //   2: 店舗承認（deposit status=2）
        //   3: 請求書発行（deposit status=3）
        //   4: 店舗入金（deposit status=4 or 5）
        //   5: 振込実行（deposit status=6）
        //   6: 受領完了（deposit status=7）
        $depositStatus = $deposit['status_code'] ?? null;
        $progressIndex = match ((int) ($depositStatus ?? -1)) {
            -1 => 0,            // deposit 未作成
            0, 1 => 1,
            2 => 2,
            3 => 3,
            4, 5 => 4,
            6 => 5,
            default => 6,       // 7 以上は完了
        };
        $isCompleted = $progressIndex >= 6;

        $stages = [
            ['key' => 'hired',         'label' => '採用確定',       'desc' => '店舗が採用を決定'],
            ['key' => 'cast_request',  'label' => 'ボーナス申請',   'desc' => 'キャストから申請'],
            ['key' => 'shop_approve',  'label' => '店舗承認',       'desc' => '店舗が承認'],
            ['key' => 'invoice_issue', 'label' => '請求書発行',     'desc' => '運営が発行'],
            ['key' => 'shop_pay',      'label' => '店舗入金',       'desc' => '店舗が支払い'],
            ['key' => 'cast_transfer', 'label' => '振込実行',       'desc' => '運営から振込'],
            ['key' => 'received',      'label' => '受領完了',       'desc' => '受領確認済み'],
        ];

        // 「次のアクション」算出
        // - 0: 採用直後 → ボーナス申請（cast）
        // - 5: 振込実行済み → 受領確認（cast）
        // - それ以外：相手方の対応待ち
        $actionableState = null;
        $actionableLabel = null;
        if ($progressIndex === 0) {
            $actionableState = 'request';
            $actionableLabel = 'ボーナス申請を行う';
        } elseif ($progressIndex === 5) {
            $actionableState = 'confirm';
            $actionableLabel = '入金を確認しました';
        }

        $waitingOnLabel = null;
        if ($actionableState === null) {
            $waitingOnLabel = match ($progressIndex) {
                1 => '店舗の承認待ち',
                2 => '運営の請求書発行待ち',
                3 => '店舗の入金待ち',
                4 => '運営の振込実行待ち',
                6 => null,
                default => null,
            };
        }

        $primaryStatus = match (true) {
            $isCompleted              => ['label' => '振込完了',       'tone' => 'done'],
            $progressIndex === 5      => ['label' => '受領確認待ち',   'tone' => 'action'],
            $progressIndex >= 1       => ['label' => '入金処理中',     'tone' => 'progress'],
            default                   => ['label' => '採用済（未申請）', 'tone' => 'action'],
        };

        return [
            'application_id' => (int) ($emp['application_id'] ?? 0),
            'shop_name'      => $emp['shop_name'] ?? '',
            'shop_id'        => $deposit['shop_id'] ?? ($emp['shop_id'] ?? null),
            'talk_link'      => $emp['link'] ?? null,
            'hired_at'       => $emp['applied_at'] ?? null,
            'hired_hourly_wage_display' => $emp['hired_hourly_wage_display'] ?? null,
            'bonus_at_apply_lines' => $emp['bonus_at_apply_lines'] ?? [],

            // パイプライン
            'stages'        => $stages,
            'progress_index' => $progressIndex,
            'is_completed'  => $isCompleted,

            // 状態表示
            'status_label'  => $primaryStatus['label'],
            'status_tone'   => $primaryStatus['tone'],
            'waiting_on'    => $waitingOnLabel,
            'actionable'    => $actionableState,
            'actionable_label' => $actionableLabel,

            // deposit のスナップショット
            'deposit'       => $deposit ? [
                'id'                  => $deposit['id'] ?? null,
                'status_label'        => $deposit['status_label'] ?? '',
                'invoice_number'      => $deposit['invoice_number'] ?? null,
                'invoice_issued_at'   => $deposit['invoice_issued_at'] ?? null,
                'invoice_due_date'    => $deposit['invoice_due_date'] ?? null,
                'shop_payment_confirmed_at' => $deposit['shop_payment_confirmed_at'] ?? null,
                'cast_transferred_at' => $deposit['cast_transferred_at'] ?? null,
                'completed_at'        => $deposit['completed_at'] ?? null,
                'cast_transfer_amount' => $deposit['cast_transfer_amount'] ?? null,
                'bonus_amount'        => $deposit['bonus_amount'] ?? null,
                'updated_at_label'    => $deposit['updated_at_label'] ?? null,
            ] : null,
        ];
    }

    // Identity verification flow (view / upload / submit / remind) was extracted
    // to App\Http\Controllers\Casts\IdentityController on 2026-08-02.
    // Route bindings updated to the new controller in routes/web.php.

    /**
     * キャスト画像アップロード（DB: cast_images に保存）
     */
    public function uploadImage(Request $request)
    {
        // 8MB まで許容（スマホの高解像度撮影を許容）。クライアント側の image-editor.js が
        // 1MB 前後に再圧縮するため通常はこの上限には達しないが、フォールバック経路と
        // 未編集アップロードのために余裕を持たせる。
        // dimensions: 極端に巨大な画像はメモリ圧迫の温床なので上限を切る。
        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:8192',
                'dimensions:max_width=6000,max_height=6000',
            ],
        ], [
            'image.max'        => '画像は 8MB 以下のファイルをご利用ください。',
            'image.mimes'      => 'JPEG/PNG/GIF/WebP 形式のみアップロードできます。iPhone の HEIC 形式は端末側で JPEG に変換してからお試しください。',
            'image.dimensions' => '画像サイズが大きすぎます。6000×6000 ピクセル以下のファイルをご利用ください。',
        ]);

        try {
            $dir = public_path('uploads/casts/gallery');
            File::ensureDirectoryExists($dir);
            $file = $request->file('image');
            $name = $file->hashName();
            $file->move($dir, $name);
            $path = 'uploads/casts/gallery/' . $name;
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => '画像の保存に失敗しました。'], 500);
        }

        $slotIndex = (int) $request->input('slot_index', -1);

        $castId = $this->currentCastId();

        $maxOrder = DB::table('cast_images')
            ->where('cast_id', $castId)
            ->max('main_order');
        $mainOrder = $maxOrder !== null ? $maxOrder + 1 : 0;

        // is_main は常に 0 で投入し、後段の syncCastImageOrder で「先頭1枚=1、他=0」に揃える。
        // UNIQUE(cast_id, CASE WHEN is_main=1 THEN 1 ELSE NULL END) の競合を避けるため。
        $id = DB::table('cast_images')->insertGetId([
            'cast_id'       => $castId,
            'image_path'    => $path,
            'status'        => 0,
            'is_main'       => 0,
            'main_order'    => $mainOrder,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $orderedIds = DB::table('cast_images')
            ->where('cast_id', $castId)
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

        // 並び順の同期は副次処理。万一 sync で例外が出ても cast_images 自体は登録済みなので、
        // 成功として返してログだけ残す（リロードすると正しく表示される事態を避ける）。
        try {
            $this->syncCastImageOrder($castId, $orderedIds);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('syncCastImageOrder failed after upload: ' . $e->getMessage(), [
                'cast_id' => $castId,
                'image_id' => $id,
            ]);
        }

        return response()->json(['success' => true, 'path' => asset($path), 'id' => $id]);
    }

    /**
     * キャスト画像削除（DB: cast_images から削除）
     */
    public function deleteImage(Request $request, $id)
    {
        $castId = $this->currentCastId();

        $currentCount = (int) DB::table('cast_images')
            ->where('cast_id', $castId)
            ->count();

        $row = DB::table('cast_images')
            ->where('id', $id)
            ->where('cast_id', $castId)
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => '画像が見つかりません'], 404);
        }

        if ($currentCount <= 1) {
            return response()->json(['success' => false, 'message' => 'プロフィール画像は1枚以上必要です。最低1枚は残してください。'], 422);
        }

        $fullPath = str_starts_with($row->image_path ?? '', 'uploads/')
            ? public_path($row->image_path)
            : storage_path('app/' . $row->image_path);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
        DB::table('cast_images')->where('id', $id)->delete();

        $orderedIds = DB::table('cast_images')
            ->where('cast_id', $castId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($imageId) => (int) $imageId)
            ->all();

        $this->syncCastImageOrder($castId, $orderedIds);

        return response()->json(['success' => true, 'message' => '画像を削除しました']);
    }

    public function updateImageOrder(Request $request)
    {
        $imageOrder = $request->input('images');

        if (!is_array($imageOrder)) {
            return response()->json(['success' => false, 'message' => 'データが不正です'], 400);
        }

        $orderedIds = array_values(array_unique(array_map('intval', $imageOrder)));
        $this->syncCastImageOrder($this->currentCastId(), $orderedIds);

        return response()->json(['success' => true, 'message' => '並び順を保存しました']);
    }

    /**
     * キャスト側：入金申請（ボーナス条件達成後に押す想定）
     * application_id があればその案件で申請（モーダル「完了」用）
     */
    public function requestDeposit(\Illuminate\Http\Request $request)
    {
        $applicationId = $request->filled('application_id') ? (int) $request->input('application_id') : null;
        $result = $this->billingManagementService->requestDepositForCast(
            $this->currentCastId(),
            $request->all(),
            $applicationId
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        $redirect = redirect()
            ->route('cast.mypage.management')
            ->with($result['success'] ? 'status' : 'error', $result['message']);

        return $result['success'] ? $redirect : $redirect->withInput();
    }

    /**
     * レビューのみ投稿（モーダル用）。成功時はボーナス条件達成確認用の request_target を返す
     */
    public function postReview(Request $request)
    {
        $request->validate(['application_id' => 'required|integer']);
        $applicationId = (int) $request->input('application_id');
        $result = $this->billingManagementService->submitReviewOnly(
            $this->currentCastId(),
            $applicationId,
            $request->all()
        );
        return response()->json($result);
    }

    /**
     * 指定案件のボーナス条件達成確認用データ（採用時点の焼き付け）を返す
     */
    public function getDepositRequestTarget(Request $request)
    {
        $applicationId = $request->input('application_id');
        $shopId = $request->input('shop_id');
        $castId = $this->currentCastId();

        if ($applicationId !== null && $applicationId !== '') {
            $target = $this->billingManagementService->getRequestTargetByApplicationId($castId, (int) $applicationId);
        } elseif ($shopId !== null && $shopId !== '') {
            $target = $this->billingManagementService->getRequestTargetByCastAndShopId($castId, (string) $shopId);
        } else {
            $target = null;
        }

        if ($target === null) {
            return response()->json(['success' => false, 'message' => '対象の採用案件が見つかりません。'], 404);
        }
        return response()->json(['success' => true, 'request_target' => $target]);
    }

    /**
     * キャスト側：入金完了確認（最終確認）
     */
    public function confirmDeposit(\Illuminate\Http\Request $request)
    {
        $result = $this->billingManagementService->confirmCastReceipt($this->currentCastId());

        return redirect()
            ->route('cast.mypage.management')
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * 入金フローの現在ステータス（3者分）を組み立てる
     */
    private function buildDepositFlowState(int $step): array
    {
        $map = [
            0 => ['cast' => '未申請',       'shop' => '未稼働',           'admin' => '未稼働'],
            1 => ['cast' => '申請中',       'shop' => '未稼働',           'admin' => '未稼働'],
            2 => ['cast' => '店舗審査中',   'shop' => '店舗審査中',       'admin' => '店舗審査待ち'],
            3 => ['cast' => 'お振込準備中', 'shop' => 'お支払い準備中',   'admin' => '店舗入金依頼中'],
            4 => ['cast' => 'お振込準備中', 'shop' => 'お支払い済み',     'admin' => '店舗入金確認中'],
            5 => ['cast' => 'お振込手続き中', 'shop' => 'お支払い完了', 'admin' => 'キャスト振込済'],
            6 => ['cast' => '完了',         'shop' => '完了',             'admin' => '完了'],
        ];

        return $map[$step] ?? $map[0];
    }

    /**
     * application_deposits.status から3者分のステータスを構成
     */
    private function buildDepositFlowStateFromDb($deposit): array
    {
        if (!$deposit) {
            return $this->buildDepositFlowState(0);
        }

        $status = (int)$deposit->status;

        // 4.Transaction の表をベースに簡易マッピング
        return match ($status) {
            1 => ['cast' => '申請中', 'shop' => '承認待ち', 'admin' => '提出待ち'],
            2 => ['cast' => '申請中', 'shop' => '店舗確認中', 'admin' => '請求待ち'],
            3 => ['cast' => 'お振込準備中', 'shop' => 'お支払い待ち', 'admin' => '店舗へ請求中'],
            4 => ['cast' => 'お振込準備中', 'shop' => '入金報告済', 'admin' => '店舗入金確認中'],
            5 => ['cast' => 'お振込準備中', 'shop' => 'お支払い完了', 'admin' => '店舗入金確認済'],
            6 => ['cast' => 'お振込手続き中', 'shop' => 'お支払い完了', 'admin' => 'キャスト振込済'],
            7 => ['cast' => '完了', 'shop' => '完了', 'admin' => '完了'],
            default => $this->buildDepositFlowState(0),
        };
    }

    /**
     * キャスト側の振込先口座情報登録
     *
     * 入力された口座情報は BillingManagementService::saveCastBankAccount() を経由して
     * bank_accounts テーブル（holder_type = cast）に永続化される。
     * 採用ボーナス振込時の振込先として参照される。
     */
    public function updateBank(\Illuminate\Http\Request $request)
    {
        $request->merge(
            $this->billingManagementService->normalizeBankAccountData($request->all())
        );

        $request->validate([
            'bank_code'      => ['required', 'regex:/^\d{4}$/'],
            'bank_name'      => 'required|string|max:100',
            'branch_code'    => ['required', 'regex:/^\d{3}$/'],
            'branch_name'    => 'required|string|max:100',
            'account_type'   => 'required|in:ordinary,current',
            'account_number' => ['required', 'regex:/^\d{7,8}$/'],
            'account_name'   => ['required', 'string', 'max:100', new KouzaMeig()],
        ], [
            'bank_code.required' => '金融機関を候補から選択してください。',
            'bank_code.regex' => '金融機関コードが不正です。',
            'branch_code.required' => '支店を候補から選択してください。',
            'branch_code.regex' => '支店コードが不正です。',
            'account_number.required' => '口座番号を入力してください。',
            'account_number.regex' => '口座番号は7桁または8桁の数字で入力してください。',
            'account_name.required' => '口座名義（カナ）を入力してください。',
        ]);

        $this->billingManagementService->saveCastBankAccount($this->currentCastId(), $request->only([
            'bank_code',
            'bank_name',
            'bank_name_kana',
            'branch_code',
            'branch_name',
            'branch_name_kana',
            'account_type',
            'account_number',
            'account_name',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'キャスト口座情報を保存しました。',
        ]);
    }

    /**
     * レビュー一覧（お店の mypage/reviews と同様）
     * ?shop_id=XXX があると、そのお店に投稿したレビューだけにフィルタする。
     */
    public function reviews(Request $request)
    {
        $cast = $this->getCastFromDatabase($this->currentCastId());
        $filterShopId = $request->query('shop_id');
        $filterShopName = null;

        $reviews = $cast['reviews'];
        if (!empty($filterShopId)) {
            $reviews = array_values(array_filter($reviews, function ($r) use ($filterShopId) {
                return (string) ($r['shop_id'] ?? '') === (string) $filterShopId;
            }));
            $shopRow = DB::table('shops')
                ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
                ->where('shops.id', $filterShopId)
                ->select('shop_profiles.shop_name')
                ->first();
            $filterShopName = $shopRow->shop_name ?? null;
        }

        $reviewCount = count($reviews);
        $castData = [
            'review_avg'   => $reviewCount > 0
                ? round(array_sum(array_column($reviews, 'score')) / $reviewCount, 1)
                : 0,
            'review_count' => $reviewCount,
        ];
        return view('casts.mypage.reviews', [
            'pageId'         => 'mypage',
            'castData'       => $castData,
            'reviews'        => $reviews,
            'filterShopId'   => $filterShopId,
            'filterShopName' => $filterShopName,
        ]);
    }

    /**
     * ひとことを cast_posts に保存（店舗の updateWord と同様）
     */
    public function updateWord(Request $request)
    {
        $request->validate([
            'word' => 'nullable|string|max:50',
        ]);

        $castId = $this->currentCastId();
        $word = $request->input('word', '');
        $word = is_string($word) ? trim($word) : '';

        if (!Schema::hasTable('cast_posts')) {
            return response()->json(['success' => false, 'message' => 'cast_posts テーブルが存在しません。'], 500);
        }

        if (!Schema::hasColumn('cast_posts', 'body')) {
            return response()->json(['success' => false, 'message' => 'cast_posts.body がありません。php artisan migrate を実行してください。'], 500);
        }

        $now = Carbon::now();
        $exists = DB::table('cast_posts')->where('cast_id', $castId)->exists();
        if ($exists) {
            DB::table('cast_posts')->where('cast_id', $castId)->update([
                'body' => $word,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('cast_posts')->insert([
                'cast_id' => $castId,
                'body' => $word,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json([
            'success' => true,
            'appeal_updated_at' => $now->format('Y/m/d H:i'),
        ]);
    }

    /**
     * キャスト・プロフィール・レビューをDBから取得し、画面用配列に整形
     */
    private function getCastFromDatabase(string $castId): array
    {
        $castRow = DB::table('casts')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->where('casts.id', $castId)
            ->select(
                'casts.id',
                'casts.email',
                'casts.status',
                'casts.identity_status',
                'casts.last_login_at',
                'cast_profiles.nickname',
                'cast_profiles.name',
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
                    : DB::raw('NULL as personality_type'),
                'cast_profiles.updated_at as profile_updated_at'
            )
            ->first();

        if (!$castRow) {
            // プロフィール未登録キャスト向けの空の既定値（プレースホルダ）
            return $this->buildEmptyCast();
        }

        $birthday = $castRow->birthday ? Carbon::parse($castRow->birthday) : null;
        $age = $birthday ? $birthday->age : null;
        $nightWorkExp = ((int) ($castRow->exp ?? 0) === 1 ? 'yes' : 'none');
        $looksTags = $this->getCastTagNamesByType($castId, 'looks');
        $personalityTags = $this->getCastTagNamesByType($castId, 'personality');
        $industryNames = $this->resolveDesiredJobByIndustries($castId, $castRow->industry_id ?? null);
        $looksSummary = $looksTags !== [] ? implode(' / ', $looksTags) : '';
        $personalitySummary = $personalityTags !== [] ? implode(' / ', $personalityTags) : '';
        // 自分のプロフィールが閲覧された回数
        $viewCount = app(\App\Services\ProfileViewService::class)
            ->countFor(\App\Models\ProfileView::TYPE_CAST, $castId);

        $matchCount = 0;
        if (Schema::hasTable('shop_job_applications')) {
            $matchCount = (int) DB::table('shop_job_applications')
                ->where('cast_id', $castId)
                ->where('status', 4)
                ->count();
        }

        $bonusTotal = 0;
        if (Schema::hasTable('application_deposits') && Schema::hasTable('shop_job_applications')) {
            $bonusTotal = (int) DB::table('application_deposits')
                ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
                ->where('shop_job_applications.cast_id', $castId)
                ->whereNotNull('application_deposits.bonus_amount')
                ->sum('application_deposits.bonus_amount');
        }

        // 画像: cast_images を id + url で取得（is_main を先に）
        $images = [];
        $castImages = DB::table('cast_images')
            ->where('cast_id', $castId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->get();
        foreach ($castImages as $img) {
            $images[] = [
                'id'  => $img->id,
                'url' => $this->assetPathForStored($img->image_path),
            ];
        }
        if (empty($images)) {
            $images[] = ['id' => null, 'url' => asset('assets/images/common/no-image.png')];
        }

        // レビュー（レビュー本文＋平均スコア＋投稿先 shop_id）
        $reviewRows = DB::table('reviews')
            ->leftJoin('review_details', 'reviews.id', '=', 'review_details.review_id')
            ->where('reviews.cast_id', $castId)
            ->groupBy('reviews.id', 'reviews.contents', 'reviews.shop_id', 'reviews.created_at')
            ->select(
                'reviews.id',
                'reviews.contents',
                'reviews.shop_id',
                'reviews.created_at',
                DB::raw('AVG(review_details.score) as avg_score')
            )
            ->get();

        $reviews = [];
        foreach ($reviewRows as $r) {
            $reviews[] = [
                'score'   => $r->avg_score !== null ? (float) $r->avg_score : 0.0,
                'text'    => $r->contents ?? '',
                'shop_id' => $r->shop_id ?? null,
            ];
        }

        // ひとこと：cast_posts テーブルから取得（店舗の catch と同様）
        $word = '';
        $appealUpdatedAt = null;
        if (Schema::hasTable('cast_posts') && Schema::hasColumn('cast_posts', 'body')) {
            $post = DB::table('cast_posts')->where('cast_id', $castId)->first();
            if ($post && isset($post->body) && $post->body !== null && trim((string) $post->body) !== '') {
                $word = trim((string) $post->body);
                $appealUpdatedAt = !empty($post->updated_at)
                    ? Carbon::parse($post->updated_at)->format('Y/m/d H:i')
                    : null;
            }
        }

        return [
            'id'               => $castRow->id,
            'nickname'         => $castRow->nickname ?? '',
            'name'             => $castRow->name ?? '',
            'age'              => $age,
            'birth_year'       => $birthday ? (string) $birthday->year : null,
            'birth_month'      => $birthday ? (string) $birthday->month : null,
            'birth_day'        => $birthday ? (string) $birthday->day : null,
            'images'           => $images,
            'img'              => $images[0]['url'] ?? null,
            'is_applied'       => true,
            'is_kept'          => true,
            'view_cnt'         => $viewCount,
            'match_cnt'        => $matchCount,
            'bonus_total'      => $bonusTotal,
            'zip'              => $castRow->zip ?? '',
            'pref'             => $castRow->pref ?? '',
            'city'             => $castRow->city ?? '',
            'addr1'            => trim(implode(' ', array_filter([(string) ($castRow->addr ?? ''), (string) ($castRow->building ?? '')]))),
            'height'           => $castRow->height,
            'weight'           => $castRow->weight,
            'bust'             => $castRow->bust,
            'waist'            => $castRow->waist,
            'hip'              => $castRow->hip,
            'word'             => $word,
            'pr'               => $castRow->pr ?? '',
            'intro'            => $castRow->pr ?? '',
            'appeal_updated_at'=> $appealUpdatedAt,
            'industry_names'   => $industryNames,
            'desired_job'      => $industryNames,
            'my_field'         => $looksSummary,
            'my_inner_skills'  => $personalitySummary,
            'personality_type' => $this->resolvePersonalityType($castRow->personality_type ?? null),
            'looks_tags'       => $looksTags,
            'personality_tags' => $personalityTags,
            'memo_data'        => [
                'industry_names' => $industryNames,
                'desired_job' => $industryNames,
                'my_field' => $looksSummary,
                'my_inner_skills' => $personalitySummary,
                'exp' => $nightWorkExp,
                'night_work_exp' => $nightWorkExp,
                'profession' => $castRow->profession ?? '',
                'current_job' => $castRow->profession ?? '',
            ],
            'profession'       => $castRow->profession ?? '',
            'current_job'      => $castRow->profession ?? '',
            'exp'              => $nightWorkExp,
            'night_work_exp'   => $nightWorkExp,
            'night_work_label' => $nightWorkExp === 'yes' ? '有り' : '無し',
            'reviews'          => $reviews,
        ];
    }

    /** パスが / 始まりなら public 相対にし asset() で URL 化 */
    private function assetPath(?string $path): string
    {
        if (empty($path)) {
            return asset('assets/images/common/no-image.png');
        }
        $path = ltrim($path, '/');
        return asset($path);
    }

    // assetPathForStored() is now provided by ResolvesActor trait.
    // The old body ended with `return $this->assetPath($path)`, which is equivalent to
    // the trait's `asset(ltrim($path, '/'))` fallback branch.

    private function buildEmptyCast(): array
    {
        $images = [['id' => null, 'url' => asset('assets/images/common/no-image.png')]];
        return [
            'id'               => null,
            'nickname'         => '',
            'name'             => '',
            'age'              => null,
            'birth_year'       => null,
            'birth_month'      => null,
            'birth_day'        => null,
            'images'           => $images,
            'img'              => $images[0]['url'] ?? null,
            'is_applied'       => false,
            'is_kept'          => false,
            'view_cnt'         => 0,
            'match_cnt'        => 0,
            'bonus_total'      => 0,
            'zip'              => '',
            'pref'             => '',
            'city'             => '',
            'addr1'            => '',
            'height'           => null,
            'weight'           => null,
            'bust'             => null,
            'waist'            => null,
            'hip'              => null,
            'word'             => '',
            'pr'               => '',
            'intro'            => '',
            'appeal_updated_at'=> null,
            'industry_names'   => '',
            'desired_job'      => '',
            'my_field'         => '',
            'my_inner_skills'  => '',
            'personality_type' => '',
            'looks_tags'       => [],
            'personality_tags' => [],
            'memo_data'        => [
                'industry_names' => '',
                'desired_job' => '',
                'my_field' => '',
                'my_inner_skills' => '',
                'exp' => '',
                'night_work_exp' => '',
                'profession' => '',
                'current_job' => '',
            ],
            'profession'       => '',
            'current_job'      => '',
            'exp'              => '',
            'night_work_exp'   => '',
            'night_work_label' => '',
            'reviews'          => [],
        ];
    }

    // currentCastId() is now provided by ResolvesActor trait (returns ?string).
    // All callers here run behind member.auth middleware, so it is effectively non-null.

    private function syncCastImageOrder(string $castId, array $orderedIds): void
    {
        $existingImages = DB::table('cast_images')
            ->where('cast_id', $castId)
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

        DB::transaction(function () use ($castId, $orderedIds, $existingImages) {
            DB::table('cast_images')
                ->where('cast_id', $castId)
                ->update([
                    'is_main' => 0,
                    'updated_at' => now(),
                ]);

            foreach ($orderedIds as $index => $imageId) {
                DB::table('cast_images')
                    ->where('cast_id', $castId)
                    ->where('id', $imageId)
                    ->update([
                        'main_order' => $index,
                        'is_main' => $index === 0 ? 1 : 0,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('cast_profiles')
                ->where('cast_id', $castId)
                ->update([
                    'updated_at' => now(),
                ]);
        });
    }

    private function resolvePersonalityType(?string $columnType): string
    {
        $type = $columnType ?? '';

        return is_string($type) && preg_match('/^[LF][CP][IO][HR]$/', $type) ? $type : '';
    }

    /**
     * cast_tag_relations からタグ名を取得。なければ memo 内 id から補完。
     *
     * @param array<int, mixed> $memoTagIds
     * @return array<int, string>
     */
    private function getCastTagNamesByType(string $castId, string $tagType, array $memoTagIds = []): array
    {
        $tagTable = $this->resolveCastTagMasterTable();
        if ($tagTable === null) {
            return [];
        }

        $names = [];
        if (Schema::hasTable('cast_tag_relations')) {
            $tagTypes = array_values(array_unique(array_filter([$tagType, rtrim($tagType, 's')])));
            $names = DB::table('cast_tag_relations as r')
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

        if ($names !== []) {
            return $names;
        }

        $ids = collect($memoTagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        if ($ids === []) {
            return [];
        }

        return DB::table($tagTable)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
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

    private function shiftHopeLabel($shift): string
    {
        return match ((int) ($shift ?? 0)) {
            1 => '週1回出勤',
            2 => '週2回出勤',
            3 => '週3回以上',
            default => '',
        };
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

    private function mapApplicationStatus(int $status): array
    {
        return match ($status) {
            2 => ['label' => '面談日調整中', 'class' => 'status-pending'],
            3 => ['label' => '面談日決定', 'class' => 'status-pending'],
            4 => ['label' => '採用', 'class' => 'status-paid'],
            5 => ['label' => '不採用', 'class' => 'status-ng'],
            6 => ['label' => '本採用', 'class' => 'status-paid'],
            7 => ['label' => '体験後不採用', 'class' => 'status-ng'],
            default => ['label' => 'やり取り中', 'class' => 'status-pending'],
        };
    }
}
