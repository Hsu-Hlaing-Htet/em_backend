<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

test('admin dashboard charts endpoint returns live chart metrics', function () {
    $admin = dashboardAdmin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $availableRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-101',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1500000,
        'rent_deposit_price' => 300000,
        'booking_deposit_price' => 150000,
    ]);

    $occupiedRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-102',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1600000,
        'rent_deposit_price' => 320000,
        'booking_deposit_price' => 160000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-TEST-001',
        'user_id' => $customer->id,
        'room_id' => $occupiedRoom->id,
        'contract_total' => 1200000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-TEST-001',
        'type' => 'rent',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'late_fee' => 0,
        'total_amount' => 500000,
        'status' => 'issued',
        'created_by' => $admin->id,
    ]);

    $paymentMethod = PaymentMethod::query()->firstOrFail();

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'created_by' => $customer->id,
        'amount' => 250000,
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertOk()
        ->assertJsonStructure([
            'kpi_stats',
            'revenue_summary' => ['total_paid', 'outstanding', 'collected_this_month', 'growth_percent'],
            'revenue_chart',
            'revenue_collections' => ['collection_rate', 'points'],
            'receivable_aging',
            'occupancy_by_building',
            'upcoming_contracts',
            'pending_approval_breakdown' => ['total', 'items', 'latest'],
            'property_stats',
            'invoice_stats',
            'system_alerts' => [
                'expired_contracts',
                'unresolved_maintenance',
                'overdue_invoices',
                'items',
            ],
            'activity_timeline',
        ]);

    expect($response->json('property_stats'))->toHaveCount(5);
    expect(collect($response->json('property_stats'))->firstWhere('key', 'available')['value'])->toBe(1);
    expect(collect($response->json('property_stats'))->firstWhere('key', 'occupied')['value'])->toBe(1);

    expect(collect($response->json('invoice_stats'))->firstWhere('key', 'issued')['value'])->toBe(1);

    expect($response->json('revenue_summary.total_paid'))->toEqual(250000);
    expect($response->json('revenue_summary.collected_this_month'))->toEqual(250000);
    expect($response->json('revenue_summary.outstanding'))->toEqual(250000);

    expect($response->json('revenue_chart'))->toHaveCount(12);
    expect(collect($response->json('revenue_chart'))->last()['amount'])->toEqual(250000);

    expect(collect($response->json('kpi_stats'))->firstWhere('key', 'revenue')['value'])->toContain('MMK');
    expect(collect($response->json('kpi_stats'))->firstWhere('key', 'occupancy')['value'])->toContain('%');
    expect($response->json('revenue_collections.points'))->toHaveCount(6);
    expect($response->json('receivable_aging'))->toHaveCount(4);
    expect($response->json('occupancy_by_building.0.label'))->toBe('Rosewood Tower');
    expect($response->json('pending_approval_breakdown.items'))->toHaveCount(4);
    expect(collect($response->json('pending_approval_breakdown.items'))->pluck('key')->all())
        ->toBe(['payments', 'invoices', 'utilities', 'others']);
    expect($response->json('pending_approval_breakdown.latest'))->toBeArray();
    expect($response->json('system_alerts'))->toHaveKeys([
        'expired_contracts',
        'unresolved_maintenance',
        'overdue_invoices',
        'items',
    ]);
    expect($response->json('system_alerts.items'))->toBeArray();
    expect($response->json('activity_timeline'))->toBe([]);
});

test('customer cannot access admin dashboard charts endpoint', function () {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertForbidden();
});

test('system alerts expose individual overdue invoices and high-priority maintenance', function () {
    $admin = dashboardAdmin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Golden Hill Residence',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'C-107',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1500000,
        'rent_deposit_price' => 300000,
        'booking_deposit_price' => 150000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-ALERT-001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 1200000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-000148',
        'type' => 'rent',
        'issued_date' => now()->subDays(20)->toDateString(),
        'due_date' => now()->subDays(5)->toDateString(),
        'late_fee' => 0,
        'total_amount' => 805419,
        'status' => 'overdue',
        'created_by' => $admin->id,
    ]);

    $maintenance = \App\Models\MaintenanceRequest::query()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Bathroom drain clogged',
        'category' => 'hvac',
        'priority' => 'high',
        'description' => 'Drain backs up in bathroom.',
        'status' => 'in_progress',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(1),
    ]);

    // Low-priority open request must not appear in System Alerts items.
    \App\Models\MaintenanceRequest::query()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Light bulb replacement',
        'category' => 'electrical',
        'priority' => 'low',
        'description' => 'Hallway bulb out.',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertOk();

    $items = collect($response->json('system_alerts.items'));

    $invoiceAlert = $items->firstWhere('kind', 'overdue_invoice');
    expect($invoiceAlert)->not->toBeNull();
    expect($invoiceAlert['number'])->toBe('INV-000148');
    expect($invoiceAlert['title'])->toBe('rent · INV-000148');
    expect($invoiceAlert['detail'])->toContain('Golden Hill Residence');
    expect($invoiceAlert['detail'])->toContain('C-107');
    expect($invoiceAlert['amount_label'])->toBe('MMK 805,419');
    expect($invoiceAlert['to'])->toBe('/admin/invoices/'.$invoice->id.'/document');
    expect($invoiceAlert['due_date'])->toBe(
        \Carbon\Carbon::parse($invoice->due_date)->format('d M Y')
    );

    $maintenanceAlert = $items->firstWhere('kind', 'high_priority_maintenance');
    expect($maintenanceAlert)->not->toBeNull();
    expect($maintenanceAlert['title'])->toContain('Bathroom drain clogged');
    expect($maintenanceAlert['detail'])->toContain('HVAC');
    expect($maintenanceAlert['status_label'])->toBe('In Progress');
    expect($maintenanceAlert['created_at'])->toBe($maintenance->created_at->format('d M Y'));
    expect($maintenanceAlert['created_at'])->not->toContain(':');
    expect($maintenanceAlert['to'])->toBe('/admin/maintenance-requests/'.$maintenance->id);
    expect($items->contains(fn ($item) => str_contains((string) ($item['title'] ?? ''), 'Light bulb')))->toBeFalse();
    expect($items)->toHaveCount(2);
});

