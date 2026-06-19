<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\SmsMessage;
use App\Services\Integrations\PayGroService;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\SmsConfigurator;
use Illuminate\Console\Command;

class SmsSendTokenTestCommand extends Command
{
    protected $signature = 'sms:send-token-test
        {customer : Customer ID, account number (PG-xxx), or phone}
        {--phone= : Override recipient phone}
        {--skip-paygro : Send a dummy token message without fetching from PayGro}';

    protected $description = 'End-to-end token SMS test: fetch latest PayGro token and send via Africa\'s Talking (same path as Customer 360)';

    public function handle(PayGroService $payGro, AfricasTalkingSmsService $sms): int
    {
        $customer = $this->resolveCustomer((string) $this->argument('customer'));

        if (! $customer) {
            $this->error('Customer not found.');

            return self::FAILURE;
        }

        $to = trim((string) ($this->option('phone') ?: $customer->phone ?: ''));

        if ($to === '') {
            $this->error('Customer has no phone. Pass --phone=2547...');

            return self::FAILURE;
        }

        if (! $sms->isValidKenyanMobileNumber($to)) {
            $this->error('Invalid phone "'.$to.'". Use format 254725584124 or 0725584124.');

            return self::FAILURE;
        }

        $to = $sms->normalizePhoneNumber($to);

        if ($customer->sms_opted_out) {
            $this->error('Customer has opted out of SMS.');

            return self::FAILURE;
        }

        $credentials = SmsConfigurator::resolveOutboundCredentials();

        $this->table(['Field', 'Value'], [
            ['Customer', $customer->full_name.' (#'.$customer->id.')'],
            ['Account', $customer->account_number ?? '—'],
            ['Send to', $to],
            ['AT username', $credentials['username']],
            ['Sender ID', $credentials['sender_id'] ?? '(account default)'],
            ['API key', substr($credentials['api_key'], 0, 6).'…'.substr($credentials['api_key'], -4)],
        ]);

        $token = null;

        if ($this->option('skip-paygro')) {
            $token = [
                'product_serial_number' => 'TEST-SERIAL',
                'generated_token_value' => '0000-0000-0000',
                'token_generation_date_display' => now()->format('M j, Y'),
            ];
            $this->warn('Skipping PayGro fetch — using dummy token.');
        } else {
            $this->info('Fetching latest PayGro token…');

            try {
                $token = $payGro->syncLatestFreeTokenForCustomer($customer);
            } catch (\Throwable $e) {
                $this->error('PayGro token fetch failed: '.$e->getMessage());

                return self::FAILURE;
            }

            if (! $token) {
                $serials = $payGro->customerPayGroSerials($customer)->join(', ') ?: '(none)';
                $this->error('No PayGro token found for this customer.');
                $this->line('Matching unit serials: '.$serials);

                return self::FAILURE;
            }

            $this->table(['Token field', 'Value'], [
                ['Serial', $token['product_serial_number'] ?? '—'],
                ['Token', $token['generated_token_value'] ?? '—'],
                ['Generated', $token['token_generation_date_display'] ?? ($token['token_generation_date'] ?? '—')],
                ['Source', $token['token_source'] ?? 'free'],
            ]);
        }

        $serial = $token['product_serial_number'] ?? 'your unit';
        $value = $token['generated_token_value'] ?? '';
        $date = $token['token_generation_date_display'] ?? 'recently';
        $name = trim((string) ($customer->first_name ?? ''));
        $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
        $body = "{$greeting} your latest Megasol token for {$serial} is {$value}. Generated {$date}.";

        $this->newLine();
        $this->line('Message: '.$body);
        $this->newLine();
        $this->info('Sending SMS (instant, same as Customer 360 token send)…');

        $smsMessage = SmsMessage::create([
            'customer_id' => $customer->id,
            'to' => $to,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'queued',
            'meta' => [
                'source' => 'paygro_latest_token',
                'sent_by' => null,
                'cli_test' => true,
                'paygro_token' => [
                    'product_serial_number' => $token['product_serial_number'] ?? null,
                    'generated_token_value' => $token['generated_token_value'] ?? null,
                ],
            ],
        ]);

        try {
            $result = $sms->send(
                to: $to,
                message: $body,
                meta: ['customer_id' => $customer->id, 'source' => 'paygro_latest_token'],
                existingMessageId: $smsMessage->id,
            );
        } catch (\Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());
            $this->line('SMS log #'.$smsMessage->id.' status: '.$smsMessage->fresh()->status);

            return self::FAILURE;
        }

        $smsMessage->refresh();

        $this->newLine();
        $this->info(($result['success'] ?? false) ? 'Provider accepted the token SMS.' : 'Provider rejected the token SMS.');

        $this->table(['Field', 'Value'], [
            ['Success', ($result['success'] ?? false) ? 'yes' : 'no'],
            ['Status', $result['status'] ?? 'n/a'],
            ['Message ID', $result['message_id'] ?? 'n/a'],
            ['SMS log ID', $smsMessage->id],
            ['Log status', $smsMessage->status],
            ['Normalized to', $smsMessage->to],
            ['Error', $smsMessage->error_message ?? '—'],
        ]);

        if (! empty($result['raw'])) {
            $this->newLine();
            $this->line('Raw response:');
            $this->line(json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveCustomer(string $needle): ?Customer
    {
        if (ctype_digit($needle)) {
            return Customer::find((int) $needle);
        }

        if (preg_match('/^PG-/i', $needle)) {
            return Customer::query()->where('account_number', strtoupper($needle))->first();
        }

        $digits = preg_replace('/\D/', '', $needle);

        return Customer::query()
            ->where('phone', 'like', '%'.$digits.'%')
            ->orderByDesc('id')
            ->first();
    }
}
