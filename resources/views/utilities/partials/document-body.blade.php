<section class="utility-doc-body">
    @php
        $customer = $document['customerInfo'] ?? [];
        $readings = $document['readings'] ?? [];
    @endphp

    <div class="utility-doc-customer">
        <div class="utility-doc-customer__content">
            <div class="utility-doc-customer__name">{{ $customer['name'] ?? '—' }}</div>
            <div class="utility-doc-customer__muted">{{ $customer['address'] ?? '—' }}</div>
            <div class="utility-doc-customer__line">{{ $customer['phone'] ?? '—' }}</div>
            <div class="utility-doc-customer__line utility-doc-customer__line--wrap">{{ $customer['email'] ?? '—' }}</div>
            <div class="utility-doc-customer__line utility-doc-customer__line--spaced">{{ $customer['building'] ?? '—' }}</div>
            <div class="utility-doc-customer__line">{{ $customer['room'] ?? '—' }}</div>
        </div>
        <div class="utility-doc-customer__date">{{ $customer['issuedDate'] ?? '—' }}</div>
    </div>

    <div class="utility-doc-divider"></div>

    <div class="utility-doc-table-wrap">
        <table class="utility-doc-table">
            <thead>
                <tr>
                    <th class="utility-doc-table__head">Utility Type</th>
                    <th class="utility-doc-table__head utility-doc-table__numeric">Previous Unit</th>
                    <th class="utility-doc-table__head utility-doc-table__numeric">Current Unit</th>
                    <th class="utility-doc-table__head utility-doc-table__numeric">Usage</th>
                    <th class="utility-doc-table__head utility-doc-table__numeric">Unit Price</th>
                    <th class="utility-doc-table__head utility-doc-table__numeric">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readings as $row)
                    <tr>
                        <td class="utility-doc-table__cell">{{ $row['utility_type'] ?? '—' }}</td>
                        <td class="utility-doc-table__cell utility-doc-table__numeric">{{ $row['previous_reading'] ?? '—' }}</td>
                        <td class="utility-doc-table__cell utility-doc-table__numeric">{{ $row['current_reading'] ?? '—' }}</td>
                        <td class="utility-doc-table__cell utility-doc-table__numeric">{{ $row['usage'] ?? '—' }}</td>
                        <td class="utility-doc-table__cell utility-doc-table__numeric">{{ $row['unit_price'] ?? '—' }}</td>
                        <td class="utility-doc-table__cell utility-doc-table__numeric">{{ $row['amount'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="utility-doc-table__empty">No utility readings recorded.</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($readings))
                <tfoot>
                    <tr>
                        <td colspan="5" class="utility-doc-table__total-label">Total</td>
                        <td class="utility-doc-table__numeric utility-doc-table__total-value">
                            {{ $document['totalDue']['amount'] ?? '—' }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if (! empty($document['summaryNote']))
        <p class="utility-doc-summary">{{ $document['summaryNote'] }}</p>
    @endif
</section>

<style>
.utility-doc-body {
    margin-top: 0.5rem;
}

.utility-doc-customer {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.utility-doc-customer__name {
    margin-top: 1.25rem;
    font-size: 10pt;
    font-weight: 600;
    line-height: 1.5;
    color: #1c1c1c;
    word-break: break-word;
}

.utility-doc-customer__muted {
    margin-top: 0.375rem;
    font-size: 9.375pt;
    line-height: 1.6;
    color: #1c1c1c;
    opacity: 0.75;
    word-break: break-word;
}

.utility-doc-customer__line {
    margin-top: 1.25rem;
    font-size: 9.375pt;
    line-height: 1.5;
    color: #1c1c1c;
    word-break: break-word;
}

.utility-doc-customer__line--wrap {
    margin-top: 0.375rem;
    word-break: break-all;
}

.utility-doc-customer__line--spaced {
    margin-top: 1.25rem;
}

.utility-doc-customer__date {
    font-size: 8.75pt;
    line-height: 1.5;
    color: #1c1c1c;
    opacity: 0.7;
}

.utility-doc-divider {
    margin: 0 0 1.25rem;
    border-top: 1px solid rgba(28, 28, 28, 0.12);
}

.utility-doc-table-wrap {
    overflow-x: auto;
}

.utility-doc-table {
    width: 100%;
    min-width: 44rem;
    border-collapse: collapse;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.utility-doc-table__head,
.utility-doc-table__cell {
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid rgba(28, 28, 28, 0.12);
    text-align: left;
    font-size: 8.75pt;
    vertical-align: top;
}

.utility-doc-table__head {
    font-weight: 600;
    white-space: nowrap;
    color: #6b6560;
}

.utility-doc-table__cell {
    color: #1c1c1c;
}

.utility-doc-table__numeric {
    text-align: right;
    white-space: nowrap;
}

.utility-doc-table__empty {
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid rgba(28, 28, 28, 0.12);
    text-align: center;
    color: #6b6560;
    font-size: 8.75pt;
}

.utility-doc-table tfoot td {
    border-bottom: none;
    padding-top: 1rem;
}

.utility-doc-table__total-label {
    text-align: right;
    font-weight: 600;
    color: #1c1c1c;
}

.utility-doc-table__total-value {
    font-weight: 600;
    color: #7a3149;
}

.utility-doc-summary {
    margin: 2.5rem 0 0;
    font-size: 8.75pt;
    line-height: 1.6;
    color: #6b6560;
}

@media (min-width: 768px) {
    .utility-doc-customer {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 2rem;
        align-items: start;
    }

    .utility-doc-customer__date {
        text-align: right;
        padding-top: 1.25rem;
    }
}
</style>
