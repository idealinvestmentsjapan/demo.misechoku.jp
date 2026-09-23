<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ユーザー間通報の受付。トークルーム／プロフィール画面から呼ばれる。
 * 送信フォームは modal で、AJAX / 通常 POST どちらでも動作する。
 *
 * 通報者は今ログイン中の cast or shop、対象は相手側になる。
 * 通報の重複防止：同一 reporter × target × 24h 以内は 1 件に集約（軽微な rate limit）。
 */
class UserReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['cast', 'shop'])],
            'target_id'   => ['required', 'string', 'max:20'],
            'reason'      => ['required', Rule::in(array_keys(UserReport::REASONS))],
            'detail'      => ['nullable', 'string', 'max:1000'],
            'context_type'       => ['nullable', 'string', 'max:16'],
            'context_message_id' => ['nullable', 'integer'],
        ]);

        [$reporterType, $reporterId] = $this->resolveReporter();
        if ($reporterType === null) {
            return response()->json(['success' => false, 'message' => 'ログイン後に通報してください。'], 401);
        }

        // 自分自身の通報は禁止
        if ($reporterType === $data['target_type'] && $reporterId === $data['target_id']) {
            return response()->json(['success' => false, 'message' => '自分自身は通報できません。'], 422);
        }

        // 直近 24 時間の重複通報チェック
        $existing = UserReport::query()
            ->where('reporter_type', $reporterType)
            ->where('reporter_id', $reporterId)
            ->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existing) {
            return response()->json([
                'success'  => true,
                'message'  => '同じ相手への通報を最近受け付けています。運営が確認中です。',
                'reportId' => $existing->id,
                'deduped'  => true,
            ]);
        }

        $report = UserReport::create([
            'reporter_type'      => $reporterType,
            'reporter_id'        => $reporterId,
            'target_type'        => $data['target_type'],
            'target_id'          => $data['target_id'],
            'reason'             => $data['reason'],
            'detail'             => $data['detail'] ?? null,
            'context_type'       => $data['context_type'] ?? null,
            'context_message_id' => $data['context_message_id'] ?? null,
            'status'             => UserReport::STATUS_PENDING,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Ops notification: same scheme as support inquiries
        // (DB record + ops log; mail/Slack can hook in later via .env).
        Log::channel(config('logging.default', 'stack'))->info('ユーザー通報を受け付けました', [
            'report_id'     => $report->id,
            'reporter_type' => $reporterType,
            'reporter_id'   => $reporterId,
            'target_type'   => $data['target_type'],
            'target_id'     => $data['target_id'],
            'reason'        => $data['reason'],
            'context_type'  => $data['context_type'] ?? null,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => '通報を受け付けました。運営で内容を確認いたします。',
            'reportId' => $report->id,
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveReporter(): array
    {
        if (auth()->guard('member')->check()) {
            return ['cast', (string) auth()->guard('member')->id()];
        }
        if (auth()->guard('shop')->check()) {
            $manager = auth()->guard('shop')->user();
            return ['shop', (string) ($manager->shop_id ?? '')];
        }
        return [null, null];
    }
}
