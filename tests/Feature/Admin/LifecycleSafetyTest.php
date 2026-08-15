<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lifecycleActors(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return [
        User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail(),
    ];
}

test('customer room and building history is preserved through lifecycle transitions', function (): void {
    [$admin, $customer] = lifecycleActors();
    $building = Building::factory()->create();
    $room = Room::factory()->occupied()->create(['building_id' => $building->id]);
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/residents/{$customer->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->deleteJson("/api/rooms/{$room->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->deleteJson("/api/buildings/{$building->id}")->assertOk();

    expect($customer->fresh()->status)->toBe(User::STATUS_INACTIVE)
        ->and($room->fresh()->status)->toBe(Room::STATUS_INACTIVE)
        ->and($building->fresh()->status)->toBe(Building::STATUS_ARCHIVED)
        ->and(Contract::query()->find($contract->id))->not->toBeNull();

    $this->actingAs($admin, 'sanctum')->postJson("/api/residents/{$customer->id}/activate")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/buildings/{$building->id}/activate")->assertOk();

    expect($customer->fresh()->status)->toBe(User::STATUS_ACTIVE)
        ->and($building->fresh()->status)->toBe(Building::STATUS_ACTIVE);
});

test('generic user deletion deactivates customers without deleting their history', function (): void {
    [$admin, $customer] = lifecycleActors();
    $room = Room::factory()->occupied()->create();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);
    $payment = Payment::factory()->pending()->create([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
    ]);
    $maintenanceRequest = MaintenanceRequest::factory()->pending()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/users/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Customer deactivated successfully.');

    expect($customer->fresh()->status)->toBe(User::STATUS_INACTIVE)
        ->and($customer->fresh()->deleted_at)->toBeNull()
        ->and(Contract::query()->find($contract->id))->not->toBeNull()
        ->and(Invoice::query()->find($invoice->id))->not->toBeNull()
        ->and(Payment::query()->find($payment->id))->not->toBeNull()
        ->and(MaintenanceRequest::query()->find($maintenanceRequest->id))->not->toBeNull();
});

test('inactive customers cannot authenticate or access customer routes', function (): void {
    [, $customer] = lifecycleActors();
    $customer->update(['status' => User::STATUS_INACTIVE]);

    $this->postJson('/api/auth/login', [
        'email' => $customer->email,
        'password' => 'p@ssword',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertForbidden();
});

test('rooms with utility or maintenance history are deactivated instead of deleted', function (): void {
    [$admin, $customer] = lifecycleActors();
    $room = Room::factory()->create();
    $utility = Utility::factory()->draft()->create([
        'room_id' => $room->id,
        'created_by' => $admin->id,
    ]);
    $maintenanceRequest = MaintenanceRequest::factory()->pending()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/rooms/{$room->id}")
        ->assertOk();

    expect($room->fresh()->status)->toBe(Room::STATUS_INACTIVE)
        ->and($room->fresh()->deleted_at)->toBeNull()
        ->and(Utility::query()->find($utility->id))->not->toBeNull()
        ->and(MaintenanceRequest::query()->find($maintenanceRequest->id))->not->toBeNull();
});

test('buildings with current or soft deleted rooms are archived instead of deleted', function (): void {
    [$admin] = lifecycleActors();
    $buildingWithCurrentRoom = Building::factory()->create();
    Room::factory()->create(['building_id' => $buildingWithCurrentRoom->id]);
    $buildingWithDeletedRoom = Building::factory()->create();
    $deletedRoom = Room::factory()->create(['building_id' => $buildingWithDeletedRoom->id]);
    $deletedRoom->delete();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/buildings/{$buildingWithCurrentRoom->id}")
        ->assertOk();
    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/buildings/{$buildingWithDeletedRoom->id}")
        ->assertOk();

    expect($buildingWithCurrentRoom->fresh()->status)->toBe(Building::STATUS_ARCHIVED)
        ->and($buildingWithCurrentRoom->fresh()->deleted_at)->toBeNull()
        ->and($buildingWithDeletedRoom->fresh()->status)->toBe(Building::STATUS_ARCHIVED)
        ->and($buildingWithDeletedRoom->fresh()->deleted_at)->toBeNull();
});

test('inactive customers and archived buildings cannot be used for new records', function (): void {
    [$admin, $customer] = lifecycleActors();
    $customer->update(['status' => User::STATUS_INACTIVE]);
    $building = Building::factory()->create();
    $room = Room::factory()->forSale()->create(['building_id' => $building->id]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422);

    $customer->update(['status' => User::STATUS_ACTIVE]);
    $room->update(['status' => Room::STATUS_INACTIVE]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422);

    $room->update(['status' => Room::STATUS_AVAILABLE]);
    $building->update(['status' => Building::STATUS_ARCHIVED]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rooms', [
            'building_id' => $building->id,
            'room_number' => 'ARCH-01',
            'floor_number' => 1,
            'area_sqft' => 500,
            'type' => 'rent',
            'status' => Room::STATUS_AVAILABLE,
            'sale_price' => 0,
            'rent_price' => 500000,
            'rent_deposit_price' => 1000000,
            'booking_deposit_price' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['building_id'], 'data');
});

test('active contracts are cancelled without deletion and release their room', function (): void {
    [$admin, $customer] = lifecycleActors();
    $room = Room::factory()->occupied()->create();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contracts/active/{$contract->id}/cancel", [
            'reason' => 'Customer requested cancellation.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($contract->fresh()->status)->toBe('cancelled')
        ->and($contract->fresh()->remark)->toBe('Customer requested cancellation.')
        ->and($room->fresh()->status)->toBe(Room::STATUS_AVAILABLE)
        ->and(Contract::query()->find($contract->id))->not->toBeNull();
});

test('invoice and payment delete routes preserve records through status transitions', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $invoice = Invoice::factory()->draft()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);
    $payment = Payment::factory()->pending()->create([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/invoices/{$invoice->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->deleteJson("/api/payments/{$payment->id}")->assertOk();

    expect($invoice->fresh()->status)->toBe('cancelled')
        ->and($payment->fresh()->status)->toBe('rejected')
        ->and(Invoice::query()->find($invoice->id))->not->toBeNull()
        ->and(Payment::query()->find($payment->id))->not->toBeNull();
});

test('draft contracts with billing history cannot be deleted', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rent()->draft()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
    Invoice::factory()->draft()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/rent-contract-drafts/{$contract->id}")
        ->assertStatus(422);

    expect(Contract::query()->find($contract->id))->not->toBeNull();
});

test('unused draft contracts can be safely soft deleted', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rent()->draft()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/rent-contract-drafts/{$contract->id}")
        ->assertOk();

    $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
});

test('non draft contracts cannot be deleted through a draft endpoint', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/rent-contract-drafts/{$contract->id}")
        ->assertConflict();

    expect(Contract::query()->find($contract->id))->not->toBeNull();
});

test('invoice cancellation is blocked by approved payment or receipt history', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $invoiceWithPayment = Invoice::factory()->issued()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);
    Payment::factory()->approved()->create([
        'invoice_id' => $invoiceWithPayment->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $invoiceWithReceipt = Invoice::factory()->issued()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);
    $pendingPayment = Payment::factory()->pending()->create([
        'invoice_id' => $invoiceWithReceipt->id,
        'created_by' => $admin->id,
    ]);
    Receipt::factory()->issued()->create([
        'payment_id' => $pendingPayment->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    foreach ([$invoiceWithPayment, $invoiceWithReceipt] as $invoice) {
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/invoices/{$invoice->id}")
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'This invoice cannot be cancelled because approved payments or receipts exist.',
            );

        expect($invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
    }
});

test('approved payments and receipts cannot be deleted', function (): void {
    [$admin, $customer] = lifecycleActors();
    $contract = Contract::factory()->rentActive()->create([
        'user_id' => $customer->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
    ]);
    $payment = Payment::factory()->approved()->create([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);
    $receipt = Receipt::factory()->issued()->create([
        'payment_id' => $payment->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/payments/{$payment->id}")
        ->assertStatus(422);
    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/receipts/{$receipt->id}")
        ->assertMethodNotAllowed();

    expect(Payment::query()->find($payment->id))->not->toBeNull()
        ->and(Receipt::query()->find($receipt->id))->not->toBeNull();
});
