<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\SmsConfigurator;
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

    public string $test_phone = '';

    public string $test_message = 'This is a test SMS from MegaSol.';

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

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
        $this->validate(['test_phone' => 'required|string|min:9|max:20']);

        // Persist + apply first so the test uses the latest settings.
        $this->save();

        try {
            $sms->send($this->test_phone, $this->test_message);

            $this->flashStatus("Test SMS sent to {$this->test_phone}.", false);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, '401') || str_contains($message, 'authentication is invalid')) {
                $message = 'Africa\'s Talking rejected the username or API key (401). '
                    .'Copy a fresh API key from your app dashboard at account.africastalking.com '
                    .'for username "'.config('africastalking.username').'" and save it here.';
            }
            $this->flashStatus('Failed to send test SMS: '.$message, true);
        }
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
