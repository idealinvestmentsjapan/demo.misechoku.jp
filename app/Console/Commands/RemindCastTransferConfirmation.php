<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\BillingManagementService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationService;
use App\Services\NotificationSpecService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 振込済みでキャストがまだ「入金確認済み」を押していない案件に対してリマインドを送る督促バッチ。
 *
 * 仕様:
 * - 送信タイミングは NotificationSpecService の cast_transfer.confirm_24h / _3d / _7d
 *   （運営が /admin/notification-spec でオフセット・文言・ON/OFF を編集可能）。
 *   7日後警告の後は 7 日ごとに警告文言でループ。
 * - 配信はアプリ内おしらせ + Web Push + LINE（NotificationService が
 *   push_enabled / line_enabled を見てチャネルごとに配信可否を判定）。
 * - キャストの deadline_reminder_enabled が OFF の場合は送信しない。
 * - 同一 deposit × 同一ウィンドウは notifications テーブルを参照して重複送信しない
 *   （コマンドは hourly 実行でウィンドウ幅が ±2h あるため）。
 * - 振込直後の通知は BillingManagementService の billing.cast_transferred
 *   （イベント通知）が担うため、本コマンドでは送らない。
 */
class RemindCastTransferConfirmation extends Command
{
    protected $signature = 'billing:remind-cast-transfer-confirmation {--dry-run : 送信せず対象のみ表示}';

    protected $description = '振込済み・キャスト未確認の案件にリマインド（督促）を送信する';

    private const WINDOW_TOLERANCE_HOURS = 2;

