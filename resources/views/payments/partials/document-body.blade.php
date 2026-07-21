@include('documents.partials.fields', [
    'title' => 'Details',
    'fields' => $document['details'],
])

@include('documents.partials.amount-summary', [
    'totalLabel' => $document['amountPaid']['label'],
    'totalAmount' => $document['amountPaid']['amount'],
])

<p class="pdf-doc-note">This document confirms that the payment listed above has been recorded against the referenced invoice.</p>
