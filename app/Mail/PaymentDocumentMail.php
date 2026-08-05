<?php

namespace App\Mail;

use App\Models\Payment;

class PaymentDocumentMail extends HtmlDocumentMail
{
    public function __construct(Payment $payment, string $documentPdf, string $filename)
    {
        $reference = sprintf('PAY-%05d', $payment->id);

        parent::__construct(
            referenceNumber: $reference,
            documentPdf: $documentPdf,
            subjectPrefix: 'Payment Confirmation',
            filename: $filename,
        );
    }
}
