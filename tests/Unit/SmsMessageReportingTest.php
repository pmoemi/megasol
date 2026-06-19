<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsMessageReportingTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane'.Str::random(4).'@test.com',
            'phone' => '254712345678',
            'account_number' => 'A'.Str::random(4),
        ]);
    }

    public function test_for_reporting_excludes_settings_and_terminal_tests(): void
    {
        $customer = $this->customer();

        SmsMessage::create([
            'customer_id' => $customer->id,
            'to' => '254712345678',
            'body' => 'Real token SMS',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'paygro_latest_token'],
            'sent_at' => now(),
        ]);

        SmsMessage::create([
            'to' => '254725584124',
            'body' => 'Settings test',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'settings_test'],
            'sent_at' => now(),
        ]);

        SmsMessage::create([
            'to' => '254725584124',
            'body' => 'Terminal test',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'terminal_test'],
            'sent_at' => now(),
        ]);

        SmsMessage::create([
            'customer_id' => $customer->id,
            'to' => '254725584124',
            'body' => 'CLI token test',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'paygro_latest_token', 'cli_test' => true],
            'sent_at' => now(),
        ]);

        SmsMessage::create([
            'to' => '254725584124',
            'body' => 'Orphan CLI test',
            'direction' => 'outbound',
            'status' => 'success',
            'sent_at' => now(),
        ]);

        $this->assertSame(1, SmsMessage::query()->forReporting()->count());
        $this->assertSame(1, SmsMessage::query()->forReporting()->successfullySent()->count());
    }
}
