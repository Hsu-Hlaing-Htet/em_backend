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

    $partyColumnValues = static function (array $fields) use ($hasDocumentValue): array {
        $byLabel = collect($fields)->mapWithKeys(
            static fn ($item) => [($item['label'] ?? '') => ($item['value'] ?? null)]
        );

        $name = $byLabel->get('Full Name');
        $lines = collect(['NRC / ID', 'Phone', 'Email'])
            ->map(static fn ($label) => $byLabel->get($label))
            ->filter(static fn ($value) => $hasDocumentValue($value))
            ->values()
            ->all();

        return [
            'name' => $hasDocumentValue($name) ? $name : '—',
            'lines' => $lines,
        ];
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
    $secondCustomerFields = collect($document['secondCustomer'] ?? [])->reject(fn ($item): bool => ($item['label'] ?? null) === 'Address')->values()->all();
    $authorizationRows = $documentRows($document['authorizationRows'] ?? $document['approval'] ?? []);
    $covenants = array_values(array_filter([
        $contractVariant['customerObligation'] ?? null,
        $contractVariant['companyObligation'] ?? null,
        ! empty($document['remarks']) ? 'Remarks: '.$document['remarks'] : null,
    ], static fn ($item): bool => $hasDocumentValue($item)));
    $hasSecondCustomer = count($documentRows($secondCustomerFields)) > 0;
    $customerRole = $contractVariant['customerRole'] ?? 'Customer';
    $primaryParty = $partyColumnValues($customerFields);
    $secondParty = $hasSecondCustomer ? $partyColumnValues($secondCustomerFields) : null;

    $signatureGroups = [];
    foreach (($document['signatures'] ?? []) as $signature) {
        $role = (string) ($signature['role'] ?? '');
        $label = (string) ($signature['label'] ?? '');
        $isCompany = (bool) preg_match('/authorized officer|company representative|company|seller|landlord/i', trim($role.' '.$label));
        $roleKey = $isCompany ? 'company' : ($role !== '' ? $role : ($label !== '' ? $label : 'Signature'));
        $lastIndex = count($signatureGroups) - 1;

        if ($lastIndex >= 0 && ! $isCompany && ($signatureGroups[$lastIndex]['roleKey'] ?? null) === $roleKey) {
            $signatureGroups[$lastIndex]['parties'][] = $signature;
            continue;
        }

        $signatureGroups[] = [
            'roleKey' => $roleKey,
            'heading' => $isCompany
                ? ($label !== '' ? $label : 'Company Representative')
                : ($role !== '' ? $role : ($label !== '' ? $label : 'Signature')),
            'parties' => [$signature],
        ];
    }

    $hasJointSignatures = collect($signatureGroups)->contains(
        static fn (array $group): bool => count($group['parties']) > 1
    );
@endphp

<section class="contract-doc-section">
    <h2 class="contract-doc-section-title">I. Parties to the Agreement</h2>
    <div class="contract-doc-parties{{ $hasSecondCustomer ? ' contract-doc-parties--joint' : '' }}">
        <div class="contract-doc-party">
            <p class="contract-doc-party-role">{{ $contractVariant['companyRole'] }}</p>
            <p class="contract-doc-party-name">{{ $companyName }}</p>
            @foreach ($companyLines as $line)
                <p class="contract-doc-party-line">{{ $line['value'] }}</p>
            @endforeach
        </div>
        <div class="contract-doc-party-divider" aria-hidden="true"></div>
        <div class="contract-doc-party">
            <p class="contract-doc-party-role">{{ $customerRole }}</p>
            @if ($hasSecondCustomer)
                <div class="contract-doc-party-columns">
                    <div class="contract-doc-party-column">
                        <p class="contract-doc-party-name">{{ $primaryParty['name'] }}</p>
                        @foreach ($primaryParty['lines'] as $line)
                            <p class="contract-doc-party-line">{{ $line }}</p>
                        @endforeach
                    </div>
                    <div class="contract-doc-party-column">
                        <p class="contract-doc-party-name">{{ $secondParty['name'] }}</p>
                        @foreach ($secondParty['lines'] as $line)
                            <p class="contract-doc-party-line">{{ $line }}</p>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="contract-doc-party-name">{{ $primaryParty['name'] }}</p>
                @foreach ($primaryParty['lines'] as $line)
                    <p class="contract-doc-party-line">{{ $line }}</p>
                @endforeach
            @endif
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
    <div class="contract-doc-signatures{{ $hasJointSignatures ? ' contract-doc-signatures--joint' : '' }}">
        @foreach ($signatureGroups as $group)
            <div class="contract-doc-signature-block{{ count($group['parties']) > 1 ? ' contract-doc-signature-block--multi' : '' }}">
                <p class="contract-doc-signature-role">{{ $group['heading'] }}</p>
                <div class="contract-doc-signature-parties">
                    @foreach ($group['parties'] as $signature)
                        <div class="contract-doc-signature-party">
                            <p class="contract-doc-signature-blank-line">________________</p>
                            <p class="contract-doc-signature-name">{{ $signature['name'] ?? '________________' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
