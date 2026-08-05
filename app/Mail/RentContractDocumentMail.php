<?php

namespace App\Mail;

class RentContractDocumentMail extends ContractDocumentMail
{
    public function __construct(\App\Models\Contract $contract, string $documentPdf, string $filename)
    {
        parent::__construct($contract, $documentPdf, 'Property Rent Agreement', $filename);
    }
}
