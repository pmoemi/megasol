<?php

namespace App\Services\Sms;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\SmsMessage;
use App\Support\SmsConfigurator;
use App\Traits\NormalizesPhoneNumbers;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    use NormalizesPhoneNumbers;

    /** @var array<string, AfricasTalking> */
    protected array $clients = [];

    public function resetClients(): void
    {
        $this->clients = [];
    }

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
        $credentials = SmsConfigurator::credentialsForSend($username, $apiKey, $senderId);
        $username = $credentials['username'];
        $apiKey = $credentials['api_key'];
        $senderId = $credentials['sender_id'];

        $this->resetClients();

        $to = $this->normalizePhone($to);

        if (! $this->isValidKenyanMobile($to)) {
            throw new \RuntimeException(
                'Invalid Kenyan mobile number: '.$to.'. Use format 254725584124 or 0725584124.'
            );
        }

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

            if (! $parsed['success']) {
                $errorMessage = $parsed['error'] ?? 'Africa\'s Talking rejected the SMS (status: '.$parsed['status'].').';

                $log->update([
                    'to' => $to,
                    'provider_message_id' => $parsed['message_id'],
                    'status' => $parsed['status'] === 'unknown' ? 'failed' : $parsed['status'],
                    'provider_response' => $parsed['raw'],
                    'cost' => $parsed['cost'],
                    'error_message' => $errorMessage,
                    'sent_at' => now(),
                ]);

                throw new \RuntimeException($errorMessage);
            }

            $log->update([
                'to' => $to,
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
        $credentials = SmsConfigurator::credentialsForSend(null, null, $senderId);
        $senderId = $credentials['sender_id'];

        $this->resetClients();

        $phones = $this->normalizePhones($recipients);
        $phones = array_values(array_filter($phones, fn (string $phone) => $this->isValidKenyanMobile($phone)));

        if ($phones === []) {
            return ['success' => false, 'results' => []];
        }

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
            $response = $this->client($credentials['username'], $credentials['api_key'])->sms()->send($payload);
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

        if (strtolower((string) ($data['status'] ?? '')) === 'error') {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'raw' => $data,
                'error' => $this->formatSdkError($data),
            ];
        }

        $payload = $this->unwrapSdkPayload($data);
        $recipient = $this->firstRecipient($payload);
        $overallMessage = $payload['SMSMessageData']['Message'] ?? null;

        if (is_string($overallMessage) && str_contains($overallMessage, 'Sent to 0/')) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'raw' => $data,
                'error' => $overallMessage,
            ];
        }

        $status = $recipient['status'] ?? null;
        $statusCode = isset($recipient['statusCode']) ? (int) $recipient['statusCode'] : null;

        if (! $status && $statusCode !== null) {
            $status = $this->statusFromCode($statusCode);
        }
        if (! $status && strtolower((string) ($data['status'] ?? '')) === 'success') {
            $status = 'Success';
        }

        $status = (string) ($status ?: 'Unknown');
        $normalizedStatus = strtolower($status);
        $messageId = $recipient['messageId'] ?? $recipient['message_id'] ?? null;

        if ($normalizedStatus === 'userinblacklist') {
            return [
                'success' => false,
                'message_id' => $messageId,
                'status' => 'rejected',
                'cost' => isset($recipient['cost']) ? (string) $recipient['cost'] : null,
                'raw' => $data,
                'error' => 'This phone number is blacklisted on Africa\'s Talking.',
            ];
        }

        if ($normalizedStatus === 'success' || $statusCode === 100 || $statusCode === 101) {
            $success = true;
            $normalizedStatus = $statusCode === 101 ? 'sent' : 'success';
        } else {
            $success = ! in_array($normalizedStatus, ['failed', 'rejected', 'invalidphonenumber'], true);

            if ($normalizedStatus === 'unknown') {
                $success = $messageId !== null && $messageId !== '';
            }

            if ($statusCode !== null && ! in_array($statusCode, [100, 101], true)) {
                $success = false;
            }
        }

        $error = null;
        if (! $success) {
            $error = $recipient['status']
                ?? (is_string($overallMessage) ? $overallMessage : null)
                ?? $this->formatSdkError($data);
        }

        return [
            'success' => $success,
            'message_id' => $messageId,
            'status' => $normalizedStatus,
            'cost' => isset($recipient['cost']) ? (string) $recipient['cost'] : null,
            'raw' => $data,
            'error' => is_string($error) ? $error : null,
        ];
    }

    /**
     * @param  mixed  $response
     * @return array<int, array<string, mixed>>
     */
    protected function parseBulkSendResponse(mixed $response): array
    {
        $data = $this->responseToArray($response);
        $payload = $this->unwrapSdkPayload($data);
        $recipients = $payload['SMSMessageData']['Recipients'] ?? $payload['Recipients'] ?? [];

        if (! is_array($recipients)) {
            return [];
        }

        return array_map(function ($recipient) {
            $recipient = is_array($recipient) ? $recipient : [];
            $status = $recipient['status'] ?? null;
            if (! $status && isset($recipient['statusCode'])) {
                $status = $this->statusFromCode((int) $recipient['statusCode']);
            }

            return [
                'number' => $recipient['number'] ?? null,
                'message_id' => $recipient['messageId'] ?? $recipient['message_id'] ?? null,
                'status' => strtolower((string) ($status ?: 'Unknown')),
                'cost' => isset($recipient['cost']) ? (string) $recipient['cost'] : null,
            ];
        }, $recipients);
    }

    /**
     * AfricasTalking PHP SDK v3 wraps API payloads as:
     * ['status' => 'success', 'data' => ['SMSMessageData' => ...]].
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function unwrapSdkPayload(array $data): array
    {
        if (! isset($data['data'])) {
            return $data;
        }

        $inner = $data['data'];
        if (is_object($inner)) {
            $inner = json_decode(json_encode($inner), true);
        }

        if (is_array($inner) && (isset($inner['SMSMessageData']) || isset($inner['Recipients']))) {
            return $inner;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function firstRecipient(array $payload): array
    {
        $recipients = $payload['SMSMessageData']['Recipients'] ?? $payload['Recipients'] ?? [];

        if (! is_array($recipients) || $recipients === []) {
            return [];
        }

        $recipient = $recipients[0] ?? reset($recipients);

        return is_array($recipient) ? $recipient : [];
    }

    protected function statusFromCode(int $code): string
    {
        return match ($code) {
            100 => 'Processed',
            101 => 'Sent',
            102 => 'Rejected',
            401, 402 => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function formatSdkError(array $data): string
    {
        $payload = $data['data'] ?? null;

        if (is_string($payload) && trim($payload) !== '') {
            return trim($payload);
        }

        if (is_array($payload)) {
            foreach (['errorMessage', 'message', 'description'] as $key) {
                if (! empty($payload[$key]) && is_string($payload[$key])) {
                    return $payload[$key];
                }
            }
        }

        $message = $data['SMSMessageData']['Message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        return 'Africa\'s Talking returned an error response.';
    }

    protected function responseToArray(mixed $response): array
    {
        if (is_array($response)) {
            return $this->deepArray($response);
        }

        if (is_object($response) && method_exists($response, 'json')) {
            return $this->deepArray((array) $response->json());
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            return $this->deepArray($response->toArray());
        }

        return $this->deepArray(json_decode(json_encode($response), true) ?? []);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    protected function deepArray(array $value): array
    {
        return json_decode(json_encode($value), true) ?? [];
    }

    public function normalizePhoneNumber(string $phone): string
    {
        return $this->normalizePhone($phone);
    }

    public function isValidKenyanMobileNumber(string $phone): bool
    {
        return $this->isValidKenyanMobile($phone);
    }

    /**
     * Normalize and validate a recipient phone for outbound SMS.
     * Returns null when the number cannot be delivered reliably.
     */
    public function resolveRecipientPhone(string $phone): ?string
    {
        if (trim($phone) === '') {
            return null;
        }

        $normalized = $this->normalizePhone($phone);

        return $this->isValidKenyanMobile($normalized) ? $normalized : null;
    }
}
