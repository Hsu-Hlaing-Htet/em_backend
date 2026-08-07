<?php

use App\Mail\ReceiptDocumentMail;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function receiptDeliveryAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function receiptDeliveryCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function seedReceiptDeliveryPayment(User $admin, User $customer): array
{
    $building = \App\Models\Building::query()->create([
        'building_name' => 'Delivery Tower',
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'DLV-101',
        'floor_number' => 10,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 450000,
        'rent_deposit_price' => 900000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-DLV-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 5400000,
        'deposit_amount' => 900000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-DLV-0001',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 450000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => ChargeType::query()->where('slug', 'monthly-rent')->value('id'),
        'description' => 'Monthly rent',
        'amount' => 450000,
    ]);

    $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'amount' => null,
        'proof_image_path' => 'payments/delivery-proof.jpg',
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'note' => 'Delivery workflow payment',
    ]);

    return compact('invoice', 'payment', 'method', 'customer');
}

test('approved payment creates exactly one pending draft receipt', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", [
            'amount' => 450000,
        ])
        ->assertOk()
        ->assertJsonPath('data.receipt_id', fn ($value) => $value !== null);

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);

    $receipt = Receipt::query()->where('payment_id', $payment->id)->first();
    expect($receipt?->status)->toBe('draft');
    expect($receipt?->approval_status)->toBe('pending');
    expect($receipt?->sent_at)->toBeNull();
});

test('draft and approved-but-unsent receipts are hidden from customer portal', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertNotFound();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.can_send_email', true);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertNotFound();
});

test('successful email send makes receipt visible to customer', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_sent', true)
        ->assertJsonPath('data.sent_by', $admin->id);

    Mail::assertSent(ReceiptDocumentMail::class);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $receipt->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertOk()
        ->assertJsonPath('data.receipt_number', $receipt->receipt_number);
});

test('failed email send keeps receipt hidden and allows retry', function () {
    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk();

    $this->mock(\App\Services\ReceiptDocumentService::class, function ($mock): void {
        $mock->makePartial();
        $mock->shouldReceive('sendEmailToRecipient')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));
    });

    $this->app->forgetInstance(\App\Services\ReceiptService::class);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'SMTP unavailable'));

    $fresh = Receipt::query()->find($receipt->id);
    expect($fresh?->sent_at)->toBeNull();
    expect($fresh?->status)->toBe('draft');

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    Mail::fake();
    Mockery::close();
    $this->app->forgetInstance(\App\Services\ReceiptDocumentService::class);
    $this->app->forgetInstance(\App\Services\ReceiptService::class);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    Mail::assertSent(ReceiptDocumentMail::class);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('unapproved receipt cannot be sent by email', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertStatus(422);

    Mail::assertNothingSent();
});

test('repeated send returns conflict without duplicate receipts', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertStatus(409);

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);
    expect(Mail::sent(ReceiptDocumentMail::class)->count())->toBe(1);
});

test('another customer cannot access a sent receipt', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    $otherCustomer = User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    $this->actingAs($otherCustomer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertNotFound();

    $this->actingAs($otherCustomer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

test('receipt cannot be sent to a non-customer email address', function () {
    Mail::fake();

    $admin = receiptDeliveryAdmin();
    $customer = receiptDeliveryCustomer();
    ['payment' => $payment] = seedReceiptDeliveryPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => 'wrong@example.com',
        ])
        ->assertStatus(422);

    Mail::assertNothingSent();
});
