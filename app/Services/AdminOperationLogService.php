<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 運営の重要操作（書類承認・差戻し、アカウント停止、ロール変更、振込実行など）を
 * `admin_operation_logs` テーブルに監査ログとして残すサービス。
 *
 * - テーブルが未作成の環境では laravel.log に流す（デプロイ前後でも安全）
 * - actor は `auth()->guard('admin')->user()` から取得
 * - target_type / target_id / summary / payload を残し、検索・追跡できるようにする
 */
class AdminOperationLogService
{
    private const TABLE = 'admin_operation_logs';

    public function record(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $summary = null,
        array $payload = []
    ): void {
        $admin = Auth::guard('admin')->user();
        $request = request();
        $detail = [
            'operator_email' => $admin?->email,
            'operator_role' => $admin?->role,
            'summary' => $summary !== null ? mb_substr($summary, 0, 255) : null,
            'payload' => $payload !== [] ? $payload : null,
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ];
        $row = [
            'admin_id' => $admin?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip_address' => $request?->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            if (Schema::hasTable(self::TABLE)) {
                DB::table(self::TABLE)->insert($row);
                return;
            }
        } catch (\Throwable $e) {
            Log::error('運営操作ログをDBへ保存できませんでした。', [
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);
        }

        Log::channel(config('logging.default'))->info('[admin-op] ' . $action, $row + $detail);
    }

    /**
     * @return array<int, object>
     */
    public function recent(int $limit = 200): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }
        try {
            return DB::table(self::TABLE)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => $this->normalizeRow($row))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 期間／action フィルタ付きで取得（管理画面用）。
     *
     * @return array<int, object>
     */
    public function search(?string $action, ?string $targetType, int $limit = 500): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }
        try {
            $q = DB::table(self::TABLE)->orderByDesc('id');
            if ($action !== null && $action !== '') {
                $q->where('action', 'LIKE', $action . '%');
            }
            if ($targetType !== null && $targetType !== '') {
                $q->where('target_type', $targetType);
            }
            return $q->limit($limit)->get()
                ->map(fn ($row) => $this->normalizeRow($row))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * action ごとのラベル（UI 表示用）。
     */
    public function actionLabel(string $action): string
    {
        return match ($action) {
            'cast.suspend' => 'キャスト停止',
            'cast.unsuspend' => 'キャスト停止解除',
            'shop.suspend' => '店舗停止',
            'shop.unsuspend' => '店舗停止解除',
            'cast.private_unlock' => 'キャスト非公開情報の解除',
            'shop.private_unlock' => '店舗非公開情報の解除',
            'role.update' => 'ロール権限変更',
            'verification.cast.approve' => '本人確認 承認',
            'verification.cast.reject' => '本人確認 差戻し',
            'verification.shop.approve' => '店舗書類 承認',
            'verification.shop.reject' => '店舗書類 差戻し',
            default => $action,
        };
    }

    private function normalizeRow(object $row): object
    {
        $detail = json_decode((string) ($row->detail ?? ''), true);
        $detail = is_array($detail) ? $detail : [];
        $row->operator_id = $row->admin_id ?? null;
        $row->operator_email = $detail['operator_email'] ?? null;
        $row->operator_role = $detail['operator_role'] ?? null;
        $row->summary = $detail['summary'] ?? null;
        $row->payload = isset($detail['payload'])
            ? json_encode($detail['payload'], JSON_UNESCAPED_UNICODE)
            : null;
        $row->user_agent = $detail['user_agent'] ?? null;

        return $row;
    }
}
