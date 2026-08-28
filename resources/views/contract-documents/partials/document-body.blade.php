@php
    $hasDocumentValue = static function ($value): bool {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return ! in_array(trim($value), ['', '-', '—'], true);
        }

        return true;
    };

    $documentRows = static function (array $items) use ($hasDocumentValue): array {
        return array_values(array_filter($items, static fn ($item): bool => $hasDocumentValue($item['value'] ?? null)));
    };

    $companyFields = collect($document['company'] ?? []);
    $companyName = $companyFields->firstWhere('label', 'Company Name')['value'] ?? 'Rosewood Royale Residences';
    $companyLines = $documentRows([
        ['value' => $companyFields->firstWhere('label', 'Registration')['value'] ?? null],
        ['value' => $companyFields->firstWhere('label', 'Address')['value'] ?? null],
        ['value' => ($companyFields->firstWhere('label', 'Phone')['value'] ?? null) ? 'Phone: '.$companyFields->firstWhere('label', 'Phone')['value'] : null],
        ['value' => ($companyFields->firstWhere('label', 'Email')['value'] ?? null) ? 'Email: '.$companyFields->firstWhere('label', 'Email')['value'] : null],
        ['value' => ($companyFields->firstWhere('label', 'Website')['value'] ?? null) ? 'Website: '.$companyFields->firstWhere('label', 'Website')['value'] : null],
    ]);
    $customerFields = collect($document['customer'] ?? [])->reject(fn ($item): bool => ($item['label'] ?? null) === 'Address')->values()->all();
    $authorizationRows = $documentRows($document['authorizationRows'] ?? $document['approval'] ?? []);
    $covenants = array_values(array_filter([
        $contractVariant['customerObligation'] ?? null,
        $contractVariant['companyObligation'] ?? null,
        ! empty($document['remarks']) ? 'Remarks: '.$document['remarks'] : null,
    ], static fn ($item): bool => $hasDocumentValue($item)));
@endphp

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">I. Parties to the Agreement</h2>
    <div class="contract-doc-parties">
        <div class="contract-doc-party">
            <p class="contract-doc-party-role">{{ $contractVariant['companyRole'] }}</p>
            <p class="contract-doc-party-name">{{ $companyName }}</p>
            @foreach ($companyLines as $line)
                <p class="contract-doc-party-line">{{ $line['value'] }}</p>
            @endforeach
        </div>
        <div class="contract-doc-party-divider" aria-hidden="true"></div>
        <div class="contract-doc-party">
            <p class="contract-doc-party-role">{{ $contractVariant['customerRole'] }}</p>
            <div class="contract-doc-fields">
                @foreach ($documentRows($customerFields) as $item)
                    <div class="contract-doc-field">
                        <span class="contract-doc-field-label">{{ $item['label'] }}</span>
                        <span class="contract-doc-field-value">{{ $item['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">II. Property</h2>
    <div class="contract-doc-rows contract-doc-rows--property">
        @foreach ($documentRows($document['propertyLocation'] ?? $document['property'] ?? []) as $item)
            <div class="contract-doc-row">
                <span class="contract-doc-row-label">{{ $item['label'] }}</span>
                <span class="contract-doc-row-value">{{ $item['value'] }}</span>
            </div>
        @endforeach
    </div>
</section>

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">III. Term and Financial Conditions</h2>
    <div class="contract-doc-rows">
        @foreach ($documentRows($document['financial'] ?? $document['payment'] ?? []) as $item)
            <div class="contract-doc-row">
                <span class="contract-doc-row-label">{{ $item['label'] }}</span>
                <span class="contract-doc-row-value">{{ $item['value'] }}</span>
            </div>
        @endforeach
    </div>
</section>

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">IV. General Covenants</h2>
    <ol class="contract-doc-covenants">
        @foreach ($covenants as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
</section>

@if (count($authorizationRows) > 0)
    <section class="contract-doc-section">
        <h2 class="contract-doc-section-title">V. Document Authorization</h2>
        <div class="contract-doc-rows">
            @foreach ($authorizationRows as $item)
                <div class="contract-doc-row">
                    <span class="contract-doc-row-label">{{ $item['label'] }}</span>
                    <span class="contract-doc-row-value">{{ $item['value'] }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endif

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">VI. Execution</h2>
    <p class="contract-doc-witness">
        IN WITNESS WHEREOF, the parties hereto have executed this {{ $contractVariant['agreementName'] }}
        as of the date first written above.
    </p>
    <div class="contract-doc-signatures">
        @foreach ($document['signatures'] as $signature)
            <p class="contract-doc-signature-line">
                <span class="contract-doc-signature-label">{{ $signature['label'] }}:</span>
                <span class="contract-doc-signature-blank">________</span>
            </p>
        @endforeach
    </div>
</section>
