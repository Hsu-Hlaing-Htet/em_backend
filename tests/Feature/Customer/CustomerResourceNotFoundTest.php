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
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customerNotFoundUsers(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return [
        'admin' => User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        'customer' => User::query()->where('email', 'mgmg@gmail.com')->firstOrFail(),
        'other' => User::query()->where('email', 'hlahla@gmail.com')->firstOrFail(),
        'joint' => User::query()->where('email', 'ko@gmail.com')->firstOrFail(),
    ];
}

function customerNotFoundContract(User $owner, User $admin, ?User $second = null): Contract
{
    $building = Building::query()->create([
        'building_name' => 'Not Found Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'NF-'.fake()->unique()->numerify('###'),
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 400000,
        'booking_deposit_price' => 0,
    ]);

    return Contract::query()->create([
        'contract_number' => 'R-NF-'.fake()->unique()->numerify('######'),
        'user_id' => $owner->id,
        'second_user_id' => $second?->id,
        'room_id' => $room->id,
        'contract_total' => 1200000,
        'deposit_amount' => 400000,
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

function assertSafeCustomerNotFound($response, string $message): void
{
    $response->assertNotFound()
        ->assertJsonPath('message', $message)
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('file')
        ->assertJsonMissingPath('trace');

    $body = $response->json('message') ?? '';
    expect($body)->not->toContain('App\\Models\\')
        ->and($body)->not->toContain('No query results for model');
}

it('returns a safe 404 for a missing invoice and another customers invoice', function (): void {
    ['admin' => $admin, 'customer' => $customer, 'other' => $other] = customerNotFoundUsers();
    $ownContract = customerNotFoundContract($customer, $admin);
    $otherContract = customerNotFoundContract($other, $admin);

    $ownInvoice = Invoice::query()->create([
        'contract_id' => $ownContract->id,
        'invoice_number' => 'INV-NF-OWN',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $otherInvoice = Invoice::query()->create([
        'contract_id' => $otherContract->id,
        'invoice_number' => 'INV-NF-OTHER',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$ownInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $ownInvoice->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$ownInvoice->id}/document/preview")
        ->assertOk();

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson('/api/customer/invoices/999999'),
        'Invoice not found.',
    );

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/invoices/{$otherInvoice->id}"),
        'Invoice not found.',
    );

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/invoices/{$otherInvoice->id}/document/preview"),
        'Invoice not found.',
    );
});

it('keeps joint second-party invoice access while blocking unrelated customers', function (): void {
    ['admin' => $admin, 'customer' => $customer, 'other' => $other, 'joint' => $joint] = customerNotFoundUsers();
    $contract = customerNotFoundContract($customer, $admin, $joint);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-NF-JOINT',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);

    $this->actingAs($joint, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);

    assertSafeCustomerNotFound(
        $this->actingAs($other, 'sanctum')->getJson("/api/customer/invoices/{$invoice->id}"),
        'Invoice not found.',
    );
});

it('returns safe 404 copy for payments receipts contracts and maintenance requests', function (): void {
    ['admin' => $admin, 'customer' => $customer, 'other' => $other] = customerNotFoundUsers();
    $ownContract = customerNotFoundContract($customer, $admin);
    $otherContract = customerNotFoundContract($other, $admin);

    $ownInvoice = Invoice::query()->create([
        'contract_id' => $ownContract->id,
        'invoice_number' => 'INV-NF-PAY',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $otherInvoice = Invoice::query()->create([
        'contract_id' => $otherContract->id,
        'invoice_number' => 'INV-NF-PAY-OTHER',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $method = PaymentMethod::query()->create([
        'name' => 'Not Found Transfer',
        'slug' => 'nf-transfer-'.fake()->unique()->numerify('###'),
        'type' => 'bank_transfer',
        'status' => 'active',
        'is_customer_visible' => true,
    ]);

    $ownPayment = Payment::query()->create([
        'invoice_id' => $ownInvoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'amount_received' => 400000,
        'proof_image_path' => 'payments/nf-own.jpg',
    ]);

    $otherPayment = Payment::query()->create([
        'invoice_id' => $otherInvoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $other->id,
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'amount_received' => 400000,
        'proof_image_path' => 'payments/nf-other.jpg',
    ]);

    $ownReceipt = Receipt::query()->create([
        'payment_id' => $ownPayment->id,
        'receipt_number' => 'RCP-NF-OWN',
        'status' => 'issued',
        'issued_at' => now(),
        'created_by' => $admin->id,
    ]);

    $otherReceipt = Receipt::query()->create([
        'payment_id' => $otherPayment->id,
        'receipt_number' => 'RCP-NF-OTHER',
        'status' => 'issued',
        'issued_at' => now(),
        'created_by' => $admin->id,
    ]);

    $ownRequest = MaintenanceRequest::query()->create([
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'room_id' => $ownContract->room_id,
        'title' => 'Own request',
        'category' => 'plumbing',
        'priority' => 'medium',
        'description' => 'Leak',
        'status' => 'pending',
    ]);

    $otherRequest = MaintenanceRequest::query()->create([
        'user_id' => $other->id,
        'created_by' => $other->id,
        'room_id' => $otherContract->room_id,
        'title' => 'Other request',
        'category' => 'hvac',
        'priority' => 'high',
        'description' => 'Noise',
        'status' => 'pending',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/payments/{$ownPayment->id}")
        ->assertOk();

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/payments/{$otherPayment->id}"),
        'Payment not found.',
    );

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/receipts/{$ownReceipt->id}")
        ->assertOk();

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/receipts/{$otherReceipt->id}"),
        'Receipt not found.',
    );

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/contracts/{$ownContract->id}")
        ->assertOk();

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/contracts/{$otherContract->id}"),
        'Contract not found.',
    );

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$ownRequest->id}")
        ->assertOk();

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson("/api/customer/maintenance-requests/{$otherRequest->id}"),
        'Request not found.',
    );

    assertSafeCustomerNotFound(
        $this->actingAs($customer, 'sanctum')->getJson('/api/customer/contracts/999999'),
        'Contract not found.',
    );
});
