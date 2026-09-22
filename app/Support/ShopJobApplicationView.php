<?php

namespace App\Support;

/**
 * shop_job_applications の表示用。applied_* を最優先し、移行前の列はフォールバック。
 */
final class ShopJobApplicationView
{
    public static function normalizeWageDigits(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = preg_replace('/\D+/', '', (string) $raw);

        return $s !== '' ? $s : null;
    }

    public static function wageAtApplication(object $row): ?string
    {
        if (property_exists($row, 'applied_regular_hourly_wage')
            && $row->applied_regular_hourly_wage !== null
            && trim((string) $row->applied_regular_hourly_wage) !== '') {
            return (string) $row->applied_regular_hourly_wage;
        }
        if (property_exists($row, 'hourly_wage_regular')
            && $row->hourly_wage_regular !== null
            && trim((string) $row->hourly_wage_regular) !== '') {
            return (string) $row->hourly_wage_regular;
        }

        return null;
    }

    public static function wageAtHire(object $row): ?string
    {
        if (property_exists($row, 'hired_regular_hourly_wage')
            && $row->hired_regular_hourly_wage !== null
            && trim((string) $row->hired_regular_hourly_wage) !== '') {
            return (string) $row->hired_regular_hourly_wage;
        }
        if (property_exists($row, 'hourly_wage_regular')
            && $row->hourly_wage_regular !== null
            && trim((string) $row->hourly_wage_regular) !== '') {
            return (string) $row->hourly_wage_regular;
        }

        return null;
    }

    public static function bonusConditionAtApplication(object $row): string
    {
        if (property_exists($row, 'applied_bonus_condition') && $row->applied_bonus_condition !== null) {
            return trim((string) $row->applied_bonus_condition);
        }

        return '';
    }

    public static function bonusRewardAtApplication(object $row): ?int
    {
        if (property_exists($row, 'applied_bonus_reward') && $row->applied_bonus_reward !== null) {
            return (int) $row->applied_bonus_reward;
        }

        return null;
    }

    public static function bonusNormaSummaryAtApplication(object $row): string
    {
        $parts = [];
        if (property_exists($row, 'applied_norma_day') && $row->applied_norma_day !== null && $row->applied_norma_day !== '') {
            $parts[] = '在籍日数 ' . (int) $row->applied_norma_day . '日';
        }
        if (property_exists($row, 'applied_norma_hours') && $row->applied_norma_hours !== null && $row->applied_norma_hours !== '') {
            $parts[] = '1日の勤務 ' . (int) $row->applied_norma_hours . '時間';
        }

        return implode(' / ', $parts);
    }

    /**
     * 採用一覧など：応募時点の求人の要約（PR以外の条件系）
     *
     * @return list<string>
     */
    public static function appliedJobSummaryLines(object $row): array
    {
        $lines = [];
        $w = self::wageAtApplication($row);
        if ($w !== null && $w !== '') {
            $lines[] = '本入時給（応募時）: ' . $w . '円';
        }
        $trial = property_exists($row, 'applied_trial_hourly_wage') ? $row->applied_trial_hourly_wage : null;
        if ($trial !== null && trim((string) $trial) !== '') {
            $lines[] = '体験時給（応募時）: ' . $trial . '円';
        }
        $help = property_exists($row, 'applied_help_hourly_wage') ? $row->applied_help_hourly_wage : null;
        if ($help !== null && trim((string) $help) !== '') {
            $lines[] = 'ヘルプ時給（応募時）: ' . $help . '円';
        }
        $norma = self::bonusNormaSummaryAtApplication($row);
        if ($norma !== '') {
            $lines[] = 'ボーナス条件（応募時）: ' . $norma;
        }
        $br = self::bonusRewardAtApplication($row);
        if ($br !== null && $br > 0) {
            $lines[] = 'ボーナス金額（応募時）: ¥' . number_format($br);
        }
        $remarks = property_exists($row, 'applied_bonus_remarks') && $row->applied_bonus_remarks !== null
            ? trim((string) $row->applied_bonus_remarks)
            : '';
        if ($remarks !== '') {
            $lines[] = 'ボーナス補足: ' . $remarks;
        }
        $cond = self::bonusConditionAtApplication($row);
        if ($cond !== '') {
            $lines[] = '達成条件: ' . $cond;
        }
        foreach (['applied_working_day' => '勤務日', 'applied_working_hours' => '勤務時間', 'applied_regular_holiday' => '定休日', 'applied_qualification' => '応募資格'] as $col => $label) {
            if (property_exists($row, $col) && $row->{$col} !== null && trim((string) $row->{$col}) !== '') {
                $lines[] = $label . ': ' . trim((string) $row->{$col});
            }
        }

        return $lines;
    }
}
