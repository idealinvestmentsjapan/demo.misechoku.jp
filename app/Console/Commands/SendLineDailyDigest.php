<?php

namespace App\Console\Commands;

use App\Services\LineNotificationService;
use App\Services\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SendLineDailyDigest extends Command
{
    protected $signature = 'line:send-daily-digest {--dry-run : 実送信せず対象のみ表示}';

    protected $description = 'LINE通知を日次ダイジェストで送信する';

    /** @var array<string, array{userType: string, userId: string, lines: array<int, string>}> */
    private array $digest = [];

    public function handle(
        NotificationPreferenceService $prefs
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();
        $since = $now->copy()->subDay();
        $line = class_exists('LINE\\LINEBot') ? app(LineNotificationService::class) : null;

        $this->collectShopDepositRequested($since);
        $this->collectShopDeposit14DaysReminder($now);
        $this->collectShopInterviewOverdueReminder($now);
        $this->collectCastInterviewOffer($since);
        $this->collectCastHired14DaysNoDeposit($now);

        $sent = 0;
        foreach ($this->digest as $entry) {
            $pref = $prefs->get($entry['userType'], $entry['userId']);
            if (!($pref['line_enabled'] ?? true)) {
                continue;
            }
            if (empty($entry['lines'])) {
                continue;
            }

            $message = $this->buildDigestMessage($entry['lines']);
            if ($dryRun) {
                $this->line('[DRY] ' . $entry['userType'] . ':' . $entry['userId']);
                $this->line($message);
            } else {
                if (!$line) {
                    $this->warn('LINE SDK が未導入のため、LINE送信をスキップしました。');
                    continue;
                }
                if ($entry['userType'] === 'cast') {
                    $line->sendToCast($entry['userId'], $message);
                } else {
                    $line->sendToShopManager($entry['userId'], $message);
                }
            }
            $sent++;
        }

        $this->info("LINEダイジェスト送信対象: {$sent} 件");
        return self::SUCCESS;
    }

    private function collectShopDepositRequested(Carbon $since): void
    {
        $rows = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shop_managers', 'shop_jobs.shop_id', '=', 'shop_managers.shop_id')
            ->where('application_deposits.status', 1)
            ->where('application_deposits.created_at', '>=', $since)
            ->select('shop_managers.id as manager_id')
            ->get();

        foreach ($rows as $row) {
            $this->addDigestLine('shop_manager', (string) $row->manager_id, '・入金依頼が届いています。確認をお願いします。');
        }
    }

    private function collectShopDeposit14DaysReminder(Carbon $now): void
    {
        $rows = DB::table('application_deposits')
            ->join('shop_job_applications', 'application_deposits.shop_job_application_id', '=', 'shop_job_applications.id')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shop_managers', 'shop_jobs.shop_id', '=', 'shop_managers.shop_id')
            ->where('application_deposits.status', 1)
            ->where('application_deposits.created_at', '<=', $now->copy()->subDays(14))
            ->select('shop_managers.id as manager_id', 'application_deposits.id as deposit_id')
            ->get();

        foreach ($rows as $row) {
            $this->addDigestLine(
                'shop_manager',
                (string) $row->manager_id,
                '・入金依頼から14日以上経過した案件があります（ID: ' . $row->deposit_id . '）。'
            );
        }
    }

    private function collectShopInterviewOverdueReminder(Carbon $now): void
    {
        $rows = DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->join('shop_managers', 'shop_jobs.shop_id', '=', 'shop_managers.shop_id')
            ->where('shop_job_applications.status', 3)
            ->whereNotNull('shop_job_applications.result_date')
            ->where('shop_job_applications.result_date', '<', $now->toDateString())
            ->select('shop_managers.id as manager_id', 'shop_job_applications.id as application_id')
            ->get();

        foreach ($rows as $row) {
            $this->addDigestLine(
                'shop_manager',
                (string) $row->manager_id,
                '・面談日を過ぎても採用／不採用未登録の案件があります（応募ID: ' . $row->application_id . '）。'
            );
        }
    }

    private function collectCastInterviewOffer(Carbon $since): void
    {
        $rows = DB::table('messages')
            ->where('type', 2)
            ->where('sender_type', 2)
            ->where('created_at', '>=', $since)
            ->select('cast_id')
            ->get();

        foreach ($rows as $row) {
            $this->addDigestLine('cast', (string) $row->cast_id, '・面談候補日が届いています。確認してください。');
        }
    }

    private function collectCastHired14DaysNoDeposit(Carbon $now): void
    {
        $rows = DB::table('shop_job_applications')
            ->leftJoin('application_deposits', 'shop_job_applications.id', '=', 'application_deposits.shop_job_application_id')
            ->whereIn('shop_job_applications.status', [4, 6])
            ->where('shop_job_applications.updated_at', '<=', $now->copy()->subDays(14))
            ->whereNull('application_deposits.id')
            ->select('shop_job_applications.cast_id', 'shop_job_applications.id as application_id')
            ->get();

        foreach ($rows as $row) {
            $this->addDigestLine(
                'cast',
                (string) $row->cast_id,
                '・採用後14日以上経過し、完了／入金依頼が未実施の案件があります（応募ID: ' . $row->application_id . '）。'
            );
        }
    }

    private function addDigestLine(string $userType, string $userId, string $line): void
    {
        $key = $userType . ':' . $userId;
        if (!isset($this->digest[$key])) {
            $this->digest[$key] = [
                'userType' => $userType,
                'userId' => $userId,
                'lines' => [],
            ];
        }
        if (!in_array($line, $this->digest[$key]['lines'], true)) {
            $this->digest[$key]['lines'][] = $line;
        }
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildDigestMessage(array $lines): string
    {
        $head = '【ミセチョク】本日の重要通知まとめ';
        return $head . "\n" . implode("\n", $lines);
    }
}

