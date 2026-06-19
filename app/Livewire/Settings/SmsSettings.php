<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\Automation\AutomationRunner;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\AutomationSettings;
use App\Support\SmsConfigurator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'SMS Gateway'])]
class SmsSettings extends Component
{
    public string $at_username = '';

    public string $at_api_key = '';

    public string $at_sender_id = '';

    public string $at_default_country_code = '254';

    public string $at_dlr_secret = '';

    // ── Inbound (two-way) — separate shortcode / credentials / secret ────
    public string $in_username = '';

    public string $in_api_key = '';

    public string $in_sender_id = '';

    public string $in_secret = '';

    public bool $apiKeyIsSet = false;

    public bool $inApiKeyIsSet = false;

    /** Global pause switch for scheduled SMS automations. */
    public bool $automations_paused = false;

    // ── Automation behaviour (dynamic) ───────────────────────────────────
    public int $cooldown_hours = AutomationSettings::DEFAULT_COOLDOWN_HOURS;

    public int $reminder_lead_days = AutomationSettings::DEFAULT_REMINDER_LEAD_DAYS;

    public int $overdue_after_days = AutomationSettings::DEFAULT_OVERDUE_AFTER_DAYS;

    public int $max_per_run = AutomationSettings::DEFAULT_MAX_PER_RUN;

    public string $quiet_hours_start = '';

    public string $quiet_hours_end = '';

    public string $test_phone = '';

    public string $test_message = 'This is a test SMS from MegaSol.';

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

    public bool $testSendInProgress = false;

    public function mount(): void
    {
        $this->at_username = (string) Setting::get(SmsConfigurator::KEY_USERNAME, config('africastalking.username', 'sandbox'));
        $this->at_sender_id = (string) Setting::get(SmsConfigurator::KEY_SENDER_ID, config('africastalking.sender_id', ''));
        $this->at_default_country_code = (string) Setting::get(SmsConfigurator::KEY_DEFAULT_COUNTRY_CODE, config('africastalking.default_country_code', '254'));
        $this->at_dlr_secret = (string) Setting::get(SmsConfigurator::KEY_DLR_SECRET, config('africastalking.dlr_secret', ''));
        $this->apiKeyIsSet = (bool) Setting::get(SmsConfigurator::KEY_API_KEY, config('africastalking.api_key'));

        $this->in_username = (string) Setting::get(SmsConfigurator::KEY_INBOUND_USERNAME, config('africastalking.inbound.username', ''));
        $this->in_sender_id = (string) Setting::get(SmsConfigurator::KEY_INBOUND_SENDER_ID, config('africastalking.inbound.sender_id', ''));
        $this->in_secret = (string) Setting::get(SmsConfigurator::KEY_INBOUND_SECRET, config('africastalking.inbound.secret', ''));
        $this->inApiKeyIsSet = (bool) Setting::get(SmsConfigurator::KEY_INBOUND_API_KEY, config('africastalking.inbound.api_key'));

        $this->automations_paused = AutomationRunner::isPaused();
        $this->cooldown_hours = AutomationSettings::cooldownHours();
        $this->reminder_lead_days = AutomationSettings::reminderLeadDays();
        $this->overdue_after_days = AutomationSettings::overdueAfterDays();
        $this->max_per_run = AutomationSettings::maxPerRun();
        $this->quiet_hours_start = (string) AutomationSettings::quietStart();
        $this->quiet_hours_end = (string) AutomationSettings::quietEnd();
    }

    /**
     * Pause or resume all scheduled SMS automations immediately (one click,
     * no full-form save). When paused, the hourly automation runner sends
     * nothing until resumed.
     */
    public function toggleAutomationsPaused(): void
    {
        $this->automations_paused = ! $this->automations_paused;

        Setting::set(AutomationSettings::KEY_PAUSED, $this->automations_paused ? '1' : '0');

        $this->flashStatus(
            $this->automations_paused
                ? 'Scheduled SMS automations are now PAUSED. No automated messages will be sent until you resume.'
                : 'Scheduled SMS automations resumed.',
            false,
        );
    }

    /**
     * Persist the dynamic automation behaviour (timing, cooldown, caps, quiet
     * hours) used by the scheduled SMS runner.
     */
    public function saveAutomationSettings(): void
    {
        $this->validate([
            'cooldown_hours' => 'required|integer|min:0|max:8760',
            'reminder_lead_days' => 'required|integer|min:0|max:365',
            'overdue_after_days' => 'required|integer|min:0|max:365',
            'max_per_run' => 'required|integer|min:1|max:100000',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i|required_with:quiet_hours_start',
        ]);

        Setting::set(AutomationSettings::KEY_COOLDOWN_HOURS, (string) $this->cooldown_hours);
        Setting::set(AutomationSettings::KEY_REMINDER_LEAD_DAYS, (string) $this->reminder_lead_days);
        Setting::set(AutomationSettings::KEY_OVERDUE_AFTER_DAYS, (string) $this->overdue_after_days);
        Setting::set(AutomationSettings::KEY_MAX_PER_RUN, (string) $this->max_per_run);
        Setting::set(AutomationSettings::KEY_QUIET_START, $this->quiet_hours_start);
        Setting::set(AutomationSettings::KEY_QUIET_END, $this->quiet_hours_end);

        $this->flashStatus('Automation settings saved.', false);
    }

