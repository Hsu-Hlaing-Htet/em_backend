@php
    $contractVariant = [
        'companyRole' => 'Landlord',
        'customerRole' => 'Tenant',
        'agreementName' => 'Rental/Lease Agreement',
        'customerObligation' => 'The Tenant shall pay all amounts due under this Agreement in accordance with the agreed payment schedule, maintain the property in good condition, and comply with all applicable building rules and regulations.',
        'companyObligation' => 'The Landlord shall provide the property in habitable condition, maintain common areas, and ensure the premises remain available for the Tenant for the lease term.',
    ];
@endphp

@include('contract-documents.partials.document-body')
