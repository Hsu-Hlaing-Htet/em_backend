<?php

use App\Exceptions\ConcurrentConflictException;
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
use App\Services\PaymentService;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function payConcurrencyAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function payConcurrencyCustomer(): User
{
    return User::query()->where('email', 'hsuhtet562@gmail.com')->firstOrFail();
}

/**
 * @return array{invoice: Invoice, cash: PaymentMethod, wallet: PaymentMethod}
 */
function payConcurrencyIssuedInvoice(User $admin, User $customer, float $total): array
{
    $building = Building::query()->create([
        'building_name' => 'Pay Concurrency Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'PC-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 450000,
        'rent_deposit_price' => 900000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-PC-'.fake()->unique()->numerify('######'),
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
        'invoice_number' => 'INV-PC-'.fake()->unique()->numerify('######'),
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

    return [
        'invoice' => $invoice,
        'cash' => PaymentMethod::query()->where('type', PaymentMethod::TYPE_CASH)->firstOrFail(),
        'wallet' => PaymentMethod::query()->availableForCustomer()->orderBy('sort_order')->firstOrFail(),
    ];
}

function payConcurrencyAssertInvoiceConsistent(Invoice $invoice): void
{
    $invoice->refresh()->load('payments');
    $service = app(PaymentService::class);
    $balance = $service->invoiceCurrentBalance($invoice);
    $approved = $service->invoiceApprovedPaidAmount($invoice);
    $due = $service->invoiceTotalDue($invoice);

    expect($balance)->toBeGreaterThanOrEqual(0.0)
        ->and(round($approved + $balance, 2))->toBe(round($due, 2));

    if ($balance <= 0.009) {
        expect($invoice->status)->toBe('paid');
    } elseif ($approved > 0) {
        expect($invoice->status)->toBe('partial');
    }

    $approvedIds = $invoice->payments->where('status', Payment::STATUS_APPROVED)->pluck('id');
    foreach ($approvedIds as $paymentId) {
        expect(Receipt::query()->where('payment_id', $paymentId)->count())->toBe(1);
    }
}

test('two concurrent full admin payments: only one settles, loser gets 409 not 500', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash] = payConcurrencyIssuedInvoice($admin, $customer, 693681);

    Auth::login($admin);
    $service = app(PaymentService::class);

    $payload = [
        'invoice_id' => $invoice->id,
        'payment_method_id' => $cash->id,
        'amount' => 693681,
        'amount_received' => 700000,
        'note' => 'Full A',
    ];

    // Simulate lock serialization: both requests would have read the same pre-commit balance.
    $first = $service->create($payload);
    expect($first->status)->toBe(Payment::STATUS_PENDING);

    $secondError = null;
    try {
        $service->create([
            ...$payload,
            'note' => 'Full B',
        ]);
    } catch (ConcurrentConflictException $exception) {
        $secondError = $exception->getMessage();
    }

    expect($secondError)->toBe('This invoice already has a pending payment.');
    expect(Payment::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->count())->toBe(1);

    $approved = $service->approve($first);
    expect($approved->status)->toBe(Payment::STATUS_APPROVED);
    expect(Receipt::query()->where('payment_id', $first->id)->count())->toBe(1);

    $afterPaidError = null;
    try {
        $service->create($payload);
    } catch (ConcurrentConflictException $exception) {
        $afterPaidError = $exception->getMessage();
    }

    expect($afterPaidError)->toBe('Invoice has already been fully paid.');
    payConcurrencyAssertInvoiceConsistent($invoice->fresh());

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', $payload)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Invoice has already been fully paid.');
});

test('sequential partial payments validate against latest remaining balance under lock', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash] = payConcurrencyIssuedInvoice($admin, $customer, 693681);

    Auth::login($admin);
    $service = app(PaymentService::class);

    $first = $service->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $cash->id,
        'amount' => 100000,
        'amount_received' => 100000,
    ]);
    $service->approve($first);

    expect((float) $service->invoiceCurrentBalance($invoice->fresh()))->toBe(593681.0);

    $second = $service->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $cash->id,
        'amount' => 200000,
        'amount_received' => 200000,
    ]);
    expect((float) $second->amount)->toBe(200000.0);
    $service->approve($second);

    expect((float) $service->invoiceCurrentBalance($invoice->fresh()))->toBe(393681.0);
    payConcurrencyAssertInvoiceConsistent($invoice->fresh());
});

