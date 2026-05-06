<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignOutreach extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subject,
        public string $bodyHtml,
        public string $campaignName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-outreach',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'campaignName' => $this->campaignName,
            ],
        );
    }
}
