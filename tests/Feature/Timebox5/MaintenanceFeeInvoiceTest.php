<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\MaintenanceCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function maintenanceFeeUsers(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new MaintenanceCategorySeeder)->run();

    return [
        'admin' => User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        'customer' => User::query()->where('email', 'hsuhtet562@gmail.com')->firstOrFail(),
        'secondCustomer' => User::query()->where('email', 'hlahla@gmail.com')->firstOrFail(),
    ];
}

function maintenanceFeeRoom(User $admin, User $customer, ?User $secondCustomer = null): array
{
    $building = Building::query()->create([
        'building_name' => 'Maintenance Fee Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'MF-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 4,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 390000,
        'rent_deposit_price' => 39000,
        'booking_deposit_price' => 9000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-MF-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'second_user_id' => $secondCustomer?->id,
        'room_id' => $room->id,
        'contract_total' => 4680000,
        'deposit_amount' => 39000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 5,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    return compact('room', 'contract');
}

function maintenanceFeeInProgressRequest(User $admin, User $customer, Room $room): MaintenanceRequest
{
    $requestId = test()->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Electrical repair',
            'category' => 'electrical',
            'priority' => 'high',
            'description' => 'Outlet not working.',
        ])
        ->assertCreated()
        ->json('data.id');

    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/start")
        ->assertOk();

    return MaintenanceRequest::query()->findOrFail($requestId);
}

test('completing maintenance with fee zero creates no invoice item', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);
    $invoiceCountBefore = Invoice::query()->count();
    $itemCountBefore = InvoiceItem::query()->count();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repaired without charge.',
            'maintenance_fee_amount' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.maintenance_fee_amount', null)
        ->assertJsonPath('data.invoice_id', null);

    expect(Invoice::query()->count())->toBe($invoiceCountBefore)
        ->and(InvoiceItem::query()->count())->toBe($itemCountBefore)
        ->and($request->fresh()->status)->toBe('completed');
});

test('completing maintenance with fee creates maintenance invoice item on draft invoice', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room, 'contract' => $contract] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.maintenance_fee_amount', 50000)
        ->assertJsonPath('data.charge_description', 'Electrical repair service');

    $invoiceId = $response->json('data.invoice_id');
    expect($invoiceId)->not->toBeNull();

    $invoice = Invoice::query()->with('items.chargeType')->findOrFail($invoiceId);
    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->contract_id)->toBe($contract->id);

    $maintenanceItem = $invoice->items->first(
        fn (InvoiceItem $item) => $item->chargeType?->slug === 'maintenance-fee'
    );

    expect($maintenanceItem)->not->toBeNull()
        ->and($maintenanceItem->description)->toBe('Maintenance Fee')
        ->and((float) $maintenanceItem->amount)->toBe(50000.0)
        ->and($maintenanceItem->previous_reading)->toBeNull()
        ->and($maintenanceItem->current_reading)->toBeNull()
        ->and($maintenanceItem->usage)->toBeNull()
        ->and($maintenanceItem->maintenance_request_id)->toBe($request->id)
        ->and((float) $invoice->total_amount)->toBeGreaterThanOrEqual(50000.0);

    expect($request->fresh()->charge_description)->toBe('Electrical repair service');
});

test('maintenance fee is appended to existing compatible pending invoice', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room, 'contract' => $contract] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $this->actingAs($admin, 'sanctum');
    $existing = app(InvoiceService::class)->generateFromContract($contract);
    expect($existing->status)->toBe(Invoice::STATUS_DRAFT);

    $invoiceCountBefore = Invoice::query()->count();
    $existingTotal = (float) $existing->total_amount;

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk()
        ->assertJsonPath('data.invoice_id', $existing->id);

    expect(Invoice::query()->count())->toBe($invoiceCountBefore);

    $fresh = $existing->fresh(['items.chargeType']);
    $maintenanceItems = $fresh->items->where(fn ($item) => $item->chargeType?->slug === 'maintenance-fee');

    expect($maintenanceItems)->toHaveCount(1)
        ->and((float) $fresh->total_amount)->toBe(round($existingTotal + 50000, 2));
});

