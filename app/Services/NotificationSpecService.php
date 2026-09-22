<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 運営管理：通知・リマインダー通知・未済タスクの仕様を一元管理するサービス。
 *
 * - 「通知」：トリガー条件は固定。ON/OFF と文章のみ編集可。
 * - 「リマインダー通知」：トリガー条件は固定。発火タイミング（日数/時間）と文章を編集可。
 * - 「未済タスク」：条件・解消条件ともに仕様（読み取り専用）。表示文言のみ編集可。
 *
 * 設定値は admin_notification_settings テーブルに保存し、テーブルが存在しない場合は
 * デフォルト（カタログ定義）にフォールバックする。
 */
class NotificationSpecService
{
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_TASK = 'task';

    private const TABLE = 'admin_notification_settings';

    /**
     * 通知（トリガー固定／ON-OFF＋文章編集）のカタログ。
     *
     * @return array<int, array{key:string, group:string, label:string, condition:string, default_title:string, default_body:string, default_enabled:bool}>
     */
    public function notificationCatalog(): array
    {
        return [
            ['key' => 'talk.message_received', 'group' => 'トーク', 'label' => '新着メッセージ',
             'condition' => 'キャスト⇄店舗のトーク画面から相手にメッセージが送信された時',
             'default_title' => '新着メッセージ',
             'default_body' => '{partner}からメッセージが届きました。内容を確認してください。',
             'default_enabled' => true],

            ['key' => 'talk.interview_offer', 'group' => 'トーク',  'label' => '面談候補日が届きました',
             'condition' => '店舗が面談候補日（日時オプション）をキャストに提示した時',
             'default_title' => '面談候補日のご案内',
             'default_body' => '{shop_name}から面談候補日が届きました。トークから日程を選択してください。',
             'default_enabled' => true],

            ['key' => 'talk.interview_confirmed', 'group' => 'トーク', 'label' => '面談日が確定',
             'condition' => 'キャストが面談候補日のうち一つを選択し、面談日が確定した時',
             'default_title' => '面談日が確定しました',
             'default_body' => '{cast_name}が面談日を確定しました。日時：{interview_at}',
             'default_enabled' => true],

            ['key' => 'talk.hired', 'group' => 'トーク', 'label' => '採用通知',
             'condition' => '店舗が面談後の結果として「採用」を選択した時',
             'default_title' => '採用が決定しました',
             'default_body' => '{shop_name}より採用が決定しました。雇用形態：{employment_kind}。詳細はトークをご確認ください。',
             'default_enabled' => true],

            ['key' => 'talk.rejected', 'group' => 'トーク', 'label' => '不採用通知',
             'condition' => '店舗が面談後の結果として「不採用」を選択した時',
             'default_title' => '選考結果のご連絡',
             'default_body' => '{shop_name}より選考結果のご連絡があります。トークから内容をご確認ください。',
             'default_enabled' => true],

            ['key' => 'talk.status_changed', 'group' => 'トーク', 'label' => '選考・勤務ステータス更新',
             'condition' => '面談キャンセル依頼／承諾、日程再調整、本入店リクエスト、勤務完了報告、ボーナス達成報告などのトークアクションが実行された時',
             'default_title' => 'トーク更新',
             'default_body' => 'トーク内容が更新されました。トーク画面でご確認ください。',
             'default_enabled' => true],

            ['key' => 'talk.work_complete_report', 'group' => 'トーク', 'label' => '勤務完了報告（運営宛）',
             'condition' => 'キャスト or 店舗が勤務完了報告／本入店達成報告を送信した時',
             'default_title' => '振込指示が届きました',
             'default_body' => '勤務完了報告を受領しました。指示額: ¥{amount}。入金確認後に振込を実施してください。',
             'default_enabled' => true],

            ['key' => 'billing.invoice_issued', 'group' => '請求・入金', 'label' => '請求書発行',
             'condition' => '運営が請求書を発行（status を「請求書発行」に遷移）した時',
             'default_title' => '請求書が発行されました',
             'default_body' => '請求番号 {invoice_number} の請求書が発行されました。期限：{invoice_due_date}',
             'default_enabled' => true],

            ['key' => 'billing.payment_confirmed', 'group' => '請求・入金', 'label' => '店舗入金確認',
             'condition' => '運営が店舗からの入金を確認した時',
             'default_title' => '入金が確認されました',
             'default_body' => '請求番号 {invoice_number} の入金を確認しました。',
             'default_enabled' => true],

            ['key' => 'billing.cast_transferred', 'group' => '請求・入金', 'label' => 'キャスト振込実行',
             'condition' => '運営がキャスト宛の振込を実行した時',
             'default_title' => '振込を実行しました',
             'default_body' => '採用ボーナス ¥{cast_transfer_amount} を振り込みました。ご確認ください。',
             'default_enabled' => true],

            ['key' => 'verification.cast_approved', 'group' => '本人確認・書類審査', 'label' => 'キャスト本人確認 承認',
             'condition' => '運営がキャストの本人確認書類を承認した時',
             'default_title' => '本人確認が完了しました',
             'default_body' => '本人確認書類が承認されました。すべての機能をご利用いただけます。',
             'default_enabled' => true],

            ['key' => 'verification.cast_rejected', 'group' => '本人確認・書類審査', 'label' => 'キャスト本人確認 差戻し',
             'condition' => '運営がキャストの本人確認書類を差戻した時',
             'default_title' => '本人確認の再提出のお願い',
             'default_body' => '本人確認書類について確認できない点がありました。理由：{reason}。マイページから再提出してください。',
             'default_enabled' => true],

            ['key' => 'verification.shop_approved', 'group' => '本人確認・書類審査', 'label' => '店舗書類 承認',
             'condition' => '運営が店舗の許可書類を承認した時',
             'default_title' => '店舗書類が承認されました',
             'default_body' => '提出書類が承認されました。求人公開などの機能をご利用いただけます。',
             'default_enabled' => true],

            ['key' => 'verification.shop_rejected', 'group' => '本人確認・書類審査', 'label' => '店舗書類 差戻し',
             'condition' => '運営が店舗の許可書類を差戻した時',
             'default_title' => '店舗書類の再提出のお願い',
             'default_body' => '提出書類について確認できない点がありました。理由：{reason}。マイページから再提出してください。',
             'default_enabled' => true],

            ['key' => 'review.posted', 'group' => 'レビュー', 'label' => 'レビュー投稿',
             'condition' => 'キャストが勤務完了後にレビューを投稿した時',
             'default_title' => 'キャストからレビューが届きました',
             'default_body' => '{cast_name}からレビューが投稿されました。内容を確認して返信できます。',
             'default_enabled' => true],

            ['key' => 'inquiry.received', 'group' => '問合せ', 'label' => '問合せ受付（運営宛）',
             'condition' => 'キャスト・店舗・未ログインユーザから問合せフォームが送信された時',
             'default_title' => '新しい問合せが届きました',
             'default_body' => '差出人：{from_name}（{from_type}）\n件名：{subject}',
             'default_enabled' => true],
        ];
    }

