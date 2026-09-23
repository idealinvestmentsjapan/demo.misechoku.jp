<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** One database-independent definition for the UI, seeding, SQL export and tests. */
final class SalesDemoFixture
{
    public const MAX_SLOT = 20;

    public const SCENARIOS = [
        'start' => ['label' => '最初から商談', 'description' => '候補者探し・KEEP・最初の声かけを見せる'],
        'interview' => ['label' => '面談日が決まった場面', 'description' => '返信・面談の提案・日時確定を見せる'],
        'invoice' => ['label' => '採用後・請求の場面', 'description' => '本人へのボーナスと手数料の内訳を見せる'],
        'completed' => ['label' => '本人が受け取った場面', 'description' => '申請から受領までの7段階を見せる'],
    ];

    public static function ids(int $slot): array
    {
        if ($slot < 1 || $slot > self::MAX_SLOT) {
            throw new InvalidArgumentException('営業セットは1〜20で指定してください。');
        }

        return [
            'shop' => sprintf('s89%06d', $slot),
            'manager' => sprintf('m89%06d', $slot),
            'casts' => array_map(fn ($i) => sprintf('c89%03d%03d', $slot, $i), [1, 2, 3]),
            'base' => 8900000 + $slot * 1000,
        ];
    }

    public static function email(string $id): string
    {
        return $id . '@sales-demo.invalid';
    }

