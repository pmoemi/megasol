<?php

namespace App\Services\Sms;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\SmsMessage;
use App\Traits\NormalizesPhoneNumbers;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    use NormalizesPhoneNumbers;

    /** @var array<string, AfricasTalking> */
    protected array $clients = [];

    protected function client(?string $username = null, ?string $apiKey = null): AfricasTalking
    {
        $username = $username ?: config('africastalking.username');
        $apiKey = $apiKey ?: config('africastalking.api_key');

        if (! $username || ! $apiKey) {
            throw new \RuntimeException(
                'Africa\'s Talking is not configured. Set the username and API key in Settings → SMS Gateway.'
            );
        }

        $cacheKey = $username.'|'.$apiKey;

        if (! isset($this->clients[$cacheKey])) {
            $client = new AfricasTalking($username, $apiKey);
            $this->disableSslVerificationForLocalDev($client);
            $this->clients[$cacheKey] = $client;
        }

        return $this->clients[$cacheKey];
    }

    protected function disableSslVerificationForLocalDev(AfricasTalking $client): void
    {
        if (! app()->environment(['local', 'development']) && ! config('app.debug')) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($client);

            foreach (['client', 'contentClient'] as $propertyName) {
                if (! $reflection->hasProperty($propertyName)) {
                    continue;
                }

                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $httpClient = $property->getValue($client);

                if (! $httpClient instanceof \GuzzleHttp\Client) {
                    continue;
                }

                $config = array_merge($httpClient->getConfig(), ['verify' => false]);
                $property->setValue($client, new \GuzzleHttp\Client($config));
            }
        } catch (\Throwable $e) {
            Log::warning('Africa\'s Talking: could not disable SSL verification for local dev', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a single SMS and persist a delivery log record.
     *
     * @return array{success: bool, message_id: ?string, status: string, raw: mixed}
     */
    public function send(
        string $to,
        string $message,
        ?string $senderId = null,
        array $meta = [],
        bool $enqueue = false,
        ?int $existingMessageId = null,
        ?string $username = null,
        ?string $apiKey = null,
    ): array {
        $to = $this->normalizePhone($to);
        $senderId = $senderId ?: config('africastalking.sender_id');

        $payload = [
            'to' => $to,
            'message' => $message,
        ];

        if ($senderId) {
            $payload['from'] = $senderId;
        }

        if ($enqueue) {
            $payload['enqueue'] = true;
        }

        $log = $existingMessageId
            ? SmsMessage::findOrFail($existingMessageId)
            : SmsMessage::create([
                'to' => $to,
                'from' => $senderId,
                'body' => $message,
                'direction' => 'outbound',
                'status' => 'queued',
                'meta' => $meta ?: null,
            ]);

        try {
            $response = $this->client($username, $apiKey)->sms()->send($payload);
            $parsed = $this->parseSendResponse($response);

            $log->update([
                'provider_message_id' => $parsed['message_id'],
                'status' => $parsed['status'],
                'provider_response' => $parsed['raw'],
                'cost' => $parsed['cost'],
                'sent_at' => now(),
            ]);

            Log::info('Africa\'s Talking: SMS sent', [
                'to' => $to,
                'message_id' => $parsed['message_id'],
                'status' => $parsed['status'],
            ]);

            return [
                'success' => $parsed['success'],
                'message_id' => $parsed['message_id'],
                'status' => $parsed['status'],
                'raw' => $parsed['raw'],
                'sms_message_id' => $log->id,
            ];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Africa\'s Talking: SMS failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send the same message to multiple recipients (batched by Africa's Talking).
     *
     * @param  array<int, string>  $recipients
     * @return array{success: bool, results: array<int, array<string, mixed>>}
     */
    public function sendBulk(array $recipients, string $message, ?string $senderId = null, array $meta = []): array
    {
        $phones = $this->normalizePhones($recipients);

        if ($phones === []) {
            return ['success' => false, 'results' => []];
        }

        $senderId = $senderId ?: config('africastalking.sender_id');
        $enqueue = (bool) config('africastalking.enqueue_bulk', true);

        $payload = [
            'to' => $phones,
            'message' => $message,
        ];

        if ($senderId) {
            $payload['from'] = $senderId;
        }

        if ($enqueue) {
            $payload['enqueue'] = true;
        }

        $logs = [];

        foreach ($phones as $phone) {
            $logs[$phone] = SmsMessage::create([
                'to' => $phone,
                'from' => $senderId,
                'body' => $message,
                'direction' => 'outbound',
                'status' => 'queued',
                'meta' => $meta ?: null,
            ]);
        }

        try {
            $response = $this->client()->sms()->send($payload);
            $entries = $this->parseBulkSendResponse($response);
            $results = [];

            foreach ($entries as $entry) {
                $phone = $this->normalizePhone($entry['number'] ?? '');
                $log = $logs[$phone] ?? null;

                if ($log) {
                    $log->update([
                        'provider_message_id' => $entry['message_id'],
                        'status' => $entry['status'],
                        'provider_response' => $entry,
                        'cost' => $entry['cost'],
                        'sent_at' => now(),
                    ]);
                }

                $results[] = $entry;
            }

            return [
                'success' => collect($results)->contains(fn ($r) => ($r['status'] ?? '') !== 'Failed'),
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            foreach ($logs as $log) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * @param  mixed  $response
     * @return array{success: bool, message_id: ?string, status: string, cost: ?string, raw: mixed}
     */
    protected function parseSendResponse(mixed $response): array
    {
        $data = $this->responseToArray($response);
        $recipient = $data['SMSMessageData']['Recipients'][0] ?? $data['Recipients'][0] ?? [];

        $status = $recipient['status'] ?? 'Unknown';
        $success = ! in_array(strtolower((string) $status), ['failed', 'rejected', 'invalidphoneNumber'], true);

        return [
            'success' => $success,
            'message_id' => $recipient['messageId'] ?? $recipient['message_id'] ?? null,
            'status' => strtolower((string) $status),
            'cost' => isset($recipient['cost']) ? (string) $recipient['cost'] : null,
            'raw' => $data,
        ];
    }

    /**
     * @param  mixed  $response
     * @return array<int, array<string, mixed>>
     */
    protected function parseBulkSendResponse(mixed $response): array
    {
        $data = $this->responseToArray($response);
        $recipients = $data['SMSMessageData']['Recipients'] ?? $data['Recipients'] ?? [];

        return array_map(function (array $recipient) {
            $status = $recipient['status'] ?? 'Unknown';

            return [
                'number' => $recipient['number'] ?? null,
                'message_id' => $recipient['messageId'] ?? $recipient['message_id'] ?? null,
                'status' => strtolower((string) $status),
                'cost' => isset($recipient['cost']) ? (string) $recipient['cost'] : null,
            ];
        }, $recipients);
    }

    protected function responseToArray(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'json')) {
            return (array) $response->json();
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        return json_decode(json_encode($response), true) ?? [];
    }
}
