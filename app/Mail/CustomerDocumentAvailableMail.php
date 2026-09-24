<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Customer-facing document availability email — plain text notification only.
 * Never attaches PDFs or includes Customer Portal links/buttons.
 */
class CustomerDocumentAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $customerName,
        public string $introLine,
        public string $detailLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $name = e($this->customerName !== '' ? $this->customerName : 'Customer');
        $intro = e($this->introLine);
        $detail = e($this->detailLine);

        return new Content(
            htmlString: <<<HTML
<p>Dear {$name},</p>
<p>{$intro}</p>
<p>{$detail}</p>
<p>Rosewood Royale</p>
HTML,
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
