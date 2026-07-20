<?php

namespace App\Mail;

use App\Models\Invoice;

class InvoiceDocumentMail extends HtmlDocumentMail
{
    public function __construct(Invoice $invoice, string $documentHtml)
    {
        parent::__construct(
            referenceNumber: $invoice->invoice_number,
            documentHtml: $documentHtml,
            subjectPrefix: 'Tax Invoice',
            filename: ($invoice->invoice_number ?: 'invoice').'.html',
        );
    }
}
