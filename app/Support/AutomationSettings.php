<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Central, settings-driven behaviour for scheduled SMS automations. Values are
 * managed from Settings → SMS Gateway and stored in the `settings` table, with
 * safe defaults so the runner works before anything is configured.
 */
class AutomationSettings
{
    public const KEY_PAUSED = 'sms.automations_paused';
    public const KEY_COOLDOWN_HOURS = 'sms.automation_cooldown_hours';
    public const KEY_REMINDER_LEAD_DAYS = 'sms.reminder_lead_days';
    public const KEY_OVERDUE_AFTER_DAYS = 'sms.overdue_after_days';
    public const KEY_MAX_PER_RUN = 'sms.automation_max_per_run';
    public const KEY_QUIET_START = 'sms.quiet_hours_start';
    public const KEY_QUIET_END = 'sms.quiet_hours_end';

    public const DEFAULT_COOLDOWN_HOURS = 72;
    public const DEFAULT_REMINDER_LEAD_DAYS = 3;
    public const DEFAULT_OVERDUE_AFTER_DAYS = 1;
    public const DEFAULT_MAX_PER_RUN = 250;

    public static function isPaused(): bool
    {
        return filter_var(Setting::get(self::KEY_PAUSED, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Minimum hours before the same automation may message a customer again. */
    public static function cooldownHours(): int
    {
        return self::intSetting(self::KEY_COOLDOWN_HOURS, self::DEFAULT_COOLDOWN_HOURS, 0, 8760);
    }

    /** Payment reminders only go to customers due within this many days (0 = no date filter). */
    public static function reminderLeadDays(): int
    {
        return self::intSetting(self::KEY_REMINDER_LEAD_DAYS, self::DEFAULT_REMINDER_LEAD_DAYS, 0, 365);
    }

    /** Overdue reminders only go once a customer is at least this many days past due (0 = immediately). */
    public static function overdueAfterDays(): int
    {
        return self::intSetting(self::KEY_OVERDUE_AFTER_DAYS, self::DEFAULT_OVERDUE_AFTER_DAYS, 0, 365);
    }

    /** Hard cap on recipients per automation per run, preventing accidental mass-sends. */
    public static function maxPerRun(): int
    {
        return self::intSetting(self::KEY_MAX_PER_RUN, self::DEFAULT_MAX_PER_RUN, 1, 100000);
    }

    /** "HH:MM" quiet-hours start, or null when disabled. */
    public static function quietStart(): ?string
    {
        return self::timeSetting(self::KEY_QUIET_START);
    }

    /** "HH:MM" quiet-hours end, or null when disabled. */
    public static function quietEnd(): ?string
    {
        return self::timeSetting(self::KEY_QUIET_END);
    }

    /**
     * Whether the given moment falls inside the configured quiet hours. Handles
     * overnight ranges (e.g. 20:00 → 08:00). Returns false when either bound is
     * unset.
     */
    public static function inQuietHours(?Carbon $at = null): bool
    {
        $start = self::quietStart();
        $end = self::quietEnd();

        if ($start === null || $end === null || $start === $end) {
            return false;
        }

        $now = ($at ?? Carbon::now())->format('H:i');

        // Same-day window (e.g. 08:00 → 20:00).
        if ($start < $end) {
            return $now >= $start && $now < $end;
        }

        // Overnight window (e.g. 20:00 → 08:00).
        return $now >= $start || $now < $end;
    }

    protected static function intSetting(string $key, int $default, int $min, int $max): int
    {
        $value = Setting::get($key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    protected static function timeSetting(string $key): ?string
    {
        $value = trim((string) Setting::get($key, ''));

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }
}
