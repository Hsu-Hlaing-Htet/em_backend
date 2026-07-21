<section class="pdf-block pdf-block--amount">
    <div class="pdf-amount-summary">
        <div class="pdf-amount-summary__details">
            @foreach ($details ?? [] as $item)
                <div class="pdf-amount-summary__row">
                    <span class="pdf-amount-summary__label">{{ $item['label'] }}</span>
                    <span class="pdf-amount-summary__value">{{ $item['value'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="pdf-amount-summary__total">
            <span class="pdf-amount-summary__total-label">{{ $totalLabel ?? 'Total Due' }}</span>
            <span class="pdf-amount-summary__total-value">{{ $totalAmount }}</span>
        </div>
    </div>
</section>
