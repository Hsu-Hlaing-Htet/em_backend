<div class="receipt-doc__intro">
    <div class="receipt-doc__intro-left">
        <section>
            <p class="receipt-doc__block-label">Bill To</p>
            <p class="receipt-doc__block-name">{{ $document['billTo']['name'] ?? '—' }}</p>
            <p class="receipt-doc__block-line">{{ $document['billTo']['email'] ?? '—' }}</p>
            <p class="receipt-doc__block-line">{{ $document['billTo']['phone'] ?? '—' }}</p>
        </section>

        <section>
            <p class="receipt-doc__block-label">Property</p>
            <p class="receipt-doc__block-name">{{ $document['property']['building'] ?? '—' }}</p>
            <p class="receipt-doc__block-line">
                @if (($document['property']['room'] ?? '—') === '—')
                    —
                @else
                    Unit {{ $document['property']['room'] }}
                @endif
            </p>
        </section>
    </div>

    <aside class="receipt-doc__summary">
        <div class="receipt-doc__summary-row">
            <span class="receipt-doc__summary-label">Receipt No.</span>
            <span class="receipt-doc__summary-value">{{ $document['summary']['receipt_number'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__summary-row">
            <span class="receipt-doc__summary-label">Issue Date</span>
            <span class="receipt-doc__summary-value">{{ $document['summary']['issue_date'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__summary-row">
            <span class="receipt-doc__summary-label">Invoice No.</span>
            <span class="receipt-doc__summary-value">{{ $document['summary']['invoice_number'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__summary-row">
            <span class="receipt-doc__summary-label">Payment Date</span>
            <span class="receipt-doc__summary-value">{{ $document['summary']['payment_date'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__summary-row receipt-doc__summary-row--due">
            <span class="receipt-doc__summary-label">Amount Received</span>
            <span class="receipt-doc__summary-value">{{ $document['summary']['amount_received'] ?? '—' }}</span>
        </div>
    </aside>
</div>

<div class="receipt-doc__table-wrap">
    <table class="receipt-doc__table">
        <thead>
            <tr>
                <th class="receipt-doc__col-desc">Description</th>
                <th class="receipt-doc__col-meter is-center">Previous Unit</th>
                <th class="receipt-doc__col-meter is-center">Current Unit</th>
                <th class="receipt-doc__col-meter is-center">Usage</th>
                <th class="receipt-doc__col-price is-num">Unit Price</th>
                <th class="receipt-doc__col-amount is-num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($document['items'] ?? []) as $row)
                <tr>
                    <td>{{ $row['description'] ?? '—' }}</td>
                    <td class="is-center">{{ $row['previous_reading'] ?? '—' }}</td>
                    <td class="is-center">{{ $row['current_reading'] ?? '—' }}</td>
                    <td class="is-center">{{ $row['usage'] ?? '—' }}</td>
                    <td class="is-num">{{ $row['unit_price'] ?? '—' }}</td>
                    <td class="is-num">{{ $row['amount'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No line items recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="receipt-doc__after">
    <section class="receipt-doc__notes">
        <p class="receipt-doc__notes-title">Notes</p>
        <p>{{ $document['notes'] ?? '' }}</p>
    </section>

    <div class="receipt-doc__totals">
        <div class="receipt-doc__totals-row">
            <span>Invoice Total</span>
            <span>{{ $document['totals']['invoice_total'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__totals-row">
            <span>Amount Received</span>
            <span>{{ $document['totals']['amount_received'] ?? '—' }}</span>
        </div>
        <div class="receipt-doc__totals-row receipt-doc__totals-row--due">
            <span>Balance</span>
            <span>{{ $document['totals']['balance'] ?? '—' }}</span>
        </div>
    </div>
</div>
