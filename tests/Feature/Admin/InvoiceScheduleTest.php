<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\ContractInvoiceSchedule;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function scheduledBillingAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function scheduledBillingCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function scheduledBillingRoom(string $type = 'rent'): Room
{
    $building = Building::query()->create([
        'building_name' => 'Schedule Tower',
        'location' => 'Yangon',
    ]);

    return Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'SCH-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 2,
        'type' => $type,
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 120000000,
        'rent_price' => 450000,
        'rent_deposit_price' => 45000,
        'booking_deposit_price' => 12000000,
    ]);
}

test('rent schedule uses start-date day and generates seven days before due', function (): void {
    $start = Carbon::parse('2026-08-15');
    $due = Carbon::parse('2026-09-15');

    expect(ContractInvoiceSchedule::generateDateForDueDate($due)->toDateString())
        ->toBe('2026-09-08');

    $admin = scheduledBillingAdmin();
    $customer = scheduledBillingCustomer();
    $room = scheduledBillingRoom('rent');

    $contract = Contract::query()->create([
        'contract_number' => 'R-SCH-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 5400000,
        'deposit_amount' => 45000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => $start->toDateString(),
        'end_date' => '2027-08-14',
    ]);

    $periods = ContractInvoiceSchedule::periodsDueForGeneration(
        $contract,
        Carbon::parse('2026-09-08'),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]['due_date']->toDateString())->toBe('2026-09-15')
        ->and($periods[0]['generate_date']->toDateString())->toBe('2026-09-08')
        ->and($periods[0]['billing_month']->toDateString())->toBe('2026-09-01');

    $result = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));

    expect($result['created'])->toBe(1);

    $invoice = Invoice::query()->where('contract_id', $contract->id)->firstOrFail();

    expect($invoice->status)->toBe('draft')
        ->and($invoice->due_date->toDateString())->toBe('2026-09-15')
        ->and($invoice->billing_month->toDateString())->toBe('2026-09-01');

    // No duplicate on re-run.
    $second = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));
    expect($second['created'])->toBe(0);
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(1);
});

test('rent schedule does not generate before contract start or after end', function (): void {
    $admin = scheduledBillingAdmin();
    $customer = scheduledBillingCustomer();
    $room = scheduledBillingRoom('rent');

    $contract = Contract::query()->create([
        'contract_number' => 'R-SCH-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 5400000,
        'deposit_amount' => 45000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2026-08-15',
        'end_date' => '2026-10-14',
    ]);

    $beforeStart = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-08-01'));
    expect($beforeStart['created'])->toBe(0);

    $afterFirstGenerate = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));
    expect($afterFirstGenerate['created'])->toBe(1);

    // Oct 15 due is after end_date Oct 14 — should not create.
    $afterEnd = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-10-08'));
    expect($afterEnd['created'])->toBe(0);
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(1);
});

test('sale installment schedule generates seven days before each due date', function (): void {
    $admin = scheduledBillingAdmin();
    $customer = scheduledBillingCustomer();
    $room = scheduledBillingRoom('sale');

    $contract = Contract::query()->create([
        'contract_number' => 'S-SCH-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 120000000,
        'deposit_amount' => 12000000,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 3,
        // Stale billing_day must be ignored; due day comes from start_date.
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2026-08-15',
        'end_date' => null,
    ]);

    expect(ContractInvoiceSchedule::recurringDueDay($contract))->toBe(15);

    $periods = ContractInvoiceSchedule::periodsDueForGeneration(
        $contract,
        Carbon::parse('2026-09-08'),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]['due_date']->toDateString())->toBe('2026-09-15')
        ->and($periods[0]['generate_date']->toDateString())->toBe('2026-09-08');

    $result = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));
    expect($result['created'])->toBe(1);

    $invoice = Invoice::query()->where('contract_id', $contract->id)->firstOrFail();
    expect($invoice->type)->toBe('sale')
        ->and($invoice->status)->toBe('draft')
        ->and((float) $invoice->total_amount)->toBe(round(120000000 / 3, 2));

    // Second installment generate date = Oct 8.
    $secondRun = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-10-08'));
    expect($secondRun['created'])->toBe(1);
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(2);

    // After all 3 installments, no more.
    app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-11-08'));
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(3);

    $extra = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-12-08'));
    expect($extra['created'])->toBe(0);
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(3);
});

test('sale full payment contracts are excluded from recurring generation', function (): void {
    $admin = scheduledBillingAdmin();
    $customer = scheduledBillingCustomer();
    $room = scheduledBillingRoom('sale');

    $contract = Contract::query()->create([
        'contract_number' => 'S-FULL-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 120000000,
        'deposit_amount' => 12000000,
        'type' => 'sale',
        'payment_type' => 'full',
        'duration_months' => null,
        'billing_day' => null,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2026-08-15',
    ]);

    expect(ContractInvoiceSchedule::supportsRecurringGeneration($contract))->toBeFalse();

    $result = app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));
    expect($result['created'])->toBe(0);
    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(0);
});

test('scheduled draft invoices become customer-visible only after issue', function (): void {
    $admin = scheduledBillingAdmin();
    $customer = scheduledBillingCustomer();
    $room = scheduledBillingRoom('rent');

    $contract = Contract::query()->create([
        'contract_number' => 'R-ISS-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 5400000,
        'deposit_amount' => 45000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2026-08-15',
        'end_date' => '2027-08-14',
    ]);

    app(InvoiceService::class)->generateScheduledInvoices(Carbon::parse('2026-09-08'));
    $invoice = Invoice::query()->where('contract_id', $contract->id)->firstOrFail();

    expect($invoice->status)->toBe('draft');

    $draftList = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->json('data.data');

    expect(collect($draftList)->pluck('id'))->not->toContain($invoice->id);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    $issuedList = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->json('data.data');

    expect(collect($issuedList)->pluck('id'))->toContain($invoice->id);
});

test('month boundary due date arithmetic uses calendar subtraction', function (): void {
    $due = Carbon::parse('2026-03-05');
    $generate = ContractInvoiceSchedule::generateDateForDueDate($due);

    expect($generate->toDateString())->toBe('2026-02-26');
});
