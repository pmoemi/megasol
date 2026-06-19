<?php

namespace App\Livewire\Settings;

use App\Models\PaygroSyncLog;
use App\Models\Setting;
use App\Services\Integrations\PayGroService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'PayGro Integration'])]
class PayGroSettings extends Component
{
    public string $paygro_base_url = '';

    public string $paygro_distributor_company_srl_no = '7';

    public string $paygro_username = '';

    public string $paygro_password = '';

    public string $paygro_report_start_date = '';

    public string $paygro_report_end_date = '';

    public string $sync_start_date = '';

    public string $sync_end_date = '';

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

    public function mount(PayGroService $payGro): void
    {
        $this->paygro_base_url = (string) $this->settingOrConfig(
            PayGroService::SETTING_BASE_URL,
            'paygro.base_url',
            'https://app-main.pay-gro.com',
        );
        $this->paygro_distributor_company_srl_no = (string) $this->settingOrConfig(
            PayGroService::SETTING_DISTRIBUTOR_ID,
            'paygro.distributor_company_srl_no',
            '7',
        );
        $this->paygro_username = (string) Setting::get(PayGroService::SETTING_USERNAME, '');
        $this->paygro_report_start_date = (string) Setting::get(PayGroService::SETTING_START_DATE, '');
        $this->paygro_report_end_date = (string) Setting::get(PayGroService::SETTING_END_DATE, '');

        $status = $payGro->getConnectionStatus();

        if (! $status['first_sync_completed']) {
            $this->sync_start_date = now()->subMonths(2)->toDateString();
            $this->sync_end_date = now()->addMonth()->toDateString();
        } else {
            $this->sync_start_date = $this->paygro_report_start_date ?: now()->subDays(60)->toDateString();
            $this->sync_end_date = $this->paygro_report_end_date ?: now()->addDays(30)->toDateString();
        }
    }

    protected function settingOrConfig(string $settingKey, string $configKey, mixed $default = ''): mixed
    {
        $fromSetting = Setting::get($settingKey);

        if ($fromSetting !== null && $fromSetting !== '') {
            return $fromSetting;
        }

        return config($configKey, $default);
    }

    protected function rules(): array
    {
        return [
            'paygro_base_url' => 'required|url|max:500',
            'paygro_distributor_company_srl_no' => 'required|integer|min:1',
            'paygro_username' => 'required|string|max:120',
            'paygro_password' => 'nullable|string|max:120',
            'paygro_report_start_date' => 'nullable|date',
            'paygro_report_end_date' => 'nullable|date',
            'sync_start_date' => 'required|date',
            'sync_end_date' => 'required|date|after_or_equal:sync_start_date',
        ];
    }

    public function save(PayGroService $payGro): void
    {
        $this->validate([
            'paygro_base_url' => 'required|url|max:500',
            'paygro_distributor_company_srl_no' => 'required|integer|min:1',
            'paygro_username' => 'required|string|max:120',
            'paygro_password' => 'nullable|string|max:120',
            'paygro_report_start_date' => 'nullable|date',
            'paygro_report_end_date' => 'nullable|date',
        ]);

        Setting::set(PayGroService::SETTING_BASE_URL, $this->paygro_base_url);
        Setting::set(PayGroService::SETTING_DISTRIBUTOR_ID, $this->paygro_distributor_company_srl_no);
        Setting::set(PayGroService::SETTING_START_DATE, $this->paygro_report_start_date);
        Setting::set(PayGroService::SETTING_END_DATE, $this->paygro_report_end_date);

        $payGro->saveCredentials(
            $this->paygro_username,
            $this->paygro_password !== '' ? $this->paygro_password : null,
        );

        if ($this->paygro_password !== '') {
            $this->paygro_password = '';
        }

        $this->flashStatus('PayGro settings saved.', false);
        session()->flash('success', 'PayGro settings saved.');
    }

    public function connect(PayGroService $payGro): void
    {
        if ($this->paygro_username === '') {
            $this->flashStatus('Enter your PayGro username before connecting.', true);

            return;
        }

        if (! $payGro->hasStoredPassword() && $this->paygro_password === '') {
            $this->flashStatus('Enter your PayGro password before connecting.', true);

            return;
        }

        Setting::set(PayGroService::SETTING_BASE_URL, $this->paygro_base_url);
        Setting::set(PayGroService::SETTING_DISTRIBUTOR_ID, $this->paygro_distributor_company_srl_no);

        $payGro->saveCredentials(
            $this->paygro_username,
            $this->paygro_password !== '' ? $this->paygro_password : null,
        );

        if ($this->paygro_password !== '') {
            $this->paygro_password = '';
        }

        $result = $payGro->login();

        if ($result['success']) {
            $this->paygro_distributor_company_srl_no = (string) $this->settingOrConfig(
                PayGroService::SETTING_DISTRIBUTOR_ID,
                'paygro.distributor_company_srl_no',
                '7',
            );
        }

        $this->flashStatus($result['message'], ! $result['success']);
    }

