<?php

namespace Tests\Feature;

use App\Livewire\Settings\SmsSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\SmsConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin'.Str::random(4).'@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    public function test_send_test_sms_only_sends_once_per_click(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Setting::set(SmsConfigurator::KEY_USERNAME, 'megawattenergies');
        Setting::set(SmsConfigurator::KEY_API_KEY, 'test-api-key');
        Setting::set(SmsConfigurator::KEY_SENDER_ID, 'MEGATECH');

        $this->mock(AfricasTalkingSmsService::class, function ($mock) {
            $mock->shouldReceive('resetClients')->once();
            $mock->shouldReceive('send')
                ->once()
                ->andReturn([
                    'success' => true,
                    'status' => 'success',
                    'message_id' => 'ATXid_test',
                    'sms_message_id' => 1,
                    'raw' => [],
                ]);
        });

        Livewire::test(SmsSettings::class)
            ->set('test_phone', '254725584124')
            ->set('test_message', 'Single test SMS')
            ->call('sendTest')
            ->assertHasNoErrors()
            ->assertSet('testSendInProgress', false);
    }

    public function test_can_save_sms_gateway_settings(): void
    {
        $this->actingAs($this->user());

        Livewire::test(SmsSettings::class)
            ->set('at_username', 'megasol')
            ->set('at_api_key', 'super-secret-key')
            ->set('at_sender_id', 'MEGASOL')
            ->set('at_default_country_code', '254')
            ->set('at_dlr_secret', 'webhook-secret')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('megasol', Setting::get(SmsConfigurator::KEY_USERNAME));
        $this->assertSame('super-secret-key', Setting::get(SmsConfigurator::KEY_API_KEY));
        $this->assertSame('MEGASOL', Setting::get(SmsConfigurator::KEY_SENDER_ID));
        $this->assertSame('webhook-secret', Setting::get(SmsConfigurator::KEY_DLR_SECRET));

        $this->assertSame('megasol', config('africastalking.username'));
        $this->assertSame('super-secret-key', config('africastalking.api_key'));
        $this->assertSame('MEGASOL', config('africastalking.sender_id'));
        $this->assertSame('webhook-secret', config('africastalking.dlr_secret'));
    }

    public function test_inbound_config_is_saved_separately(): void
    {
        $this->actingAs($this->user());

        Livewire::test(SmsSettings::class)
            ->set('at_username', 'megasol')
            ->set('at_sender_id', 'MEGASOL')
            ->set('in_sender_id', '20880')
            ->set('in_secret', 'inbound-secret')
            ->set('in_username', 'inbound-acct')
            ->set('in_api_key', 'inbound-key')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('20880', Setting::get(SmsConfigurator::KEY_INBOUND_SENDER_ID));
        $this->assertSame('inbound-secret', Setting::get(SmsConfigurator::KEY_INBOUND_SECRET));
        $this->assertSame('inbound-acct', Setting::get(SmsConfigurator::KEY_INBOUND_USERNAME));
        $this->assertSame('inbound-key', Setting::get(SmsConfigurator::KEY_INBOUND_API_KEY));

        // Outbound sender stays separate from the inbound shortcode.
        $this->assertSame('MEGASOL', config('africastalking.sender_id'));
        $this->assertSame('20880', config('africastalking.inbound.sender_id'));
    }

    public function test_api_key_is_not_overwritten_when_left_blank(): void
    {
        $this->actingAs($this->user());
        Setting::set(SmsConfigurator::KEY_API_KEY, 'existing-key');

        Livewire::test(SmsSettings::class)
            ->assertSet('apiKeyIsSet', true)
            ->set('at_username', 'megasol')
            ->set('at_api_key', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('existing-key', Setting::get(SmsConfigurator::KEY_API_KEY));
    }
}
