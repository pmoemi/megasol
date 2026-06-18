<?php

namespace Tests\Feature;

use App\Livewire\Settings\SecuritySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $password = 'old-password'): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin'.Str::random(4).'@test.com',
            'password' => bcrypt($password),
            'is_active' => true,
        ]);
    }

    public function test_can_update_password(): void
    {
        $user = $this->user('old-password');
        $this->actingAs($user);

        Livewire::test(SecuritySettings::class)
            ->set('current_password', 'old-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = $this->user('old-password');
        $this->actingAs($user);

        Livewire::test(SecuritySettings::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password']);
    }

    public function test_can_enable_and_confirm_two_factor_authentication(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $component = Livewire::test(SecuritySettings::class)
            ->call('enableTwoFactorAuthentication')
            ->assertSet('showingQrCode', true);

        $this->assertNotNull($user->fresh()->two_factor_secret);

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)
            ->call('confirmTwoFactorAuthentication')
            ->assertHasNoErrors()
            ->assertSet('showingRecoveryCodes', true);

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_invalid_code_fails_two_factor_confirmation(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(SecuritySettings::class)
            ->call('enableTwoFactorAuthentication')
            ->set('code', '000000')
            ->call('confirmTwoFactorAuthentication')
            ->assertHasErrors('code');

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_can_disable_two_factor_authentication(): void
    {
        $user = $this->user('current-password');
        $this->actingAs($user);

        $component = Livewire::test(SecuritySettings::class)
            ->call('enableTwoFactorAuthentication');

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)->call('confirmTwoFactorAuthentication');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());

        $component->set('confirm_password', 'current-password')
            ->call('disableTwoFactorAuthentication')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_disable_requires_correct_password(): void
    {
        $user = $this->user('current-password');
        $this->actingAs($user);

        $component = Livewire::test(SecuritySettings::class)
            ->call('enableTwoFactorAuthentication');

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $component->set('code', $code)->call('confirmTwoFactorAuthentication');

        $component->set('confirm_password', 'wrong-password')
            ->call('disableTwoFactorAuthentication')
            ->assertHasErrors('confirm_password');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }
}