    /**
     * リマインダー通知のカタログ。
     *
     * unit は `hours` または `days` で、ユーザは数値のみ変更可。
     *
     * @return array<int, array{key:string, group:string, label:string, condition:string, unit:string, default_offset:int, default_title:string, default_body:string}>
     */
    public function reminderCatalog(): array
    {
        return [
            ['key' => 'interview.before_24h', 'group' => '面談リマインド', 'label' => '面接前リマインド（早期）',
             'condition' => '面談確定済みの面談予定が、現在時刻のN時間前にあるとき',
             'unit' => 'hours', 'default_offset' => 24,
             'default_title' => '面接リマインド',
             'default_body' => '【ミセチョク】面接{offset}時間前リマインド\n面接予定：{scheduled_at}'],

            ['key' => 'interview.before_3h', 'group' => '面談リマインド', 'label' => '面接前リマインド（直前）',
             'condition' => '面談確定済みの面談予定が、現在時刻のN時間前にあるとき',
             'unit' => 'hours', 'default_offset' => 3,
             'default_title' => '面接リマインド',
             'default_body' => '【ミセチョク】面接{offset}時間前リマインド\n面接予定：{scheduled_at}'],

            ['key' => 'payment.before_1d', 'group' => '請求リマインド', 'label' => '支払期限の前日',
             'condition' => '請求書発行済みの請求が、期限のN日前のとき',
             'unit' => 'days', 'default_offset' => 1,
             'default_title' => '支払期限リマインド',
             'default_body' => '【ミセチョク】支払期限の{offset}日前です\n請求番号：{invoice_number}\n期限：{invoice_due_date}'],

            ['key' => 'payment.on_due', 'group' => '請求リマインド', 'label' => '支払期限の当日',
             'condition' => '請求書発行済みの請求が、期限当日（残N日）のとき',
             'unit' => 'days', 'default_offset' => 0,
             'default_title' => '支払期限リマインド',
             'default_body' => '【ミセチョク】本日が支払期限です\n請求番号：{invoice_number}\n期限：{invoice_due_date}'],

            ['key' => 'payment.overdue_3d', 'group' => '請求リマインド', 'label' => '支払期限超過（警告）',
             'condition' => '請求書発行済みの請求が、期限からN日超過しているとき',
             'unit' => 'days', 'default_offset' => 3,
             'default_title' => '支払期限超過のお知らせ',
             'default_body' => '【ミセチョク】支払期限を{offset}日超過しています\n請求番号：{invoice_number}\n期限：{invoice_due_date}\n至急ご対応ください。'],

            ['key' => 'talk.unread_30m', 'group' => '未読メッセージ', 'label' => '未読メッセージ（早期）',
             'condition' => '相手からのメッセージが未読のまま N分経過したとき（最初の通知）',
             'unit' => 'minutes', 'default_offset' => 30,
             'default_title' => '未読メッセージのリマインド',
             'default_body' => '{partner}から未読メッセージがあります。トーク画面でご確認ください。'],

            ['key' => 'talk.unread_3h', 'group' => '未読メッセージ', 'label' => '未読メッセージ（再通知）',
             'condition' => '相手からのメッセージが未読のまま N分経過したとき（再通知）',
             'unit' => 'minutes', 'default_offset' => 180,
             'default_title' => '未読メッセージのリマインド',
             'default_body' => '{partner}から未読メッセージがあります。トーク画面でご確認ください。'],

            ['key' => 'cast_transfer.confirm_24h', 'group' => '振込受領確認', 'label' => '振込確認（24時間後）',
             'condition' => '運営が振込実行後、キャストからの受領確認がない状態でN時間経過したとき',
             'unit' => 'hours', 'default_offset' => 24,
             'default_title' => '振込のご確認',
             'default_body' => '採用ボーナスの振込を実行しました。マイページから受領確認をお願いします。'],

            ['key' => 'cast_transfer.confirm_3d', 'group' => '振込受領確認', 'label' => '振込確認（3日後）',
             'condition' => '運営が振込実行後、キャストからの受領確認がない状態でN日経過したとき',
             'unit' => 'days', 'default_offset' => 3,
             'default_title' => '振込のご確認',
             'default_body' => '採用ボーナスの振込から{offset}日が経過しました。マイページからご確認ください。'],

            ['key' => 'cast_transfer.confirm_7d', 'group' => '振込受領確認', 'label' => '振込確認（警告・7日後）',
             'condition' => '運営が振込実行後、キャストからの受領確認がない状態でN日経過したとき（警告）',
             'unit' => 'days', 'default_offset' => 7,
             'default_title' => '振込のご確認【重要】',
             'default_body' => '採用ボーナスの振込から{offset}日が経過しました。受領確認がまだの場合は至急ご対応ください。'],
        ];
    }