    public function handle(
        NotificationService $notifications,
        NotificationPreferenceService $prefs,
        NotificationSpecService $specs
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $deposits = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('casts', 'shop_job_applications.cast_id', '=', 'casts.id')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->where('application_deposits.status', BillingManagementService::STATUS_CAST_TRANSFERRED)
            ->whereNotNull('application_deposits.cast_transferred_at')
            ->whereNull('application_deposits.completed_at')
            ->select(
                'application_deposits.id',
                'application_deposits.cast_transferred_at',
                'shop_job_applications.cast_id',
                'cast_profiles.nickname'
            )
            ->get();

        $settings = [
            '24h' => $this->reminderSetting($specs, 'cast_transfer.confirm_24h'),
            '3d' => $this->reminderSetting($specs, 'cast_transfer.confirm_3d'),
            '7d' => $this->reminderSetting($specs, 'cast_transfer.confirm_7d'),
        ];

        $now = Carbon::now();
        $targets = [];
        foreach ($deposits as $d) {
            $transferredAt = Carbon::parse($d->cast_transferred_at);
            $hoursAgo = (int) $transferredAt->diffInHours($now);

            $resolved = $this->resolveWindow($hoursAgo, $settings);
            if ($resolved === null) {
                continue;
            }

            $targets[] = [
                'deposit_id' => (int) $d->id,
                'cast_id' => (string) $d->cast_id,
                'cast_name' => $d->nickname ?: $d->cast_id,
                'window' => $resolved['window'],
                'spec_key' => $resolved['spec_key'],
                'title' => $resolved['title'],
                'body' => $resolved['body'],
                'transferred_at' => $transferredAt->format('Y-m-d H:i'),
            ];
        }

        if (empty($targets)) {
            $this->info('リマインド対象は0件です。');
            return self::SUCCESS;
        }

        $this->info('リマインド対象: ' . count($targets) . ' 件');

        $sent = 0;
        $skipped = 0;
        foreach ($targets as $t) {
            $this->line("  #{$t['deposit_id']} {$t['cast_name']} ({$t['cast_id']}) - {$t['window']} - 振込日時: {$t['transferred_at']}");

            if ($dryRun) {
                continue;
            }

            if ($this->alreadyReminded($t['cast_id'], $t['spec_key'], $t['deposit_id'], $t['window'])) {
                $skipped++;
                continue;
            }

            $castPrefs = $prefs->get('cast', $t['cast_id']);
            if (empty($castPrefs['deadline_reminder_enabled'])) {
                $skipped++;
                continue;
            }

            // In-app record + Web Push + LINE (channels gated by user preferences)
            $notifications->createForCast(
                $t['cast_id'],
                $t['spec_key'],
                $t['title'],
                $t['body'],
                url('/cast/mypage/management'),
                ['deposit_id' => $t['deposit_id'], 'window' => $t['window']]
            );
            $sent++;

            Log::info('billing.remind_cast_transfer', [
                'deposit_id' => $t['deposit_id'],
                'cast_id' => $t['cast_id'],
                'window' => $t['window'],
            ]);
        }

        if ($dryRun) {
            $this->info('--dry-run のため送信は行いません。');
        } else {
            $this->info("送信: {$sent} 件 / スキップ（送信済み・設定OFF）: {$skipped} 件");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{enabled: bool, offset: int, unit: string, title: string, body: string}
     */
    private function reminderSetting(NotificationSpecService $specs, string $key): array
    {
        $catalog = collect($specs->reminderCatalog())->firstWhere('key', $key) ?? [];
        $setting = $specs->getSetting(NotificationSpecService::TYPE_REMINDER, $key, [
            'enabled' => true,
            'offset' => $catalog['default_offset'] ?? 0,
            'title' => $catalog['default_title'] ?? '振込のご確認',
            'body' => $catalog['default_body'] ?? '採用ボーナスの振込を実行しました。マイページから受領確認をお願いします。',
        ]);
        $setting['unit'] = $catalog['unit'] ?? 'hours';

        return $setting;
    }

    /**
     * @param array<string, array{enabled: bool, offset: int, unit: string, title: string, body: string}> $settings
     * @return array{window: string, spec_key: string, title: string, body: string}|null
     */
    private function resolveWindow(int $hoursAgo, array $settings): ?array
    {
        $slots = [
            '24h' => ['spec_key' => 'cast_transfer.confirm_24h', 'target' => $this->offsetHours($settings['24h'])],
            '3d' => ['spec_key' => 'cast_transfer.confirm_3d', 'target' => $this->offsetHours($settings['3d'])],
            '7d' => ['spec_key' => 'cast_transfer.confirm_7d', 'target' => $this->offsetHours($settings['7d'])],
        ];

        foreach ($slots as $window => $slot) {
            $setting = $settings[$window];
            if (!$setting['enabled'] || $slot['target'] <= 0) {
                continue;
            }
            if (abs($hoursAgo - $slot['target']) <= self::WINDOW_TOLERANCE_HOURS) {
                return [
                    'window' => $window,
                    'spec_key' => $slot['spec_key'],
                    'title' => $setting['title'],
                    'body' => $this->applyOffset($setting['body'], $setting['offset']),
                ];
            }
        }

        // Weekly loop after the 7d warning, reusing the warning text.
        $warning = $settings['7d'];
        $warningDays = (int) ceil($this->offsetHours($warning) / 24);
        $daysAgo = (int) floor($hoursAgo / 24);
        if ($warning['enabled'] && $warningDays > 0 && $daysAgo >= $warningDays + 7 && $daysAgo % 7 === 0) {
            return [
                'window' => $daysAgo . 'd',
                'spec_key' => 'cast_transfer.confirm_7d',
                'title' => $warning['title'],
                'body' => $this->applyOffset($warning['body'], $daysAgo),
            ];
        }

        return null;
    }

    private function offsetHours(array $setting): int
    {
        return match ($setting['unit']) {
            'days' => (int) $setting['offset'] * 24,
            'minutes' => (int) ceil((int) $setting['offset'] / 60),
            default => (int) $setting['offset'],
        };
    }

    private function applyOffset(string $body, int $offset): string
    {
        return str_replace('{offset}', (string) $offset, $body);
    }

    private function alreadyReminded(string $castId, string $type, int $depositId, string $window): bool
    {
        if (!Schema::hasTable('notifications')) {
            return false;
        }

        return Notification::query()
            ->forUser(Notification::USER_CAST, $castId)
            ->where('type', $type)
            ->get()
            ->contains(function (Notification $n) use ($depositId, $window) {
                $payload = $n->payload ?? [];

                return (int) ($payload['deposit_id'] ?? 0) === $depositId
                    && (string) ($payload['window'] ?? '') === $window;
            });
    }
}
