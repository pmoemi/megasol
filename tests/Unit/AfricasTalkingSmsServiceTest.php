<?php

namespace Tests\Unit;

use App\Services\Sms\AfricasTalkingSmsService;
use Tests\TestCase;

class AfricasTalkingSmsServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $response
     * @return array{success: bool, message_id: ?string, status: string, cost: ?string, raw: mixed}
     */
    private function parseSendResponse(array $response): array
    {
        $service = app(AfricasTalkingSmsService::class);
        $method = new \ReflectionMethod($service, 'parseSendResponse');
        $method->setAccessible(true);

        return $method->invoke($service, $response);
    }

    public function test_parse_send_response_unwraps_africastalking_sdk_v3_envelope(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'success',
            'data' => [
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1 Total Cost: KES 0.8000',
                    'Recipients' => [[
                        'statusCode' => 101,
                        'number' => '+254737468555',
                        'status' => 'Success',
                        'messageId' => 'ATXid_abc123',
                        'cost' => 'KES 0.8000',
                    ]],
                ],
            ],
        ]);

        $this->assertTrue($parsed['success']);
        $this->assertSame('success', $parsed['status']);
        $this->assertSame('ATXid_abc123', $parsed['message_id']);
        $this->assertSame('KES 0.8000', $parsed['cost']);
    }

    public function test_parse_send_response_reads_direct_api_payload(): void
    {
        $parsed = $this->parseSendResponse([
            'SMSMessageData' => [
                'Recipients' => [[
                    'status' => 'Sent',
                    'messageId' => 'ATXid_direct',
                ]],
            ],
        ]);

        $this->assertSame('sent', $parsed['status']);
        $this->assertSame('ATXid_direct', $parsed['message_id']);
    }

    public function test_parse_send_response_maps_status_code_when_status_missing(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'success',
            'data' => [
                'SMSMessageData' => [
                    'Recipients' => [[
                        'statusCode' => 101,
                        'messageId' => 'ATXid_code_only',
                    ]],
                ],
            ],
        ]);

        $this->assertSame('sent', $parsed['status']);
        $this->assertSame('ATXid_code_only', $parsed['message_id']);
    }

    public function test_parse_send_response_treats_sdk_error_envelope_as_failure(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'error',
            'data' => 'Invalid sender id: MEGASOL',
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('failed', $parsed['status']);
        $this->assertSame('Invalid sender id: MEGASOL', $parsed['error']);
    }

    public function test_parse_send_response_treats_status_code_100_as_success_like_megawatt(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'success',
            'data' => [
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1 Total Cost: KES 0.8000',
                    'Recipients' => [[
                        'statusCode' => 100,
                        'status' => 'Success',
                        'messageId' => 'ATXid_test',
                        'number' => '+254737468555',
                    ]],
                ],
            ],
        ]);

        $this->assertTrue($parsed['success']);
        $this->assertSame('success', $parsed['status']);
    }

    public function test_parse_send_response_detects_blacklisted_number(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'success',
            'data' => [
                'SMSMessageData' => [
                    'Recipients' => [[
                        'status' => 'UserInBlacklist',
                        'messageId' => 'ATXid_bl',
                    ]],
                ],
            ],
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('rejected', $parsed['status']);
        $this->assertStringContainsString('blacklisted', strtolower($parsed['error'] ?? ''));
    }

    public function test_parse_send_response_fails_when_no_recipients_reached(): void
    {
        $parsed = $this->parseSendResponse([
            'status' => 'success',
            'data' => [
                'SMSMessageData' => [
                    'Message' => 'Sent to 0/1 Total Cost: KES 0.0000',
                    'Recipients' => [],
                ],
            ],
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('failed', $parsed['status']);
    }

    public function test_resolve_recipient_phone_normalizes_valid_kenyan_numbers(): void
    {
        $service = app(AfricasTalkingSmsService::class);

        $this->assertSame('254725584124', $service->resolveRecipientPhone('254725584124'));
        $this->assertSame('254725584124', $service->resolveRecipientPhone('0725584124'));
        $this->assertSame('254725584124', $service->resolveRecipientPhone('+254725584124'));
    }

    public function test_resolve_recipient_phone_rejects_invalid_numbers(): void
    {
        $service = app(AfricasTalkingSmsService::class);

        $this->assertNull($service->resolveRecipientPhone('25472584124'));
        $this->assertNull($service->resolveRecipientPhone(''));
        $this->assertNull($service->resolveRecipientPhone('12345'));
    }
}
