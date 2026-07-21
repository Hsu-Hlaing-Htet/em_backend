<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customerPortalUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

it('returns customer dashboard data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active_contracts',
                'unpaid_invoices',
                'paid_invoices',
                'recent_payments',
            ],
        ]);
});

it('returns customer profile data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $customer->email);
});

it('returns customer contracts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/contracts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer invoices list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer payments list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payments')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer receipts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer notifications', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns customer payment methods', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payment-methods')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('forbids non-customer access to customer portal routes', function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertForbidden();
});
