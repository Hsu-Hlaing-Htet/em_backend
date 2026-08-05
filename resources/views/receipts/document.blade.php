<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $document['summary']['receipt_number'] ?? '' }}</title>
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
                    <p class="receipt-doc__company">{{ $document['company']['name'] }}</p>
                    <p class="receipt-doc__company-sub">{{ $document['company']['tagline'] }}</p>
                </div>
            </div>
            <h1 class="receipt-doc__title">{{ $document['title'] }}</h1>
        </header>

        @include('receipts.partials.document-body')

        <footer class="receipt-doc__foot">
            <div class="receipt-doc__foot-company">
                <strong>{{ $document['company']['name'] }}</strong>
                <span>{{ $document['company']['address'] }}</span><br>
                <span>{{ $document['company']['phone'] }} · {{ $document['company']['email'] }}</span><br>
                <span>{{ $document['company']['website'] ?? '' }}</span>
            </div>
            <div class="receipt-doc__foot-confidential">
                <span class="receipt-doc__foot-confidential-label">Confidential</span>
                <span>{{ $document['confidentialNotice'] ?? 'This receipt is intended solely for the named recipient.' }}</span>
            </div>
            <div class="receipt-doc__foot-page">Page 1 of 1</div>
        </footer>
    </article>
</body>
</html>