test('maintenance fee creates new pending invoice when none exists', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room, 'contract' => $contract] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    expect(
        Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('status', Invoice::STATUS_DRAFT)
            ->exists()
    )->toBeFalse();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk();

    $drafts = Invoice::query()
        ->where('contract_id', $contract->id)
        ->where('status', Invoice::STATUS_DRAFT)
        ->get();

    expect($drafts)->toHaveCount(1)
        ->and(
            InvoiceItem::query()
                ->where('invoice_id', $drafts->first()->id)
                ->where('maintenance_request_id', $request->id)
                ->exists()
        )->toBeTrue();
});

test('maintenance fee draft appears in invoice approval queue', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $invoiceId = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk()
        ->json('data.invoice_id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/invoices?payment_status=draft&per_page=200')
        ->assertOk()
        ->assertJsonFragment(['id' => $invoiceId, 'status' => 'draft']);

    $detail = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/invoices/{$invoiceId}")
        ->assertOk();

    $descriptions = collect($detail->json('data.items'))->pluck('description');
    expect($descriptions)->toContain('Maintenance Fee');
});

test('charge description is required when maintenance fee is greater than zero', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.charge_description.0', 'Charge description is required when a maintenance fee is charged.');

    expect($request->fresh()->status)->toBe('in_progress');
});

test('duplicate complete does not create duplicate maintenance charge', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Retry',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertStatus(409);

    expect(
        InvoiceItem::query()->where('maintenance_request_id', $request->id)->count()
    )->toBe(1);
});

test('concurrent complete attempts produce one maintenance item only', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $payload = [
        'resolution_note' => 'Repair completed',
        'maintenance_fee_amount' => 50000,
        'charge_description' => 'Electrical repair service',
    ];

    $results = [];

    DB::transaction(function () use ($admin, $request, $payload, &$results): void {
        $results[] = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-requests/{$request->id}/complete", $payload)
            ->status();
    });

    $results[] = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", $payload)
        ->status();

    expect($results)->toContain(200)
        ->and(collect($results)->contains(409) || collect($results)->filter(fn ($s) => $s === 200)->count() === 1)->toBeTrue()
        ->and(InvoiceItem::query()->where('maintenance_request_id', $request->id)->count())->toBe(1);
});

test('billing failure rolls back maintenance completion', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    ChargeType::query()->where('slug', 'maintenance-fee')->delete();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertStatus(422);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe('in_progress')
        ->and($fresh->invoice_id)->toBeNull()
        ->and(InvoiceItem::query()->where('maintenance_request_id', $request->id)->exists())->toBeFalse();
});

test('joint contract maintains one shared billing stream for maintenance fee', function () {
    ['admin' => $admin, 'customer' => $customer, 'secondCustomer' => $second] = maintenanceFeeUsers();
    ['room' => $room, 'contract' => $contract] = maintenanceFeeRoom($admin, $customer, $second);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk();

    $drafts = Invoice::query()
        ->where('contract_id', $contract->id)
        ->where('status', Invoice::STATUS_DRAFT)
        ->get();

    expect($drafts)->toHaveCount(1)
        ->and($contract->second_user_id)->toBe($second->id)
        ->and(
            InvoiceItem::query()
                ->whereIn('invoice_id', $drafts->pluck('id'))
                ->where('maintenance_request_id', $request->id)
                ->count()
        )->toBe(1);
});

test('maintenance fee amount is included in invoice subtotal total', function () {
    ['admin' => $admin, 'customer' => $customer] = maintenanceFeeUsers();
    ['room' => $room, 'contract' => $contract] = maintenanceFeeRoom($admin, $customer);
    $request = maintenanceFeeInProgressRequest($admin, $customer, $room);

    $this->actingAs($admin, 'sanctum');
    $existing = app(InvoiceService::class)->generateFromContract($contract);
    $rentTotal = (float) $existing->total_amount;

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$request->id}/complete", [
            'resolution_note' => 'Repair completed',
            'maintenance_fee_amount' => 50000,
            'charge_description' => 'Electrical repair service',
        ])
        ->assertOk();

    $fresh = $existing->fresh();
    $itemSum = (float) InvoiceItem::query()->where('invoice_id', $fresh->id)->sum('amount');

    expect((float) $fresh->total_amount)->toBe(round($rentTotal + 50000, 2))
        ->and((float) $fresh->total_amount)->toBe($itemSum);
});
