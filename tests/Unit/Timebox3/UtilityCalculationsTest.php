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
