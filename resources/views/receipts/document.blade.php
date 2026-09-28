<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $document['header']['receipt_number'] ?? $document['info']['receipt_number'] ?? '' }}</title>
    <style>
        {!! file_get_contents(resource_path('documents/receipt-document.css')) !!}
    </style>
</head>
<body class="receipt-doc-body">
    <article id="pdf-print" class="receipt-doc">
        <header class="receipt-doc__head">
            <div class="receipt-doc__brand">
                <img src="{{ asset('images/logo-dark.jpg') }}" alt="Rosewood Royale" class="receipt-doc__logo">
                <div>
                    <p class="receipt-doc__company">{{ $document['company']['name'] ?? 'Rosewood Royale Residences' }}</p>
                    <p class="receipt-doc__company-sub">{{ $document['company']['tagline'] ?? 'Residences & Property Management' }}</p>
                </div>
            </div>
            <div class="receipt-doc__head-meta">
                <div class="receipt-doc__head-meta-row">
                    <span class="receipt-doc__head-meta-label">Receipt No.</span>
                    <span class="receipt-doc__head-meta-value">{{ $document['header']['receipt_number'] ?? '—' }}</span>
                </div>
                <div class="receipt-doc__head-meta-row">
                    <span class="receipt-doc__head-meta-label">Date</span>
                    <span class="receipt-doc__head-meta-value">{{ $document['header']['date'] ?? '—' }}</span>
                </div>
            </div>
        </header>

        <section class="receipt-doc__title-block">
            <h1 class="receipt-doc__title">{{ $document['title'] ?? 'PAYMENT RECEIPT' }}</h1>
            <p class="receipt-doc__subtitle">{{ $document['subtitle'] ?? 'THANK YOU FOR YOUR PAYMENT' }}</p>
        </section>

        @include('receipts.partials.document-body')

        <footer class="receipt-doc__foot">
            <div class="receipt-doc__foot-company">
                <strong>{{ $document['company']['name'] ?? 'Rosewood Royale Residences' }}</strong>
                <span>{{ $document['company']['address'] ?? '' }}</span><br>
                <span>{{ $document['company']['phone'] ?? '' }}</span><br>
                <span>{{ $document['company']['email'] ?? '' }}</span>
            </div>
            <div class="receipt-doc__foot-confidential">
                <span class="receipt-doc__foot-confidential-label">Confidential</span>
                <span>{{ $document['footer']['confidential_notice'] ?? 'System-generated receipt · No signature required' }}</span>
            </div>
        </footer>
    </article>
</body>
</html>
