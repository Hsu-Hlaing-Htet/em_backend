<?php

use App\Mail\ReceiptDocumentMail;
use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function tb4Admin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function tb4Customer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function tb4OtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail();
}

function tb4IssuedInvoice(User $admin, User $customer, float $total = 100000): array
{
    $building = Building::query()->create([
        'building_name' => 'TB4 Payment Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'TB4-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 4,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 450000,
        'rent_deposit_price' => 900000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-TB4-'.fake()->unique()->numerify('######'),
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
        'invoice_number' => 'INV-TB4-'.fake()->unique()->numerify('######'),
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => $total,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => ChargeType::query()->where('slug', 'monthly-rent')->value('id'),
        'description' => 'Monthly rent',
        'amount' => $total,
    ]);

    $method = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    return compact('building', 'room', 'contract', 'invoice', 'method');
}

test('customer can submit payment with proof and invalid proof is rejected', function () {
    Storage::fake('public');
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'note' => 'Bank transfer',
            'proof' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['proof']);

    $paymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'note' => 'Bank transfer',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', null)
        ->json('data.id');

    expect(Payment::query()->find($paymentId)?->proof_image_path)->not->toBeNull();
});

test('admin can retrieve pending payments then approve or reject', function () {
    Storage::fake('public');
    Mail::fake();
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $approveInvoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 100000);
    ['invoice' => $rejectInvoice] = tb4IssuedInvoice($admin, $customer, 80000);

    $approvePaymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $approveInvoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('approve.jpg'),
        ])
        ->assertCreated()
        ->json('data.id');

    $rejectPaymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $rejectInvoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('reject.jpg'),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments?status=pending')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$approvePaymentId}/approve", [
            'amount' => 100000,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.amount', '100000.00')
        ->assertJsonPath('data.receipt_id', fn ($value) => $value !== null);

    expect(Invoice::query()->find($approveInvoice->id)?->status)->toBe('paid');

    $receipt = Receipt::query()->where('payment_id', $approvePaymentId)->first();
    expect($receipt)->not->toBeNull();
    expect($receipt->status)->toBe('draft');
    expect($receipt->approval_status)->toBe('pending');
    expect($receipt->payment_id)->toBe($approvePaymentId);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$rejectPaymentId}/reject", [
            'rejection_reason' => 'Unreadable proof image.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Invoice::query()->find($rejectInvoice->id)?->status)->toBe('issued');
});

test('receipt becomes visible to customer only after delivery email', function () {
    Storage::fake('public');
    Mail::fake();
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 120000);

    $paymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('paid.jpg'),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", ['amount' => 120000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $paymentId)->firstOrFail();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    Mail::assertSent(ReceiptDocumentMail::class);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $receipt->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $receipt->id);
});

test('customer can access own payment data and unauthorized approval is rejected', function () {
    Storage::fake('public');
    $admin = tb4Admin();
    $customer = tb4Customer();
    $other = tb4OtherCustomer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 90000);

    $paymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('own.jpg'),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payments')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $paymentId);

    $this->actingAs($other, 'sanctum')
        ->getJson('/api/customer/payments')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", ['amount' => 90000])
        ->assertForbidden();

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", ['amount' => 90000])
        ->assertForbidden();
});

test('admin payment proof upload endpoint accepts valid files', function () {
    Storage::fake('public');
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 50000);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $admin->id,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'amount' => null,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->post("/api/payments/{$payment->id}/proof", [
            'proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    expect(Payment::query()->find($payment->id)?->proof_image_path)->not->toBeNull();
});

test('timebox 4 relationships and payment method seeder idempotency', function () {
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 70000);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'amount' => 70000,
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCP-TB4-0001',
        'status' => 'draft',
        'approval_status' => 'pending',
        'created_by' => $admin->id,
    ]);

    expect($payment->invoice->id)->toBe($invoice->id);
    expect($payment->paymentMethod->id)->toBe($method->id);
    expect($payment->receipt->id)->toBe($receipt->id);
    expect($receipt->payment->id)->toBe($payment->id);
    expect($invoice->payments)->toHaveCount(1);

    $methodCount = PaymentMethod::query()->count();
    (new PaymentMethodSeeder)->run();
    expect(PaymentMethod::query()->count())->toBe($methodCount);
});
