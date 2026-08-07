<?php

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UtilityRateSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function utilityWorkflowAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new UtilityTypeSeeder)->run();
    (new UtilityRateSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function utilityWorkflowCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function seedUtilityWorkflowStack(User $admin): array
{
    $building = \App\Models\Building::query()->create([
        'building_name' => 'Utility Tower',
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '9A',
        'floor_number' => 9,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 1000,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 40000,
        'booking_deposit_price' => 8000,
    ]);

    $customer = utilityWorkflowCustomer();

    $contract = Contract::query()->create([
        'contract_number' => 'CTR-UTIL-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();

    return compact('building', 'room', 'customer', 'contract', 'utilityType');
}

test('utility batch create uses previous reading and active rate then approval generates draft invoice', function () {
    $admin = utilityWorkflowAdmin();
    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType] = seedUtilityWorkflowStack($admin);

    $rate = UtilityRate::query()
        ->where('utility_type_id', $utilityType->id)
        ->where('status', 'active')
        ->firstOrFail();

    $previousMonth = now()->subMonth()->startOfMonth()->toDateString();
    $billingMonth = now()->startOfMonth()->toDateString();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => $previousMonth,
        'status' => 'approved',
        'total_amount' => 15000,
        'created_by' => $admin->id,
    ]);

    $utility->items()->create([
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 1000,
        'current_reading' => 1100,
        'usage' => 100,
        'unit_price' => (float) $rate->unit_price,
        'amount' => 15000,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities/form-data?' . http_build_query([
            'utility_type_id' => $utilityType->id,
            'billing_month' => $billingMonth,
            'room_ids' => [$room->id],
        ]))
        ->assertOk()
        ->assertJsonPath('data.unit_price', (float) $rate->unit_price)
        ->assertJsonPath('data.rooms.0.previous_reading', 1100)
        ->assertJsonPath('data.rooms.0.has_previous_data', true);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities/active-rate?utility_type_id='.$utilityType->id)
        ->assertOk()
        ->assertJsonPath('data.unit_price', (float) $rate->unit_price);

    $createResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/batch', [
            'billing_month' => $billingMonth,
            'utility_type_id' => $utilityType->id,
            'entries' => [
                [
                    'room_id' => $room->id,
                    'current_reading' => 1250,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'pending');

    $utilityId = $createResponse->json('data.0.id');
    $expectedAmount = round((1250 - 1100) * (float) $rate->unit_price, 2);

    expect(Utility::query()->find($utilityId)?->total_amount)->toBe(number_format($expectedAmount, 2, '.', ''));

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utilityId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $invoice = Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', $billingMonth)
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe('draft');
    expect($invoice->type)->toBe('rent');
    expect((float) $invoice->total_amount)->toBe(round(400000 + $expectedAmount, 2));

    $utility = Utility::query()->findOrFail($utilityId);
    $utility->refresh();
    expect($utility->invoice_id)->toBe($invoice->id);

    $chargeType = ChargeType::query()->where('slug', 'utility-charges')->firstOrFail();
    $rentCharge = ChargeType::query()->where('slug', 'monthly-rent')->firstOrFail();
    expect($invoice->items()->where('charge_type_id', $chargeType->id)->exists())->toBeTrue();
    expect($invoice->items()->where('charge_type_id', $rentCharge->id)->exists())->toBeTrue();
});

test('utility form data defaults previous reading to zero when no prior month exists', function () {
    $admin = utilityWorkflowAdmin();
    ['room' => $room, 'utilityType' => $utilityType] = seedUtilityWorkflowStack($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities/form-data?' . http_build_query([
            'utility_type_id' => $utilityType->id,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'room_ids' => [$room->id],
        ]))
        ->assertOk()
        ->assertJsonPath('data.rooms.0.previous_reading', 0)
        ->assertJsonPath('data.rooms.0.has_previous_data', false);
});
