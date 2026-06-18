<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'Security'])]
class SecuritySettings extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $showingQrCode = false;

    public bool $showingRecoveryCodes = false;

    public string $code = '';

    public string $confirm_password = '';

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        auth()->user()->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->flashStatus('Password updated.', false);
    }

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        $enable(auth()->user());

        $this->showingQrCode = true;
        $this->showingRecoveryCodes = false;
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        try {
            $confirm(auth()->user(), $this->code);
        } catch (ValidationException $e) {
            $this->addError('code', $e->validator->errors()->first('code'));

            return;
        }

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = true;
        $this->flashStatus('Two-factor authentication has been enabled.', false);
    }

    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(auth()->user());

        $this->showingRecoveryCodes = true;
        $this->flashStatus('New recovery codes have been generated.', false);
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        $this->validate([
            'confirm_password' => ['required', 'string', 'current_password:web'],
        ], [
            'confirm_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        $disable(auth()->user());

        $this->reset('confirm_password', 'code');
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = false;
        $this->flashStatus('Two-factor authentication has been disabled.', false);
    }

    protected function flashStatus(string $message, bool $isError): void
    {
        $this->statusMessage = $message;
        $this->statusIsError = $isError;
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.settings.security-settings', [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'qrCodeSvg' => $this->showingQrCode ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $this->showingRecoveryCodes && $user->two_factor_recovery_codes ? $user->recoveryCodes() : [],
        ]);
    }
}
