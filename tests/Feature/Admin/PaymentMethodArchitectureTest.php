<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    Storage::fake('public');
});

function paymentMethodAdmin(): User
{
    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function paymentMethodCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

it('seeds wallet methods with shared phone and hides cash from customers', function (): void {
    (new PaymentMethodSeeder)->run();
    $firstCount = PaymentMethod::query()->count();
    (new PaymentMethodSeeder)->run();
    expect(PaymentMethod::query()->count())->toBe($firstCount);

    $wallets = ['kbz-pay', 'aya-pay', 'uab-pay', 'wave-pay'];

    foreach ($wallets as $slug) {
        $method = PaymentMethod::query()->where('slug', $slug)->firstOrFail();
        expect($method->name)->not->toBeEmpty();
        expect($method->type)->toBe(PaymentMethod::TYPE_WALLET);
        expect($method->phone_number)->toBe('09779959901');
        expect($method->status)->toBe(PaymentMethod::STATUS_ACTIVE);
        expect($method->is_customer_visible)->toBeTrue();
    }

    $uab = PaymentMethod::query()->where('slug', 'uab-pay')->firstOrFail();
    expect($uab->name)->toBe('uab Pay');

    $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    expect($cash->type)->toBe(PaymentMethod::TYPE_CASH);
    expect($cash->phone_number)->toBeNull();
    expect($cash->status)->toBe(PaymentMethod::STATUS_ACTIVE);
    expect($cash->is_customer_visible)->toBeFalse();
});

it('allows admin to create edit toggle and upload qr for payment methods', function (): void {
    $admin = paymentMethodAdmin();

    $create = $this->actingAs($admin, 'sanctum')
        ->post('/api/payment-methods', [
            'name' => 'Test Wallet',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09770001122',
            'is_customer_visible' => true,
            'sort_order' => 5,
            'qr_image' => UploadedFile::fake()->image('qr.png', 200, 200),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Test Wallet')
        ->assertJsonPath('data.type', 'wallet')
        ->assertJsonPath('data.phone_number', '09770001122')
        ->assertJsonPath('data.is_customer_visible', true);

    $id = (int) $create->json('data.id');
    expect($create->json('data.qr_image_url'))->not->toBeNull();

    $this->actingAs($admin, 'sanctum')
        ->post("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09779959901',
            'is_customer_visible' => '1',
            'sort_order' => 6,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Test Wallet Updated')
        ->assertJsonPath('data.phone_number', '09779959901');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    $this->actingAs($admin, 'sanctum')
        ->post("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'type' => 'wallet',
            'status' => 'active',
            'is_customer_visible' => '0',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_customer_visible', false);
});

it('keeps cash available in admin and not in customer payment methods', function (): void {
    (new PaymentMethodSeeder)->run();
    $admin = paymentMethodAdmin();
    $customer = paymentMethodCustomer();

    $adminList = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payment-methods?per_page=50')
        ->assertOk()
        ->json('data.data');

    $adminNames = collect($adminList)->pluck('name')->all();
    expect($adminNames)->toContain('Cash');
    expect($adminNames)->toContain('KBZ Pay');
    expect($adminNames)->toContain('uab Pay');

    $customerList = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payment-methods')
        ->assertOk()
        ->json('data');

    $customerNames = collect($customerList)->pluck('name')->all();
    expect($customerNames)->toContain('KBZ Pay');
    expect($customerNames)->toContain('AYA Pay');
    expect($customerNames)->toContain('uab Pay');
    expect($customerNames)->toContain('Wave Pay');
    expect($customerNames)->not->toContain('Cash');
    expect($customerNames)->not->toContain('Cheque');

    $kbz = collect($customerList)->firstWhere('name', 'KBZ Pay');
    expect($kbz['phone_number'])->toBe('09779959901');
    expect($kbz)->not->toHaveKey('qr_image_path');
    expect(array_key_exists('qr_image_url', $kbz))->toBeTrue();
});

it('rejects customer payment with cash or inactive method ids', function (): void {
    (new PaymentMethodSeeder)->run();
    $customer = paymentMethodCustomer();

    $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    $inactive = PaymentMethod::factory()->create([
        'name' => 'Hidden Wallet',
        'slug' => 'hidden-wallet-test',
        'type' => PaymentMethod::TYPE_WALLET,
        'status' => PaymentMethod::STATUS_INACTIVE,
        'is_customer_visible' => true,
        'phone_number' => '09779959901',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->post('/api/customer/payments', [
            'invoice_id' => 1,
            'payment_method_id' => $cash->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method_id']);

    $this->actingAs($customer, 'sanctum')
        ->post('/api/customer/payments', [
            'invoice_id' => 1,
            'payment_method_id' => $inactive->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method_id']);
});

it('forbids customers from mutating admin payment method routes', function (): void {
    (new PaymentMethodSeeder)->run();
    $customer = paymentMethodCustomer();
    $method = PaymentMethod::query()->where('slug', 'kbz-pay')->firstOrFail();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Hack',
            'type' => 'wallet',
            'status' => 'active',
        ])
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->putJson("/api/payment-methods/{$method->id}", [
            'name' => 'Hack',
            'status' => 'inactive',
        ])
        ->assertForbidden();
});
