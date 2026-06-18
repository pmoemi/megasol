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

        try {
            $result = $payGro->syncCustomers(
                $this->sync_start_date,
                $this->sync_end_date,
                $markFirst,
            );

            $this->flashStatus(
                "Sync completed: {$result['processed']} customers imported, {$result['failed']} failed.",
                $result['status'] === 'failed',
            );
        } catch (\Throwable $e) {
            $this->flashStatus('Sync failed: '.$e->getMessage(), true);
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
