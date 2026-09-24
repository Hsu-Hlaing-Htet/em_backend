<?php

namespace App\Mail;

use App\Models\Payment;

/**
 * Unused via HTTP (payment has no customer document send route).
 * Kept as notification-only if ever invoked — never attaches a PDF or portal link.
 */
class PaymentDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(Payment $payment, string $customerName)
    {
        parent::__construct(
            emailSubject: 'Payment Update',
            customerName: $customerName,
            introLine: 'Your payment status has been updated in your Customer Portal.',
            detailLine: 'Please log in to your Customer Portal to view the details.',
        );
    }
}
