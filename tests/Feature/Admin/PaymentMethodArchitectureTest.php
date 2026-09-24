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
    (new PaymentMethodSeeder)->run();
    $maxSort = (int) PaymentMethod::query()->max('sort_order');

    $create = $this->actingAs($admin, 'sanctum')
        ->post('/api/payment-methods', [
            'name' => 'Test Wallet',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09770001122',
            'qr_image' => UploadedFile::fake()->image('qr.png', 200, 200),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Test Wallet')
        ->assertJsonPath('data.type', 'wallet')
        ->assertJsonPath('data.phone_number', '09770001122')
        ->assertJsonPath('data.is_customer_visible', true)
        ->assertJsonPath('data.sort_order', $maxSort + 10);

    $id = (int) $create->json('data.id');
    expect($create->json('data.qr_image_url'))->not->toBeNull();

    $this->actingAs($admin, 'sanctum')
        ->post("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09779959901',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Test Wallet Updated')
        ->assertJsonPath('data.phone_number', '09779959901')
        ->assertJsonPath('data.sort_order', $maxSort + 10);

    // Status-only toggle (list UI) — no type-specific fields required.
    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.is_customer_visible', false);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_customer_visible', true);

    // Client-provided is_customer_visible is ignored; Active non-Cash stays visible.
    $this->actingAs($admin, 'sanctum')
        ->post("/api/payment-methods/{$id}", [
            'name' => 'Test Wallet Updated',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09779959901',
            'is_customer_visible' => '0',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_customer_visible', true);
});

it('accepts local and international myanmar wallet phones and stores local form', function (): void {
    $admin = paymentMethodAdmin();

    foreach ([
        '09779959901',
        '+959779959901',
        '+95 9779959901',
        '09 779 959 901',
    ] as $index => $phone) {
        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/payment-methods', [
                'name' => "Wallet Phone {$index}",
                'type' => 'wallet',
                'status' => 'active',
                'phone_number' => $phone,
            ])
            ->assertCreated()
            ->json('data');

        expect($created['phone_number'])->toBe('09779959901');
    }

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Wallet Empty Phone',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Wallet Bad Phone',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '12345',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Wallet Letters Phone',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => 'not-a-phone',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'], 'data');

    $wallet = PaymentMethod::query()->where('slug', 'wallet-phone-0')->first()
        ?? PaymentMethod::factory()->create([
            'name' => 'Editable Wallet Phone',
            'slug' => 'editable-wallet-phone',
            'type' => PaymentMethod::TYPE_WALLET,
            'status' => PaymentMethod::STATUS_ACTIVE,
            'phone_number' => '09779959901',
        ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet->id}", [
            'name' => $wallet->name,
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '+95 9779959901',
        ])
        ->assertOk()
        ->assertJsonPath('data.phone_number', '09779959901');
});

it('requires phone number for wallet and account fields for bank transfer on create and update', function (): void {
    $admin = paymentMethodAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Wallet Missing Phone',
            'type' => 'wallet',
            'status' => 'active',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Wallet With Phone',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09770001122',
        ])
        ->assertCreated()
        ->assertJsonMissingValidationErrors(['account_name', 'account_number'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Bank Missing Account Number',
            'type' => 'bank_transfer',
            'status' => 'active',
            'account_name' => 'Rosewood Office',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_number'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Bank Missing Account Name',
            'type' => 'bank_transfer',
            'status' => 'active',
            'account_number' => '00112233',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_name'], 'data');

    $bank = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Valid Bank Transfer',
            'type' => 'bank_transfer',
            'status' => 'active',
            'account_name' => 'Rosewood Office',
            'account_number' => '00112233',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_customer_visible', true)
        ->assertJsonMissingValidationErrors(['phone_number'], 'data')
        ->json('data');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$bank['id']}", [
            'name' => 'Valid Bank Transfer',
            'type' => 'bank_transfer',
            'status' => 'active',
            'account_name' => '',
            'account_number' => '00112233',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_name'], 'data');

    $wallet = PaymentMethod::factory()->create([
        'name' => 'Editable Wallet',
        'slug' => 'editable-wallet-validation',
        'type' => PaymentMethod::TYPE_WALLET,
        'status' => PaymentMethod::STATUS_ACTIVE,
        'phone_number' => '09770001122',
        'is_customer_visible' => true,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet->id}", [
            'name' => 'Editable Wallet',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'], 'data');

    // Status-only update omits phone_number and must succeed.
    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet->id}", [
            'name' => 'Editable Wallet',
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.is_customer_visible', false);
});

