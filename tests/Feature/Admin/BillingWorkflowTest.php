<?php

use App\Mail\InvoiceDocumentMail;
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

function billingAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function billingCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function seedPropertyStack(): array
{
    $building = \App\Models\Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '8A',
        'floor_number' => 8,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 1200,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 50000,
        'booking_deposit_price' => 10000,
    ]);

    $customer = billingCustomer();

    return compact('building', 'room', 'customer');
}

function seedBillingContract(User $admin, User $customer): Contract
{
    ['room' => $room] = seedPropertyStack();

    return Contract::query()->create([
        'contract_number' => 'CTR-BILL-0001',
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
}

function seedDraftInvoice(Contract $contract, User $admin, float $total = 100000): Invoice
{
    $rentCharge = ChargeType::query()->where('slug', 'monthly-rent')->firstOrFail();

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-TEST-'.str_pad((string) (Invoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'type' => 'rent',
        'status' => 'draft',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => $total,
        'created_by' => $admin->id,
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $rentCharge->id,
        'description' => 'Monthly rent',
        'amount' => $total,
    ]);

    return $invoice->fresh(['contract.user', 'items']);
}

function submitCustomerPayment(mixed $test, User $customer, Invoice $invoice, PaymentMethod $paymentMethod): int
{
    $test->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_date' => now()->toDateString(),
            'note' => 'Bank transfer',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', null);

    return (int) Payment::query()->orderByDesc('id')->value('id');
}

test('invoice payment receipt workflow completes end to end', function () {
    Mail::fake();

    $admin = billingAdmin();
    $customer = billingCustomer();
    $contract = seedBillingContract($admin, $customer);
    $invoice = seedDraftInvoice($contract, $admin);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.approved_by.id', $admin->id)
        ->assertJsonPath('data.approved_by.name', $admin->name)
        ->assertJsonPath('data.approved_at', fn ($value) => ! empty($value));

    Mail::assertSent(InvoiceDocumentMail::class);

    $paymentId = submitCustomerPayment($this, $customer, $invoice, $paymentMethod);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments?status=pending')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $paymentId);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [
            'amount' => 100000,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.amount', '100000.00')
        ->assertJsonPath('data.receipt_id', fn ($value) => $value !== null);

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('paid');
    expect(Payment::query()->find($paymentId)?->proof_image_path)->not->toBeNull();
    Mail::assertNotSent(ReceiptDocumentMail::class);

    $receipt = Receipt::query()->first();
    expect($receipt)->not->toBeNull();
    expect($receipt->status)->toBe('draft');
    expect($receipt->approval_status)->toBe('pending');
    expect($receipt->issued_at)->toBeNull();
    expect($receipt->approved_at)->toBeNull();
    expect($receipt->payment_id)->toBe($paymentId);
    expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments?status=pending')
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?approval_status=pending')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $receipt->id);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/issue")
        ->assertStatus(422);

    Mail::assertNotSent(ReceiptDocumentMail::class);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertStatus(422);

    // Re-approve must be rejected and must not create a second receipt.
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [
            'amount' => 100000,
        ])
        ->assertStatus(422);

    expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);
    Mail::assertNotSent(ReceiptDocumentMail::class);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.approved_by.id', $admin->id)
        ->assertJsonPath('data.approved_at', fn ($value) => ! empty($value));

    Mail::assertNotSent(ReceiptDocumentMail::class);

    $approved = Receipt::query()->find($receipt->id);
    expect($approved?->status)->toBe('draft');
    expect($approved?->approval_status)->toBe('approved');
    expect($approved?->issued_at)->toBeNull();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.issued_at', fn ($value) => ! empty($value));

    Mail::assertNotSent(ReceiptDocumentMail::class);

    $issued = Receipt::query()->find($receipt->id);
    expect($issued?->status)->toBe('issued');
    expect($issued?->approval_status)->toBe('approved');
    expect($issued?->issued_at)->not->toBeNull();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    Mail::assertSent(ReceiptDocumentMail::class, function (ReceiptDocumentMail $mail) use ($customer) {
        return $mail->hasTo($customer->email);
    });

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $customer->email,
        ])
        ->assertOk();

    expect(Receipt::query()->find($receipt->id)?->approval_status)->toBe('approved');
    expect(Mail::sent(ReceiptDocumentMail::class)->count())->toBe(2);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonFragment(['type' => 'receipt']);
});

test('rejecting payment keeps invoice payment status synchronized', function () {
    Mail::fake();

    $admin = billingAdmin();
    $customer = billingCustomer();
    $contract = seedBillingContract($admin, $customer);
    $invoice = seedDraftInvoice($contract, $admin);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk();

    $paymentId = submitCustomerPayment($this, $customer, $invoice, $paymentMethod);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [
            'amount' => 50000,
        ])
        ->assertOk();

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('partial');

    $secondPaymentId = submitCustomerPayment($this, $customer, $invoice->fresh(), $paymentMethod);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$secondPaymentId}/reject", [
            'rejection_reason' => 'Proof is unclear.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Proof is unclear.');

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('partial');
    expect(Payment::query()->find($secondPaymentId)?->amount)->toBeNull();
    expect(Receipt::query()->count())->toBe(1);
});

test('payment approval rejects overpayment and customer cannot submit amount', function () {
    $admin = billingAdmin();
    $customer = billingCustomer();
    $contract = seedBillingContract($admin, $customer);
    $invoice = seedDraftInvoice($contract, $admin);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertStatus(422);

    $paymentId = submitCustomerPayment($this, $customer, $invoice, $paymentMethod);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [
            'amount' => 150000,
        ])
        ->assertStatus(422);

    expect(Payment::query()->find($paymentId)?->status)->toBe('pending');
    expect(Invoice::query()->find($invoice->id)?->status)->toBe('issued');
    expect(Receipt::query()->count())->toBe(0);
});

test('customer cannot view draft receipts', function () {
    $admin = billingAdmin();
    $customer = billingCustomer();
    $contract = seedBillingContract($admin, $customer);
    $invoice = seedDraftInvoice($contract, $admin);
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk();

    $paymentId = submitCustomerPayment($this, $customer, $invoice, $paymentMethod);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [
            'amount' => 100000,
        ])
        ->assertOk();

    $receiptId = Receipt::query()->value('id');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receiptId}")
        ->assertNotFound();
});
