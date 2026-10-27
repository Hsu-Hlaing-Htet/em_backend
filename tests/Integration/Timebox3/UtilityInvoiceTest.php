<?php

use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\Utility;
use App\Models\UtilityType;
use Database\Seeders\LateFeeSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
});
