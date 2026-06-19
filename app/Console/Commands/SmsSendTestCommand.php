<?php

namespace App\Console\Commands;

use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\SmsConfigurator;
use Illuminate\Console\Command;

class SmsSendTestCommand extends Command
{
    protected $signature = 'sms:send-test
        {phone : Recipient phone, e.g. 0737468555 or +254737468555}
        {--message= : Optional message body}
        {--username= : Override Africa\'s Talking username}
        {--api-key= : Override Africa\'s Talking API key}
        {--sender= : Override sender ID}';

    protected $description = 'Send a test SMS via Africa\'s Talking and print the full provider response';

    public function handle(AfricasTalkingSmsService $sms): int
    {
        SmsConfigurator::apply();
        $sms->resetClients();

        $phone = (string) $this->argument('phone');
        $message = (string) ($this->option('message') ?: 'MegaSol SMS test at '.now()->format('Y-m-d H:i:s'));

        $username = (string) ($this->option('username') ?: config('africastalking.username'));
        $senderId = (string) ($this->option('sender') ?: config('africastalking.sender_id'));
        $apiKey = (string) ($this->option('api-key') ?: config('africastalking.api_key'));

        $this->table(['Setting', 'Value'], [
            ['Username', $username ?: '(empty)'],
            ['Sender ID', $senderId ?: '(account default)'],
            ['API key', $apiKey !== '' ? substr($apiKey, 0, 6).'…'.substr($apiKey, -4) : '(empty)'],
            ['To', $phone],
            ['Message', $message],
        ]);

        if ($username === '' || $apiKey === '') {
            $this->error('Africa\'s Talking username or API key is missing. Check Settings → SMS or .env.');

            return self::FAILURE;
        }

        try {
            $result = $sms->send(
                to: $phone,
                message: $message,
                senderId: $senderId !== '' ? $senderId : null,
                meta: ['source' => 'terminal_test'],
                username: $username,
                apiKey: $apiKey,
            );

            $this->newLine();
            $this->info($result['success'] ? 'Provider accepted the SMS.' : 'Provider rejected the SMS.');

            if (! ($result['success'] ?? false) && ! empty($result['raw'])) {
                $this->warn('Check raw response below for the rejection reason.');
            }

            $this->table(['Field', 'Value'], [
                ['Success', $result['success'] ? 'yes' : 'no'],
                ['Status', $result['status'] ?? 'n/a'],
                ['Message ID', $result['message_id'] ?? 'n/a'],
                ['SMS log ID', $result['sms_message_id'] ?? 'n/a'],
            ]);

            if (! empty($result['raw'])) {
                $this->newLine();
                $this->line('Raw response:');
                $this->line(json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
