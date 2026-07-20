@include('documents.partials.fields', [
    'title' => 'Details',
    'fields' => $document['details'],
])

@include('documents.partials.table', [
    'title' => 'Meter Readings',
    'columns' => [
        ['label' => 'Utility Type', 'key' => 'utility_type'],
        ['label' => 'Previous', 'key' => 'previous_reading'],
        ['label' => 'Current', 'key' => 'current_reading'],
        ['label' => 'Usage', 'key' => 'usage'],
        ['label' => 'Unit Price', 'key' => 'unit_price'],
        ['label' => 'Amount', 'key' => 'amount'],
    ],
    'rows' => $document['readings'],
    'emptyMessage' => 'No meter readings recorded.',
])

@include('documents.partials.amount-summary', [
    'totalLabel' => $document['totalDue']['label'],
    'totalAmount' => $document['totalDue']['amount'],
])
