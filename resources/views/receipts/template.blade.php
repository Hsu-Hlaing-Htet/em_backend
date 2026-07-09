<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: Georgia, serif; color: #552032; margin: 2rem; }
        h1 { color: #7a3149; }
        .meta { margin: 1.5rem 0; line-height: 1.6; }
        .amount { font-size: 1.5rem; font-weight: bold; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Rosewood Royale</h1>
    <h2>Payment Receipt</h2>
    <div class="meta">
        <div><strong>Receipt #:</strong> {{ $receipt->receipt_number }}</div>
        <div><strong>Issued:</strong> {{ optional($receipt->issued_at)->format('Y-m-d H:i') }}</div>
        <div><strong>Customer:</strong> {{ $receipt->payment->invoice->contract->user->name ?? 'N/A' }}</div>
        <div><strong>Invoice #:</strong> {{ $receipt->payment->invoice->invoice_number ?? 'N/A' }}</div>
        <div><strong>Payment method:</strong> {{ $receipt->payment->paymentMethod->name ?? 'N/A' }}</div>
    </div>
    <div class="amount">Amount paid: {{ number_format((float) $receipt->payment->amount, 2) }}</div>
</body>
</html>