    protected function rules(): array
    {
        return [
            'at_username' => 'required|string|max:255',
            'at_api_key' => 'nullable|string|max:255',
            'at_sender_id' => 'nullable|string|max:255',
            'at_default_country_code' => 'required|string|max:5',
            'at_dlr_secret' => 'nullable|string|max:255',
            'in_username' => 'nullable|string|max:255|required_with:in_api_key',
            'in_api_key' => 'nullable|string|max:255',
            'in_sender_id' => 'nullable|string|max:255',
            'in_secret' => 'nullable|string|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Setting::set(SmsConfigurator::KEY_USERNAME, $this->at_username);
        Setting::set(SmsConfigurator::KEY_SENDER_ID, $this->at_sender_id);
        Setting::set(SmsConfigurator::KEY_DEFAULT_COUNTRY_CODE, $this->at_default_country_code);
        Setting::set(SmsConfigurator::KEY_DLR_SECRET, $this->at_dlr_secret);

        // Only overwrite the stored API key when a new one was entered.
        if ($this->at_api_key !== '') {
            Setting::set(SmsConfigurator::KEY_API_KEY, trim($this->at_api_key));
            $this->at_api_key = '';
            $this->apiKeyIsSet = true;
        }

        // Inbound (two-way) — separate shortcode / credentials / secret.
        Setting::set(SmsConfigurator::KEY_INBOUND_USERNAME, $this->in_username);
        Setting::set(SmsConfigurator::KEY_INBOUND_SENDER_ID, $this->in_sender_id);
        Setting::set(SmsConfigurator::KEY_INBOUND_SECRET, $this->in_secret);

        if ($this->in_api_key !== '') {
            Setting::set(SmsConfigurator::KEY_INBOUND_API_KEY, $this->in_api_key);
            $this->in_api_key = '';
            $this->inApiKeyIsSet = true;
        }

        // Refresh runtime config so a subsequent test send uses the new values.
        SmsConfigurator::apply();

        $this->flashStatus('SMS gateway settings saved.', false);
        session()->flash('success', 'SMS gateway settings saved.');
    }

    public function sendTest(AfricasTalkingSmsService $sms): void
    {
        if ($this->testSendInProgress) {
            return;
        }

        $lockKey = 'sms-settings-test-send:'.(auth()->id() ?? 'guest');

        if (! Cache::add($lockKey, true, 15)) {
            $this->flashStatus('A test SMS is already being sent. Please wait.', true);

            return;
        }

        $this->testSendInProgress = true;

        try {
            $this->validate([
                'test_phone' => 'required|string|min:9|max:20',
                'at_username' => 'required|string|max:255',
            ]);

            $apiKey = $this->resolveApiKeyForSend();
            if ($apiKey === null || $apiKey === '') {
                $this->flashStatus('API key is missing. Paste your Africa\'s Talking API key above, or save settings first.', true);

                return;
            }

            $this->applyCredentialsForSend($apiKey);
            $sms->resetClients();

            $sender = $this->at_sender_id ?: '(account default)';

            $result = $sms->send(
                to: $this->test_phone,
                message: $this->test_message,
                meta: ['source' => 'settings_test'],
                senderId: $this->at_sender_id !== '' ? $this->at_sender_id : null,
                username: $this->at_username,
                apiKey: $apiKey,
            );

            $status = $result['status'] ?? 'sent';
            $messageId = $result['message_id'] ?? null;
            $suffix = $messageId ? " ID: {$messageId}" : '';

            $this->flashStatus(
                "Test SMS accepted ({$status}) to {$this->test_phone} via {$this->at_username} / {$sender}.{$suffix} Save settings to persist these credentials.",
                false,
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, '401') || str_contains($message, 'authentication is invalid')) {
                $message = 'Africa\'s Talking rejected the username or API key (401) for "'
                    .$this->at_username.'". Use megawattenergies + your working atsk_ key, then click Save Settings.';
            }
            $this->flashStatus('Failed to send test SMS: '.$message, true);
        } finally {
            $this->testSendInProgress = false;
            Cache::forget($lockKey);
        }
    }

    protected function resolveApiKeyForSend(): ?string
    {
        if (trim($this->at_api_key) !== '') {
            return trim($this->at_api_key);
        }

        $stored = Setting::get(SmsConfigurator::KEY_API_KEY);

        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $envKey = config('africastalking.api_key');

        return is_string($envKey) && trim($envKey) !== '' ? trim($envKey) : null;
    }

    protected function applyCredentialsForSend(string $apiKey): void
    {
        config([
            'africastalking.username' => $this->at_username,
            'africastalking.api_key' => $apiKey,
            'africastalking.sender_id' => $this->at_sender_id !== '' ? $this->at_sender_id : null,
            'africastalking.default_country_code' => $this->at_default_country_code,
        ]);
    }

    protected function flashStatus(string $message, bool $isError): void
    {
        $this->statusMessage = $message;
        $this->statusIsError = $isError;
    }

    public function render()
    {
        return view('livewire.settings.sms-settings', [
            'dlr_url' => route('webhooks.africastalking.dlr'),
            'inbound_url' => route('webhooks.africastalking.inbound'),
        ]);
    }
}
