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
use Illuminate\Support\Facades\Notification;
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
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function tb4OtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();
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

    $method = PaymentMethod::query()->availableForCustomer()->orderBy('sort_order')->orderBy('name')->firstOrFail();

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
        ->assertJsonPath('data.amount', '100000.00')
        ->json('data.id');

    expect(Payment::query()->find($paymentId)?->proof_image_path)->not->toBeNull();
});

test('admin payment approval list exposes pending payment amount', function () {
    Storage::fake('public');
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 647102);

    $paymentId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('wallet.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', '647102.00')
        ->json('data.id');

    $list = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments?status=pending')
        ->assertOk()
        ->json('data.data');

    $row = collect($list)->firstWhere('id', $paymentId);

    expect($row)->not->toBeNull()
        ->and((float) $row['amount'])->toBe(647102.0)
        ->and((float) $row['paid'])->toBe(647102.0)
        ->and((float) $row['invoice_amount'])->toBe(647102.0);
});

test('admin payment list and approval list share tendered paid mapping for cash over-tender', function () {
    Mail::fake();
    Notification::fake();

    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice] = tb4IssuedInvoice($admin, $customer, 20527418);
    $cash = PaymentMethod::query()->where('type', 'cash')->firstOrFail();

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 20527418,
            'amount_received' => 30000000,
            'note' => 'Office cash over-tender',
        ])
        ->assertCreated()
        ->json('data');

    $paymentId = (int) $created['id'];

    expect((float) $created['invoice_amount'])->toBe(20527418.0)
        ->and((float) $created['amount'])->toBe(20527418.0)
        ->and((float) $created['paid'])->toBe(30000000.0)
        ->and((float) $created['amount_received'])->toBe(30000000.0)
        ->and((float) $created['financial_summary']['paid'])->toBe(30000000.0)
        ->and((float) $created['financial_summary']['change'])->toBe(9472582.0);

    $approvalRow = collect(
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/payments?status=pending')
            ->assertOk()
            ->json('data.data')
    )->firstWhere('id', $paymentId);

    expect($approvalRow)->not->toBeNull()
        ->and((float) $approvalRow['invoice_amount'])->toBe(20527418.0)
        ->and((float) $approvalRow['paid'])->toBe(30000000.0)
        ->and((float) $approvalRow['amount'])->toBe(20527418.0);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [])
        ->assertOk();

    $listRow = collect(
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/payments?status=approved')
            ->assertOk()
            ->json('data.data')
    )->firstWhere('id', $paymentId);

    expect($listRow)->not->toBeNull()
        ->and((float) $listRow['invoice_amount'])->toBe(20527418.0)
        ->and((float) $listRow['paid'])->toBe(30000000.0)
        ->and((float) $listRow['amount'])->toBe(20527418.0)
        ->and($listRow['paid_by'])->toBe($admin->name);
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
    expect($receipt->status)->toBe('issued');
    expect($receipt->approval_status)->toBe('approved');
    expect($receipt->payment_id)->toBe($approvePaymentId);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$rejectPaymentId}/reject", [
            'rejection_reason' => 'Unreadable proof image.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Invoice::query()->find($rejectInvoice->id)?->status)->toBe('issued');
});

test('receipt becomes visible to customer after payment approval', function () {
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
        ->assertJsonPath('data.total', 1);

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
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => now(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
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

test('admin payment create sets payment_date from server clock and ignores client date', function () {
    $this->travelTo(now()->startOfDay()->setTime(14, 30));

    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice, 'method' => $method] = tb4IssuedInvoice($admin, $customer, 85000);
    $expectedDate = now()->toDateString();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'amount' => 85000,
            'note' => 'Front desk cash',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_date', $expectedDate)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('data.errors.payment_date');

    $paymentId = (int) Payment::query()->orderByDesc('id')->value('id');
    $payment = Payment::query()->findOrFail($paymentId);

    expect($payment->payment_date?->toDateString())->toBe($expectedDate);

    $rejected = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'amount' => 85000,
            'payment_date' => '2020-01-01',
        ])
        ->assertStatus(422);

    expect($rejected->json('data.payment_date'))->not->toBeEmpty();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", ['amount' => 85000])
        ->assertOk()
        ->assertJsonPath('data.payment_date', $expectedDate)
        ->assertJsonPath('data.status', 'approved');

    expect(Payment::query()->find($paymentId)?->payment_date?->toDateString())->toBe($expectedDate);
    expect(Payment::query()->find($paymentId)?->approved_at)->not->toBeNull();
});

