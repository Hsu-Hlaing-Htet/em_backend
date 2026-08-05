<?php

namespace App\Mail;

class SaleContractDocumentMail extends ContractDocumentMail
{
    public function __construct(\App\Models\Contract $contract, string $documentPdf, string $filename)
    {
        parent::__construct($contract, $documentPdf, 'Property Sale Agreement', $filename);
    }
}
