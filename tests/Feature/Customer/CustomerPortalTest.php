<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function customerPortalUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function customerPortalAdmin(): User
{
    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function customerPortalContract(string $type, string $contractNumber, User $customer, User $admin): Contract
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $type === 'rent' ? 'C-107' : 'A-401',
        'floor_number' => $type === 'rent' ? 1 : 4,
        'type' => $type,
        'status' => $type === 'rent' ? 'occupied' : 'sold',
        'area_sqft' => 900,
        'sale_price' => $type === 'sale' ? 800000000 : 0,
        'rent_price' => $type === 'rent' ? 365000 : 0,
        'rent_deposit_price' => $type === 'rent' ? 365000 : 0,
        'booking_deposit_price' => $type === 'sale' ? 80000000 : 0,
    ]);

    return Contract::query()->create([
        'contract_number' => $contractNumber,
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => $type === 'rent' ? 1095000 : 800000000,
        'deposit_amount' => $type === 'rent' ? 365000 : 80000000,
        'type' => $type,
        'payment_type' => $type === 'rent' ? 'installment' : 'full',
        'duration_months' => $type === 'rent' ? 3 : null,
        'status' => Contract::STATUS_ACTIVE,
        'start_date' => '2027-07-01',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);
}

it('returns customer dashboard data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active_contracts',
                'completed_contracts',
                'unpaid_invoices',
                'paid_invoices',
                'total_payments',
                'pending_payments',
                'completed_payments',
                'total_paid_amount',
                'recent_payments',
            ],
        ]);
});

it('returns customer profile data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $customer->email);
});

it('returns customer contracts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/contracts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('downloads customer sale contract with the admin pdf template and customer filename', function (): void {
    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('sale', 'S-000040', $customer, $admin);

    $adminResponse = $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contracts/approved/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000040.pdf"');

    $customerResponse = $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/contracts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000040.pdf"');

    expect($customerResponse->getContent())->toBe($adminResponse->getContent());
});

it('downloads customer rent contract with the admin pdf template and customer filename', function (): void {
    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('rent', 'R-000070', $customer, $admin);

    $adminResponse = $this->actingAs($admin, 'sanctum')
        ->get("/api/rent-contracts/active/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Rent_Contract_R-000070.pdf"');

    $customerResponse = $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/contracts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Rent_Contract_R-000070.pdf"');

    expect($customerResponse->getContent())->toBe($adminResponse->getContent());
});

it('allows customers to export the rendered contract preview through the shared pdf converter', function (): void {
    $customer = customerPortalUser();

    app()->bind(DocumentPdfConverter::class, fn () => new class implements DocumentPdfConverter {
        public function convert(string $html): string
        {
            return '%PDF-1.4 customer preview export';
        }
    });

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/document-preview/pdf', [
            'html' => '<html><body><article id="pdf-print">Customer contract preview</article></body></html>',
            'filename' => 'Rosewood_Royale_Sale_Contract_S-000041.pdf',
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000041.pdf"')
        ->assertSee('%PDF', false);
});

it('returns customer invoices list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer payments list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payments')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer receipts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer notifications', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns customer payment methods', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payment-methods')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('forbids non-customer access to customer portal routes', function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertForbidden();
});

it('shows an owned payment and denies another customer payment', function (): void {
    Storage::fake('public');

    $customer = customerPortalUser();
    $otherCustomer = User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('rent', 'R-PAY-SHOW-1', $customer, $admin);
    $otherContract = customerPortalContract('rent', 'R-PAY-SHOW-2', $otherCustomer, $admin);

    $paymentMethod = \App\Models\PaymentMethod::query()->create([
        'name' => 'Bank Transfer',
        'slug' => 'bank-transfer-show',
        'type' => 'bank_transfer',
        'status' => 'active',
        'is_customer_visible' => false,
    ]);

    $ownInvoice = \App\Models\Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-PAY-OWN-1',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 100000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $otherInvoice = \App\Models\Invoice::query()->create([
        'contract_id' => $otherContract->id,
        'invoice_number' => 'INV-PAY-OTHER-1',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 100000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $ownPayment = \App\Models\Payment::query()->create([
        'invoice_id' => $ownInvoice->id,
        'payment_method_id' => $paymentMethod->id,
        'created_by' => $customer->id,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payments/own-proof.jpg',
    ]);

    $otherPayment = \App\Models\Payment::query()->create([
        'invoice_id' => $otherInvoice->id,
        'payment_method_id' => $paymentMethod->id,
        'created_by' => $otherCustomer->id,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payments/other-proof.jpg',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/payments/{$ownPayment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $ownPayment->id)
        ->assertJsonPath('data.invoice_id', $ownInvoice->id)
        ->assertJsonPath('data.invoice_summary.invoice_number', 'INV-PAY-OWN-1');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/payments/{$otherPayment->id}")
        ->assertNotFound();
});

it('rejects customer payment payloads that include amount', function (): void {
    Storage::fake('public');

    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('rent', 'R-PAY-AMT-1', $customer, $admin);

    $paymentMethod = \App\Models\PaymentMethod::query()->create([
        'name' => 'KBZ Pay',
        'slug' => 'kbz-pay-amount',
        'type' => 'wallet',
        'status' => 'active',
        'is_customer_visible' => true,
        'phone_number' => '09779959901',
    ]);

    $invoice = \App\Models\Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-PAY-AMT-1',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 150000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_date' => now()->toDateString(),
            'amount' => 150000,
            'proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('exposes pending payment flags on customer invoice detail', function (): void {
    Storage::fake('public');

    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('rent', 'R-INV-PEND-1', $customer, $admin);

    $paymentMethod = \App\Models\PaymentMethod::query()->create([
        'name' => 'Cash',
        'slug' => 'cash-pending-flag',
        'type' => 'cash',
        'status' => 'active',
        'is_customer_visible' => false,
    ]);

    $invoice = \App\Models\Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-PEND-1',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 200000,
        'late_fee' => 0,
        'created_by' => $admin->id,
    ]);

    $payment = \App\Models\Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'created_by' => $customer->id,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'proof_image_path' => 'payments/pending-proof.jpg',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.has_pending_payment', true)
        ->assertJsonPath('data.pending_payment_id', $payment->id);
});
