<?php

namespace App\Mail;

class SaleContractDocumentMail extends ContractDocumentMail
{
    public function __construct(\App\Models\Contract $contract, string $documentHtml)
    {
        parent::__construct($contract, $documentHtml, 'Property Sale Agreement');
    }
}