    public static function build(int $slot, string $scenario, CarbonImmutable $now): array
    {
        $ids = self::ids($slot);
        if (!isset(self::SCENARIOS[$scenario])) {
            throw new InvalidArgumentException('用意する場面を選んでください。');
        }
        $base = $ids['base'];
        $shop = $ids['shop'];
        $cast = $ids['casts'][0];
        $stamp = $now->toDateTimeString();
        $rows = [];
        $add = function (string $table, array $values, array $owner) use (&$rows): void {
            $rows[] = compact('table', 'values', 'owner');
        };
        $dated = ['created_at' => $stamp, 'updated_at' => $stamp];
        $add('shops', ['id' => $shop, 'email' => self::email($shop), 'status' => 1, 'license_status' => 3,
            'business_license_status' => 3, 'entertainment_license_status' => 3, 'deleted_at' => null] + $dated, ['email' => self::email($shop)]);
        $add('shop_managers', ['id' => $ids['manager'], 'shop_id' => $shop, 'name' => '実演用の店長',
            'email' => self::email($ids['manager']), 'email_verified_at' => $stamp, 'password' => null,
            'role' => 1, 'status' => 1, 'last_login_at' => $stamp] + $dated, ['shop_id' => $shop, 'email' => self::email($ids['manager'])]);
        $add('shop_profiles', ['id' => $base, 'shop_id' => $shop, 'industry_id' => 1, 'industry_label' => 'キャバクラ',
            'shop_name' => sprintf('実演用ミセチョクラウンジ %02d', $slot), 'pref' => '東京都', 'city' => '新宿区',
            'addr' => '実演用住所（架空）', 'tel' => null, 'open_time' => '20:00:00', 'close_time' => '01:00:00',
            'close_is_last' => 0, 'latitude' => 35.6938, 'longitude' => 139.7034] + $dated, ['shop_id' => $shop]);
        $add('shop_images', ['id' => $base, 'shop_id' => $shop, 'image_path' => 'assets/images/demo-sales/shop.svg',
            'type' => 1, 'is_main' => 1, 'main_order' => 1] + $dated, ['shop_id' => $shop]);
        $job = ['id' => $base, 'shop_id' => $shop, 'pr' => '落ち着いた会話を大切にする実演用のお店です。経験や出勤できる曜日を、本人と直接相談して決めます。',
            'catch_copy' => '採用のお金を、働く本人の力に。', 'job_content' => 'お客様との会話とドリンクの提供。すべて架空の募集条件です。',
            'regular_status' => 1, 'regular_hourly_wage' => '4000', 'regular_hourly_wage_max' => 5000,
            'norma_day' => 30, 'norma_hours' => 4, 'bonus_reward' => 100000, 'bonus_remarks' => '実演用の金額',
            'bonus_condition' => '本入店後30日間在籍し、1日4時間の勤務条件を満たした場合。実演用の条件です。',
            'trial_hourly_wage' => '4000', 'trial_status' => 1, 'has_help' => 1, 'help_hourly_wage' => '3500', 'help_status' => 1,
            'working_day' => '週2〜3回から相談', 'working_hours' => '20:00〜翌1:00の間で相談', 'regular_holiday' => '日曜日',
            'qualification' => '18歳以上（高校生不可）。未経験の方も相談できます。', 'shift_time_start' => '20:00:00',
            'shift_time_end' => '01:00:00', 'shift_end_is_last' => 0, 'deleted_at' => null] + $dated;
        $add('shop_jobs', $job, ['shop_id' => $shop]);
        $people = [
            ['あかり', '落ち着いた接客が好きです。週2〜3回、夜の時間帯に働けるお店を探しています。', 'ISFJ', 1],
            ['みお', '人と話すのが好きです。体験入店でお店の雰囲気を確認したいです。', 'ENFP', 1],
            ['ゆい', '未経験です。仕事の流れを教えてもらえるお店を探しています。', 'INFJ', 0],
        ];
        foreach ($ids['casts'] as $i => $id) {
            [$name, $pr, $type, $exp] = $people[$i];
            $n = $base + $i + 1;
            $add('casts', ['id' => $id, 'email' => self::email($id), 'email_verified_at' => $stamp,
                'password' => null, 'status' => 1, 'identity_status' => 1, 'last_login_at' => $stamp,
                'deleted_at' => null] + $dated, ['email' => self::email($id)]);
            $add('cast_profiles', ['id' => $n, 'cast_id' => $id, 'industry_id' => 1, 'nickname' => sprintf('デモ%02d・', $slot) . $name,
                'name' => '実演用 ' . $name, 'birthday' => $now->subYears(24 + $i)->toDateString(), 'pref' => '東京都',
                'city' => '新宿区', 'profession' => '実演用プロフィール', 'exp' => $exp, 'pr' => $pr . '（架空の人物です）',
                'personality_type' => $type, 'latitude' => 35.6939 + $i / 1000, 'longitude' => 139.7035] + $dated, ['cast_id' => $id]);
            // Tier A demo: declare today as a candidate work date
            $add('availability_dates', ['owner_type' => 'cast', 'owner_id' => $id,
                'available_on' => $now->toDateString()] + $dated, ['owner_type' => 'cast', 'owner_id' => $id, 'available_on' => $now->toDateString()]);
            $add('cast_images', ['id' => $n, 'cast_id' => $id, 'image_path' => 'assets/images/demo-sales/cast-' . ($i + 1) . '.svg',
                'status' => 1, 'is_main' => 1, 'main_order' => 1] + $dated, ['cast_id' => $id]);
            $add('cast_posts', ['id' => $n, 'cast_id' => $id, 'body' => $pr] + $dated, ['cast_id' => $id]);
            $add('cast_search_preferences', ['id' => $n, 'cast_id' => $id, 'mode' => 'profile', 'max_distance_km' => 10,
                'shift_frequency' => '週3回以上', 'work_periods' => '["night"]', 'hourly_wage_min' => 3000,
                'industry_ids' => '[1]'] + $dated, ['cast_id' => $id]);
            foreach ([37, 40, 45] as $j => $tag) {
                $add('cast_tag_relations', ['id' => $base + 10 + $i * 3 + $j, 'cast_id' => $id, 'tag_id' => $tag,
                    'tag_type' => 'personality'] + $dated, ['cast_id' => $id]);
            }
        }
        foreach ([$ids['manager'], ...$ids['casts']] as $i => $id) {
            $add('notification_preferences', ['id' => $base + $i, 'user_type' => $i === 0 ? 'shop' : 'cast',
                'user_id' => $id, 'push_enabled' => 0, 'line_enabled' => 0,
                'interview_reminder_enabled' => 0, 'deadline_reminder_enabled' => 0] + $dated, ['user_id' => $id]);
        }
        // B is ready for existing-talk / mutual-KEEP demos; A is untouched in start mode.
        foreach (['cast', 'shop'] as $i => $sender) {
            $add('favorites', ['id' => $base + $i, 'cast_id' => $ids['casts'][1], 'shop_id' => $shop,
                'sender_type' => $sender, 'action_type' => 'keep', 'created_at' => $stamp], ['cast_id' => $ids['casts'][1], 'shop_id' => $shop]);
        }
        $message = function (int $offset, string $who, int $sender, int $type, string $content, CarbonImmutable $at) use ($add, $base, $shop): void {
            $add('messages', ['id' => $base + $offset, 'cast_id' => $who, 'shop_id' => $shop, 'sender_type' => $sender,
                'type' => $type, 'content' => $content, 'is_read' => 1, 'deleted_at' => null,
                'created_at' => $at->toDateTimeString(), 'updated_at' => $at->toDateTimeString()], ['cast_id' => $who, 'shop_id' => $shop]);
        };
        $message(20, $ids['casts'][1], 2, 1, '【実演用】プロフィールを拝見しました。体験入店についてお話しできますか？', $now->subDays(2));
        $message(21, $ids['casts'][1], 1, 1, '【実演用】ありがとうございます。出勤できる曜日から相談したいです。', $now->subDay());
        if ($scenario !== 'start') {
            $isBonus = in_array($scenario, ['invoice', 'completed'], true);
            $talkAt = $isBonus ? $now->subDays(40) : $now->subDays(2);
            $meeting = ($isBonus ? $now->subDays(38) : $now->addDays(2))->setTime(19, 0);
            $add('shop_job_applications', ['id' => $base, 'cast_id' => $cast, 'shop_job_id' => $base,
                'status' => $isBonus ? 4 : 3, 'talk_job_kind' => 'fulltime',
                'result_date' => $isBonus ? $now->subDays(37)->toDateString() : null,
                'real_start_date' => $isBonus ? $now->subDays(36)->toDateString() : null,
                'hourly_wage_regular' => $isBonus ? '4000' : null, 'hired_bonus_amount' => $isBonus ? 100000 : null,
                'hired_bonus_condition' => $isBonus ? $job['bonus_condition'] : null,
                'created_at' => $talkAt->toDateTimeString(), 'updated_at' => $stamp] + self::snapshot($job), ['cast_id' => $cast, 'shop_job_id' => $base]);
            $message(1, $cast, 2, 1, '【実演用】落ち着いた接客が好き、というお話を拝見しました。直接条件をお話しできればと思います。', $talkAt);
            $message(2, $cast, 1, 1, '【実演用】ありがとうございます。週2〜3回、夜の時間帯で相談したいです。', $talkAt->addHour());
            $token = 'sales-demo-' . $slot;
            $message(3, $cast, 2, 2, self::json(['offer_token' => $token, 'options' => [$meeting->toDateTimeString(), $meeting->addDay()->toDateTimeString()]]), $talkAt->addHours(2));
            $message(4, $cast, 1, 3, self::json(['offer_token' => $token, 'selected_option' => $meeting->toDateTimeString()]), $talkAt->addHours(3));
            if ($isBonus) {
                $message(5, $cast, 2, 4, '【実演用】本入店の採用が決まりました。時給4,000円、採用報酬10万円、求人に記載した条件でお迎えします。', $now->subDays(37));
                $status = $scenario === 'completed' ? 7 : 3;
                $add('application_deposits', ['id' => $base, 'shop_job_application_id' => $base, 'status' => $status,
                    'is_read' => 0, 'invoice_number' => 'DEMO-' . $slot . '-' . $now->format('Ymd'), 'bonus_amount' => 100000,
                    'system_fee_amount' => 10000, 'invoice_amount' => 110000, 'cast_transfer_amount' => 100000,
                    'invoice_issued_at' => $now->subDays(4)->toDateTimeString(), 'invoice_due_date' => $now->addDays(3)->toDateString(),
                    'invoice_sent_at' => null, 'shop_payment_reported_at' => $status === 7 ? $now->subDays(3)->toDateTimeString() : null,
                    'shop_payment_reported_amount' => $status === 7 ? 110000 : null, 'shop_payment_reference' => 'DEMO・実際の送金なし',
                    'shop_payment_confirmed_at' => $status === 7 ? $now->subDays(2)->toDateTimeString() : null,
                    'cast_transferred_at' => $status === 7 ? $now->subDay()->toDateTimeString() : null,
                    'cast_transfer_reference' => 'DEMO・実際の送金なし', 'cast_transfer_note' => '営業説明用の架空の進捗です。',
                    'completed_at' => $status === 7 ? $stamp : null, 'created_at' => $now->subDays(6)->toDateTimeString(), 'updated_at' => $stamp],
                    ['shop_job_application_id' => $base]);
                for ($i = 1; $i <= $status; $i++) {
                    $at = $now->subDays(7 - $i)->toDateTimeString();
                    $add('application_deposit_histories', ['id' => $base + $i, 'application_deposit_id' => $base,
                        'status' => $i, 'status_date' => $at, 'created_at' => $at], ['application_deposit_id' => $base]);
                }
            }
        }

        return ['slot' => $slot, 'scenario' => $scenario, 'ids' => $ids, 'rows' => $rows];
    }

    private static function snapshot(array $job): array
    {
        $keys = ['regular_status', 'regular_hourly_wage', 'norma_day', 'norma_hours', 'bonus_reward', 'bonus_remarks',
            'bonus_condition', 'trial_hourly_wage', 'trial_status', 'has_help', 'help_hourly_wage', 'help_status',
            'working_day', 'working_hours', 'regular_holiday', 'qualification', 'shift_time_start', 'shift_time_end',
            'shift_end_is_last', 'regular_hourly_wage_max'];
        $result = [];
        foreach ($keys as $key) {
            $result['applied_' . $key] = $job[$key];
        }
        return $result;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
