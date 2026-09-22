<?php

namespace App\Support;

/** Initial installation only. Existing sets are reset through SalesDemoService. */
final class SalesDemoSql
{
    public static function render(array $plan): string
    {
        $checks = [];
        foreach ($plan['rows'] as $row) {
            $checks[] = 'NOT EXISTS (SELECT 1 FROM `' . $row['table'] . '` WHERE `id` = ' . self::literal($row['values']['id']) . ')';
            if (isset($row['values']['email'])) {
                $checks[] = 'NOT EXISTS (SELECT 1 FROM `' . $row['table'] . '` WHERE `email` = ' . self::literal($row['values']['email']) . ')';
            }
        }
        $checks[] = 'EXISTS (SELECT 1 FROM `industries` WHERE `id` = 1 AND `del_flg` = 0)';
        $checks[] = '(SELECT COUNT(*) FROM `cast_tags` WHERE `id` IN (37,40,45) AND `del_flg` = 0) = 3';
        $sql = "-- 営業デモ初回投入用 / MySQL 8.0 / セット {$plan['slot']} / {$plan['scenario']}\n"
            . "-- デモ専用DBで開発担当者が実行してください。既存のセットは変更しません。\n"
            . "-- 実行エラー時は ROLLBACK。mysql --force（エラー後の続行）は使用しないでください。\n"
            . "-- 文字列はSQLモードに左右されないUTF-8の16進数リテラルです。定義は SalesDemoFixture.php。\n"
            . "START TRANSACTION;\nSET @sales_demo_can_create = (\n  " . implode("\n  AND ", $checks) . "\n);\n";
        foreach ($plan['rows'] as $row) {
            $columns = implode(', ', array_map(fn ($key) => '`' . $key . '`', array_keys($row['values'])));
            $values = implode(', ', array_map([self::class, 'literal'], array_values($row['values'])));
            $sql .= "INSERT INTO `{$row['table']}` ($columns)\nSELECT $values FROM DUAL WHERE @sales_demo_can_create = 1;\n";
        }
        return $sql . "COMMIT;\nSELECT IF(@sales_demo_can_create = 1, 'created', 'skipped: existing IDs or missing masters') AS sales_demo_result;\n";
    }

    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "_utf8mb4 X'" . bin2hex((string) $value) . "'";
    }
}
