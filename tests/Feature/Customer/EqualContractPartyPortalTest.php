<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Notifications\PaymentApprovedNotification;
use App\Services\CustomerRentAssistantContextService;
use Database\Seeders\MaintenanceCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function equalPartyUsers(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new MaintenanceCategorySeeder)->run();

    return [
        'admin' => User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        'customer1' => User::query()->where('email', 'mgmg@gmail.com')->firstOrFail(),
        'customer2' => User::query()->where('email', 'hlahla@gmail.com')->firstOrFail(),
        'customer3' => User::query()->where('email', 'ko@gmail.com')->firstOrFail(),
    ];
}

function equalPartySharedContract(User $customer1, User $customer2, User $admin, ?User $onlyCustomer = null): Contract
{
    $building = Building::query()->create([
        'building_name' => 'Equal Party Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'EP-201',
        'floor_number' => 2,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 950,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 500000,
        'booking_deposit_price' => 0,
    ]);

    return Contract::query()->create([
        'contract_number' => 'R-EQ-'.fake()->unique()->numerify('######'),
        'user_id' => ($onlyCustomer ?? $customer1)->id,
        'second_user_id' => $onlyCustomer ? null : $customer2->id,
        'room_id' => $room->id,
        'contract_total' => 1500000,
        'deposit_amount' => 500000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 3,
        'status' => Contract::STATUS_ACTIVE,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);
}

function equalPartyInvoice(Contract $contract, User $admin, string $number = 'INV-EQ-001', float $amount = 500000): Invoice
{
    return Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => $number,
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => $amount,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);
}

function equalPartyPaymentMethod(): PaymentMethod
{
    return PaymentMethod::query()->create([
        'name' => 'Equal Party Transfer',
        'slug' => 'equal-party-'.fake()->unique()->numerify('###'),
        'type' => 'bank_transfer',
        'status' => 'active',
        'is_customer_visible' => true,
    ]);
}

it('gives equal contract access to both parties and blocks unrelated customers', function (): void {
    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2, 'customer3' => $c3] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $contract->id)
        ->assertJsonPath('data.second_user_id', $c2->id)
        ->assertJsonPath('data.second_customer.id', $c2->id);

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $contract->id)
        ->assertJsonPath('data.customer.id', $c1->id);

    $this->actingAs($c3, 'sanctum')
        ->getJson("/api/customer/contracts/{$contract->id}")
        ->assertNotFound();
});

it('shows the same shared invoice to both parties and hides it from others', function (): void {
    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2, 'customer3' => $c3] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);
    $invoice = equalPartyInvoice($contract, $admin);

    expect(Invoice::query()->where('contract_id', $contract->id)->count())->toBe(1);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', 'INV-EQ-001')
        ->assertJsonPath('data.total_amount', '500000.00');

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', 'INV-EQ-001')
        ->assertJsonPath('data.total_amount', '500000.00');

    $this->actingAs($c3, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertNotFound();

    $this->actingAs($c1, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($c2, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

it('lets either party submit payment, records the submitter, and shares approved state', function (): void {
    Storage::fake('public');
    Notification::fake();

    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);
    $invoice = equalPartyInvoice($contract, $admin);
    $method = equalPartyPaymentMethod();

    $this->actingAs($c2, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'note' => 'Paid by customer 2',
            'proof' => UploadedFile::fake()->image('proof-c2.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.created_by', $c2->id)
        ->assertJsonPath('data.submitted_by_user_id', $c2->id);

    $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($payment->created_by)->toBe($c2->id);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id)
        ->assertJsonPath('data.submitted_by_user_id', $c2->id);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", [
            'amount' => 500000,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.submitted_by_user_id', $c2->id)
        ->assertJsonPath('data.submitted_by_name', $c2->name);

    expect(Invoice::query()->find($invoice->id)?->status)->toBe('paid');

    Notification::assertSentTo($c1, PaymentApprovedNotification::class);
    Notification::assertSentTo($c2, PaymentApprovedNotification::class);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $this->actingAs($c1, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof-again.jpg'),
        ])
        ->assertStatus(422);
});

it('lets customer 1 also submit payment on a shared invoice', function (): void {
    Storage::fake('public');

    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);
    $invoice = equalPartyInvoice($contract, $admin, 'INV-EQ-C1', 250000);
    $method = equalPartyPaymentMethod();

    $this->actingAs($c1, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof-c1.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.created_by', $c1->id)
        ->assertJsonPath('data.submitted_by_user_id', $c1->id);
});

it('shares receipts with both parties after approval', function (): void {
    Storage::fake('public');
    Notification::fake();

    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2, 'customer3' => $c3] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);
    $invoice = equalPartyInvoice($contract, $admin, 'INV-EQ-RCP');
    $method = equalPartyPaymentMethod();

    $this->actingAs($c2, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof-rcp.jpg'),
        ])
        ->assertCreated();

    $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 500000])
        ->assertOk();

    $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();
    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $receipt->id);

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $receipt->id);

    $this->actingAs($c3, 'sanctum')
        ->getJson("/api/customer/receipts/{$receipt->id}")
        ->assertNotFound();
});

