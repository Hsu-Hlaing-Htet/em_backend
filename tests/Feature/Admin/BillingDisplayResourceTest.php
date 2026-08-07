<?php

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

uses(RefreshDatabase::class);

function billingDisplayAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedBillingDisplayFixture(User $admin): array
{
    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    $building = \App\Models\Building::query()->create([
        'building_name' => 'Display Audit Tower',
        'location' => 'Kamayut Township, Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'DA-501',
        'floor_number' => 5,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 1000,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 1000000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-DISP-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 6000000,
        'deposit_amount' => 1000000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 5,
        'status' => 'active',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-DISP-0001',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 500000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => ChargeType::query()->where('slug', 'monthly-rent')->value('id'),
        'description' => 'Monthly rent',
        'amount' => 500000,
    ]);

    $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'amount' => 200000,
        'proof_image_path' => 'payments/disp.jpg',
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'note' => 'Display fixture payment',
    ]);

    $invoice->update(['status' => 'partial']);

    return compact('customer', 'building', 'room', 'contract', 'invoice', 'payment', 'method');
}

function seedBillingDisplayReceipt(User $admin): array
{
    $fixture = seedBillingDisplayFixture($admin);

    $receipt = Receipt::query()->create([
        'payment_id' => $fixture['payment']->id,
        'receipt_number' => 'RCP-DISP-0001',
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => now(),
        'sent_at' => now(),
        'sent_by' => $admin->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    return [...$fixture, 'receipt' => $receipt];
}

test('payment show includes invoice customer method and property fields', function () {
    $admin = billingDisplayAdmin();
    ['payment' => $payment, 'customer' => $customer, 'method' => $method] = seedBillingDisplayFixture($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', 'INV-DISP-0001')
        ->assertJsonPath('data.customer_name', $customer->name)
        ->assertJsonPath('data.payment_method_name', $method->name)
        ->assertJsonPath('data.building_name', 'Display Audit Tower')
        ->assertJsonPath('data.room_number', 'DA-501')
        ->assertJsonPath('data.property_unit', 'Display Audit Tower · DA-501')
        ->assertJsonPath('data.amount', '200000.00')
        ->assertJsonPath('data.payment_type', 'rent');
});

test('payment list includes nested display fields for each row', function () {
    $admin = billingDisplayAdmin();
    seedBillingDisplayFixture($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payments?per_page=10')
        ->assertOk()
        ->assertJsonFragment([
            'invoice_number' => 'INV-DISP-0001',
            'customer_name' => 'Mg Mg',
            'payment_method_name' => 'Cash',
            'building_name' => 'Display Audit Tower',
            'room_number' => 'DA-501',
        ]);
});

test('invoice show includes customer contract room and line items', function () {
    $admin = billingDisplayAdmin();
    ['invoice' => $invoice, 'customer' => $customer] = seedBillingDisplayFixture($admin);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/invoices/{$invoice->id}")
        ->assertOk();

    $response
        ->assertJsonPath('data.invoice_number', 'INV-DISP-0001')
        ->assertJsonPath('data.customer_name', $customer->name)
        ->assertJsonPath('data.building_name', 'Display Audit Tower')
        ->assertJsonPath('data.room_number', 'DA-501')
        ->assertJsonPath('data.property_unit', 'Display Audit Tower · DA-501')
        ->assertJsonPath('data.total_amount', '500000.00')
        ->assertJsonPath('data.paid_amount', 200000)
        ->assertJsonPath('data.items.0.description', 'Rent')
        ->assertJsonPath('data.items.0.amount', '500000.00');
});

test('invoice list includes customer building and room fields', function () {
    $admin = billingDisplayAdmin();
    seedBillingDisplayFixture($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/invoices?per_page=10')
        ->assertOk()
        ->assertJsonFragment([
            'invoice_number' => 'INV-DISP-0001',
            'customer_name' => 'Mg Mg',
            'building_name' => 'Display Audit Tower',
            'room_number' => 'DA-501',
        ]);
});

test('customer cannot access admin payment endpoints', function () {
    $admin = billingDisplayAdmin();
    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
    seedBillingDisplayFixture($admin);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/payments')
        ->assertForbidden();
});

test('customer payment list includes invoice and payment method fields', function () {
    $admin = billingDisplayAdmin();
    ['customer' => $customer, 'method' => $method] = seedBillingDisplayFixture($admin);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payments?per_page=10')
        ->assertOk()
        ->assertJsonFragment([
            'invoice_number' => 'INV-DISP-0001',
            'payment_method_name' => $method->name,
            'building_name' => 'Display Audit Tower',
            'room_number' => 'DA-501',
        ]);
});

test('receipt show includes customer invoice and property fields', function () {
    $admin = billingDisplayAdmin();
    ['receipt' => $receipt, 'customer' => $customer] = seedBillingDisplayReceipt($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/receipts/{$receipt->id}")
        ->assertOk()
        ->assertJsonPath('data.receipt_number', 'RCP-DISP-0001')
        ->assertJsonPath('data.invoice_number', 'INV-DISP-0001')
        ->assertJsonPath('data.customer_name', $customer->name)
        ->assertJsonPath('data.building_name', 'Display Audit Tower')
        ->assertJsonPath('data.room_number', 'DA-501')
        ->assertJsonPath('data.property_unit', 'Display Audit Tower · DA-501')
        ->assertJsonPath('data.payment_type', 'rent');
});

test('receipt list includes customer and property display fields', function () {
    $admin = billingDisplayAdmin();
    seedBillingDisplayReceipt($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?per_page=10')
        ->assertOk()
        ->assertJsonFragment([
            'receipt_number' => 'RCP-DISP-0001',
            'customer_name' => 'Mg Mg',
            'building_name' => 'Display Audit Tower',
            'room_number' => 'DA-501',
        ]);
});

test('active rent contract show includes customer and room fields', function () {
    $admin = billingDisplayAdmin();
    ['contract' => $contract, 'customer' => $customer] = seedBillingDisplayFixture($admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/rent-contracts/active/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.contract_number', 'R-DISP-0001')
        ->assertJsonPath('data.customer_name', $customer->name)
        ->assertJsonPath('data.building_name', 'Display Audit Tower')
        ->assertJsonPath('data.room_number', 'DA-501');
});
