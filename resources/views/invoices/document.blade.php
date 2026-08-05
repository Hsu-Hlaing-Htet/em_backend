<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $document['summary']['invoice_number'] ?? '' }}</title>
    <style>
        {!! file_get_contents(resource_path('documents/invoice-document.css')) !!}
    </style>
</head>
<body class="invoice-doc-body">
    <article id="pdf-print" class="invoice-doc">
        <header class="invoice-doc__head">
            <div class="invoice-doc__brand">
                <img src="{{ asset('images/logo-dark.jpg') }}" alt="Rosewood Royale" class="invoice-doc__logo">
                <div>
                    <p class="invoice-doc__company">{{ $document['company']['name'] }}</p>
                    <p class="invoice-doc__company-sub">{{ $document['company']['tagline'] }}</p>
                </div>
            </div>
            <h1 class="invoice-doc__title">{{ $document['title'] }}</h1>
        </header>

        @include('invoices.partials.document-body')

        <footer class="invoice-doc__foot">
            <div class="invoice-doc__foot-company">
                <strong>{{ $document['company']['name'] }}</strong>
                <span>{{ $document['company']['address'] }}</span><br>
                <span>{{ $document['company']['phone'] }} · {{ $document['company']['email'] }}</span><br>
                <span>{{ $document['company']['website'] ?? '' }}</span>
            </div>
            <div class="invoice-doc__foot-confidential">
                <span class="invoice-doc__foot-confidential-label">Confidential</span>
                <span>{{ $document['confidentialNotice'] ?? 'This invoice is intended solely for the named recipient.' }}</span>
            </div>
            <div class="invoice-doc__foot-page">Page 1 of 1</div>
        </footer>
    </article>
</body>
</html>
