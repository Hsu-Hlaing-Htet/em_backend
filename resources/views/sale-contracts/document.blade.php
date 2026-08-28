<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Sale Agreement {{ $document['header']['contractNo'] ?? '' }}</title>
    <link rel="stylesheet" href="{{ asset('css/contract-document.css') }}">
</head>
<body>
    <article id="pdf-print" class="pdf-sheet contract-doc-sheet">
        <footer class="contract-doc-foot">
            <div class="contract-doc-foot-line"></div>
            <div class="contract-doc-foot-row">
                <span class="contract-doc-foot-brand">Rosewood Royale Residences</span>
                <span class="contract-doc-foot-contract">Contract No. {{ $document['header']['contractNo'] ?? '—' }}</span>
                <span class="contract-doc-foot-page" aria-label="Page number"></span>
            </div>
        </footer>

        <div class="contract-doc-lead">
            <header class="contract-doc-head">
                <div class="contract-doc-brand">
                    <img src="{{ asset('images/logo-dark.jpg') }}" alt="Rosewood Royale" class="contract-doc-logo">
                    <p class="contract-doc-company">Rosewood Royale Residences</p>
                    <p class="contract-doc-tagline">Residences &amp; Property Management</p>
                </div>
                <div class="contract-doc-meta-bar">
                    <div class="contract-doc-meta-line"></div>
                    <div class="contract-doc-meta-row">
                        <span class="contract-doc-meta-item">
                            <span class="contract-doc-meta-label">Contract No.</span>
                            <strong>{{ $document['header']['contractNo'] ?? '—' }}</strong>
                        </span>
                        <span class="contract-doc-meta-item contract-doc-meta-item--right">
                            <span class="contract-doc-meta-label">Issue Date</span>
                            <strong>{{ $document['header']['issuedDate'] ?? '—' }}</strong>
                        </span>
                    </div>
                    <div class="contract-doc-meta-line"></div>
                </div>
                <h1 class="contract-doc-title">Property Sale Agreement</h1>
                <div class="contract-doc-title-rule"></div>
            </header>

            <p class="contract-doc-preamble">
                This Property Sale Agreement ("Agreement") is made between the Seller and the
                Purchaser identified below, concerning the residential unit described herein, upon
                the terms and conditions set forth in this document.
            </p>
        </div>

        <div class="contract-doc-body">
            @include('sale-contracts.partials.document-body')
        </div>
    </article>
</body>
</html>
