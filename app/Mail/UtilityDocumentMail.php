<?php

namespace App\Mail;

use App\Models\Utility;

class UtilityDocumentMail extends HtmlDocumentMail
{
    public function __construct(Utility $utility, string $documentHtml)
    {
        $reference = sprintf(
            'UTL-%s-%s',
            optional($utility->billing_month)->format('Y-m') ?? '0000-00',
            $utility->room?->room_number ?? $utility->room_id,
        );

        parent::__construct(
            referenceNumber: $reference,
            documentHtml: $documentHtml,
            subjectPrefix: 'Utility Bill',
            filename: $reference.'.html',
        );
    }
}
