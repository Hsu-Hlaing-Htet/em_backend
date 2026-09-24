<?php

namespace App\Mail;

use App\Models\Contract;

class ContractDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(
        public Contract $contract,
        string $emailSubject,
        string $customerName,
        string $introLine,
    ) {
        parent::__construct(
            emailSubject: $emailSubject,
            customerName: $customerName,
            introLine: $introLine,
            detailLine: 'Please log in to your Customer Portal to view or download your contract.',
        );
    }
}
