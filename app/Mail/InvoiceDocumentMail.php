<?php

namespace App\Mail;

use App\Models\Invoice;

class InvoiceDocumentMail extends HtmlDocumentMail
{
    public function __construct(Invoice $invoice, string $documentPdf, string $filename)
    {
        parent::__construct(
            referenceNumber: $invoice->invoice_number,
            documentPdf: $documentPdf,
            subjectPrefix: 'Tax Invoice',
            filename: $filename,
        );
    }
}
