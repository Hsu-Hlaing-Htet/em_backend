<?php

use App\Models\Contract;
use App\Models\User;
use App\Support\ContractInvoiceSchedule;
use App\Support\PhoneNumber;
use App\Support\UserEmail;
use Illuminate\Support\Carbon;

test('contract identifies accessible parties and removes duplicate party ids', function () {
    $contract = new Contract([
        'user_id' => 12,
        'second_user_id' => 12,
    ]);

    expect($contract->isAccessibleBy(12))->toBeTrue()
        ->and($contract->isAccessibleBy(99))->toBeFalse()
        ->and($contract->partyUserIds())->toBe([12]);
});

test('contract formats unique party names and emails', function () {
    $primary = (new User([
        'name' => '  Aye Aye ',
        'email' => 'Aye@example.test',
    ]))->forceFill(['id' => 1]);
    $secondary = (new User([
        'name' => 'Ko Ko',
        'email' => 'aye@example.test',
    ]))->forceFill(['id' => 2]);

    $contract = new Contract;
    $contract->setRelation('user', $primary);
    $contract->setRelation('secondUser', $secondary);

    expect($contract->partyDisplayName())->toBe('Aye Aye + Ko Ko')
        ->and($contract->partyEmails())->toBe(['Aye@example.test']);
});

test('recurring rent schedule starts one month after the contract start date', function () {
    $contract = new Contract([
        'status' => Contract::STATUS_ACTIVE,
        'type' => 'rent',
        'start_date' => '2026-01-15',
        'end_date' => '2026-04-30',
    ]);

    $periods = ContractInvoiceSchedule::periodsDueForGeneration(
        $contract,
        Carbon::parse('2026-02-08'),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]['billing_month']->toDateString())->toBe('2026-02-01')
        ->and($periods[0]['due_date']->toDateString())->toBe('2026-02-15')
        ->and($periods[0]['generate_date']->toDateString())->toBe('2026-02-08');
});

test('contract schedule clamps due dates and supports only eligible contracts', function () {
    $rent = new Contract([
        'status' => Contract::STATUS_ACTIVE,
        'type' => 'rent',
        'start_date' => '2026-01-31',
    ]);
    $sale = new Contract([
        'status' => Contract::STATUS_ACTIVE,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 3,
        'start_date' => '2026-01-31',
    ]);
    $inactive = new Contract([
        'status' => Contract::STATUS_COMPLETED,
        'type' => 'rent',
        'start_date' => '2026-01-01',
    ]);

    expect(ContractInvoiceSchedule::supportsRecurringGeneration($rent))->toBeTrue()
        ->and(ContractInvoiceSchedule::supportsRecurringGeneration($sale))->toBeTrue()
        ->and(ContractInvoiceSchedule::supportsRecurringGeneration($inactive))->toBeFalse()
        ->and(ContractInvoiceSchedule::recurringDueDay($rent))->toBe(28)
        ->and(ContractInvoiceSchedule::dueDateInMonth(Carbon::parse('2026-02-01'), 31)->toDateString())
        ->toBe('2026-02-28')
        ->and(ContractInvoiceSchedule::generateDateForDueDate(Carbon::parse('2026-02-15'))->toDateString())
        ->toBe('2026-02-08');
});

test('resident email and wallet phone helpers normalize supported values', function () {
    expect(UserEmail::normalize('  Resident@GMAIL.COM '))
        ->toBe('resident@gmail.com')
        ->and(UserEmail::isReservedSuperAdminEmail(' ADMIN@ROSEWOODROYALE.COM '))
        ->toBeTrue()
        ->and(PhoneNumber::isValid('+95977959901'))
        ->toBeTrue()
        ->and(PhoneNumber::normalizePaymentMethodWalletPhone('+95 9779959901'))
        ->toBe('09779959901')
        ->and(PhoneNumber::normalizePaymentMethodWalletPhone('invalid'))
        ->toBeNull();
});
