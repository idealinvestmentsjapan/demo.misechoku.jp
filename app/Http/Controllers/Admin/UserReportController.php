<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Services\AdminOperationLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 管理者：ユーザー通報の一覧・対応。
 * ステータス変更（対応中／完了／却下）+ メモ書き。
 * 通報対象アカウント（キャスト／店舗）の停止処理もこの画面から実行可能。
 */
class UserReportController extends Controller
{
    public function __construct(
        private readonly AdminOperationLogService $opLog,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');

        $q = UserReport::query()->orderByDesc('created_at');
        if ($status === 'pending') {
            $q->where('status', UserReport::STATUS_PENDING);
        } elseif ($status === 'in_review') {
            $q->where('status', UserReport::STATUS_IN_REVIEW);
        } elseif ($status === 'resolved') {
            $q->where('status', UserReport::STATUS_RESOLVED);
        } elseif ($status === 'dismissed') {
            $q->where('status', UserReport::STATUS_DISMISSED);
        }

        $reports = $q->limit(200)->get();

        // 通報者・対象の表示名を lookup（重複解消）
        $names = $this->fetchNamesFor($reports);
        // 対象アカウントの停止状態（status=2 か）を lookup
        $suspended = $this->fetchSuspendedFor($reports);

        $counts = [
            'pending'   => UserReport::where('status', UserReport::STATUS_PENDING)->count(),
            'in_review' => UserReport::where('status', UserReport::STATUS_IN_REVIEW)->count(),
            'resolved'  => UserReport::where('status', UserReport::STATUS_RESOLVED)->count(),
            'dismissed' => UserReport::where('status', UserReport::STATUS_DISMISSED)->count(),
        ];

        return view('admin.user_reports.index', [
            'reports'      => $reports,
            'names'        => $names,
            'suspended'    => $suspended,
            'counts'       => $counts,
            'currentTab'   => $status,
            'reasonLabels' => UserReport::REASONS,
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status'         => ['required', Rule::in([
                UserReport::STATUS_PENDING,
                UserReport::STATUS_IN_REVIEW,
                UserReport::STATUS_RESOLVED,
                UserReport::STATUS_DISMISSED,
            ])],
            'admin_note'     => ['nullable', 'string', 'max:2000'],
            'suspend_target' => ['nullable'],
        ]);

        $report = UserReport::findOrFail($id);

        $report->update([
            'status'     => (int) $data['status'],
            'admin_note' => $data['admin_note'] ?? $report->admin_note,
            'handled_by' => (int) auth()->guard('admin')->id(),
            'handled_at' => now(),
            'updated_at' => now(),
        ]);

        // 対象アカウントの停止（オプション）
        $suspendMessage = '';
        if (!empty($data['suspend_target'])) {
            $suspendMessage = $this->suspendTargetAccount($report);
        }

        $message = '通報のステータスを更新しました。' . $suspendMessage;

        return redirect()
            ->route('admin.user_reports.index', ['status' => $request->query('return_to', $request->input('return_to', 'all'))])
            ->with('message', $message);
    }

    /**
     * 対象アカウントを停止する（既に停止済みなら何もしない）。
     */
    private function suspendTargetAccount(UserReport $report): string
    {
        $type = $report->target_type;
        $id = (string) $report->target_id;

        if ($type === 'cast') {
            $cast = DB::table('casts')->where('id', $id)->first();
            if (!$cast) return ' （対象キャストが見つからず停止処理をスキップ）';
            if ((int) ($cast->status ?? 0) === 2) return ' （対象キャストは既に停止中）';
            DB::table('casts')->where('id', $id)->update([
                'status' => 2,
                'updated_at' => now(),
            ]);
            $this->opLog->record('cast.suspend', 'cast', $id, 'キャスト停止（通報 #' . $report->id . ' から）');
            return ' 対象キャスト（' . $id . '）も停止しました。';
        }

        if ($type === 'shop') {
            $shop = DB::table('shops')->where('id', $id)->first();
            if (!$shop) return ' （対象店舗が見つからず停止処理をスキップ）';
            if ((int) ($shop->status ?? 0) === 2) return ' （対象店舗は既に停止中）';
            DB::table('shops')->where('id', $id)->update([
                'status' => 2,
                'updated_at' => now(),
            ]);
            DB::table('shop_managers')->where('shop_id', $id)->update([
                'status' => 0,
                'updated_at' => now(),
            ]);
            $this->opLog->record('shop.suspend', 'shop', $id, '店舗停止（通報 #' . $report->id . ' から）');
            return ' 対象店舗（' . $id . '）も停止しました。';
        }

        return '';
    }

    /**
     * @return array<string, string>  key = "{type}:{id}", value = 表示名
     */
    private function fetchNamesFor($reports): array
    {
        $castIds = [];
        $shopIds = [];
        foreach ($reports as $r) {
            if ($r->reporter_type === 'cast') $castIds[] = $r->reporter_id;
            if ($r->target_type === 'cast') $castIds[] = $r->target_id;
            if ($r->reporter_type === 'shop') $shopIds[] = $r->reporter_id;
            if ($r->target_type === 'shop') $shopIds[] = $r->target_id;
        }
        $castIds = array_unique($castIds);
        $shopIds = array_unique($shopIds);

        $names = [];
        if (!empty($castIds)) {
            DB::table('cast_profiles')
                ->whereIn('cast_id', $castIds)
                ->select('cast_id', 'nickname', 'name')
                ->get()
                ->each(function ($row) use (&$names) {
                    $names['cast:' . $row->cast_id] = $row->nickname ?: ($row->name ?: $row->cast_id);
                });
        }
        if (!empty($shopIds)) {
            DB::table('shop_profiles')
                ->whereIn('shop_id', $shopIds)
                ->select('shop_id', 'shop_name')
                ->get()
                ->each(function ($row) use (&$names) {
                    $names['shop:' . $row->shop_id] = $row->shop_name ?: $row->shop_id;
                });
        }
        return $names;
    }

    /**
     * 対象アカウントが現在停止中（status = 2）かを lookup。
     * @return array<string, bool>  key = "{type}:{id}"
     */
    private function fetchSuspendedFor($reports): array
    {
        $castIds = [];
        $shopIds = [];
        foreach ($reports as $r) {
            if ($r->target_type === 'cast') $castIds[] = $r->target_id;
            if ($r->target_type === 'shop') $shopIds[] = $r->target_id;
        }
        $castIds = array_unique($castIds);
        $shopIds = array_unique($shopIds);

        $out = [];
        if (!empty($castIds)) {
            DB::table('casts')->whereIn('id', $castIds)
                ->select('id', 'status')
                ->get()
                ->each(function ($row) use (&$out) {
                    $out['cast:' . $row->id] = ((int) $row->status) === 2;
                });
        }
        if (!empty($shopIds)) {
            DB::table('shops')->whereIn('id', $shopIds)
                ->select('id', 'status')
                ->get()
                ->each(function ($row) use (&$out) {
                    $out['shop:' . $row->id] = ((int) $row->status) === 2;
                });
        }
        return $out;
    }
}
