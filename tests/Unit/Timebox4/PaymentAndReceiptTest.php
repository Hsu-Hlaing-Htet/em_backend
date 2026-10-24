<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\PaymentService;
use App\Support\PaymentFinancialSummary;

test('payment summary calculates cash change from tendered and applied amounts', function () {
    $invoice = new Invoice([
        'total_amount' => 100000,
        'late_fee' => 5000,
    ]);
    $payment = new Payment([
        'amount' => 105000,
        'amount_received' => 110000,
    ]);

    expect(PaymentFinancialSummary::fromPayment($payment, $invoice))
        ->toMatchArray([
            'subtotal' => 100000.0,
            'late_fee' => 5000.0,
            'total' => 105000.0,
            'paid' => 110000.0,
            'applied_amount' => 105000.0,
            'change' => 5000.0,
            'balance' => null,
            'show_change' => true,
            'settlement_label' => 'change',
        ]);
});

test('payment summary uses an exact balance row when there is no over-tender', function () {
    $invoice = new Invoice([
        'total_amount' => 100000,
        'late_fee' => 0,
    ]);
    $payment = new Payment(['amount' => 100000]);

    expect(PaymentFinancialSummary::fromPayment($payment, $invoice))
        ->toMatchArray([
            'paid' => 100000.0,
            'change' => null,
            'balance' => 0.0,
            'show_change' => false,
            'settlement_label' => 'balance',
        ]);
});

test('receipt status predicates enforce approval and delivery rules', function () {
    $receipt = new Receipt([
        'status' => Receipt::STATUS_DRAFT,
        'approval_status' => Receipt::APPROVAL_APPROVED,
    ]);

    expect($receipt->isApproved())->toBeTrue()
        ->and($receipt->isPendingApproval())->toBeFalse()
        ->and($receipt->canBeIssued())->toBeTrue()
        ->and($receipt->canBeEmailed())->toBeTrue()
        ->and($receipt->isIssued())->toBeFalse();
});

test('receipt predicates distinguish pending, issued, and emailed states', function () {
    $pending = new Receipt([
        'status' => Receipt::STATUS_DRAFT,
        'approval_status' => Receipt::APPROVAL_PENDING,
    ]);
    $issued = new Receipt([
        'status' => Receipt::STATUS_ISSUED,
        'approval_status' => Receipt::APPROVAL_APPROVED,
        'sent_at' => now(),
    ]);
    $rejected = new Receipt([
        'status' => Receipt::STATUS_DRAFT,
        'approval_status' => Receipt::APPROVAL_REJECTED,
    ]);

    expect($pending->isPendingApproval())->toBeTrue()
        ->and($pending->canBeIssued())->toBeFalse()
        ->and($pending->canBeEmailed())->toBeFalse()
        ->and($issued->isIssued())->toBeTrue()
        ->and($issued->isDeliveredToCustomer())->toBeTrue()
        ->and($issued->isEmailSent())->toBeTrue()
        ->and($issued->canBeEmailed())->toBeTrue()
        ->and($rejected->isApproved())->toBeFalse()
        ->and($rejected->canBeIssued())->toBeFalse();
});

test('payment service calculates due and remaining balance from approved payments', function () {
    $invoice = new Invoice([
        'total_amount' => 100000,
        'late_fee' => 5000,
    ]);
    $invoice->setRelation('payments', collect([
        new Payment(['status' => Payment::STATUS_APPROVED, 'amount' => 60000]),
        new Payment(['status' => Payment::STATUS_PENDING, 'amount' => 50000]),
        new Payment(['status' => Payment::STATUS_APPROVED, 'amount' => 20000]),
    ]));

    $service = app(PaymentService::class);

    expect($service->invoiceTotalDue($invoice))->toBe(105000.0)
        ->and($service->invoiceApprovedPaidAmount($invoice))->toBe(80000.0)
        ->and($service->invoiceCurrentBalance($invoice))->toBe(25000.0);
});
