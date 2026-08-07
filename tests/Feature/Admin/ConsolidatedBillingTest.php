<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use App\Services\InvoiceService;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function consolidatedAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function consolidatedCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function consolidatedRoom(string $type = 'rent'): Room
{
    $building = Building::query()->create([
        'building_name' => 'Consolidated Tower',
        'location' => 'Yangon',
    ]);

    return Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'CB-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 3,
        'type' => $type,
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 120000000,
        'rent_price' => 450000,
        'rent_deposit_price' => 45000,
        'booking_deposit_price' => 9000,
    ]);
}

function consolidatedRentContract(User $admin, User $customer, Room $room): Contract
{
    return Contract::query()->create([
        'contract_number' => 'R-CB-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 5400000,
        'deposit_amount' => 45000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 5,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);
}

function consolidatedSaleContract(User $admin, User $customer, Room $room): Contract
{
    return Contract::query()->create([
        'contract_number' => 'S-CB-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 120000000,
        'deposit_amount' => 12000000,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 24,
        'billing_day' => 10,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(23)->toDateString(),
    ]);
}

function consolidatedUtilityType(string $slug, string $name): UtilityType
{
    return UtilityType::query()->create([
        'name' => $name,
        'slug' => $slug,
        'unit' => 'unit',
        'status' => 'active',
    ]);
}

function consolidatedApprovedUtility(
    Room $room,
    User $admin,
    UtilityType $utilityType,
    float $amount,
    ?string $billingMonth = null,
): Utility {
    $billingMonth ??= now()->startOfMonth()->toDateString();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => $billingMonth,
        'status' => 'approved',
        'total_amount' => $amount,
        'created_by' => $admin->id,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $utility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 100,
        'current_reading' => 150,
        'usage' => 50,
        'unit_price' => round($amount / 50, 2),
        'amount' => $amount,
    ]);

    return $utility;
}

test('rent and approved utility charges appear on one consolidated invoice', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-rent', 'Electricity');
    $utilityAmount = 12500;
    $utility = consolidatedApprovedUtility($room, $admin, $electricity, $utilityAmount);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated()
        ->assertJsonPath('data.type', 'rent')
        ->assertJsonPath('data.status', 'draft');

    $invoice = Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', now()->startOfMonth()->toDateString())
        ->firstOrFail();

    $utility->refresh();
    $invoice->refresh()->load('items.chargeType');

    expect($utility->invoice_id)->toBe($invoice->id);
    expect($invoice->items)->toHaveCount(2);
    expect((float) $invoice->total_amount)->toBe(round(450000 + $utilityAmount, 2));
    expect($invoice->items->pluck('chargeType.slug')->sort()->values()->all())
        ->toBe(['monthly-rent', 'utility-charges']);
});

test('sale installment and approved utility charges appear on one consolidated invoice', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('sale');
    $contract = consolidatedSaleContract($admin, $customer, $room);
    $water = consolidatedUtilityType('water-cb-sale', 'Water');
    $utilityAmount = 8000;
    $utility = consolidatedApprovedUtility($room, $admin, $water, $utilityAmount);
    $installmentAmount = round(120000000 / 24, 2);

    app(InvoiceService::class)->generateFromUtility($utility);

    $invoice = Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', now()->startOfMonth()->toDateString())
        ->firstOrFail();

    expect($invoice->type)->toBe('sale');
    expect($invoice->items)->toHaveCount(2);
    expect((float) $invoice->total_amount)->toBe(round($installmentAmount + $utilityAmount, 2));
});

test('one contract and billing period cannot create duplicate invoices', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $billingMonth = now()->startOfMonth()->toDateString();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated()
        ->assertJsonPath('data.billing_period', now()->startOfMonth()->format('F Y'));

    expect(Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', $billingMonth)
        ->count())->toBe(1);
});

test('utility item cannot be invoiced twice', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-dup', 'Electricity');
    $utility = consolidatedApprovedUtility($room, $admin, $electricity, 5000);

    app(InvoiceService::class)->generateFromUtility($utility);

    expect(fn () => app(InvoiceService::class)->generateFromUtility($utility->fresh()))
        ->toThrow(\App\Exceptions\ConcurrentConflictException::class);
});

