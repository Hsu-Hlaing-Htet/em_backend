@include('documents.partials.fields', [
    'title' => 'Details',
    'fields' => $document['details'],
])

@include('documents.partials.table', [
    'title' => 'Line Items',
    'columns' => [
        ['label' => 'Description', 'key' => 'description'],
        ['label' => 'Charge Type', 'key' => 'charge_type'],
        ['label' => 'Amount', 'key' => 'amount'],
    ],
    'rows' => $document['items'],
    'emptyMessage' => 'No line items recorded.',
])

@include('documents.partials.amount-summary', [
    'totalLabel' => $document['totalDue']['label'],
    'totalAmount' => $document['totalDue']['amount'],
    'details' => $document['totalDue']['details'] ?? [],
])

<p class="pdf-doc-note">{{ $document['paymentTerms'] }}</p>
