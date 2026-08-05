<?php

namespace App\Mail;

use App\Models\Receipt;

class ReceiptDocumentMail extends HtmlDocumentMail
{
    public function __construct(Receipt $receipt, string $documentPdf, string $filename)
    {
        parent::__construct(
            referenceNumber: $receipt->receipt_number,
            documentPdf: $documentPdf,
            subjectPrefix: 'Payment Receipt',
            filename: $filename,
        );
    }
}
