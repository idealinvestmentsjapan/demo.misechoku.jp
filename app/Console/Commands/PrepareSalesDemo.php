<?php

namespace App\Console\Commands;

use App\Services\SalesDemoService;
use App\Support\SalesDemoFixture;
use App\Support\SalesDemoSql;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class PrepareSalesDemo extends Command
{
    protected $signature = 'demo:sales {--slot=1 : 営業セット番号（1〜20）} {--scene=start : start/interview/invoice/completed} {--write : DBに作成・復元する} {--sql= : 初回投入用SQLの保存先（DB接続なし）}';

    protected $description = '営業台本に沿う架空データを準備する。既定では内容の確認のみ。';

    public function handle(SalesDemoService $service): int
    {
        try {
            $slot = filter_var($this->option('slot'), FILTER_VALIDATE_INT);
            if ($slot === false || ($this->option('write') && $this->option('sql'))) {
                throw new \InvalidArgumentException('セット番号、または --write / --sql の指定を確認してください。');
            }
            $plan = SalesDemoFixture::build($slot, (string) $this->option('scene'), CarbonImmutable::now());
            if ($path = $this->option('sql')) {
                if (file_put_contents($path, SalesDemoSql::render($plan)) === false) {
                    throw new \RuntimeException('SQLファイルを保存できません。');
                }
                $this->info('SQLを書き出しました。DBへの接続・変更はしていません。');
            } elseif ($this->option('write')) {
                $service->prepare($slot, $plan['scenario']);
                $this->info('営業デモを準備しました。');
            } else {
                $this->info('確認のみです。DBへの接続・変更はしていません。');
            }
            $this->table(['項目', '内容'], [
                ['営業セット', $slot], ['場面', SalesDemoFixture::SCENARIOS[$plan['scenario']]['label']],
                ['店舗', $plan['ids']['shop']], ['店舗担当者', $plan['ids']['manager']],
                ['キャスト', implode(', ', $plan['ids']['casts'])], ['準備する行数', count($plan['rows'])],
            ]);
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
