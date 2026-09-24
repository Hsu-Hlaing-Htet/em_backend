<?php

namespace App\Mail;

use App\Models\Utility;

class UtilityDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(Utility $utility, string $customerName)
    {
        parent::__construct(
            emailSubject: 'Utility Bill Available',
            customerName: $customerName,
            introLine: 'Your utility bill is now available in your Customer Portal.',
            detailLine: 'Please log in to your Customer Portal to view the details and make payment if required.',
        );
    }
}
