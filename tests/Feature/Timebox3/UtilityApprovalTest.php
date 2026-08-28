<?php

use App\Models\ChargeType;
use App\Models\Invoice;
use App\Models\Utility;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('utility submit and approve generates consolidated invoice with line items', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-INV');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();
    $billingMonth = \Illuminate\Support\Carbon::parse($contract->start_date)->startOfMonth()->toDateString();

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

test('rejected utility does not generate an invoice', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-REJ');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();
    $billingMonth = \Illuminate\Support\Carbon::parse($contract->start_date)->startOfMonth()->toDateString();

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
        ->postJson("/api/utilities/{$utilityId}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(0);
    expect(Utility::query()->find($utilityId)?->invoice_id)->toBeNull();
});

test('utility batch create uses previous reading and active rate then approval generates draft invoice', function () {
    $admin = tb3Admin();
    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType] = tb3BatchStack($admin);

    $rate = UtilityRate::query()
        ->where('utility_type_id', $utilityType->id)
        ->where('status', 'active')
        ->firstOrFail();

    $previousMonth = now()->subMonth()->startOfMonth()->toDateString();
    $billingMonth = now()->startOfMonth()->toDateString();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'contract_id' => $contract->id,
        'billing_month' => $previousMonth,
        'reading_date' => now()->startOfMonth()->toDateString(),
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
        ->getJson('/api/utilities/form-data?'.http_build_query([
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
    $admin = tb3Admin();
    ['room' => $room, 'utilityType' => $utilityType] = tb3BatchStack($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities/form-data?'.http_build_query([
            'utility_type_id' => $utilityType->id,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'room_ids' => [$room->id],
        ]))
        ->assertOk()
        ->assertJsonPath('data.rooms.0.previous_reading', 0)
        ->assertJsonPath('data.rooms.0.has_previous_data', false);
});
