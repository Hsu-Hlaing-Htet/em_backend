<?php

namespace App\Mail;

use App\Models\Receipt;

class ReceiptDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(Receipt $receipt, string $customerName)
    {
        parent::__construct(
            emailSubject: 'Receipt Available',
            customerName: $customerName,
            introLine: 'Your receipt is now available in your Customer Portal.',
            detailLine: 'Please log in to your Customer Portal to view or download your receipt.',
        );
    }
}
