<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentFinancialSummary;

test('financial summary uses invoice subtotal late fee and total with cash change', function () {
    $invoice = new Invoice([
        'total_amount' => 693681,
        'late_fee' => 0,
    ]);
    $payment = new Payment([
        'amount' => 693681,
        'amount_received' => 700000,
    ]);
    $payment->setRelation('invoice', $invoice);

    $summary = PaymentFinancialSummary::fromPayment($payment, $invoice);

    expect($summary['subtotal'])->toBe(693681.0)
        ->and($summary['late_fee'])->toBe(0.0)
        ->and($summary['total'])->toBe(693681.0)
        ->and($summary['paid'])->toBe(700000.0)
        ->and($summary['applied_amount'])->toBe(693681.0)
        ->and($summary['show_change'])->toBeTrue()
        ->and($summary['change'])->toBe(6319.0)
        ->and($summary['balance'])->toBeNull()
        ->and($summary['settlement_label'])->toBe('change');
});

test('financial summary shows zero balance for exact non-cash payment', function () {
    $invoice = new Invoice([
        'total_amount' => 693681,
        'late_fee' => 0,
    ]);
    $payment = new Payment([
        'amount' => 693681,
        'amount_received' => null,
    ]);
    $payment->setRelation('invoice', $invoice);

    $summary = PaymentFinancialSummary::fromPayment($payment, $invoice);

    expect($summary['subtotal'])->toBe(693681.0)
        ->and($summary['late_fee'])->toBe(0.0)
        ->and($summary['total'])->toBe(693681.0)
        ->and($summary['paid'])->toBe(693681.0)
        ->and($summary['show_change'])->toBeFalse()
        ->and($summary['change'])->toBeNull()
        ->and($summary['balance'])->toBe(0.0)
        ->and($summary['settlement_label'])->toBe('balance');
});

test('financial summary includes late fee in total', function () {
    $invoice = new Invoice([
        'total_amount' => 500000,
        'late_fee' => 15000,
    ]);
    $payment = new Payment([
        'amount' => 515000,
        'amount_received' => null,
    ]);

    $summary = PaymentFinancialSummary::fromPayment($payment, $invoice);

    expect($summary['subtotal'])->toBe(500000.0)
        ->and($summary['late_fee'])->toBe(15000.0)
        ->and($summary['total'])->toBe(515000.0)
        ->and($summary['paid'])->toBe(515000.0)
        ->and($summary['show_change'])->toBeFalse()
        ->and($summary['balance'])->toBe(0.0);
});
