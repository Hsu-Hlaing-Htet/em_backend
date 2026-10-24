<?php

use App\Models\InvoiceItem;
use App\Models\UtilityRate;
use App\Services\UtilityBillingPeriodService;
use Illuminate\Support\Carbon;

test('utility billing defaults the reading date to the first day of the next month', function () {
    $service = new UtilityBillingPeriodService;

    expect($service->defaultReadingDate(Carbon::parse('2026-02-18'))->toDateString())
        ->toBe('2026-03-01');
});

test('invoice items are metered only when reading data is present', function () {
    expect((new InvoiceItem(['description' => 'Rent']))->isMetered())->toBeFalse()
        ->and((new InvoiceItem(['previous_reading' => 100]))->isMetered())->toBeTrue()
        ->and((new InvoiceItem(['current_reading' => 125]))->isMetered())->toBeTrue()
        ->and((new InvoiceItem(['usage' => 25]))->isMetered())->toBeTrue();
});

test('utility rate exposes the supported status values', function () {
    expect(UtilityRate::statuses())
        ->toBe([UtilityRate::STATUS_ACTIVE, UtilityRate::STATUS_INACTIVE]);
});
