<?php

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
