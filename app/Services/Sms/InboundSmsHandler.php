<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Models\SmsMessage;
use Illuminate\Support\Str;

/**
 * Processes inbound SMS from customers: records the message, matches it to a
 * customer, and handles common keywords (STOP, BALANCE, HELP) with automatic
 * replies. Anything unrecognised is logged as an inbound enquiry/complaint and
 * flagged for follow-up.
 */
class InboundSmsHandler
{
    /**
     * @return array{message: SmsMessage, customer: ?Customer, intent: string, reply: ?string}
     */
    public function handle(string $from, string $text, array $meta = []): array
    {
        $from = $this->normalizePhone($from);
        $text = trim($text);
        $keyword = Str::upper(Str::of($text)->trim()->explode(' ')->first() ?? '');

        $customer = $this->matchCustomer($from);
        $intent = $this->classify($keyword, $text);

        $inboundSender = config('africastalking.inbound.sender_id') ?: config('africastalking.sender_id');

        $message = SmsMessage::create([
            'customer_id' => $customer?->id,
            'to' => (string) ($meta['to'] ?? $inboundSender ?? ''),
            'from' => $from,
            'body' => $text,
            'direction' => 'inbound',
            'status' => 'delivered',
            'provider_message_id' => $meta['provider_message_id'] ?? null,
            'meta' => array_merge($meta, ['intent' => $intent]),
            'delivered_at' => now(),
        ]);

        $reply = $this->applyIntent($intent, $customer, $message);

        // Transactional replies to a direct inbound message are always sent —
        // the opt-out flag only suppresses proactive/marketing messaging.
        // Replies go out on the dedicated inbound shortcode / account (falling
        // back to the outbound credentials when no separate inbound config set).
        if ($reply !== null && $from !== '') {
            // Use the inbound account's own credentials as an atomic pair; only
            // fall back to the outbound account when no inbound key is set.
            $inUsername = config('africastalking.inbound.username');
            $inApiKey = config('africastalking.inbound.api_key');
            $useInboundAccount = $inUsername && $inApiKey;

            SendSmsJob::dispatch(
                to: $from,
                message: $reply,
                meta: ['inbound_reply_to' => $message->id],
                senderId: $inboundSender,
                username: $useInboundAccount ? $inUsername : null,
                apiKey: $useInboundAccount ? $inApiKey : null,
            );
        }

        return [
            'message' => $message,
            'customer' => $customer,
            'intent' => $intent,
            'reply' => $reply,
        ];
    }

    protected function classify(string $keyword, string $text): string
    {
        return match (true) {
            in_array($keyword, ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'OPTOUT', 'END', 'QUIT'], true) => 'opt_out',
            in_array($keyword, ['START', 'SUBSCRIBE', 'OPTIN', 'YES'], true) => 'opt_in',
            in_array($keyword, ['BAL', 'BALANCE', 'B'], true) => 'balance',
            in_array($keyword, ['HELP', 'INFO', 'MENU'], true) => 'help',
            Str::contains(Str::lower($text), ['complain', 'problem', 'fault', 'broken', 'not working', 'issue']) => 'complaint',
            default => 'enquiry',
        };
    }

    protected function applyIntent(string $intent, ?Customer $customer, SmsMessage $message): ?string
    {
        return match ($intent) {
            'opt_out' => $this->handleOptOut($customer),
            'opt_in' => $this->handleOptIn($customer),
            'balance' => $this->handleBalance($customer),
            'help' => $this->helpText(),
            'complaint' => $this->flag($message, 'complaint', 'Thank you. Your message has been received and an agent will contact you shortly.'),
            default => $this->flag($message, 'enquiry', null),
        };
    }

    protected function handleOptOut(?Customer $customer): string
    {
        $customer?->update(['sms_opted_out' => true]);

        return 'You have been unsubscribed and will no longer receive messages. Reply START to opt back in.';
    }

    protected function handleOptIn(?Customer $customer): string
    {
        $customer?->update(['sms_opted_out' => false]);

        return 'You have been re-subscribed. Welcome back!';
    }

    protected function handleBalance(?Customer $customer): string
    {
        if (! $customer) {
            return 'We could not find an account for this number. Please contact support.';
        }

        $balance = number_format((float) ($customer->outstanding_balance ?? 0), 2);
        $due = $customer->next_payment_date?->format('M j, Y');
        $tokens = (int) $customer->token_balance;

        $parts = ["Hi {$customer->first_name}, your balance is KES {$balance}."];
        if ($due) {
            $parts[] = "Next payment due {$due}.";
        }
        $parts[] = "Token balance: {$tokens} day(s).";

        return implode(' ', $parts);
    }

    protected function helpText(): string
    {
        return 'MegaSol: Reply BALANCE for your account balance, STOP to unsubscribe, or describe your issue and an agent will help.';
    }

    /**
     * Flag an inbound message for human follow-up and optionally auto-reply.
     */
    protected function flag(SmsMessage $message, string $reason, ?string $reply): ?string
    {
        $meta = $message->meta ?? [];
        $meta['needs_follow_up'] = true;
        $meta['follow_up_reason'] = $reason;
        $message->update(['meta' => $meta]);

        return $reply;
    }

    protected function matchCustomer(string $from): ?Customer
    {
        $digits = preg_replace('/\D+/', '', $from);
        $tail = Str::substr($digits, -9); // match on last 9 digits to ignore country-code variance

        return Customer::query()
            ->where('phone', $from)
            ->orWhere('phone', 'like', "%{$tail}")
            ->first();
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        return $phone === '' ? $phone : (Str::startsWith($phone, '+') ? $phone : '+'.ltrim($phone, '+'));
    }
}