it('rejects direct api bypass and clears stale fields when switching types on update', function (): void {
    $admin = paymentMethodAdmin();

    $wallet = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Switchable Wallet',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09770001122',
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet['id']}", [
            'name' => 'Now Bank Transfer',
            'type' => 'bank_transfer',
            'status' => 'active',
            'phone_number' => '09770001122',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_name', 'account_number'], 'data');

    $switched = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet['id']}", [
            'name' => 'Now Bank Transfer',
            'type' => 'bank_transfer',
            'status' => 'active',
            'account_name' => 'Rosewood Treasury',
            'account_number' => '44556677',
            'phone_number' => '09770001122',
        ])
        ->assertOk()
        ->json('data');

    expect($switched['type'])->toBe('bank_transfer');
    expect($switched['account_name'])->toBe('Rosewood Treasury');
    expect($switched['account_number'])->toBe('44556677');
    expect($switched['phone_number'])->toBeNull();
    expect($switched['is_customer_visible'])->toBeTrue();

    $backToWallet = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/payment-methods/{$wallet['id']}", [
            'name' => 'Back To Wallet',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09771112233',
            'account_name' => 'Should Clear',
            'account_number' => '999',
        ])
        ->assertOk()
        ->json('data');

    expect($backToWallet['type'])->toBe('wallet');
    expect($backToWallet['phone_number'])->toBe('09771112233');
    expect($backToWallet['account_name'])->toBeNull();
    expect($backToWallet['account_number'])->toBeNull();
});

it('does not require wallet or bank fields for cash payment methods', function (): void {
    $admin = paymentMethodAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-methods', [
            'name' => 'Office Cash Desk',
            'type' => 'cash',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'cash')
        ->assertJsonPath('data.phone_number', null)
        ->assertJsonPath('data.account_name', null)
        ->assertJsonPath('data.account_number', null)
        ->assertJsonPath('data.is_customer_visible', false);
});

it('normalizes type-specific fields when creating wallet and bank transfer methods', function (): void {
    $admin = paymentMethodAdmin();

    $wallet = $this->actingAs($admin, 'sanctum')
        ->post('/api/payment-methods', [
            'name' => 'Wallet With Stale Bank Fields',
            'type' => 'wallet',
            'status' => 'active',
            'phone_number' => '09770001122',
            'account_name' => 'Should Clear',
            'account_number' => '999',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->json('data');

    expect($wallet['phone_number'])->toBe('09770001122');
    expect($wallet['account_name'])->toBeNull();
    expect($wallet['account_number'])->toBeNull();
    expect($wallet['is_customer_visible'])->toBeTrue();

    $bank = $this->actingAs($admin, 'sanctum')
        ->post('/api/payment-methods', [
            'name' => 'Bank With Stale Wallet Fields',
            'type' => 'bank_transfer',
            'status' => 'active',
            'phone_number' => '09770001122',
            'account_name' => 'Rosewood Office',
            'account_number' => '00112233',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->json('data');

    expect($bank['account_name'])->toBe('Rosewood Office');
    expect($bank['account_number'])->toBe('00112233');
    expect($bank['phone_number'])->toBeNull();
    expect($bank['qr_image_url'])->toBeNull();
    expect($bank['is_customer_visible'])->toBeTrue();
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
    expect($customerNames)->toBe([
        'KBZ Pay',
        'AYA Pay',
        'uab Pay',
        'Wave Pay',
        'KBZ Bank Transfer',
        'AYA Bank Transfer',
    ]);
    expect($customerNames)->not->toContain('Cash');
    expect($customerNames)->not->toContain('Cheque');

    $kbz = collect($customerList)->firstWhere('name', 'KBZ Pay');
    expect($kbz['phone_number'])->toBe('09779959901');
    expect($kbz)->not->toHaveKey('qr_image_path');
    expect(array_key_exists('qr_image_url', $kbz))->toBeTrue();

    $bank = collect($customerList)->firstWhere('name', 'KBZ Bank Transfer');
    expect($bank['account_name'])->toBe('Rosewood Royale');
    expect($bank['account_number'])->toBe('0123456789');

    // Active Cash stays Admin-available; never customer-available.
    $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    expect($cash->status)->toBe(PaymentMethod::STATUS_ACTIVE);
    expect($cash->isAvailableForCustomer())->toBeFalse();
    expect($cash->is_customer_visible)->toBeFalse();
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
        'is_customer_visible' => false,
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