test('system alerts return at most five individual items', function () {
    $admin = dashboardAdmin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Alert Cap Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'B-201',
        'floor_number' => 2,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1500000,
        'rent_deposit_price' => 300000,
        'booking_deposit_price' => 150000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-ALERT-CAP',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 1200000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    foreach (range(1, 6) as $index) {
        Invoice::query()->create([
            'contract_id' => $contract->id,
            'invoice_number' => sprintf('INV-CAP-%03d', $index),
            'type' => 'rent',
            'issued_date' => now()->subDays(30 + $index)->toDateString(),
            'due_date' => now()->subDays(20 - $index)->toDateString(),
            'late_fee' => 0,
            'total_amount' => 100000 * $index,
            'status' => 'overdue',
            'created_by' => $admin->id,
        ]);
    }

    foreach (range(1, 3) as $index) {
        \App\Models\MaintenanceRequest::query()->create([
            'room_id' => $room->id,
            'user_id' => $customer->id,
            'created_by' => $customer->id,
            'title' => "High priority issue {$index}",
            'category' => 'plumbing',
            'priority' => 'high',
            'description' => 'Cap test request.',
            'status' => 'pending',
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertOk();

    $items = $response->json('system_alerts.items');

    expect($items)->toHaveCount(5);
    expect(collect($items)->every(fn ($item) => $item['kind'] === 'overdue_invoice'))->toBeTrue();
    expect($response->json('system_alerts.overdue_invoices'))->toBeGreaterThanOrEqual(6);
});

test('pending approval latest returns newest five matching kpi definition', function () {
    $admin = dashboardAdmin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $paymentMethod = PaymentMethod::query()->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Golden Hill Residence',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'C-107',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1500000,
        'rent_deposit_price' => 300000,
        'booking_deposit_price' => 150000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-000070',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 1200000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'draft',
        'created_by' => $admin->id,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $issuedInvoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-ISSUED-001',
        'type' => 'rent',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'late_fee' => 0,
        'total_amount' => 500000,
        'status' => 'issued',
        'created_by' => $admin->id,
    ]);

    $draftInvoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-000164',
        'type' => 'rent',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'late_fee' => 0,
        'total_amount' => 830209,
        'status' => 'draft',
        'created_by' => $admin->id,
    ]);
    $draftInvoice->forceFill([
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ])->saveQuietly();

    $pendingPayment = Payment::query()->create([
        'invoice_id' => $issuedInvoice->id,
        'payment_method_id' => $paymentMethod->id,
        'created_by' => $customer->id,
        'amount' => 699581,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    $pendingPayment->forceFill([
        'created_at' => now()->subMinutes(30),
        'updated_at' => now()->subMinutes(30),
    ])->saveQuietly();

    // Older pending invoice should be pushed out when more than 5 exist.
    foreach (range(1, 4) as $index) {
        $oldInvoice = Invoice::query()->create([
            'contract_id' => $contract->id,
            'invoice_number' => sprintf('INV-OLD-%03d', $index),
            'type' => 'rent',
            'issued_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'late_fee' => 0,
            'total_amount' => 100000,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $oldInvoice->forceFill([
            'created_at' => now()->subDays(5 + $index),
            'updated_at' => now()->subDays(5 + $index),
        ])->saveQuietly();
    }

    $contract->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ])->saveQuietly();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertOk();

    $latest = collect($response->json('pending_approval_breakdown.latest'));
    $kpiPending = (int) collect($response->json('kpi_stats'))->firstWhere('key', 'pending_approvals')['value'];

    expect($latest)->toHaveCount(5);
    expect($kpiPending)->toBe($response->json('pending_approval_breakdown.total'));
    expect($kpiPending)->toBeGreaterThanOrEqual(5);

    expect($latest->first()['kind'])->toBe('payment');
    expect($latest->first()['reference'])->toBe('PAY-'.str_pad((string) $pendingPayment->id, 6, '0', STR_PAD_LEFT));
    expect($latest->first()['type_label'])->toBe('Payment');
    expect($latest->first()['to'])->toBe('/admin/payments/approval/'.$pendingPayment->id);
    expect($latest->first()['created_at'])->toContain('·');
    expect($latest->pluck('kind')->all())->not->toContain('receipt');

    $invoiceItem = $latest->firstWhere('reference', 'INV-000164');
    expect($invoiceItem)->not->toBeNull();
    expect($invoiceItem['detail'])->toContain('Golden Hill Residence');
    expect($invoiceItem['to'])->toBe('/admin/invoices/approval/'.$draftInvoice->id.'/document');

    // Issued invoices must never appear as pending approvals.
    expect($latest->contains(fn ($item) => ($item['reference'] ?? '') === 'INV-ISSUED-001'))->toBeFalse();
});
