<?php

namespace App\Console\Commands;

use App\Services\Integrations\PayGroService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PayGroSyncCommand extends Command
{
    protected $signature = 'paygro:sync
        {--from=    : Override sync start date (YYYY-MM-DD)}
        {--to=      : Override sync end date (YYYY-MM-DD)}
        {--force-login : Force a fresh PayGro login before syncing, regardless of session age}
        {--no-lock  : Skip the cache lock (use only when debugging overlapping runs is needed)}
        {--source=  : Tag the sync source in logs (default: command)}';

    protected $description = 'Sync customers from PayGro — refreshes the session automatically and prevents parallel runs';

    public function handle(PayGroService $payGro): int
    {
        $lockSeconds = (int) config('paygro.sync_lock_seconds', 600);
        $useLock = ! $this->option('no-lock');
        $lockKey = 'paygro_sync_lock';

        if ($useLock) {
            $lock = Cache::lock($lockKey, $lockSeconds);

            if (! $lock->get()) {
                $this->warn('PayGro sync is already running. Skipping this run.');

                return self::SUCCESS;
            }
        }

        $source = (string) ($this->option('source') ?: 'command');

        try {
            $this->info('Starting PayGro customer sync...');

            if ($this->option('force-login')) {
                $this->info('Force-login requested — refreshing PayGro session...');
                $result = $payGro->login();

                if (! $result['success']) {
                    $this->error('PayGro login failed: '.$result['message']);

                    return self::FAILURE;
                }

                $this->info('Session refreshed: '.$result['refreshed_at']);
            } else {
                $payGro->ensureAuthenticated();
            }

            $from = $this->option('from') ?: null;
            $to = $this->option('to') ?: null;

            $result = $payGro->syncCustomers(
                startDate: $from,
                endDate: $to,
                markFirstSync: false,
                syncSource: $source,
            );

            $this->info("Sync {$result['status']}: {$result['processed']} processed, {$result['failed']} failed.");

            return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('PayGro sync failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($useLock && isset($lock)) {
                $lock->release();
            }
        }
    }
}