test('invoice total equals the sum of all invoice items', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-total', 'Electricity');
    $water = consolidatedUtilityType('water-cb-total', 'Water');

    $electricUtility = consolidatedApprovedUtility($room, $admin, $electricity, 7000);
    $waterUtility = consolidatedApprovedUtility($room, $admin, $water, 3000);

    $invoice = app(InvoiceService::class)->generateFromContract($contract);

    $invoice->refresh()->load('items');

    $itemSum = round((float) $invoice->items->sum('amount'), 2);
    expect((float) $invoice->total_amount)->toBe($itemSum);
    expect($itemSum)->toBe(round(450000 + 7000 + 3000, 2));
    expect($electricUtility->fresh()->invoice_id)->toBe($invoice->id);
    expect($waterUtility->fresh()->invoice_id)->toBe($invoice->id);
});

test('draft invoice can receive newly approved utility charges', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);

    $invoice = app(InvoiceService::class)->generateFromContract($contract);
    expect($invoice->items)->toHaveCount(1);

    $electricity = consolidatedUtilityType('electricity-cb-draft', 'Electricity');
    $utility = consolidatedApprovedUtility($room, $admin, $electricity, 4200);

    app(InvoiceService::class)->generateFromUtility($utility);

    $invoice->refresh()->load('items');
    expect($invoice->status)->toBe('draft');
    expect($invoice->items)->toHaveCount(2);
});

test('finalized invoice cannot be silently modified by utility generation', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-final', 'Electricity');

    $invoice = app(InvoiceService::class)->generateFromContract($contract);
    app(InvoiceService::class)->issue($invoice);

    $utility = consolidatedApprovedUtility($room, $admin, $electricity, 9000);

    expect(fn () => app(InvoiceService::class)->generateFromUtility($utility))
        ->toThrow(\App\Exceptions\ConcurrentConflictException::class);

    expect($utility->fresh()->invoice_id)->toBeNull();
});

test('concurrent invoice generation does not create duplicate invoices or items', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);

    DB::transaction(function () use ($contract): void {
        Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
        app(InvoiceService::class)->generateFromContract($contract);
    });

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated();

    $invoice = Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', now()->startOfMonth()->toDateString())
        ->firstOrFail();

    expect(Invoice::query()->where('contract_id', $contract->id)->whereDate('billing_month', now()->startOfMonth()->toDateString())->count())->toBe(1);
    expect(InvoiceItem::query()->where('invoice_id', $invoice->id)->whereHas('chargeType', fn ($q) => $q->where('slug', 'monthly-rent'))->count())->toBe(1);
});

test('payment and receipt workflows still work with consolidated invoices', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-pay', 'Electricity');
    $utility = consolidatedApprovedUtility($room, $admin, $electricity, 6000);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $invoice = app(InvoiceService::class)->generateFromContract($contract);
    app(InvoiceService::class)->issue($invoice->fresh());

    $totalDue = (float) $invoice->fresh()->total_amount;

    $paymentId = (int) Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => null,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payment-proofs/consolidated.jpg',
        'created_by' => $customer->id,
    ])->id;

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", ['amount' => $totalDue])
        ->assertOk()
        ->assertJsonPath('data.receipt_id', fn ($value) => $value !== null);

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('paid');
    expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);
});

test('invoice detail api returns customer room contract and all invoice items with meter fields', function (): void {
    $admin = consolidatedAdmin();
    $customer = consolidatedCustomer();
    $room = consolidatedRoom('rent');
    $contract = consolidatedRentContract($admin, $customer, $room);
    $electricity = consolidatedUtilityType('electricity-cb-api', 'Electricity');
    consolidatedApprovedUtility($room, $admin, $electricity, 5500);

    $invoice = app(InvoiceService::class)->generateFromContract($contract);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.customer_name', $customer->name)
        ->assertJsonPath('data.room_number', $room->room_number)
        ->assertJsonPath('data.contract.id', $contract->id)
        ->assertJsonCount(2, 'data.items');

    $items = collect($response->json('data.items'));
    $utilityLine = $items->first(fn (array $item) => ($item['description'] ?? '') === 'Electricity');

    expect($utilityLine)->not->toBeNull();
    expect($utilityLine['is_metered'])->toBeTrue();
    expect((float) $utilityLine['previous_reading'])->toBe(100.0);
    expect((float) $utilityLine['current_reading'])->toBe(150.0);
    expect((float) $utilityLine['usage'])->toBe(50.0);
    expect((float) $utilityLine['unit_price'])->toBeGreaterThan(0);
});
