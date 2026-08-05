<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HtmlDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $referenceNumber,
        public string $documentPdf,
        public string $subjectPrefix,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectPrefix.' - '.$this->referenceNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Please find your '.$this->subjectPrefix.' ('.e($this->referenceNumber).') attached.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->documentPdf, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
