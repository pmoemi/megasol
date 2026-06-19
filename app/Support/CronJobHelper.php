<?php

namespace App\Support;

/**
 * Builds cPanel-ready cron command strings for the current application install.
 */
class CronJobHelper
{
    /**
     * @return array<int, array{label: string, schedule: string, shell_command: string, command: string, description: string, required: bool}>
     */
    public static function commands(?string $phpBinary = null, ?string $artisanPath = null): array
    {
        $php = $phpBinary ?? '/usr/local/bin/php';
        $artisan = $artisanPath ?? base_path('artisan');
        $queue = (string) config('queue.default', 'sync');

        $jobs = [
            [
                'label' => 'Laravel scheduler',
                'schedule' => '* * * * *',
                'shell_command' => "{$php} {$artisan} schedule:run >/dev/null 2>&1",
                'command' => "* * * * * {$php} {$artisan} schedule:run >/dev/null 2>&1",
                'description' => 'Required on production. Runs SMS automations (hourly) and PayGro sync (daily).',
                'required' => true,
            ],
        ];

        if ($queue !== 'sync') {
            $shell = "{$php} {$artisan} queue:work --stop-when-empty --max-time=55 --queue=sms,campaigns,default >/dev/null 2>&1";
            $jobs[] = [
                'label' => 'Queue worker',
                'schedule' => '* * * * *',
                'shell_command' => $shell,
                'command' => "* * * * * {$shell}",
                'description' => "Required while QUEUE_CONNECTION={$queue}. Processes queued SMS and campaign messages.",
                'required' => true,
            ];
        }

        return $jobs;
    }

    /**
     * @return array<int, array{command: string, frequency: string}>
     */
    public static function scheduledTasks(): array
    {
        return [
            ['command' => 'sms:run-automations', 'frequency' => 'Every hour'],
            ['command' => 'paygro:sync --source=scheduled', 'frequency' => 'Daily'],
        ];
    }
}
