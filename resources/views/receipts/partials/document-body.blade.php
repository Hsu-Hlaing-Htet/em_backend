<section class="receipt-doc__info">
    <div class="receipt-doc__info-col">
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Customer Name</span>
            <span class="receipt-doc__info-value">{{ $document['info']['customer_name'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Building</span>
            <span class="receipt-doc__info-value">{{ $document['info']['building'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Room</span>
            <span class="receipt-doc__info-value">{{ $document['info']['room'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Paid By</span>
            <span class="receipt-doc__info-value">{{ $document['info']['paid_by'] ?? '—' }}</span>
        </div>
    </div>
    <div class="receipt-doc__info-col">
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Invoice No.</span>
            <span class="receipt-doc__info-value">{{ $document['info']['invoice_number'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Approved By</span>
            <span class="receipt-doc__info-value">{{ $document['info']['approved_by'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Payment Method</span>
            <span class="receipt-doc__info-value">{{ $document['info']['payment_method'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__info-row">
            <span class="receipt-doc__info-label">Payment Date</span>
            <span class="receipt-doc__info-value">{{ $document['info']['payment_date'] ?? '—' }}</span>
        </div>
    </div>
</section>

<div class="receipt-doc__table-wrap">
    <table class="receipt-doc__table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="is-num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($document['items'] ?? []) as $row)
                <tr>
                    <td>{{ $row['description'] ?? '—' }}</td>
                    <td class="is-num">{{ $row['amount'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No charges recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="receipt-doc__totals">
    <div class="receipt-doc__totals-row">
        <span>Subtotal</span>
        <span>{{ $document['totals']['subtotal'] ?? '—' }}</span>
    </div>
    <div class="receipt-doc__totals-row">
        <span>Late Fee</span>
        <span>{{ $document['totals']['late_fee'] ?? '—' }}</span>
    </div>
    <div class="receipt-doc__totals-row receipt-doc__totals-row--section">
        <span>Total</span>
        <span>{{ $document['totals']['total'] ?? '—' }}</span>
    </div>
    <div class="receipt-doc__totals-row">
        <span>Paid</span>
        <span>{{ $document['totals']['paid'] ?? '—' }}</span>
    </div>
    @if (! empty($document['totals']['show_change']))
        <div class="receipt-doc__totals-row receipt-doc__totals-row--due">
            <span>Change</span>
            <span>{{ $document['totals']['change'] ?? '—' }}</span>
        </div>
    @else
        <div class="receipt-doc__totals-row receipt-doc__totals-row--due">
            <span>Balance</span>
            <span>{{ $document['totals']['balance'] ?? '—' }}</span>
        </div>
    @endif
</div>

<section class="receipt-doc__confirmation">
    @if (! empty($document['late_fee_notes']['rule']))
        <div class="receipt-doc__late-fee">
            <p class="receipt-doc__late-fee-label">Late Fee Rule</p>
            <p class="receipt-doc__late-fee-line">{{ $document['late_fee_notes']['rule'] }}</p>
            @if (! empty($document['late_fee_notes']['calculation']))
                <p class="receipt-doc__late-fee-label receipt-doc__late-fee-label--calc">Calculation</p>
                @foreach ($document['late_fee_notes']['calculation'] as $line)
                    <p class="receipt-doc__late-fee-line">{{ $line }}</p>
                @endforeach
            @endif
        </div>
    @endif
    <p class="receipt-doc__confirmation-title">{{ $document['confirmation']['title'] ?? 'Payment received successfully.' }}</p>
    <p class="receipt-doc__confirmation-message">{{ $document['confirmation']['message'] ?? 'This receipt confirms that the payment has been recorded successfully.' }}</p>
</section>
