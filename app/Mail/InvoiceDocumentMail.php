<?php

namespace App\Mail;

use App\Models\Invoice;

class InvoiceDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(Invoice $invoice, string $customerName)
    {
        parent::__construct(
            emailSubject: 'Invoice Available',
            customerName: $customerName,
            introLine: 'Your invoice is now available in your Customer Portal.',
            detailLine: 'Please log in to your Customer Portal to view the details and make payment if required.',
        );
    }
}
