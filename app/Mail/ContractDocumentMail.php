<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public string $documentHtml,
        public string $subjectPrefix,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectPrefix.' - '.$this->contract->contract_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Please find your '.$this->subjectPrefix.' ('.e($this->contract->contract_number).') attached.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = ($this->contract->contract_number ?: 'contract').'.html';

        return [
            Attachment::fromData(fn () => $this->documentHtml, $filename)
                ->withMime('text/html'),
        ];
    }
}
