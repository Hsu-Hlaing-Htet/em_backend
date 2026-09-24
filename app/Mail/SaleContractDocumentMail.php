<?php

namespace App\Mail;

use App\Models\Contract;

class SaleContractDocumentMail extends ContractDocumentMail
{
    public function __construct(Contract $contract, string $customerName)
    {
        parent::__construct(
            contract: $contract,
            emailSubject: 'Sale Contract Available',
            customerName: $customerName,
            introLine: 'Your sale contract is now available in your Customer Portal.',
        );
    }
}
