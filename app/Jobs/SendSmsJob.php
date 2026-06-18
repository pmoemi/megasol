<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\Sms\AfricasTalkingSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $to,
        public string $message,
        public ?int $smsMessageId = null,
        public array $meta = [],
        public ?string $senderId = null,
        public ?string $username = null,
        public ?string $apiKey = null,
    ) {
        $this->onQueue(config('africastalking.queue', 'sms'));
    }

    public function handle(AfricasTalkingSmsService $sms): void
    {
        if ($this->smsMessageId) {
            $existing = SmsMessage::find($this->smsMessageId);

            if ($existing && in_array($existing->status, ['sent', 'delivered', 'success'], true)) {
                return;
            }
        }

        try {
            $result = $sms->send(
                to: $this->to,
                message: $this->message,
                senderId: $this->senderId,
                meta: $this->meta,
                existingMessageId: $this->smsMessageId,
                username: $this->username,
                apiKey: $this->apiKey,
            );
        } catch (\Throwable $e) {
            Log::error('SendSmsJob failed', [
                'to' => $this->to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
