<?php

namespace App\Mail;

use App\Models\Payment;

class PaymentDocumentMail extends HtmlDocumentMail
{
    public function __construct(Payment $payment, string $documentHtml)
    {
        $reference = sprintf('PAY-%05d', $payment->id);

        parent::__construct(
            referenceNumber: $reference,
            documentHtml: $documentHtml,
            subjectPrefix: 'Payment Confirmation',
            filename: $reference.'.html',
        );
    }
}
