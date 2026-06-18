<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use App\Services\Campaign\CampaignService;
use App\Services\Email\EmailSendService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $recipientId) {}

    public function handle(EmailSendService $emailSend, CampaignService $campaigns): void
    {
        $recipient = CampaignRecipient::with(['customer', 'campaign'])->find($this->recipientId);

        if (! $recipient || ! in_array($recipient->status, ['pending', 'queued'], true)) {
            return;
        }

        $campaign = $recipient->campaign;
        $customer = $recipient->customer;

        if (! $customer?->email) {
            $recipient->update(['status' => 'failed', 'error_message' => 'Customer has no email address.']);

            return;
        }

        $subject = $recipient->subject ?: $campaign?->subject ?: 'Message from MegaSol';
        $html = $recipient->body_html ?: $campaign?->body_html ?: '';

        if ($html === '') {
            $recipient->update(['status' => 'failed', 'error_message' => 'Empty email body.']);

            return;
        }

        // Inject open-tracking pixel + rewrite links for click tracking.
        $html = $this->injectTracking($html, $recipient, $campaign);

        try {
            $emailMessage = \App\Models\EmailMessage::create([
                'customer_id' => $customer->id,
                'campaign_id' => $campaign?->id,
                'campaign_recipient_id' => $recipient->id,
                'to' => $customer->email,
                'subject' => $subject,
                'body_html' => $html,
                'direction' => 'outbound',
                'status' => 'queued',
            ]);

            $emailSend->sendCampaignEmail(
                to: $customer->email,
                subject: $subject,
                htmlBody: $html,
                emailMessageId: $emailMessage->id,
                previewText: $campaign?->preview_text,
                fromEmail: $campaign?->from_email ?: null,
                fromName: $campaign?->from_name ?: null,
            );

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $campaigns->incrementCampaignStats($campaign, 'sent');
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SendCampaignEmailJob failed', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Inject the open-tracking pixel and rewrite links so opens and clicks
     * can be attributed back to this recipient (via its UUID) for reporting.
     */
    protected function injectTracking(string $html, CampaignRecipient $recipient, ?Campaign $campaign): string
    {
        if (! $recipient->uuid) {
            return $html;
        }

        $pixelUrl = url("/track/campaign/{$recipient->uuid}/open");
        $pixel = '<img src="'.$pixelUrl.'" width="1" height="1" alt="" style="display:none;">';

        $count = 0;
        $html = preg_replace('/<\/body\b/i', $pixel.'</body', $html, 1, $count);
        if (! $count) {
            $html .= $pixel;
        }

        if ($campaign) {
            $html = $this->rewriteLinks($html, $recipient, $campaign);
        }

        return $html;
    }

    /**
     * Replace href targets with click-tracking redirect URLs, recording a
     * CampaignLink row per unique destination so click counts can aggregate.
     */
    protected function rewriteLinks(string $html, CampaignRecipient $recipient, Campaign $campaign): string
    {
        return preg_replace_callback(
            '/href="([^"]+)"/i',
            function ($matches) use ($recipient, $campaign) {
                $originalUrl = $matches[1];

                // Skip anchors, mailto/tel, merge tags, and already-tracked links.
                if (
                    $originalUrl === '' ||
                    str_starts_with($originalUrl, '#') ||
                    str_starts_with($originalUrl, 'mailto:') ||
                    str_starts_with($originalUrl, 'tel:') ||
                    str_contains($originalUrl, '{{') ||
                    str_contains($originalUrl, '/track/campaign/')
                ) {
                    return $matches[0];
                }

                $link = CampaignLink::firstOrCreate(
                    ['campaign_id' => $campaign->id, 'original_url' => $originalUrl],
                    ['tracking_hash' => Str::random(32), 'clicks_count' => 0],
                );

                $trackingUrl = url("/track/campaign/{$recipient->uuid}/click")
                    .'?url='.urlencode($originalUrl)
                    .'&lh='.$link->tracking_hash;

                return 'href="'.$trackingUrl.'"';
            },
            $html,
        );
    }
}
