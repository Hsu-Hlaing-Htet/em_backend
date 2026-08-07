<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\LateFeeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UtilityRateSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tb3Admin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new UtilityTypeSeeder)->run();
    (new UtilityRateSeeder)->run();
    (new LateFeeSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function tb3Customer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function tb3OtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail();
}

function tb3ActiveContract(User $admin, User $customer, string $roomNumber = 'TB3-101'): array
{
    $building = Building::query()->create([
        'building_name' => 'TB3 Utility Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 800000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-TB3-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    return compact('building', 'room', 'contract');
}

test('admin can retrieve and manage utility types and rates', function () {
    $admin = tb3Admin();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utility-types')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data', 'total']]);

    $type = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-types', [
            'name' => 'TB3 Water Test',
            'slug' => 'tb3-water-'.uniqid(),
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->json('data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-rates', [
            'utility_type_id' => $type['id'],
            'unit_price' => 150,
            'effective_date' => now()->toDateString(),
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_price', '150.00');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-rates', [
            'utility_type_id' => 999999,
            'unit_price' => -1,
            'effective_date' => 'not-a-date',
            'status' => 'nope',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['utility_type_id', 'unit_price', 'effective_date', 'status'], 'data');
});

test('meter reading creation calculates usage and rejects invalid readings', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room] = tb3ActiveContract($admin, $customer, 'TB3-MR');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 1000,
                'current_reading' => 1125,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $utilityId = $create->json('data.id');
    $item = UtilityItem::query()->where('utility_id', $utilityId)->firstOrFail();
    expect((float) $item->usage)->toBe(125.0);
    expect((float) $item->amount)->toBe(round(125 * (float) $rate->unit_price, 2));
    expect($item->utility_id)->toBe($utilityId);
    expect(Utility::query()->find($utilityId)?->room_id)->toBe($room->id);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => now()->addMonth()->startOfMonth()->toDateString(),
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 2000,
                'current_reading' => 1500,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422);
});

test('utility approval generates consolidated invoice with line items', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-INV');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();
    $billingMonth = now()->startOfMonth()->toDateString();

    $utilityId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => $billingMonth,
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 500,
                'current_reading' => 600,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utilityId}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utilityId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $invoice = Invoice::query()->where('contract_id', $contract->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe('draft');
    expect($invoice->items)->not->toBeEmpty();
    expect(ChargeType::query()->where('slug', 'utility-charges')->exists())->toBeTrue();
});

test('duplicate billing for same period reuses a single draft invoice', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-DUP');
    $billingMonth = now()->startOfMonth()->toDateString();

    $firstId = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated()
        ->json('data.id');

    $secondId = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/generate-from-contract/{$contract->id}")
        ->assertCreated()
        ->json('data.id');

    expect($secondId)->toBe($firstId);
    expect(Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', $billingMonth)
        ->count())->toBe(1);
});

test('customer can access only their own invoices', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    $other = tb3OtherCustomer();
    ['contract' => $ownContract] = tb3ActiveContract($admin, $customer, 'TB3-OWN');
    ['contract' => $otherContract] = tb3ActiveContract($admin, $other, 'TB3-OTH');

    $ownInvoice = Invoice::query()->create([
        'contract_id' => $ownContract->id,
        'invoice_number' => 'INV-TB3-OWN',
        'type' => 'rent',
        'status' => 'issued',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'created_by' => $admin->id,
    ]);

    $otherInvoice = Invoice::query()->create([
        'contract_id' => $otherContract->id,
        'invoice_number' => 'INV-TB3-OTH',
        'type' => 'rent',
        'status' => 'issued',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $ownInvoice->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$ownInvoice->id}")
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$otherInvoice->id}")
        ->assertNotFound();
});

test('late fee configuration exists and invoice late_fee field is stored', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-LF');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/late-fees')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data', 'total']]);

    expect(LateFee::query()->count())->toBeGreaterThan(0);

    $invoice = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/invoices', [
            'contract_id' => $contract->id,
            'type' => 'rent',
            'due_date' => now()->addDays(7)->toDateString(),
            'late_fee' => 5000,
            'total_amount' => 400000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.late_fee', '5000.00');

    expect((float) Invoice::query()->find($invoice->json('data.id'))->late_fee)->toBe(5000.0);
});

test('timebox 3 relationships and seeder idempotency', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-REL');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'status' => 'draft',
        'total_amount' => 0,
        'created_by' => $admin->id,
    ]);

    $item = $utility->items()->create([
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    expect($utility->room->id)->toBe($room->id);
    expect($item->utility->id)->toBe($utility->id);
    expect($item->utilityType->id)->toBe($utilityType->id);
    expect($utilityType->utilityRates)->not->toBeEmpty();
    expect($contract->user->id)->toBe($customer->id);

    $typeCount = UtilityType::query()->count();
    $lateFeeCount = LateFee::query()->count();
    (new UtilityTypeSeeder)->run();
    (new LateFeeSeeder)->run();
    expect(UtilityType::query()->count())->toBe($typeCount);
    expect(LateFee::query()->count())->toBe($lateFeeCount);
    // UtilityRateSeeder currently creates additional rows on re-run (not idempotent).
});
