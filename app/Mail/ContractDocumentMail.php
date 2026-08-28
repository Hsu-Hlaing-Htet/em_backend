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
        public string $documentPdf,
        public string $subjectPrefix,
        public string $filename,
        public ?string $customerContractUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customerContractUrl
                ? 'Your Rosewood Royale '.$this->subjectPrefix.' – '.$this->contract->contract_number
                : $this->subjectPrefix.' - '.$this->contract->contract_number,
        );
    }

    public function content(): Content
    {
        if (! $this->customerContractUrl) {
            return new Content(
                htmlString: '<p>Please find your '.$this->subjectPrefix.' ('.e($this->contract->contract_number).') attached.</p>',
            );
        }

        $customerName = e($this->contract->user?->name ?? 'Customer');
        $contractNumber = e($this->contract->contract_number);
        $contractType = e($this->subjectPrefix);
        $portalLink = '<p><a href="'.e($this->customerContractUrl).'" style="display:inline-block;padding:10px 16px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:4px;">View Contract</a></p>';

        return new Content(
            htmlString: <<<HTML
<p>Dear {$customerName},</p>
<p>Please find attached your Rosewood Royale {$contractType}.</p>
<p>
    Contract No: {$contractNumber}<br>
    Contract Type: {$contractType}
</p>
<p>You can also view and download your contract from the Rosewood Royale Customer Portal.</p>
{$portalLink}
<p>
    Regards,<br>
    Rosewood Royale Residences<br>
    Residences &amp; Property Management
</p>
HTML,
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
