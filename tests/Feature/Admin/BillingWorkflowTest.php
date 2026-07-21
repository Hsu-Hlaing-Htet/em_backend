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

function seedWorkflowPropertyStack(): array
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
    ['room' => $room] = seedWorkflowPropertyStack();

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

function seedDraftInvoice(Contract $contract, User $admin): Invoice
{
    $rentCharge = ChargeType::query()->where('slug', 'monthly-rent')->firstOrFail();

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-TEST-0001',
        'type' => 'rent',
        'status' => 'draft',
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 100000,
        'created_by' => $admin->id,
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $rentCharge->id,
        'description' => 'Monthly rent',
        'amount' => 100000,
    ]);

    return $invoice->fresh(['contract.user', 'items']);
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

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'note' => 'Bank transfer',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    $paymentId = Payment::query()->value('id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.receipt_id', fn ($value) => $value !== null);

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('paid');

    $receiptId = Receipt::query()->value('id');
    expect(Receipt::query()->find($receiptId)?->status)->toBe('draft');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receiptId}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    Mail::assertSent(ReceiptDocumentMail::class);

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

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated();

    $paymentId = Payment::query()->value('id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve")
        ->assertOk();

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('partial');

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof-2.jpg'),
        ])
        ->assertCreated();

    $secondPaymentId = Payment::query()->orderByDesc('id')->value('id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$secondPaymentId}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('partial');
    expect(Receipt::query()->count())->toBe(1);
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

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated();

    $paymentId = Payment::query()->value('id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve")
        ->assertOk();

    $receiptId = Receipt::query()->value('id');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$receiptId}")
        ->assertNotFound();
});
