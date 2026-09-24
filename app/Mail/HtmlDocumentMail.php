<?php

namespace App\Mail;

/**
 * @deprecated Use CustomerDocumentAvailableMail. Kept so old references fail loudly if revived.
 */
class HtmlDocumentMail extends CustomerDocumentAvailableMail
{
    public function __construct(
        string $referenceNumber,
        string $documentPdf,
        string $subjectPrefix,
        string $filename,
    ) {
        unset($documentPdf, $filename);

        parent::__construct(
            emailSubject: $subjectPrefix.' Available',
            customerName: 'Customer',
            introLine: 'Your document ('.$referenceNumber.') is now available in your Customer Portal.',
            detailLine: 'Please log in to your Customer Portal to view or download it.',
        );
    }
}
