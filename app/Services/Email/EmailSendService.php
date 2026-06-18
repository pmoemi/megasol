<?php

namespace App\Services\Email;

use App\Mail\CampaignEmail;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSendService
{
    public function sendCampaignEmail(
        string $to,
        string $subject,
        string $htmlBody,
        ?int $emailMessageId = null,
        ?string $previewText = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void {
        $log = $emailMessageId
            ? EmailMessage::findOrFail($emailMessageId)
            : EmailMessage::create([
                'to' => $to,
                'subject' => $subject,
                'body_html' => $htmlBody,
                'direction' => 'outbound',
                'status' => 'queued',
            ]);

        try {
            Mail::to($to)->send(new CampaignEmail($subject, $htmlBody, $previewText, $fromEmail, $fromName));

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Campaign email sent', ['to' => $to, 'subject' => $subject]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
