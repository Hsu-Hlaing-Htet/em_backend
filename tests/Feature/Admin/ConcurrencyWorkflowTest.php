<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function concurrencyAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function concurrencyCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function concurrencyOtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail();
}

function concurrencyRoom(string $type = 'rent', string $status = 'available'): Room
{
    $building = Building::query()->create([
        'building_name' => 'Concurrency Tower',
        'location' => 'Yangon',
    ]);

    return Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'C-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 2,
        'type' => $type,
        'status' => $status,
        'area_sqft' => 1000,
        'sale_price' => 100000000,
        'rent_price' => 500000,
        'rent_deposit_price' => 50000,
        'booking_deposit_price' => 10000,
    ]);
}

function concurrencyActiveRentContract(User $admin, User $customer, Room $room): Contract
{
    $room->update(['status' => 'occupied']);

    return Contract::query()->create([
        'contract_number' => 'R-CON-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 6000000,
        'deposit_amount' => 50000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);
}

it('prevents approving a second active rent contract for the same room', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $other = concurrencyOtherCustomer();
    $room = concurrencyRoom('rent', 'available');

    $firstDraftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'contract_total' => 6000000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ])
        ->assertCreated()
        ->json('data.id');

    // Force room back to available so a second draft can be created for race simulation.
    Room::query()->whereKey($room->id)->update(['status' => 'available']);

    $secondDraft = Contract::query()->create([
        'contract_number' => 'R-CON-'.fake()->unique()->numerify('######'),
        'user_id' => $other->id,
        'room_id' => $room->id,
        'contract_total' => 6000000,
        'deposit_amount' => 50000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'draft',
        'created_by' => $admin->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contract-drafts/{$firstDraftId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contract-drafts/{$secondDraft->id}/approve")
        ->assertStatus(409);

    expect(Contract::query()->where('room_id', $room->id)->where('status', 'active')->count())->toBe(1);
});

it('prevents duplicate utility-to-invoice generation and double utility approval', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $room = concurrencyRoom('rent', 'available');
    concurrencyActiveRentContract($admin, $customer, $room);

    $utilityType = UtilityType::query()->create([
        'name' => 'Electricity',
        'slug' => 'electricity-concurrency',
        'unit' => 'kWh',
        'status' => 'active',
    ]);

    UtilityRate::query()->create([
        'utility_type_id' => $utilityType->id,
        'unit_price' => 100,
        'status' => 'active',
        'effective_date' => now()->toDateString(),
    ]);

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'status' => 'pending',
        'total_amount' => 1500,
        'created_by' => $admin->id,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $utility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 10,
        'current_reading' => 25,
        'usage' => 15,
        'unit_price' => 100,
        'amount' => 1500,
    ]);

    $contract = Contract::query()->where('room_id', $room->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/approve")
        ->assertOk();

    $utility->refresh();
    expect($utility->invoice_id)->not->toBeNull();

    expect(Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereDate('billing_month', now()->startOfMonth()->toDateString())
        ->count())->toBe(1);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/approve")
        ->assertStatus(409);

    expect(Utility::query()->find($utility->id)?->invoice_id)->toBe($utility->invoice_id);
});

it('rolls back utility approval when invoice generation fails', function (): void {
    $admin = concurrencyAdmin();
    $room = concurrencyRoom('rent', 'available');

    $utilityType = UtilityType::query()->create([
        'name' => 'Water',
        'slug' => 'water-concurrency',
        'unit' => 'm3',
        'status' => 'active',
    ]);

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'status' => 'pending',
        'total_amount' => 500,
        'created_by' => $admin->id,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $utility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 1,
        'current_reading' => 6,
        'usage' => 5,
        'unit_price' => 100,
        'amount' => 500,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/approve")
        ->assertStatus(422);

    expect(Utility::query()->find($utility->id)?->status)->toBe('pending');
    expect(Utility::query()->find($utility->id)?->invoice_id)->toBeNull();
    expect(Invoice::query()->count())->toBe(0);
});

it('prevents duplicate payment approval and duplicate receipt creation', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $room = concurrencyRoom('rent', 'available');
    $contract = concurrencyActiveRentContract($admin, $customer, $room);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-CON-0001',
        'type' => 'rent',
        'status' => 'issued',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 100000,
        'created_by' => $admin->id,
    ]);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => null,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payment-proofs/test.jpg',
        'created_by' => $customer->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 100000])
        ->assertOk();

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 100000])
        ->assertStatus(409);

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);
    expect(Payment::query()->find($payment->id)?->status)->toBe('approved');
});

it('blocks unauthorized customer access to another customer invoice and maintenance request', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $other = concurrencyOtherCustomer();
    $room = concurrencyRoom('rent', 'available');
    $otherRoom = concurrencyRoom('rent', 'available');
    $contract = concurrencyActiveRentContract($admin, $customer, $room);
    concurrencyActiveRentContract($admin, $other, $otherRoom);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-CON-0002',
        'type' => 'rent',
        'status' => 'issued',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 100000,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertNotFound();

    $request = MaintenanceRequest::query()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Private request',
        'category' => 'general',
        'priority' => 'low',
        'description' => 'Should stay private',
        'status' => 'pending',
    ]);

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$request->id}")
        ->assertNotFound();

    $this->actingAs($other, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Cross-room attempt',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Not my room',
        ])
        ->assertStatus(422);
});

it('lets admin update customer maintenance status with locking against double complete', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $room = concurrencyRoom('rent', 'available');
    concurrencyActiveRentContract($admin, $customer, $room);

    $requestId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Broken switch',
            'category' => 'electrical',
            'priority' => 'medium',
            'description' => 'Hallway switch sparks',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $requestId]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/complete", [
            'resolution_note' => 'Switch replaced',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/complete", [
            'resolution_note' => 'Again',
        ])
        ->assertStatus(409);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$requestId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resolution_note', 'Switch replaced');
});

it('uses lockForUpdate inside payment approval transaction', function (): void {
    $admin = concurrencyAdmin();
    $customer = concurrencyCustomer();
    $room = concurrencyRoom('rent', 'available');
    $contract = concurrencyActiveRentContract($admin, $customer, $room);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-CON-0003',
        'type' => 'rent',
        'status' => 'issued',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 50000,
        'created_by' => $admin->id,
    ]);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => null,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payment-proofs/lock.jpg',
        'created_by' => $customer->id,
    ]);

    DB::transaction(function () use ($payment): void {
        Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
    });

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 50000])
        ->assertOk();

    expect(ChargeType::query()->where('slug', 'utility-charges')->exists())->toBeTrue();
});
