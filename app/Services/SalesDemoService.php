<?php

namespace App\Services;

use App\Models\SalesDemoRecord;
use App\Support\SalesDemoAccess;
use App\Support\SalesDemoFixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SalesDemoService
{
    public function prepare(int $slot, string $scenario): array
    {
        if (!SalesDemoAccess::enabled()) {
            throw new RuntimeException('営業デモ作成は、この環境では有効になっていません。');
        }
        $plan = SalesDemoFixture::build($slot, $scenario, CarbonImmutable::now());

        return Cache::store('file')->lock('sales-demo-slot-' . $slot, 120)->block(3, function () use ($plan) {
            return DB::transaction(function () use ($plan) {
                $this->checkSchemaAndOwnership($plan);
                $this->clearScenario($plan['ids']);
                foreach ($plan['rows'] as $row) {
                    $model = SalesDemoRecord::forTable($row['table']);
                    $record = $model->newQuery()->find($row['values']['id']) ?? $model;
                    $record->forceFill($row['values'])->saveQuietly();
                }
                return $plan;
            });
        });
    }

    private function checkSchemaAndOwnership(array $plan): void
    {
        $columns = [];
        // Validate every possible scene, including IDs absent from the selected scene.
        $complete = SalesDemoFixture::build($plan['slot'], 'completed', CarbonImmutable::now());
        foreach ($complete['rows'] as $row) {
            $table = $row['table'];
            $columns[$table] ??= Schema::getColumnListing($table);
            $missing = array_diff(array_keys($row['values']), $columns[$table]);
            if ($missing !== []) {
                throw new RuntimeException($table . ' の構造が未対応です。開発担当者へ確認してください：' . implode(', ', $missing));
            }
            $query = SalesDemoRecord::forTable($table)->newQuery();
            $existing = (clone $query)->whereKey($row['values']['id'])->lockForUpdate()->first();
            if ($existing) {
                foreach ($row['owner'] as $key => $value) {
                    if ((string) $existing->getRawOriginal($key) !== (string) $value) {
                        throw new RuntimeException('実演用の番号が別のデータで使用されています。変更せず中止しました。');
                    }
                }
            }
            if (isset($row['values']['email']) && (clone $query)->where('email', $row['values']['email'])
                ->where('id', '!=', $row['values']['id'])->exists()) {
                throw new RuntimeException('実演用メールアドレスが別の番号で使用されています。変更せず中止しました。');
            }
        }
        if (!SalesDemoRecord::forTable('industries')->newQuery()->where('id', 1)->where('name', 'キャバクラ')->where('del_flg', 0)->exists()
            || SalesDemoRecord::forTable('cast_tags')->newQuery()->whereIn('id', [37, 40, 45])->where('category', 'personality')->where('del_flg', 0)->count() !== 3) {
            throw new RuntimeException('業種・性格タグの初期データが必要です。開発担当者へ確認してください。');
        }
        foreach (['shop.svg', 'cast-1.svg', 'cast-2.svg', 'cast-3.svg'] as $file) {
            if (!is_file(public_path('assets/images/demo-sales/' . $file))) {
                throw new RuntimeException('実演用の画像がありません。プログラムと画像を一緒に配置してください。');
            }
        }
    }

    private function clearScenario(array $ids): void
    {
        $applications = SalesDemoRecord::forTable('shop_job_applications')->newQuery()
            ->where('shop_job_id', $ids['base'])->whereIn('cast_id', $ids['casts']);
        $appIds = (clone $applications)->pluck('id');
        $deposits = SalesDemoRecord::forTable('application_deposits')->newQuery()->whereIn('shop_job_application_id', $appIds);
        $depositIds = (clone $deposits)->pluck('id');
        SalesDemoRecord::forTable('application_deposit_histories')->newQuery()->whereIn('application_deposit_id', $depositIds)->delete();
        if (Schema::hasTable('payment_tasks')) {
            SalesDemoRecord::forTable('payment_tasks')->newQuery()->whereIn('application_deposit_id', $depositIds)->delete();
        }
        $deposits->delete();
        $applications->delete();
        foreach (['messages', 'favorites', 'talk_blocks', 'cast_shop_relation'] as $table) {
            SalesDemoRecord::forTable($table)->newQuery()->where('shop_id', $ids['shop'])->whereIn('cast_id', $ids['casts'])->delete();
        }
        foreach (['cast_images', 'cast_posts', 'cast_search_preferences', 'cast_tag_relations'] as $table) {
            SalesDemoRecord::forTable($table)->newQuery()->whereIn('cast_id', $ids['casts'])->delete();
        }
        SalesDemoRecord::forTable('shop_images')->newQuery()->where('shop_id', $ids['shop'])->delete();
        foreach (['notification_preferences', 'push_subscriptions'] as $table) {
            SalesDemoRecord::forTable($table)->newQuery()->where('user_type', 'cast')->whereIn('user_id', $ids['casts'])->delete();
            SalesDemoRecord::forTable($table)->newQuery()->where('user_type', 'shop')->where('user_id', $ids['manager'])->delete();
        }
    }
}