it('shares utility-linked invoices and maintenance rooms for both parties', function (): void {
    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2, 'customer3' => $c3] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin);

    $utility = Utility::query()->create([
        'room_id' => $contract->room_id,
        'contract_id' => $contract->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'reading_date' => now()->toDateString(),
        'total_amount' => 75000,
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utilityInvoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'utility_id' => $utility->id,
        'invoice_number' => 'INV-EQ-UTIL',
        'type' => 'utility',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'total_amount' => 75000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/invoices/{$utilityInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.type', 'utility');

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/invoices/{$utilityInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.type', 'utility');

    $this->actingAs($c3, 'sanctum')
        ->getJson("/api/customer/invoices/{$utilityInvoice->id}")
        ->assertNotFound();

    $this->actingAs($c2, 'sanctum')
        ->getJson('/api/customer/maintenance-rooms')
        ->assertOk()
        ->assertJsonFragment(['id' => $contract->room_id]);

    $this->actingAs($c2, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $contract->room_id,
            'title' => 'Shared room AC issue',
            'category' => 'electrical',
            'priority' => 'medium',
            'description' => 'AC not cooling in shared unit.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $c2->id)
        ->assertJsonPath('data.room_id', $contract->room_id);

    $requestId = MaintenanceRequest::query()->where('user_id', $c2->id)->value('id');

    $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$requestId}")
        ->assertOk()
        ->assertJsonPath('data.id', $requestId);

    $this->actingAs($c3, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$requestId}")
        ->assertNotFound();
});

it('builds AI context for customer 2 and excludes unrelated customers', function (): void {
    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2, 'customer3' => $c3] = equalPartyUsers();
    $shared = equalPartySharedContract($c1, $c2, $admin);
    equalPartyInvoice($shared, $admin, 'INV-EQ-AI');
    $other = equalPartySharedContract($c3, $c3, $admin, $c3);
    equalPartyInvoice($other, $admin, 'INV-OTHER-AI', 100000);

    $service = app(CustomerRentAssistantContextService::class);
    $request = Request::create('/api/customer/ai/rent/ask', 'POST');

    $context2 = $service->build($c2, $request);
    $contractIds = collect($context2['contracts'])->pluck('id')->all();
    $invoiceNumbers = collect($context2['invoices'])->pluck('invoice_number')->all();

    expect($contractIds)->toContain($shared->id);
    expect($contractIds)->not->toContain($other->id);
    expect($invoiceNumbers)->toContain('INV-EQ-AI');
    expect($invoiceNumbers)->not->toContain('INV-OTHER-AI');

    $context3 = $service->build($c3, $request);
    $contractIds3 = collect($context3['contracts'])->pluck('id')->all();
    $invoiceNumbers3 = collect($context3['invoices'])->pluck('invoice_number')->all();

    expect($contractIds3)->toContain($other->id);
    expect($contractIds3)->not->toContain($shared->id);
    expect($invoiceNumbers3)->not->toContain('INV-EQ-AI');
});

it('keeps one-customer contracts fully compatible', function (): void {
    ['admin' => $admin, 'customer1' => $c1, 'customer2' => $c2] = equalPartyUsers();
    $contract = equalPartySharedContract($c1, $c2, $admin, $c1);
    $invoice = equalPartyInvoice($contract, $admin, 'INV-SINGLE');

    expect($contract->second_user_id)->toBeNull();

    $response = $this->actingAs($c1, 'sanctum')
        ->getJson("/api/customer/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.second_user_id', null);

    expect($response->json('data.second_customer'))->toBeNull();

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/contracts/{$contract->id}")
        ->assertNotFound();

    $this->actingAs($c2, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertNotFound();
});
