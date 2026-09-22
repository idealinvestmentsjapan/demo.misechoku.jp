<?php

namespace App\Support;

final class SalesDemoAccess
{
    public static function allowed(bool $demo, bool $sales, string $environment, string $host, array $hosts): bool
    {
        return $demo && $sales && in_array($environment, ['local', 'testing', 'demo', 'staging'], true)
            && in_array(strtolower($host), array_map('strtolower', $hosts), true);
    }

    public static function enabled(?string $host = null): bool
    {
        return self::allowed((bool) config('demo.enabled'), (bool) config('demo.sales_enabled'),
            app()->environment(), $host ?? (string) parse_url(config('app.url'), PHP_URL_HOST),
            config('demo.sales_hosts', []));
    }
}
