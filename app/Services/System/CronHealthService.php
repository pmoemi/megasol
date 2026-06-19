<?php

namespace App\Services\System;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CronHealthService
{
    public const KEY_SCHEDULER_HEARTBEAT = 'system.scheduler.last_run_at';

    public const KEY_AUTOMATIONS_LAST_RUN = 'system.scheduler.automations_last_run_at';

    public const KEY_PAYGRO_SYNC_LAST_RUN = 'system.scheduler.paygro_sync_last_run_at';

    public const HEARTBEAT_OK_MINUTES = 3;

    public const HEARTBEAT_WARNING_MINUTES = 10;

    public const AUTOMATIONS_STALE_MINUTES = 90;

    public const PAYGRO_SYNC_STALE_HOURS = 36;

    public function recordHeartbeat(): void
    {
        $this->storeTimestamp(self::KEY_SCHEDULER_HEARTBEAT);
    }

    public function recordAutomationsRun(): void
    {
        $this->storeTimestamp(self::KEY_AUTOMATIONS_LAST_RUN);
    }

    public function recordPayGroSyncRun(): void
    {
        $this->storeTimestamp(self::KEY_PAYGRO_SYNC_LAST_RUN);
    }

    /**
     * @return array{
     *     state: string,
     *     is_healthy: bool,
     *     message: string,
     *     last_run_at: ?string,
     *     last_run_human: string,
     *     minutes_since_last_run: ?int,
     *     tasks: array<int, array{
     *         key: string,
     *         label: string,
     *         command: string,
     *         frequency: string,
     *         last_run_at: ?string,
     *         last_run_human: string,
     *         state: string,
     *         message: string
     *     }>
     * }
     */
    public function status(): array
    {
        $heartbeat = $this->parseTimestamp(Setting::get(self::KEY_SCHEDULER_HEARTBEAT));
        $scheduler = $this->evaluateScheduler($heartbeat);

        return [
            'state' => $scheduler['state'],
            'is_healthy' => $scheduler['state'] === 'ok',
            'message' => $scheduler['message'],
            'last_run_at' => $heartbeat?->toIso8601String(),
            'last_run_human' => $this->formatLastRun($heartbeat),
            'minutes_since_last_run' => $this->minutesSince($heartbeat),
            'heartbeat_ok_minutes' => self::HEARTBEAT_OK_MINUTES,
            'tasks' => [
                $this->taskStatus(
                    key: 'automations',
                    label: 'SMS automations',
                    command: 'sms:run-automations',
                    frequency: 'Every hour',
                    lastRun: $this->parseTimestamp(Setting::get(self::KEY_AUTOMATIONS_LAST_RUN)),
                    staleMinutes: self::AUTOMATIONS_STALE_MINUTES,
                    neverMessage: 'Not run yet. Expected within an hour after cron is active.',
                ),
                $this->taskStatus(
                    key: 'paygro_sync',
                    label: 'PayGro sync',
                    command: 'paygro:sync --source=scheduled',
                    frequency: 'Daily',
                    lastRun: $this->parseTimestamp(Setting::get(self::KEY_PAYGRO_SYNC_LAST_RUN)),
                    staleMinutes: self::PAYGRO_SYNC_STALE_HOURS * 60,
                    neverMessage: 'Not run yet. Expected once per day after cron is active.',
                ),
            ],
        ];
    }

    /**
     * @return array{state: string, message: string}
     */
    protected function evaluateScheduler(?CarbonInterface $lastRun): array
    {
        if ($lastRun === null) {
            return [
                'state' => 'unknown',
                'message' => 'No scheduler heartbeat yet. Add the cron job below in cPanel, wait 1–2 minutes, then refresh.',
            ];
        }

        $minutesAgo = $this->minutesSince($lastRun);

        if ($minutesAgo === null) {
            return [
                'state' => 'unknown',
                'message' => 'Scheduler heartbeat timestamp is invalid.',
            ];
        }

        if ($minutesAgo <= self::HEARTBEAT_OK_MINUTES) {
            return [
                'state' => 'ok',
                'message' => "Cron appears configured. Last scheduler tick {$this->formatLastRun($lastRun)}.",
            ];
        }

        if ($minutesAgo <= self::HEARTBEAT_WARNING_MINUTES) {
            return [
                'state' => 'warning',
                'message' => "Last scheduler tick was {$minutesAgo} minute(s) ago. Cron may be delayed — verify the command path in cPanel.",
            ];
        }

        return [
            'state' => 'error',
            'message' => "Scheduler has not run recently (last tick {$this->formatLastRun($lastRun)}). Check that the MegaSol cron job is added and uses the artisan path shown below.",
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     command: string,
     *     frequency: string,
     *     last_run_at: ?string,
     *     last_run_human: string,
     *     state: string,
     *     message: string
     * }
     */
    protected function taskStatus(
        string $key,
        string $label,
        string $command,
        string $frequency,
        ?CarbonInterface $lastRun,
        int $staleMinutes,
        string $neverMessage,
    ): array {
        if ($lastRun === null) {
            return [
                'key' => $key,
                'label' => $label,
                'command' => $command,
                'frequency' => $frequency,
                'last_run_at' => null,
                'last_run_human' => 'Never',
                'state' => 'unknown',
                'message' => $neverMessage,
            ];
        }

        $minutesAgo = $this->minutesSince($lastRun) ?? 0;
        $state = $minutesAgo <= $staleMinutes ? 'ok' : 'warning';
        $message = $state === 'ok'
            ? "Last run {$this->formatLastRun($lastRun)}."
            : "Last run {$this->formatLastRun($lastRun)} — overdue for {$frequency} schedule.";

        return [
            'key' => $key,
            'label' => $label,
            'command' => $command,
            'frequency' => $frequency,
            'last_run_at' => $lastRun->toIso8601String(),
            'last_run_human' => $this->formatLastRun($lastRun),
            'state' => $state,
            'message' => $message,
        ];
    }

    protected function storeTimestamp(string $key): void
    {
        Setting::set($key, now()->toIso8601String());
    }

    protected function parseTimestamp(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function minutesSince(?CarbonInterface $timestamp): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        return (int) $timestamp->diffInMinutes(now());
    }

    protected function formatLastRun(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return 'never';
        }

        return $timestamp->diffForHumans(['parts' => 2, 'short' => false]);
    }
}
