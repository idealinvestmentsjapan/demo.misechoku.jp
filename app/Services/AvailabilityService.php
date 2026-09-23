<?php

namespace App\Services;

use App\Models\AvailabilityDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate-date availability declarations.
 * Cast: dates the cast can work. Shop: dated help recruitment.
 * Up to MAX_DATES future dates within MAX_DAYS_AHEAD days.
 */
class AvailabilityService
{
    public const MAX_DATES = 5;
    public const MAX_DAYS_AHEAD = 30;

    /**
     * Future (today included) declared dates, ascending, as 'Y-m-d' strings.
     *
     * @return list<string>
     */
    public function getDates(string $ownerType, string $ownerId): array
    {
        if (!Schema::hasTable('availability_dates')) {
            return [];
        }

        return AvailabilityDate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->whereDate('available_on', '>=', Carbon::today())
            ->orderBy('available_on')
            ->pluck('available_on')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
    }

    /**
     * Replace all declared dates for the owner.
     *
     * @param list<string> $dates 'Y-m-d' strings
     * @return array{success: bool, message: string}
     */
    public function setDates(string $ownerType, string $ownerId, array $dates): array
    {
        if (!Schema::hasTable('availability_dates')) {
            return ['success' => false, 'message' => '候補日テーブルが未作成です。運営にお問い合わせください。'];
        }

        $today = Carbon::today();
        $limit = $today->copy()->addDays(self::MAX_DAYS_AHEAD);

        $normalized = [];
        foreach ($dates as $raw) {
            try {
                $date = Carbon::parse((string) $raw)->startOfDay();
            } catch (\Throwable) {
                return ['success' => false, 'message' => '日付の形式が正しくありません。'];
            }

            if ($date->lt($today)) {
                return ['success' => false, 'message' => '過去の日付は選択できません。'];
            }
            if ($date->gt($limit)) {
                return ['success' => false, 'message' => '候補日は' . self::MAX_DAYS_AHEAD . '日先まで選択できます。'];
            }

            $normalized[$date->toDateString()] = true;
        }

        $normalized = array_keys($normalized);
        sort($normalized);

        if (count($normalized) > self::MAX_DATES) {
            return ['success' => false, 'message' => '候補日は最大' . self::MAX_DATES . '日分まで設定できます。'];
        }

        DB::transaction(function () use ($ownerType, $ownerId, $normalized) {
            AvailabilityDate::query()
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->delete();

            foreach ($normalized as $date) {
                AvailabilityDate::create([
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'available_on' => $date,
                ]);
            }
        });

        return [
            'success' => true,
            'message' => $normalized === []
                ? '候補日をすべて取り消しました。'
                : '候補日を' . count($normalized) . '日分設定しました。',
        ];
    }

    /** @return array{success: bool, message: string} */
    public function clearDates(string $ownerType, string $ownerId): array
    {
        if (!Schema::hasTable('availability_dates')) {
            return ['success' => false, 'message' => '候補日テーブルが未作成です。'];
        }

        AvailabilityDate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->delete();

        return ['success' => true, 'message' => '候補日の設定を取り消しました。'];
    }

    public function isAvailableOn(string $ownerType, string $ownerId, string $date): bool
    {
        if (!Schema::hasTable('availability_dates')) {
            return false;
        }

        return AvailabilityDate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->whereDate('available_on', Carbon::parse($date)->toDateString())
            ->exists();
    }

    /**
     * Owner ids that declared the given date.
     *
     * @return list<string>
     */
    public function ownerIdsAvailableOn(string $ownerType, string $date): array
    {
        if (!Schema::hasTable('availability_dates')) {
            return [];
        }

        return AvailabilityDate::query()
            ->where('owner_type', $ownerType)
            ->whereDate('available_on', Carbon::parse($date)->toDateString())
            ->pluck('owner_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /** @return list<string> */
    public function todayOwnerIds(string $ownerType): array
    {
        return $this->ownerIdsAvailableOn($ownerType, Carbon::today()->toDateString());
    }

    /**
     * Future declared dates for many owners at once.
     *
     * @param list<string> $ownerIds
     * @return array<string, list<string>> ownerId => ['Y-m-d', ...] ascending
     */
    public function datesByOwner(string $ownerType, array $ownerIds): array
    {
        if ($ownerIds === [] || !Schema::hasTable('availability_dates')) {
            return [];
        }

        return AvailabilityDate::query()
            ->where('owner_type', $ownerType)
            ->whereIn('owner_id', $ownerIds)
            ->whereDate('available_on', '>=', Carbon::today())
            ->orderBy('available_on')
            ->get(['owner_id', 'available_on'])
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => Carbon::parse($r->available_on)->toDateString())->values()->all())
            ->all();
    }

    /**
     * Normalize a user-selected filter date. Returns null when out of range.
     */
    public function normalizeFilterDate(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            $date = Carbon::parse(trim($raw))->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $today = Carbon::today();
        if ($date->lt($today) || $date->gt($today->copy()->addDays(self::MAX_DAYS_AHEAD))) {
            return null;
        }

        return $date->toDateString();
    }

    /** Short Japanese label like '9/28' (with '本日' for today). */
    public static function shortLabel(string $date): string
    {
        $d = Carbon::parse($date);

        if ($d->isToday()) {
            return '本日';
        }

        return $d->format('n/j');
    }
}