    /**
     * 未済タスク（条件・解消条件ともに仕様。表示文言のみ編集可）のカタログ。
     *
     * @return array<int, array{key:string, group:string, label:string, condition:string, resolution:string, default_title:string, default_body:string, actor:string}>
     */
    public function taskCatalog(): array
    {
        return [
            ['key' => 'billing.cast_requested', 'group' => '運営タスク', 'actor' => '運営',
             'label' => 'キャスト入金依頼を確認する',
             'condition' => 'キャストが「採用ボーナス入金依頼」を作成し、店舗の承認を待っている状態（application_deposits.status = 1）',
             'resolution' => '店舗が請求承認を行う、または運営が手動で承認することで status が 2 に進むと自動的に消える',
             'default_title' => 'キャスト入金依頼（店舗承認待ち）を確認する',
             'default_body' => '請求番号未採番。店舗承認を促すか、運営側で承認してください。'],

            ['key' => 'billing.shop_approved', 'group' => '運営タスク', 'actor' => '運営',
             'label' => '請求書を発行する',
             'condition' => '店舗が入金請求を承認済みで、まだ請求書が発行されていない状態（application_deposits.status = 2）',
             'resolution' => '運営が「請求書発行」を実行し status が 3 に進むと自動的に消える',
             'default_title' => '店舗へ請求書を発行する',
             'default_body' => '採用ボーナス確定。所定の期限内に請求書を発行・送付してください。'],

            ['key' => 'billing.shop_payment_reported', 'group' => '運営タスク', 'actor' => '運営',
             'label' => '店舗入金を照合する',
             'condition' => '店舗が入金報告を行ったが、運営の入金確認がまだの状態（application_deposits.status = 3）',
             'resolution' => '運営が「入金確認」を実行し status が 4 に進むと自動的に消える',
             'default_title' => '店舗入金を照合する',
             'default_body' => '銀行明細と店舗報告を照合し、入金確認を実行してください。'],

            ['key' => 'billing.shop_payment_confirmed', 'group' => '運営タスク', 'actor' => '運営',
             'label' => 'キャストへ振込を実行する',
             'condition' => '店舗入金が確認済みで、キャストへの振込がまだ実行されていない状態（application_deposits.status = 4）',
             'resolution' => '運営が「キャスト振込実行」を実行し status が 5 以降に進むと自動的に消える',
             'default_title' => 'キャストへの振込を実行する',
             'default_body' => '銀行口座情報を確認の上、当日中にキャストへの振込を実行してください。'],

            ['key' => 'verification.cast_pending', 'group' => '運営タスク', 'actor' => '運営',
             'label' => 'キャスト本人確認の審査',
             'condition' => 'キャストが本人確認書類を提出し、cast_identity_documents.status = 1（審査中）の状態',
             'resolution' => '運営が「承認」または「差戻し」を実行することで status が 2 / 3 に進むと自動的に消える',
             'default_title' => 'キャスト本人確認の審査',
             'default_body' => '提出された本人確認書類を確認し、承認または差戻しを行ってください。'],

            ['key' => 'verification.shop_pending', 'group' => '運営タスク', 'actor' => '運営',
             'label' => '店舗書類の審査',
             'condition' => '店舗が許可書類を提出し、shop_license_documents.status = 1（審査中）の状態',
             'resolution' => '運営が「承認」または「差戻し」を実行することで status が 2 / 3 に進むと自動的に消える',
             'default_title' => '店舗書類の審査',
             'default_body' => '提出された店舗書類を確認し、承認または差戻しを行ってください。'],

            ['key' => 'inquiry.unanswered', 'group' => '運営タスク', 'actor' => '運営',
             'label' => '未対応の問合せ',
             'condition' => '問合せフォーム経由のお問合せのうち、未対応／対応中ステータスのもの',
             'resolution' => '運営が「対応完了」または「クローズ」へステータス変更すると自動的に消える',
             'default_title' => '問合せの対応',
             'default_body' => '受付済みの問合せに対して、メールで返信し対応ステータスを更新してください。'],

            ['key' => 'cast.identity_unsubmitted', 'group' => 'キャスト側タスク', 'actor' => 'キャスト',
             'label' => '本人確認書類の提出（キャスト）',
             'condition' => 'キャストが本人確認書類を一度も提出していない（cast_identity_documents が無い、または status = 0）状態',
             'resolution' => 'キャストがマイページから書類を提出し、status が 1（審査中）以上に進むと自動的に消える',
             'default_title' => '本人確認書類を提出してください',
             'default_body' => '本人確認書類が未提出のため、応募・採用に関する一部機能が制限されています。マイページから提出してください。'],

            ['key' => 'cast.bank_account_unset', 'group' => 'キャスト側タスク', 'actor' => 'キャスト',
             'label' => '振込先口座の登録（キャスト）',
             'condition' => 'キャストの振込先口座（bank_accounts.holder_type = casts）が未登録の状態',
             'resolution' => 'マイページから口座情報を保存すると自動的に消える',
             'default_title' => '振込先口座を登録してください',
             'default_body' => '振込先口座が未登録のため、採用ボーナスの振込ができません。マイページから登録してください。'],

            ['key' => 'shop.license_unsubmitted', 'group' => '店舗側タスク', 'actor' => '店舗',
             'label' => '許可書類の提出（店舗）',
             'condition' => '店舗が必要な許可書類（風俗営業許可など）を提出していない状態',
             'resolution' => 'マイページから書類を提出し、status が 1（審査中）以上に進むと自動的に消える',
             'default_title' => '許可書類を提出してください',
             'default_body' => '営業に必要な許可書類が未提出のため、求人公開などの機能が制限されています。マイページから提出してください。'],

            ['key' => 'shop.bank_account_unset', 'group' => '店舗側タスク', 'actor' => '店舗',
             'label' => '振込元口座の登録（店舗）',
             'condition' => '店舗の銀行口座（bank_accounts.holder_type = shops）が未登録の状態',
             'resolution' => 'マイページから口座情報を保存すると自動的に消える',
             'default_title' => '振込元口座を登録してください',
             'default_body' => '振込元口座が未登録のため、入金フローを開始できません。マイページから登録してください。'],
        ];
    }

