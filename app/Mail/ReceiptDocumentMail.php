<?php

namespace App\Mail;

use App\Models\Receipt;

class ReceiptDocumentMail extends HtmlDocumentMail
{
    public function __construct(Receipt $receipt, string $documentHtml)
    {
        parent::__construct(
            referenceNumber: $receipt->receipt_number,
            documentHtml: $documentHtml,
            subjectPrefix: 'Payment Receipt',
            filename: ($receipt->receipt_number ?: 'receipt').'.html',
        );
    }
}
