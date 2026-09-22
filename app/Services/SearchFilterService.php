<?php

namespace App\Services;

use Carbon\Carbon;

class SearchFilterService
{
    public function matchesAvailability(array $filters, array $availability): bool
    {
        $frequency = (string) ($filters['shift_frequency'] ?? '');
        if ($frequency !== '' && $frequency !== ($availability['shift_frequency'] ?? null)) {
            return false;
        }

        $periods = (array) ($filters['work_periods'] ?? []);

        return $periods === [] || array_intersect($periods, (array) ($availability['work_periods'] ?? [])) !== [];
    }

    public function matchesCast(object $cast, array $filters, array $tags, array $availability): bool
    {
        $ageMin = (int) ($filters['age_min'] ?? 0);
        $ageMax = (int) ($filters['age_max'] ?? 0);
        if ($ageMin > 0 || $ageMax > 0) {
            if (empty($cast->birthday)) {
                return false;
            }
            $age = Carbon::parse($cast->birthday)->age;
            if (($ageMin > 0 && $age < $ageMin) || ($ageMax > 0 && $age > $ageMax)) {
                return false;
            }
        }
        foreach (['looks', 'personality'] as $category) {
            $selected = array_map('intval', (array) ($filters[$category . '_tag_ids'] ?? []));
            if ($selected !== [] && array_intersect($selected, (array) ($tags[$category] ?? [])) === []) {
                return false;
            }
        }
        $experience = (string) ($filters['night_work_exp'] ?? '');
        if (in_array($experience, ['yes', 'none'], true)) {
            if (!isset($cast->exp) || (int) $cast->exp !== ($experience === 'yes' ? 1 : 0)) {
                return false;
            }
        }

        return $this->matchesAvailability($filters, $availability);
    }
}
