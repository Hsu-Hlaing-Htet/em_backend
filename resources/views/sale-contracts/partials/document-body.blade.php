@php
    $contractVariant = [
        'companyRole' => 'Seller',
        'customerRole' => 'Owner',
        'agreementName' => 'Property Sale Agreement',
        'customerObligation' => 'The Owner shall pay all amounts due under this Agreement in accordance with the agreed payment schedule, maintain the property in good condition, and comply with all applicable building rules and regulations.',
        'companyObligation' => 'The Seller shall deliver clear title to the property, provide all necessary documentation, and ensure the property is transferred in the condition agreed upon at the time of execution.',
    ];
@endphp

@include('contract-documents.partials.document-body')
