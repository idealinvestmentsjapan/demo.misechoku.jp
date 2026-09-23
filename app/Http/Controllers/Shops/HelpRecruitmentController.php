<?php

namespace App\Http\Controllers\Shops;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityDate;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Help recruitment (dated short-term help) unified management for shops.
 *
 * Consolidates three previously-scattered concerns onto one page:
 *   1. Recruitment dates (moved from Shop MyPage availability card)
 *   2. Help hourly wage min/max + published_help toggle (moved from recruit edit)
 *   3. Simple applicant list (talks tagged talk_job_kind = 'help')
 */
class HelpRecruitmentController extends Controller
{
    use ResolvesActor;

    public function index()
    {
        $shopId = (string) $this->currentShopId();

        $availability = app(AvailabilityService::class);
        $dates = $availability->getDates(AvailabilityDate::OWNER_SHOP, $shopId);

        $wage = $this->loadHelpWage($shopId);
        $applicants = $this->loadHelpApplicants($shopId);

        return view('shops.help-recruitment.index', [
            'pageId' => 'help-recruitment',
            'availabilityDates' => $dates,
            'availabilityMax' => AvailabilityService::MAX_DATES,
            'availabilityDaysAhead' => AvailabilityService::MAX_DAYS_AHEAD,
            'helpWage' => $wage,
            'applicants' => $applicants,
        ]);
    }

    public function declareDates(Request $request)
    {
        $validated = $request->validate([
            'dates' => ['required', 'array', 'max:' . AvailabilityService::MAX_DATES],
            'dates.*' => ['date_format:Y-m-d'],
        ]);

        $service = app(AvailabilityService::class);
        $result = $service->setDates(
            AvailabilityDate::OWNER_SHOP,
            (string) $this->currentShopId(),
            $validated['dates']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'dates' => $service->getDates(AvailabilityDate::OWNER_SHOP, (string) $this->currentShopId()),
        ]);
    }

    public function clearDates()
    {
        app(AvailabilityService::class)
            ->clearDates(AvailabilityDate::OWNER_SHOP, (string) $this->currentShopId());

        return response()->json(['success' => true]);
    }

    public function updateWage(Request $request)
    {
        $validated = $request->validate([
            'published_help' => ['nullable'],
            'help_hourly_wage' => ['nullable', 'integer', 'min:0'],
            'help_hourly_wage_max' => ['nullable', 'integer', 'min:0'],
        ]);

        $shopId = (string) $this->currentShopId();
        $published = $request->boolean('published_help');
        $wageMin = isset($validated['help_hourly_wage']) && $validated['help_hourly_wage'] !== null
            ? (int) $validated['help_hourly_wage']
            : null;
        $wageMax = isset($validated['help_hourly_wage_max']) && $validated['help_hourly_wage_max'] !== null
            ? (int) $validated['help_hourly_wage_max']
            : null;

        if ($published && ($wageMin === null || $wageMin <= 0)) {
            return response()->json([
                'success' => false,
                'message' => 'ヘルプ募集を公開する場合は時給（下限）を入力してください。',
            ], 422);
        }

        if ($wageMin !== null && $wageMax !== null && $wageMax > 0 && $wageMax < $wageMin) {
            return response()->json([
                'success' => false,
                'message' => '時給の上限は下限以上で入力してください。',
            ], 422);
        }

        $patch = [];
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage')) {
            $patch['help_hourly_wage'] = $wageMin !== null ? (string) $wageMin : null;
        }
        if (Schema::hasColumn('shop_jobs', 'help_hourly_wage_max')) {
            $patch['help_hourly_wage_max'] = $wageMax !== null ? (string) $wageMax : null;
        }
        if (Schema::hasColumn('shop_jobs', 'has_help')) {
            $patch['has_help'] = ($published && $wageMin !== null) ? 1 : 0;
        }
        if (Schema::hasColumn('shop_jobs', 'status') && $published) {
            // Do not touch the general status; help publication is orthogonal
        }

        if ($patch !== []) {
            $q = DB::table('shop_jobs')->where('shop_id', $shopId);
            if (Schema::hasColumn('shop_jobs', 'job_type') && !Schema::hasColumn('shop_jobs', 'regular_status')) {
                $q->where('job_type', 1);
            }
            $q->update($patch + ['updated_at' => Carbon::now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'ヘルプ募集の時給設定を保存しました。',
            'wage' => $this->loadHelpWage($shopId),
        ]);
    }

    /**
     * @return array{min:?int,max:?int,published:bool}
     */
    private function loadHelpWage(string $shopId): array
    {
        if (!Schema::hasTable('shop_jobs')) {
            return ['min' => null, 'max' => null, 'published' => false];
        }

        $cols = ['id'];
        foreach (['help_hourly_wage', 'help_hourly_wage_max', 'has_help'] as $c) {
            if (Schema::hasColumn('shop_jobs', $c)) {
                $cols[] = $c;
            }
        }

        $q = DB::table('shop_jobs')->where('shop_id', $shopId)->select($cols);
        if (Schema::hasColumn('shop_jobs', 'job_type') && !Schema::hasColumn('shop_jobs', 'regular_status')) {
            $q->where('job_type', 1);
        }
        $row = $q->first();

        return [
            'min' => $row && !empty($row->help_hourly_wage) ? (int) $row->help_hourly_wage : null,
            'max' => $row && !empty($row->help_hourly_wage_max) ? (int) $row->help_hourly_wage_max : null,
            'published' => $row && !empty($row->has_help),
        ];
    }

    /**
     * Simple applicant list: distinct casts who have exchanged messages with this
     * shop tagged talk_job_kind = 'help'. Ordered by latest message time desc.
     *
     * @return list<array{cast_id:string,nickname:string,latest_at:?string,unread:int}>
     */
    private function loadHelpApplicants(string $shopId): array
    {
        if (!Schema::hasTable('messages')) {
            return [];
        }

        $rows = DB::table('messages')
            ->where('shop_id', $shopId)
            ->where('talk_job_kind', 'help')
            ->select(
                'cast_id',
                DB::raw('MAX(created_at) as latest_at'),
                DB::raw('SUM(CASE WHEN sender_type = "cast" AND is_read = 0 THEN 1 ELSE 0 END) as unread_count')
            )
            ->groupBy('cast_id')
            ->orderByDesc('latest_at')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $castIds = $rows->pluck('cast_id')->all();
        $castRows = Schema::hasTable('cast_profiles')
            ? DB::table('cast_profiles')->whereIn('cast_id', $castIds)->pluck('nickname', 'cast_id')
            : collect();

        return $rows->map(function ($r) use ($castRows) {
            $nickname = trim((string) ($castRows[$r->cast_id] ?? ''));
            return [
                'cast_id' => (string) $r->cast_id,
                'nickname' => $nickname !== '' ? $nickname : (string) $r->cast_id,
                'latest_at' => $r->latest_at ? Carbon::parse($r->latest_at)->format('n/j H:i') : null,
                'unread' => (int) ($r->unread_count ?? 0),
            ];
        })->values()->all();
    }
}
