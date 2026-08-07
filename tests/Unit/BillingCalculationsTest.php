<?php

test('utility usage equals current minus previous reading', function () {
    $previous = 1250.5;
    $current = 1380.25;

    $usage = $current - $previous;

    expect($usage)->toBe(129.75);
});

test('utility amount rounds usage times unit price to two decimals', function () {
    $usage = 48.333;
    $unitPrice = 250;

    $amount = round($usage * $unitPrice, 2);

    expect($amount)->toBe(12083.25);
});

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

test('installment interest applies to remaining balance after deposit', function () {
    $contractTotal = 60000000;
    $deposit = 10000000;
    $interestRate = 5;

    $remainingBalance = max($contractTotal - $deposit, 0);
    $interestAmount = $remainingBalance * ($interestRate / 100);
    $totalInstallment = $remainingBalance + $interestAmount;

    expect($remainingBalance)->toEqual(50000000)
        ->and($interestAmount)->toEqual(2500000)
        ->and($totalInstallment)->toEqual(52500000);
});