    /**
     * テーブルが存在すれば作成済み or 自動作成。存在チェックのみで副作用は最小限。
     */
    private function tableExists(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 設定の現在値（DB上書き＋デフォルトのマージ）を返す。
     *
     * @return array{enabled:bool, offset:int, title:string, body:string}
     */
    public function getSetting(string $type, string $key, array $defaults): array
    {
        $row = null;
        if ($this->tableExists()) {
            try {
                $row = DB::table(self::TABLE)
                    ->where('type', $type)
                    ->where('key', $key)
                    ->first();
            } catch (\Throwable $e) {
                $row = null;
            }
        }
        return [
            'enabled' => $row && $row->enabled !== null ? (bool) $row->enabled : (bool) ($defaults['enabled'] ?? true),
            'offset' => $row && $row->offset_value !== null ? (int) $row->offset_value : (int) ($defaults['offset'] ?? 0),
            'title' => $row && $row->title !== null && $row->title !== '' ? (string) $row->title : (string) ($defaults['title'] ?? ''),
            'body' => $row && $row->body !== null && $row->body !== '' ? (string) $row->body : (string) ($defaults['body'] ?? ''),
        ];
    }

    /**
     * 設定を保存。
     *
     * @param array{enabled?:bool, offset?:int, title?:string, body?:string} $values
     */
    public function saveSetting(string $type, string $key, array $values): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $payload = [
            'updated_at' => now(),
        ];
        if (array_key_exists('enabled', $values)) {
            $payload['enabled'] = $values['enabled'] ? 1 : 0;
        }
        if (array_key_exists('offset', $values)) {
            $payload['offset_value'] = (int) $values['offset'];
        }
        if (array_key_exists('title', $values)) {
            $payload['title'] = (string) $values['title'];
        }
        if (array_key_exists('body', $values)) {
            $payload['body'] = (string) $values['body'];
        }

        try {
            DB::table(self::TABLE)->updateOrInsert(
                ['type' => $type, 'key' => $key],
                $payload
            );
        } catch (\Throwable $e) {
            // 黙殺：UI上はデフォルトを表示し続ける
        }
    }

