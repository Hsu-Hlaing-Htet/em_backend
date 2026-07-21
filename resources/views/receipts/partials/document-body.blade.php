@include('documents.partials.fields', [
    'title' => 'Details',
    'fields' => $document['details'],
])

@include('documents.partials.amount-summary', [
    'totalLabel' => $document['amountReceived']['label'],
    'totalAmount' => $document['amountReceived']['amount'],
])

@include('documents.partials.acknowledgement', [
    'title' => 'Acknowledgement',
    'message' => 'Payment received and acknowledged by Rosewood Royale Residences.',
    'leftName' => $document['acknowledgement']['customerName'],
    'leftRole' => 'Customer Signature',
    'rightName' => $document['acknowledgement']['representativeName'],
    'rightRole' => 'Company Representative',
])
