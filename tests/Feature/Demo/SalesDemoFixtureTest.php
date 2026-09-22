<?php

namespace Tests\Feature\Demo;

use App\Support\SalesDemoAccess;
use App\Support\SalesDemoFixture;
use App\Support\SalesDemoSql;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Pure tests: no Laravel application, database, migrations or notifications are started. */
final class SalesDemoFixtureTest extends TestCase
{
    private function plan(string $scene = 'start', int $slot = 1): array
    {
        return SalesDemoFixture::build($slot, $scene, CarbonImmutable::parse('2026-09-12 10:00:00', 'Asia/Tokyo'));
    }

    private function rows(array $plan, string $table): array
    {
        return array_values(array_map(fn ($row) => $row['values'], array_filter($plan['rows'], fn ($row) => $row['table'] === $table)));
    }

    public function test_every_scene_uses_only_columns_in_the_reference_schema(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 3) . '/database/mock_demo.sql');
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`\s*\((.*?)\)\s*(?:ENGINE=[^;]+)?;/s', $schema, $tables, PREG_SET_ORDER);
        $columns = [];
        foreach ($tables as $table) {
            preg_match_all('/^\s*`([^`]+)`\s+/m', $table[2], $found);
            $columns[$table[1]] = $found[1];
        }
        foreach (array_keys(SalesDemoFixture::SCENARIOS) as $scene) {
            $seen = [];
            foreach ($this->plan($scene)['rows'] as $row) {
                $this->assertArrayHasKey($row['table'], $columns);
                $this->assertSame([], array_values(array_diff(array_keys($row['values']), $columns[$row['table']])), $row['table']);
                $key = $row['table'] . ':' . $row['values']['id'];
                $this->assertArrayNotHasKey($key, $seen, 'Duplicate fixture key');
                $seen[$key] = true;
                $this->assertNotEmpty($row['owner']);
                foreach ($row['owner'] as $field => $value) {
                    $this->assertSame($value, $row['values'][$field]);
                }
            }
        }
    }

    public function test_slots_do_not_share_primary_keys_and_keep_actor_ids_within_schema_limits(): void
    {
        $seen = [];
        for ($slot = 1; $slot <= 20; $slot++) {
            $plan = $this->plan('completed', $slot);
            foreach ($plan['rows'] as $row) {
                $key = $row['table'] . ':' . $row['values']['id'];
                $this->assertArrayNotHasKey($key, $seen);
                $seen[$key] = true;
            }
            foreach ([$plan['ids']['shop'], $plan['ids']['manager'], ...$plan['ids']['casts']] as $id) {
                $this->assertMatchesRegularExpression('/^[smc][0-9]+$/', $id);
                $this->assertLessThanOrEqual(20, strlen($id));
            }
        }
    }

    public function test_start_keeps_akari_available_for_the_first_outreach(): void
    {
        $plan = $this->plan();
        $this->assertCount(3, $this->rows($plan, 'casts'));
        $this->assertCount(0, $this->rows($plan, 'shop_job_applications'));
        $this->assertCount(0, $this->rows($plan, 'application_deposits'));
        foreach (array_merge($this->rows($plan, 'messages'), $this->rows($plan, 'favorites')) as $row) {
            $this->assertNotSame($plan['ids']['casts'][0], $row['cast_id']);
        }
        foreach ($this->rows($plan, 'messages') as $row) {
            $this->assertLessThan('2026-09-12 00:00:00', $row['created_at']);
        }
    }

    public function test_interview_messages_agree_with_each_other_and_the_application(): void
    {
        $plan = $this->plan('interview');
        $messages = array_column($this->rows($plan, 'messages'), null, 'id');
        $base = $plan['ids']['base'];
        $offer = json_decode($messages[$base + 3]['content'], true, 512, JSON_THROW_ON_ERROR);
        $confirmed = json_decode($messages[$base + 4]['content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($offer['offer_token'], $confirmed['offer_token']);
        $this->assertContains($confirmed['selected_option'], $offer['options']);
        $this->assertGreaterThan('2026-09-12', $confirmed['selected_option']);
        $application = $this->rows($plan, 'shop_job_applications')[0];
        $this->assertSame(3, $application['status']);
        $this->assertSame('fulltime', $application['talk_job_kind']);
        $this->assertSame(100000, $application['applied_bonus_reward']);
    }

    public function test_invoice_and_receipt_history_amounts_and_dates_are_consistent(): void
    {
        foreach (['invoice' => 3, 'completed' => 7] as $scene => $status) {
            $plan = $this->plan($scene);
            $deposit = $this->rows($plan, 'application_deposits')[0];
            $history = $this->rows($plan, 'application_deposit_histories');
            $this->assertSame($status, $deposit['status']);
            $this->assertSame(range(1, $status), array_column($history, 'status'));
            $this->assertSame($deposit['bonus_amount'] + $deposit['system_fee_amount'], $deposit['invoice_amount']);
            $this->assertSame($deposit['bonus_amount'], $deposit['cast_transfer_amount']);
            $this->assertSame($status === 7, $deposit['completed_at'] !== null);
            $this->assertNull($deposit['invoice_sent_at'], 'No actual email is sent');
            $application = $this->rows($plan, 'shop_job_applications')[0];
            $this->assertSame(4, $application['status']);
            $this->assertLessThan($history[0]['status_date'], $application['real_start_date']);
        }
    }

    public function test_preparation_is_repeatable_and_dates_refresh_with_the_day(): void
    {
        $first = $this->plan('completed');
        $this->assertSame($first, $this->plan('completed'));
        $later = SalesDemoFixture::build(1, 'completed', CarbonImmutable::parse('2026-10-01 10:00:00', 'Asia/Tokyo'));
        $this->assertSame($first['ids'], $later['ids']);
        $this->assertNotSame($this->rows($first, 'application_deposits')[0]['invoice_due_date'], $this->rows($later, 'application_deposits')[0]['invoice_due_date']);
    }

    public function test_external_notifications_are_disabled_and_images_are_bundled(): void
    {
        $plan = $this->plan();
        foreach ($this->rows($plan, 'notification_preferences') as $row) {
            foreach (['push_enabled', 'line_enabled', 'interview_reminder_enabled', 'deadline_reminder_enabled'] as $key) {
                $this->assertSame(0, $row[$key]);
            }
        }
        foreach (array_merge($this->rows($plan, 'cast_images'), $this->rows($plan, 'shop_images')) as $row) {
            $this->assertFileExists(dirname(__DIR__, 3) . '/public/' . $row['image_path']);
        }
        foreach ($this->rows($plan, 'casts') as $row) {
            $this->assertStringEndsWith('@sales-demo.invalid', $row['email']);
            $this->assertNull($row['password']);
        }
    }

    public function test_production_unknown_hosts_and_disabled_flags_are_denied(): void
    {
        $hosts = ['demo.misechoku.jp', 'localhost'];
        $this->assertTrue(SalesDemoAccess::allowed(true, true, 'demo', 'demo.misechoku.jp', $hosts));
        foreach ([[false, true, 'demo', 'demo.misechoku.jp'], [true, false, 'demo', 'demo.misechoku.jp'],
            [true, true, 'production', 'demo.misechoku.jp'], [true, true, 'demo', 'misechoku.jp'],
            [true, true, 'demo', 'demo.misechoku.jp.attacker.invalid']] as $args) {
            $this->assertFalse(SalesDemoAccess::allowed(...[...$args, $hosts]));
        }
    }

    public function test_sql_is_initial_insert_only_and_every_insert_is_guarded(): void
    {
        $plan = $this->plan('completed');
        $sql = SalesDemoSql::render($plan);
        $this->assertSame(count($plan['rows']), substr_count($sql, 'WHERE @sales_demo_can_create = 1;'));
        $this->assertStringNotContainsString('DELETE ', $sql);
        $this->assertStringNotContainsString('UPDATE ', $sql);
        $this->assertStringNotContainsString('DROP ', $sql);
        $this->assertStringContainsString('START TRANSACTION;', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
        $this->assertStringContainsString(bin2hex('デモ01・あかり'), $sql);
    }

    public function test_out_of_range_slots_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->plan('start', 21);
    }

    public function test_unknown_scenes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->plan('delete-all');
    }
}