    /**
     * 通知一覧をUI用に整形。
     */
    public function notificationsForView(): array
    {
        return collect($this->notificationCatalog())->map(function ($cap) {
            $current = $this->getSetting(self::TYPE_NOTIFICATION, $cap['key'], [
                'enabled' => $cap['default_enabled'],
                'title' => $cap['default_title'],
                'body' => $cap['default_body'],
            ]);
            return $cap + [
                'current_enabled' => $current['enabled'],
                'current_title' => $current['title'],
                'current_body' => $current['body'],
            ];
        })->groupBy('group')->all();
    }

    public function remindersForView(): array
    {
        return collect($this->reminderCatalog())->map(function ($cap) {
            $current = $this->getSetting(self::TYPE_REMINDER, $cap['key'], [
                'offset' => $cap['default_offset'],
                'title' => $cap['default_title'],
                'body' => $cap['default_body'],
            ]);
            return $cap + [
                'current_offset' => $current['offset'],
                'current_title' => $current['title'],
                'current_body' => $current['body'],
            ];
        })->groupBy('group')->all();
    }

    public function tasksForView(): array
    {
        return collect($this->taskCatalog())->map(function ($cap) {
            $current = $this->getSetting(self::TYPE_TASK, $cap['key'], [
                'title' => $cap['default_title'],
                'body' => $cap['default_body'],
            ]);
            return $cap + [
                'current_title' => $current['title'],
                'current_body' => $current['body'],
            ];
        })->groupBy('actor')->all();
    }

    public function unitLabel(string $unit): string
    {
        return match ($unit) {
            'minutes' => '分',
            'hours' => '時間',
            'days' => '日',
            default => $unit,
        };
    }
}
