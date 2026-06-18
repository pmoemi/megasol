<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\MailConfigurator;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'Email / SMTP'])]
class MailSettings extends Component
{
    public string $mail_mailer = 'smtp';

    public string $mail_host = '';

    public string $mail_port = '587';

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public string $test_recipient = '';

    public bool $passwordIsSet = false;

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

    public function mount(): void
    {
        $this->mail_mailer = (string) Setting::get(MailConfigurator::KEY_MAILER, config('mail.default', 'smtp'));
        $this->mail_host = (string) Setting::get(MailConfigurator::KEY_HOST, config('mail.mailers.smtp.host', ''));
        $this->mail_port = (string) Setting::get(MailConfigurator::KEY_PORT, config('mail.mailers.smtp.port', '587'));
        $this->mail_username = (string) Setting::get(MailConfigurator::KEY_USERNAME, config('mail.mailers.smtp.username', ''));
        $this->mail_encryption = (string) Setting::get(MailConfigurator::KEY_ENCRYPTION, 'tls');
        $this->mail_from_address = (string) Setting::get(MailConfigurator::KEY_FROM_ADDRESS, config('mail.from.address', ''));
        $this->mail_from_name = (string) Setting::get(MailConfigurator::KEY_FROM_NAME, config('mail.from.name', ''));
        $this->passwordIsSet = ! empty(Setting::get(MailConfigurator::KEY_PASSWORD));
        $this->test_recipient = auth()->user()->email ?? '';
    }

    protected function rules(): array
    {
        return [
            'mail_mailer' => 'required|in:smtp,log',
            'mail_host' => 'required_if:mail_mailer,smtp|nullable|string|max:255',
            'mail_port' => 'required_if:mail_mailer,smtp|nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Setting::set(MailConfigurator::KEY_MAILER, $this->mail_mailer);
        Setting::set(MailConfigurator::KEY_HOST, $this->mail_host);
        Setting::set(MailConfigurator::KEY_PORT, $this->mail_port);
        Setting::set(MailConfigurator::KEY_USERNAME, $this->mail_username);
        Setting::set(MailConfigurator::KEY_ENCRYPTION, $this->mail_encryption === 'none' ? '' : $this->mail_encryption);
        Setting::set(MailConfigurator::KEY_FROM_ADDRESS, $this->mail_from_address);
        Setting::set(MailConfigurator::KEY_FROM_NAME, $this->mail_from_name);

        // Only overwrite the stored password when a new one was entered.
        if ($this->mail_password !== '') {
            Setting::set(MailConfigurator::KEY_PASSWORD, $this->mail_password);
            $this->mail_password = '';
            $this->passwordIsSet = true;
        }

        // Refresh runtime config so a subsequent test send uses the new values.
        MailConfigurator::apply();

        $this->flashStatus('Email settings saved.', false);
        session()->flash('success', 'Email settings saved.');
    }

    public function sendTest(): void
    {
        $this->validate(['test_recipient' => 'required|email']);

        // Persist + apply first so the test uses the latest settings.
        $this->save();

        try {
            Mail::raw('This is a test email from MegaSol. Your SMTP settings are working correctly.', function ($message) {
                $message->to($this->test_recipient)->subject('MegaSol SMTP test');
            });

            $this->flashStatus("Test email sent to {$this->test_recipient}.", false);
        } catch (\Throwable $e) {
            $this->flashStatus('Failed to send test email: '.$e->getMessage(), true);
        }
    }

    protected function flashStatus(string $message, bool $isError): void
    {
        $this->statusMessage = $message;
        $this->statusIsError = $isError;
    }

    public function render()
    {
        return view('livewire.settings.mail-settings');
    }
}
