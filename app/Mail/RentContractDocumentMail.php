<?php

namespace App\Mail;

class RentContractDocumentMail extends ContractDocumentMail
{
    public function __construct(\App\Models\Contract $contract, string $documentHtml)
    {
        parent::__construct($contract, $documentHtml, 'Property Rent Agreement');
    }
}
