<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Services\Sms\InboundSmsHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking delivery reports (DLR).
 *
 * Configure in AT dashboard:
 * https://your-domain.com/api/webhooks/africastalking/dlr?secret=YOUR_DLR_SECRET
 */
class AfricasTalkingWebhookController extends Controller
{
    public function deliveryReport(Request $request): Response
    {
        $secret = config('africastalking.dlr_secret');

        if ($secret && $request->query('secret') !== $secret) {
            Log::warning('Africa\'s Talking DLR: invalid secret');

            return response('Unauthorized', 401);
        }

        $messageId = $request->input('id') ?? $request->input('messageId');
        $status = strtolower((string) ($request->input('status') ?? $request->input('finalStatus') ?? ''));

        if ($messageId) {
            $sms = SmsMessage::where('provider_message_id', $messageId)->first();

            if ($sms) {
                $updates = ['status' => $this->mapDeliveryStatus($status)];

                if (in_array($updates['status'], ['delivered', 'success'], true)) {
                    $updates['delivered_at'] = now();
                }

                $sms->update($updates);
            }
        }

        Log::info('Africa\'s Talking DLR received', $request->all());

        return response('OK', 200);
    }

    /**
     * Inbound SMS (customer replies).
     *
     * Configure in AT dashboard:
     * https://your-domain.com/api/webhooks/africastalking/inbound?secret=YOUR_DLR_SECRET
     */
    public function inbound(Request $request, InboundSmsHandler $handler): Response
    {
        // Inbound runs on its own (possibly different) shortcode, so it has a
        // separate secret; fall back to the DLR secret if not configured.
        $secret = config('africastalking.inbound.secret') ?: config('africastalking.dlr_secret');

        if ($secret && $request->query('secret') !== $secret) {
            Log::warning('Africa\'s Talking inbound: invalid secret');

            return response('Unauthorized', 401);
        }

        $from = (string) ($request->input('from') ?? $request->input('From') ?? '');
        $text = (string) ($request->input('text') ?? $request->input('Text') ?? '');

        if ($from === '') {
            return response('Bad Request', 400);
        }

        $result = $handler->handle($from, $text, [
            'to' => $request->input('to') ?? $request->input('To'),
            'provider_message_id' => $request->input('id') ?? $request->input('messageId') ?? $request->input('linkId'),
            'link_id' => $request->input('linkId'),
        ]);

        Log::info('Africa\'s Talking inbound received', [
            'from' => $from,
            'intent' => $result['intent'],
            'matched_customer' => $result['customer']?->id,
        ]);

        return response('OK', 200);
    }

    protected function mapDeliveryStatus(string $status): string
    {
        return match ($status) {
            'success', 'sent' => 'sent',
            'delivered' => 'delivered',
            'failed', 'rejected', 'invalidphonenumber' => 'failed',
            'buffered', 'submitted' => 'queued',
            default => $status ?: 'unknown',
        };
    }
}
