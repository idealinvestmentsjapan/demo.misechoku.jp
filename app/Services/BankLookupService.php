<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BankLookupService
{
    private const BANKS_CACHE_KEY = 'bank_lookup.banks';
    private const BANKS_URL = 'https://bank.teraren.com/banks.json';
    private const BRANCHES_URL_TEMPLATE = 'https://bank.teraren.com/banks/%s/branches.json';

    public function searchBanks(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        return $this->banks()
            ->filter(fn (array $bank) => $this->matchesQuery($bank, $query))
            ->take($limit)
            ->values()
            ->all();
    }

    public function searchBranches(string $bankCode, string $query = '', int $limit = 30): array
    {
        $bankCode = trim($bankCode);

        if (!preg_match('/^\d{4}$/', $bankCode)) {
            return [];
        }

        $query = trim($query);

        return $this->branches($bankCode)
            ->when($query !== '', fn (Collection $branches) => $branches->filter(
                fn (array $branch) => $this->matchesQuery($branch, $query)
            ))
            ->take($limit)
            ->values()
            ->all();
    }

    public function findBankByCode(string $bankCode): ?array
    {
        $bankCode = trim($bankCode);

        if (!preg_match('/^\d{4}$/', $bankCode)) {
            return null;
        }

        return $this->banks()
            ->first(fn (array $bank) => ($bank['code'] ?? '') === $bankCode);
    }

    public function findBranchByCode(string $bankCode, string $branchCode): ?array
    {
        $bankCode = trim($bankCode);
        $branchCode = trim($branchCode);

        if (!preg_match('/^\d{4}$/', $bankCode) || !preg_match('/^\d{3}$/', $branchCode)) {
            return null;
        }

        return $this->branches($bankCode)
            ->first(fn (array $branch) => ($branch['code'] ?? '') === $branchCode);
    }

    private function banks(): Collection
    {
        // Cache::remember caches whatever the closure returns — including empty
        // arrays produced by a transient upstream failure — for the full TTL.
        // Cache the successful payload separately with Cache::put so a bad
        // fetch never poisons the next 24 hours.
        $cached = Cache::get(self::BANKS_CACHE_KEY);
        if (is_array($cached) && count($cached) > 0) {
            return collect($cached);
        }

        $fresh = $this->fetchJson(self::BANKS_URL)
            ->map(fn (array $item) => $this->mapBank($item))
            ->all();

        if (count($fresh) > 0) {
            Cache::put(self::BANKS_CACHE_KEY, $fresh, now()->addDay());
        }

        return collect($fresh);
    }

    private function branches(string $bankCode): Collection
    {
        $cacheKey = 'bank_lookup.branches.' . $bankCode;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return collect($cached);
        }

        $url = sprintf(self::BRANCHES_URL_TEMPLATE, $bankCode);
        $fresh = $this->fetchJson($url)
            ->map(fn (array $item) => $this->mapBranch($item, $bankCode))
            ->all();

        if (count($fresh) > 0) {
            Cache::put($cacheKey, $fresh, now()->addDay());
        }

        return collect($fresh);
    }

    private function fetchJson(string $url): Collection
    {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            \Log::warning('BankLookupService fetch failed: ' . $url . ' — ' . $e->getMessage());
            return collect();
        }

        if (!$response->successful()) {
            \Log::warning('BankLookupService non-2xx: ' . $url . ' — HTTP ' . $response->status());
            return collect();
        }

        $payload = $response->json();

        return is_array($payload) ? collect($payload) : collect();
    }

    private function mapBank(array $item): array
    {
        $displayName = trim((string) data_get($item, 'normalize.name', data_get($item, 'name', '')));

        return [
            'code' => (string) ($item['code'] ?? ''),
            'name' => $displayName,
            'short_name' => (string) ($item['name'] ?? ''),
            'kana' => (string) ($item['kana'] ?? data_get($item, 'normalize.kana', '')),
            'hira' => (string) ($item['hira'] ?? data_get($item, 'normalize.hira', '')),
        ];
    }

    private function mapBranch(array $item, string $bankCode): array
    {
        $displayName = trim((string) data_get($item, 'normalize.name', data_get($item, 'name', '')));

        return [
            'bank_code' => $bankCode,
            'code' => (string) ($item['code'] ?? ''),
            'name' => $displayName,
            'short_name' => (string) ($item['name'] ?? ''),
            'kana' => (string) ($item['kana'] ?? data_get($item, 'normalize.kana', '')),
            'hira' => (string) ($item['hira'] ?? data_get($item, 'normalize.hira', '')),
        ];
    }

    private function matchesQuery(array $item, string $query): bool
    {
        $needle = mb_strtolower($query);

        foreach (['code', 'name', 'short_name', 'kana', 'hira'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));

            if ($value !== '' && mb_strpos(mb_strtolower($value), $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
