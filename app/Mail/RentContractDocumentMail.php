<?php

namespace App\Mail;

class RentContractDocumentMail extends ContractDocumentMail
{
    public function __construct(\App\Models\Contract $contract, string $documentPdf, string $filename, ?string $customerContractUrl = null)
    {
        parent::__construct($contract, $documentPdf, 'Rental/Lease Agreement', $filename, $customerContractUrl);
    }
}
