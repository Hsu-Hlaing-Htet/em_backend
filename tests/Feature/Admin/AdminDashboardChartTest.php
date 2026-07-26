<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
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
    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

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
            'property_stats',
            'invoice_stats',
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

    expect(collect($response->json('kpi_stats'))->firstWhere('key', 'properties')['value'])->toBe('2');
    expect((int) collect($response->json('kpi_stats'))->firstWhere('key', 'clients')['value'])->toBeGreaterThanOrEqual(1);
});

test('customer cannot access admin dashboard charts endpoint', function () {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertForbidden();
});
