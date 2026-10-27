<?php

test('invoice total due includes late fee', function () {
    $subtotal = 500000;
    $lateFee = 15000;

    $totalDue = round($subtotal + $lateFee, 2);

    expect($totalDue)->toBe(515000.0);
});

test('invoice balance cannot go below zero', function () {
    $totalDue = 500000;
    $approvedPaid = 520000;

    $balance = max(round($totalDue - $approvedPaid, 2), 0);

    expect($balance)->toEqual(0);
});

test('partial invoice balance reflects approved payments only', function () {
    $totalDue = 500000;
    $approvedPayments = [200000, 100000];
    $pendingPayment = 50000;

    $approvedPaid = array_sum($approvedPayments);
    $balance = max(round($totalDue - $approvedPaid, 2), 0);

    expect($approvedPaid)->toBe(300000)
        ->and($balance)->toBe(200000.0)
        ->and($pendingPayment)->not->toBeIn($approvedPayments);
});