    public function runSync(PayGroService $payGro): void
    {
        $this->validate([
            'sync_start_date' => 'required|date',
            'sync_end_date' => 'required|date|after_or_equal:sync_start_date',
        ]);

        $status = $payGro->getConnectionStatus();
        $markFirst = ! $status['first_sync_completed'];

        // The payment step can take minutes on a first/large pull — let it
        // finish even if the browser disconnects, instead of dying mid-import.
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $result = $payGro->syncCustomers(
                $this->sync_start_date,
                $this->sync_end_date,
                $markFirst,
            );

            $plans = $payGro->syncPaymentPlans();

            $units = $payGro->syncUnits(
                $this->sync_start_date,
                $this->sync_end_date,
            );

            $payments = $payGro->syncPayments(
                $this->sync_start_date,
                $this->sync_end_date,
            );

            $schedules = $payGro->syncRepaymentSchedules(
                $this->sync_start_date,
                $this->sync_end_date,
            );

            $this->flashStatus(
                "Sync completed: {$result['processed']} customers imported, {$result['failed']} failed; "
                ."{$units['processed']} units linked, {$units['skipped']} unmatched; "
                ."{$plans['processed']} payment plans synced; "
                ."{$payments['processed']} payments imported, {$payments['skipped']} skipped; "
                ."{$schedules['processed']} repayment schedules mapped, {$schedules['skipped']} skipped.",
                $result['status'] === 'failed' || $units['status'] === 'failed' || $plans['status'] === 'failed' || $payments['status'] === 'failed' || $schedules['status'] === 'failed',
            );
        } catch (\Throwable $e) {
            $this->flashStatus('Sync failed: '.$e->getMessage(), true);
        }
    }

    /**
     * Clear all PayGro-synced records and kick off a fresh full sync in the
     * background. Used to recover when an online sync left data incomplete
     * (e.g. payments that never finished importing). The sync runs as a
     * detached CLI process so the slow first/baseline pull can't time out the
     * web request.
     */
    public function resetAndResync(PayGroService $payGro): void
    {
        // Clearing local data is a DB operation and must always run — a stale
        // session must not block it. The background sync re-authenticates from
        // stored credentials, so it works even when the current session lapsed.
        $cleared = $payGro->resetSyncedData();

        $summary = collect($cleared)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $table) => $count.' '.str_replace('_', ' ', $table))
            ->implode(', ');

        $clearedText = 'Reset complete'.($summary !== '' ? ' (cleared '.$summary.')' : '').'.';

        if (! $payGro->hasStoredCredentials()) {
            $this->flashStatus(
                $clearedText.' Save your PayGro username and password, connect, then run a sync to repopulate.',
                true,
            );

            return;
        }

        $launched = $this->launchBackgroundSync();

        $this->flashStatus(
            $launched
                ? $clearedText.' A fresh full sync is running in the background — watch the sync logs below; they refresh as it progresses.'
                : $clearedText.' Start the sync from the server with `php artisan paygro:sync --fresh`, or use "Run sync now" above.',
            ! $launched,
        );
    }

    /**
     * Launch `php artisan paygro:sync --fresh` as a detached background process
     * so it outlives this web request. Returns false if it could not start.
     */
    protected function launchBackgroundSync(): bool
    {
        try {
            $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find() ?: 'php';
            $artisan = base_path('artisan');
            $command = sprintf(
                '%s %s paygro:sync --fresh --no-lock --source=ui-reset',
                escapeshellarg($php),
                escapeshellarg($artisan),
            );

            if (PHP_OS_FAMILY === 'Windows') {
                // The empty "" is a window title — without it `start` treats the
                // first quoted argument (the php path) as the title and never
                // runs the command.
                $handle = popen('start "" /B '.$command.' > NUL 2>&1', 'r');

                if ($handle !== false) {
                    pclose($handle);

                    return true;
                }

                return false;
            }

            exec($command.' > /dev/null 2>&1 &');

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to launch background PayGro sync', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function flashStatus(string $message, bool $isError): void
    {
        $this->statusMessage = $message;
        $this->statusIsError = $isError;
    }

    public function render(PayGroService $payGro)
    {
        $connection = $payGro->getConnectionStatus();
        $recentSyncs = PaygroSyncLog::query()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('livewire.settings.paygro-settings', compact('connection', 'recentSyncs'));
    }
}