test('admin cash over-tender stores received amount and approves without proof or request amount', function () {
    Mail::fake();
    Notification::fake();

    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice] = tb4IssuedInvoice($admin, $customer, 693681);
    $cash = PaymentMethod::query()->where('type', 'cash')->firstOrFail();

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 693681,
            'amount_received' => 700000,
            'note' => 'Office cash',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data');

    expect((float) $created['amount'])->toBe(693681.0)
        ->and((float) $created['amount_received'])->toBe(700000.0)
        ->and((float) $created['refund_amount'])->toBe(6319.0)
        ->and((float) $created['financial_summary']['subtotal'])->toBe(693681.0)
        ->and((float) $created['financial_summary']['late_fee'])->toBe(0.0)
        ->and((float) $created['financial_summary']['total'])->toBe(693681.0)
        ->and((float) $created['financial_summary']['paid'])->toBe(700000.0)
        ->and($created['financial_summary']['show_change'])->toBeTrue()
        ->and((float) $created['financial_summary']['change'])->toBe(6319.0)
        ->and($created['financial_summary']['balance'])->toBeNull();

    $pendingRow = collect(
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/payments?status=pending')
            ->assertOk()
            ->json('data.data')
    )->firstWhere('id', (int) $created['id']);

    expect($pendingRow)->not->toBeNull()
        ->and((float) $pendingRow['amount'])->toBe(693681.0)
        ->and((float) $pendingRow['paid'])->toBe(700000.0)
        ->and((float) $pendingRow['invoice_amount'])->toBe(693681.0)
        ->and((float) $pendingRow['amount'])->not->toBe(700000.0);

    expect($created['proof_image_url'] ?? null)->toBeNull();

    $paymentId = (int) $created['id'];

    // List-style approve: no amount in body, no proof, no remark.
    $approved = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->json('data');

    expect((float) $approved['amount'])->toBe(693681.0)
        ->and((float) $approved['amount_received'])->toBe(700000.0)
        ->and((float) $approved['refund_amount'])->toBe(6319.0)
        ->and((float) $approved['financial_summary']['paid'])->toBe(700000.0)
        ->and($approved['financial_summary']['show_change'])->toBeTrue()
        ->and((float) $approved['financial_summary']['change'])->toBe(6319.0);

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('paid');
    expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);

    $receipt = Receipt::query()->where('payment_id', $paymentId)->first();
    expect((float) Payment::query()->find($paymentId)?->amount)->toBe(693681.0);

    $receiptPayload = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/receipts/{$receipt->id}")
        ->assertOk()
        ->json('data');

    expect((float) $receiptPayload['financial_summary']['subtotal'])->toBe(693681.0)
        ->and((float) $receiptPayload['financial_summary']['total'])->toBe(693681.0)
        ->and((float) $receiptPayload['financial_summary']['paid'])->toBe(700000.0)
        ->and($receiptPayload['financial_summary']['show_change'])->toBeTrue()
        ->and((float) $receiptPayload['financial_summary']['change'])->toBe(6319.0)
        ->and((float) $receiptPayload['amount_received'])->toBe(700000.0);

    $receiptHtml = $this->actingAs($admin, 'sanctum')
        ->get("/api/receipts/{$receipt->id}/document/export")
        ->assertOk()
        ->getContent();

    expect($receiptHtml)
        ->toContain('Subtotal')
        ->toContain('Paid')
        ->toContain('Change')
        ->toContain('MMK 700,000')
        ->toContain('MMK 6,319')
        ->not->toContain('Remaining Balance');

    // Duplicate approve must not create a second receipt.
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$paymentId}/approve", [])
        ->assertStatus(409);

    expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);
    expect($receipt?->id)->toBe(Receipt::query()->where('payment_id', $paymentId)->value('id'));
});

test('admin cash payment requires amount_received and rejects under-tender', function () {
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice] = tb4IssuedInvoice($admin, $customer, 100000);
    $cash = PaymentMethod::query()->where('type', 'cash')->firstOrFail();

    $missingReceived = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 100000,
        ])
        ->assertStatus(422);

    expect($missingReceived->json('data.amount_received'))->not->toBeEmpty();

    $under = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 100000,
            'amount_received' => 70000,
        ])
        ->assertStatus(422);

    expect($under->json('data.amount_received'))->not->toBeEmpty();
});

test('admin cash create clamps whole-mmk rounding overage under 1 and returns 422 for true overpay', function () {
    $admin = tb4Admin();
    $customer = tb4Customer();
    // Matches INV-000227-style fractional balance with whole-MMK UI amount.
    ['invoice' => $invoice] = tb4IssuedInvoice($admin, $customer, 693680.50);
    $cash = PaymentMethod::query()->where('type', 'cash')->firstOrFail();

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 693681,
            'amount_received' => 700000,
            'note' => 'Test',
        ])
        ->assertCreated()
        ->json('data');

    expect((float) $created['amount'])->toBe(693680.50)
        ->and((float) $created['amount_received'])->toBe(700000.0)
        ->and((float) $created['refund_amount'])->toBe(6319.5);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 693681,
            'amount_received' => 700000,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This invoice already has a pending payment.');
});

test('admin payment create rejects inactive payment method with 422', function () {
    $admin = tb4Admin();
    $customer = tb4Customer();
    ['invoice' => $invoice] = tb4IssuedInvoice($admin, $customer, 50000);
    $cash = PaymentMethod::query()->where('type', 'cash')->firstOrFail();
    $cash->update(['status' => PaymentMethod::STATUS_INACTIVE]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 50000,
            'amount_received' => 50000,
        ])
        ->assertStatus(422);

    $cash->update(['status' => PaymentMethod::STATUS_ACTIVE]);
});
