<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $htmlBody,
        public ?string $previewText = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        // Use the per-campaign sender when provided; otherwise fall back to
        // the configured default mail "from" (config/mail.php / Mail settings).
        $from = $this->fromEmail
            ? new Address($this->fromEmail, $this->fromName ?: $this->fromEmail)
            : null;

        return new Envelope(
            from: $from,
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}
