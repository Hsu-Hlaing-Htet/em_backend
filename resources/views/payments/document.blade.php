<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Confirmation {{ $document['header']['referenceNo'] ?? '' }}</title>
    <link rel="stylesheet" href="{{ asset('css/contract-document.css') }}">
</head>
<body>
    <article id="pdf-print" class="pdf-sheet">
        <div class="pdf-document-lead">
            <header class="pdf-head">
                <div class="pdf-head-row">
                    <div class="pdf-brand">
                        <img src="{{ asset('images/logo-dark.jpg') }}" alt="Rosewood Royale" class="pdf-logo">
                        <div class="pdf-brand-text">
                            <p class="pdf-company">Rosewood Royale Residences</p>
                            <p class="pdf-company-sub">Residences &amp; Property Management</p>
                        </div>
                    </div>
                    <div class="pdf-head-meta">
                        <div class="pdf-meta-item">
                            <span class="pdf-meta-label">Payment Ref.</span>
                            <span class="pdf-meta-value">{{ $document['header']['referenceNo'] ?? '—' }}</span>
                        </div>
                        <div class="pdf-meta-item">
                            <span class="pdf-meta-label">Issue Date</span>
                            <span class="pdf-meta-value">{{ $document['header']['issuedDate'] ?? '—' }}</span>
                        </div>
                    </div>
                </div>
                <h1 class="pdf-doc-title">Payment Confirmation</h1>
                <div class="pdf-rule pdf-rule--accent"></div>
            </header>
        </div>

        @include('payments.partials.document-body')

        <footer class="pdf-foot">
            <div class="pdf-foot-row">
                <span>Confidential</span>
                <span class="pdf-foot-page">Page 1</span>
                <span class="pdf-foot-address">{{ $document['footerAddress'] ?? '' }}</span>
            </div>
        </footer>
    </article>
</body>
</html>
