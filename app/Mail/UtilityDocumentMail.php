<?php

namespace App\Mail;

use App\Models\Utility;

class UtilityDocumentMail extends HtmlDocumentMail
{
    public function __construct(Utility $utility, string $documentPdf, string $filename, string $referenceNumber)
    {
        parent::__construct(
            referenceNumber: $referenceNumber,
            documentPdf: $documentPdf,
            subjectPrefix: 'Utility Bill',
            filename: $filename,
        );
    }
}
