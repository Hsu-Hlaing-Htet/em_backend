<?php

namespace App\Mail;

use App\Models\Contract;

class RentContractDocumentMail extends ContractDocumentMail
{
    public function __construct(Contract $contract, string $customerName)
    {
        parent::__construct(
            contract: $contract,
            emailSubject: 'Rent Contract Available',
            customerName: $customerName,
            introLine: 'Your rent contract is now available in your Customer Portal.',
        );
    }
}