test('later partial that exceeds remaining balance is rejected without overpayment', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash] = payConcurrencyIssuedInvoice($admin, $customer, 300000);

    Auth::login($admin);
    $service = app(PaymentService::class);

    $first = $service->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $cash->id,
        'amount' => 200000,
        'amount_received' => 200000,
    ]);
    $service->approve($first);

    $error = null;
    try {
        $service->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 200000,
            'amount_received' => 200000,
        ]);
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }

    expect($error)->toBe('Paid amount cannot exceed the current balance.');
    expect(Payment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    expect((float) $service->invoiceCurrentBalance($invoice->fresh()))->toBe(100000.0);
    payConcurrencyAssertInvoiceConsistent($invoice->fresh());
});

test('customer and admin cash creates on same invoice serialize to one pending', function () {
    Storage::fake('public');
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash, 'wallet' => $wallet] = payConcurrencyIssuedInvoice($admin, $customer, 693681);

    $this->actingAs($customer, 'sanctum');
    $proof = UploadedFile::fake()->image('proof.jpg');

    $customerPayment = app(\App\Services\CustomerPortalService::class)->submitPayment($customer, [
        'invoice_id' => $invoice->id,
        'payment_method_id' => $wallet->id,
        'note' => 'Customer race',
    ], $proof);

    expect($customerPayment->status)->toBe(Payment::STATUS_PENDING);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 693681,
            'amount_received' => 700000,
            'note' => 'Admin race',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This invoice already has a pending payment.');

    expect(Payment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    expect((float) app(PaymentService::class)->invoiceCurrentBalance($invoice->fresh()))->toBe(693681.0);
});

test('two concurrent approve requests on same payment produce one receipt and one settlement', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'wallet' => $wallet] = payConcurrencyIssuedInvoice($admin, $customer, 50000);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $wallet->id,
        'amount' => null,
        'payment_date' => now()->toDateString(),
        'status' => Payment::STATUS_PENDING,
        'proof_image_path' => 'payment-proofs/race.jpg',
        'created_by' => $customer->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 50000])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 50000])
        ->assertStatus(409);

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);
    expect(Payment::query()->find($payment->id)?->status)->toBe(Payment::STATUS_APPROVED);
    payConcurrencyAssertInvoiceConsistent($invoice->fresh());
});

test('approving a second pending payment after full settlement returns controlled conflict', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'wallet' => $wallet] = payConcurrencyIssuedInvoice($admin, $customer, 50000);

    $first = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $wallet->id,
        'amount' => 50000,
        'payment_date' => now()->toDateString(),
        'status' => Payment::STATUS_PENDING,
        'proof_image_path' => 'payment-proofs/first.jpg',
        'created_by' => $customer->id,
    ]);

    // Residue that bypassed create (simulates race before pending gate existed).
    $second = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $wallet->id,
        'amount' => 50000,
        'payment_date' => now()->toDateString(),
        'status' => Payment::STATUS_PENDING,
        'proof_image_path' => 'payment-proofs/second.jpg',
        'created_by' => $customer->id,
    ]);

    Auth::login($admin);
    $service = app(PaymentService::class);
    $service->approve($first);

    $error = null;
    try {
        $service->approve($second);
    } catch (ConcurrentConflictException $exception) {
        $error = $exception->getMessage();
    }

    expect($error)->toBe('Invoice has already been fully paid.');
    expect(Receipt::query()->where('payment_id', $first->id)->count())->toBe(1);
    expect(Receipt::query()->where('payment_id', $second->id)->count())->toBe(0);
    expect(Payment::query()->find($second->id)?->status)->toBe(Payment::STATUS_PENDING);
    payConcurrencyAssertInvoiceConsistent($invoice->fresh());
});

test('rapid double-click style admin pay creates only one pending payment', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash] = payConcurrencyIssuedInvoice($admin, $customer, 100000);

    $payload = [
        'invoice_id' => $invoice->id,
        'payment_method_id' => $cash->id,
        'amount' => 100000,
        'amount_received' => 100000,
        'note' => 'Double click',
    ];

    $first = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', $payload)
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', $payload)
        ->assertStatus(409);

    expect(Payment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(Payment::query()->find($first)?->status)->toBe(Payment::STATUS_PENDING);
});

test('admin cash over-tender still succeeds under invoice lock', function () {
    $admin = payConcurrencyAdmin();
    $customer = payConcurrencyCustomer();
    ['invoice' => $invoice, 'cash' => $cash] = payConcurrencyIssuedInvoice($admin, $customer, 693681);

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 693681,
            'amount_received' => 700000,
        ])
        ->assertCreated()
        ->json('data');

    expect((float) $created['amount'])->toBe(693681.0)
        ->and((float) $created['amount_received'])->toBe(700000.0)
        ->and((float) $created['refund_amount'])->toBe(6319.0);
});
