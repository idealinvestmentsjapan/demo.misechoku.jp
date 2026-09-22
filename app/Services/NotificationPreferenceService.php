<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user notification preferences.
 *
 * - Channel toggles (push_enabled / line_enabled) and category toggles
 *   (notify_*_enabled) gate EXTERNAL delivery (Web Push / LINE) only.
 *   In-app inbox records are always created regardless of these flags.
 * - Reminder toggles (interview/deadline) fully suppress their reminders.
 */
class NotificationPreferenceService
{
    /** Category key => notification_preferences column. */
    public const CATEGORY_COLUMNS = [
        'talk' => 'notify_talk_enabled',
        'selection' => 'notify_selection_enabled',
        'billing' => 'notify_billing_enabled',
        'verification' => 'notify_verification_enabled',
        'review' => 'notify_review_enabled',
    ];

    /**
     * notifications.type => category key.
     * Types not listed here (admin-directed, reminders) bypass category gating.
     */
    private const TYPE_CATEGORY_MAP = [
        'talk.message_received' => 'talk',
        'talk.interview_offer' => 'selection',
        'talk.interview_confirmed' => 'selection',
        'talk.hired' => 'selection',
        'talk.rejected' => 'selection',
        'talk.status_changed' => 'selection',
        'billing.invoice_issued' => 'billing',
        'billing.payment_confirmed' => 'billing',
        'billing.cast_transferred' => 'billing',
        'verification.cast_approved' => 'verification',
        'verification.cast_rejected' => 'verification',
        'verification.shop_approved' => 'verification',
        'verification.shop_rejected' => 'verification',
        'review.posted' => 'review',
    ];

    private static ?bool $hasCategoryColumns = null;

    public static function categoryForType(string $type): ?string
    {
        return self::TYPE_CATEGORY_MAP[$type] ?? null;
    }

    public function get(string $userType, string $userId): array
    {
        if (!Schema::hasTable('notification_preferences')) {
            return $this->defaults();
        }

        $row = DB::table('notification_preferences')
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->first();

        if (!$row) {
            return $this->defaults();
        }

        $prefs = [
            'push_enabled' => (bool) $row->push_enabled,
            'line_enabled' => (bool) $row->line_enabled,
            'interview_reminder_enabled' => (bool) $row->interview_reminder_enabled,
            'deadline_reminder_enabled' => (bool) $row->deadline_reminder_enabled,
        ];
        foreach (self::CATEGORY_COLUMNS as $column) {
            // Columns may not exist yet on an un-migrated DB; default to enabled.
            $prefs[$column] = (bool) ($row->{$column} ?? true);
        }

        return $prefs;
    }

    public function save(string $userType, string $userId, array $prefs): void
    {
        if (!Schema::hasTable('notification_preferences')) {
            return;
        }

        $values = [
            'push_enabled' => (bool) ($prefs['push_enabled'] ?? true),
            'line_enabled' => (bool) ($prefs['line_enabled'] ?? true),
            'interview_reminder_enabled' => (bool) ($prefs['interview_reminder_enabled'] ?? true),
            'deadline_reminder_enabled' => (bool) ($prefs['deadline_reminder_enabled'] ?? true),
            'updated_at' => now(),
            'created_at' => now(),
        ];
        if ($this->hasCategoryColumns()) {
            foreach (self::CATEGORY_COLUMNS as $column) {
                $values[$column] = (bool) ($prefs[$column] ?? true);
            }
        }

        DB::table('notification_preferences')->updateOrInsert(
            ['user_type' => $userType, 'user_id' => $userId],
            $values
        );
    }

    /**
     * Whether external channels (push/LINE) are allowed for this notification type.
     * Unknown types are always allowed.
     */
    public function categoryEnabled(array $prefs, string $type): bool
    {
        $category = self::categoryForType($type);
        if ($category === null) {
            return true;
        }
        $column = self::CATEGORY_COLUMNS[$category];

        return (bool) ($prefs[$column] ?? true);
    }

    private function hasCategoryColumns(): bool
    {
        if (self::$hasCategoryColumns === null) {
            try {
                self::$hasCategoryColumns = Schema::hasColumn('notification_preferences', 'notify_talk_enabled');
            } catch (\Throwable) {
                self::$hasCategoryColumns = false;
            }
        }

        return self::$hasCategoryColumns;
    }

    private function defaults(): array
    {
        $defaults = [
            'push_enabled' => true,
            'line_enabled' => true,
            'interview_reminder_enabled' => true,
            'deadline_reminder_enabled' => true,
        ];
        foreach (self::CATEGORY_COLUMNS as $column) {
            $defaults[$column] = true;
        }

        return $defaults;
    }
}
