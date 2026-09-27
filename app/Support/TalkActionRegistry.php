<?php

namespace App\Support;

/**
 * Central registry of talk actions and their authorization rules.
 *
 * Extracted from TalkController::action() — previously the allow/deny logic
 * lived in inline `abort_if()` calls sprinkled through a large method.
 * Consolidating here makes the policy testable and easy to audit at a glance.
 *
 * Semantic:
 *   - "cast-only"  actions can only be triggered by the member (cast) guard
 *   - "shop-only"  actions can only be triggered by the shop guard
 *   - "both-side"  actions are allowed from either side
 */
final class TalkActionRegistry
{
    /**
     * All known action types. Any value not in this list is rejected upstream
     * (e.g. request validation `Rule::in(TalkActionRegistry::allTypes())`).
     */
    public const CAST_ONLY = [
        'interview_confirm',
        'interview_cancel_accept',
        'work_complete_report',
        'bonus_achievement_report',
    ];

    public const SHOP_ONLY = [
        'interview_offer',
        'interview_cancel_request',   // shop-side cancel request (cast accepts via interview_cancel_accept)
        'hired',
        'rejected',
        'cancel_status',
        // Edit hire terms (job kind + hourly wage) after the hired auto-message
        // was sent. Only allowed before the cast files 勤務完了報告 (deposit exists),
        // since after that the billing rows are cut.
        'update_hire_terms',
    ];

    public const BOTH_SIDE = [
        'set_job_kind',
    ];

    /**
     * @return array<int, string>
     */
    public static function allTypes(): array
    {
        return array_merge(self::CAST_ONLY, self::SHOP_ONLY, self::BOTH_SIDE);
    }

    /**
     * Whether the given actor side is authorized to trigger the given action.
     *
     * @param  string  $actionType  One of the constants above.
     * @param  bool    $isCastPortal  True = member guard (cast), false = shop guard.
     */
    public static function isAllowed(string $actionType, bool $isCastPortal): bool
    {
        if (in_array($actionType, self::BOTH_SIDE, true)) {
            return true;
        }
        if ($isCastPortal) {
            return in_array($actionType, self::CAST_ONLY, true);
        }
        return in_array($actionType, self::SHOP_ONLY, true);
    }
}
