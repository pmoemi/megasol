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
        {--fresh    : Clear all synced PayGro data first, then re-pull everything over a wide date range}
        {--force-login : Force a fresh PayGro login before syncing, regardless of session age}
        {--no-lock  : Skip the cache lock (use only when debugging overlapping runs is needed)}
        {--source=  : Tag the sync source in logs (default: command)}';

    protected $description = 'Sync customers, unit serials, and payments from PayGro';

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

            if ($this->option('fresh')) {
                $this->info('Fresh sync requested — clearing previously synced PayGro data...');
                $cleared = $payGro->resetSyncedData();
                $this->info('Cleared: '.collect($cleared)->map(fn ($n, $t) => "$n $t")->implode(', '));

                // A wide window so the customer report and transaction history
                // come back in full (the rolling defaults are too narrow).
                $from = $from ?: now()->subYears(5)->toDateString();
                $to = $to ?: now()->addMonth()->toDateString();
            }

            $result = $payGro->syncCustomers(
                startDate: $from,
                endDate: $to,
                markFirstSync: false,
                syncSource: $source,
            );

            $this->info("Customers {$result['status']}: {$result['processed']} processed, {$result['failed']} failed.");

            $this->info('Starting PayGro payment plan sync...');
            $plans = $payGro->syncPaymentPlans(syncSource: $source);

            $this->info("Payment plans {$plans['status']}: {$plans['processed']} synced, {$plans['failed']} failed.");

            $this->info('Starting PayGro unit sync...');
            $units = $payGro->syncUnits(
                startDate: $from,
                endDate: $to,
                syncSource: $source,
            );

            $this->info("Units {$units['status']}: {$units['processed']} linked, {$units['skipped']} unmatched, {$units['failed']} failed.");

            $this->info('Starting PayGro payment sync...');
            $payments = $payGro->syncPayments(
                startDate: $from,
                endDate: $to,
                syncSource: $source,
            );

            $this->info("Payments {$payments['status']}: {$payments['processed']} imported, {$payments['skipped']} skipped, {$payments['failed']} failed.");

            $this->info('Starting PayGro repayment schedule sync...');
            $schedules = $payGro->syncRepaymentSchedules(
                startDate: $from,
                endDate: $to,
                syncSource: $source,
            );

            $this->info("Repayment schedules {$schedules['status']}: {$schedules['processed']} mapped, {$schedules['skipped']} skipped, {$schedules['failed']} failed.");

            return ($result['status'] === 'failed' || $units['status'] === 'failed' || $plans['status'] === 'failed' || $payments['status'] === 'failed' || $schedules['status'] === 'failed')
                ? self::FAILURE
                : self::SUCCESS;
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
