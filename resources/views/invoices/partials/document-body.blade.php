<div class="invoice-doc__intro">
    <div class="invoice-doc__intro-left">
        <section>
            <p class="invoice-doc__block-label">Bill To</p>
            <p class="invoice-doc__block-name">{{ $document['billTo']['name'] ?? '—' }}</p>
            <p class="invoice-doc__block-line">{{ $document['billTo']['email'] ?? '—' }}</p>
            <p class="invoice-doc__block-line">{{ $document['billTo']['phone'] ?? '—' }}</p>
        </section>

        <section>
            <p class="invoice-doc__block-label">Property</p>
            <p class="invoice-doc__block-name">{{ $document['property']['building'] ?? '—' }}</p>
            <p class="invoice-doc__block-line">
                @if (($document['property']['room'] ?? '—') === '—')
                    —
                @else
                    Unit {{ $document['property']['room'] }}
                @endif
            </p>
        </section>
    </div>

    <aside class="invoice-doc__summary">
        <div class="invoice-doc__summary-row">
            <span class="invoice-doc__summary-label">Invoice No.</span>
            <span class="invoice-doc__summary-value">{{ $document['summary']['invoice_number'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__summary-row">
            <span class="invoice-doc__summary-label">Issue Date</span>
            <span class="invoice-doc__summary-value">{{ $document['summary']['issue_date'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__summary-row">
            <span class="invoice-doc__summary-label">Due Date</span>
            <span class="invoice-doc__summary-value">{{ $document['summary']['due_date'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__summary-row">
            <span class="invoice-doc__summary-label">Billing Period</span>
            <span class="invoice-doc__summary-value">{{ $document['summary']['billing_period'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__summary-row invoice-doc__summary-row--due">
            <span class="invoice-doc__summary-label">Amount Due</span>
            <span class="invoice-doc__summary-value">{{ $document['summary']['amount_due'] ?? '—' }}</span>
        </div>
    </aside>
</div>

<div class="invoice-doc__table-wrap">
    <table class="invoice-doc__table">
        <thead>
            <tr>
                <th class="invoice-doc__col-desc">Description</th>
                <th class="invoice-doc__col-meter is-center">Previous Unit</th>
                <th class="invoice-doc__col-meter is-center">Current Unit</th>
                <th class="invoice-doc__col-meter is-center">Usage</th>
                <th class="invoice-doc__col-price is-num">Unit Price</th>
                <th class="invoice-doc__col-amount is-num">Amount</th>
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

<div class="invoice-doc__after">
    <section class="invoice-doc__notes">
        <p class="invoice-doc__notes-title">Notes</p>
        <p>{{ $document['notes'] ?? '' }}</p>
    </section>

    <div class="invoice-doc__totals">
        <div class="invoice-doc__totals-row">
            <span>Subtotal</span>
            <span>{{ $document['totals']['subtotal'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__totals-row">
            <span>Late Fee</span>
            <span>{{ $document['totals']['late_fee'] ?? '—' }}</span>
        </div>
        <div class="invoice-doc__totals-row invoice-doc__totals-row--due">
            <span>Amount Due</span>
            <span>{{ $document['totals']['amount_due'] ?? '—' }}</span>
        </div>
    </div>
</div>
