<?php

use App\Mail\InvoiceDocumentMail;
use App\Mail\ReceiptDocumentMail;
use App\Mail\UtilityDocumentMail;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityType;
use App\Support\DocumentFilename;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function pdfDownloadAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();
    (new UtilityTypeSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function pdfDownloadCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function pdfDownloadStack(User $admin): array
{
    $building = \App\Models\Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'E-316',
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 500000,
        'booking_deposit_price' => 0,
    ]);

    $customer = pdfDownloadCustomer();

    $contract = Contract::query()->create([
        'contract_number' => 'R-000012',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 500000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'start_date' => '2027-07-01',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    return compact('building', 'room', 'customer', 'contract');
}

test('utility invoice and receipt downloads return named pdf attachments', function () {
    Mail::fake();

    $admin = pdfDownloadAdmin();
    ['room' => $room, 'customer' => $customer, 'contract' => $contract] = pdfDownloadStack($admin);
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => '2027-07-01',
        'status' => 'approved',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utility->items()->create([
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    $utilityFilename = DocumentFilename::utility($utility->billing_month, $room->room_number);

    $this->actingAs($admin, 'sanctum')
        ->get("/api/utilities/{$utility->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$utilityFilename.'"');

    expect($utilityFilename)->toBe('UTL-2027-07-E-316.pdf');

    $rentCharge = ChargeType::query()->where('slug', 'monthly-rent')->firstOrFail();
    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'utility_id' => $utility->id,
        'invoice_number' => 'INV-000164',
        'type' => 'utility',
        'status' => 'issued',
        'issued_date' => '2027-07-15',
        'due_date' => '2027-07-30',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $rentCharge->id,
        'description' => 'Electricity',
        'amount' => 10000,
    ]);

    $invoiceFilename = 'INV-000164.pdf';

    $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$invoiceFilename.'"');

    $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/invoices/{$invoice->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$invoiceFilename.'"');

    expect($invoiceFilename)->toBe('INV-000164.pdf');

    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();
    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => 10000,
        'payment_date' => '2027-07-20',
        'status' => 'approved',
        'created_by' => $customer->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCP-000154',
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => '2027-07-20 10:00:00',
        'approved_at' => now(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $receiptFilename = DocumentFilename::receipt($receipt->issued_at, $room->room_number, $receipt->receipt_number);

    $this->actingAs($admin, 'sanctum')
        ->get("/api/receipts/{$receipt->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$receiptFilename.'"');

    $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/receipts/{$receipt->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$receiptFilename.'"');

    expect($receiptFilename)->toBe('RCP-2027-07-E-316-000154.pdf');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/document/email")
        ->assertOk();

    Mail::assertSent(UtilityDocumentMail::class, function (UtilityDocumentMail $mail) use ($utilityFilename) {
        return $mail->filename === $utilityFilename && str_starts_with($mail->documentPdf, '%PDF');
    });

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/document/email")
        ->assertOk();

    Mail::assertSent(InvoiceDocumentMail::class, function (InvoiceDocumentMail $mail) use ($invoiceFilename) {
        return $mail->filename === $invoiceFilename && str_starts_with($mail->documentPdf, '%PDF');
    });

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email")
        ->assertOk();

    Mail::assertSent(ReceiptDocumentMail::class, function (ReceiptDocumentMail $mail) use ($receiptFilename) {
        return $mail->filename === $receiptFilename && str_starts_with($mail->documentPdf, '%PDF');
    });
});

test('invoice document html uses rosewood invoice template fields', function () {
    $admin = pdfDownloadAdmin();
    ['room' => $room, 'contract' => $contract] = pdfDownloadStack($admin);
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rentCharge = ChargeType::query()->where('slug', 'monthly-rent')->firstOrFail();
    $utilityCharge = ChargeType::query()->where('slug', 'utility-charges')->firstOrFail();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => '2027-07-01',
        'status' => 'approved',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utility->items()->create([
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'utility_id' => $utility->id,
        'invoice_number' => 'INV-000164',
        'type' => 'utility',
        'status' => 'issued',
        'issued_date' => '2027-07-15',
        'due_date' => '2027-07-30',
        'late_fee' => 500,
        'total_amount' => 510000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $rentCharge->id,
        'description' => 'Monthly Rent',
        'amount' => 500000,
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $utilityCharge->id,
        'description' => 'Electricity',
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/export")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->getContent();

    expect($html)
        ->toContain('INVOICE')
        ->toContain('Bill To')
        ->toContain('Property')
        ->toContain('Previous Unit')
        ->toContain('Current Unit')
        ->toContain('Billing Period')
        ->toContain('Amount Due')
        ->toContain('Late Fee')
        ->toContain('Notes')
        ->toContain('Please reference INV-000164 when making payment')
        ->toContain('INV-000164')
        ->toContain('MMK ')
        ->toContain('Confidential')
        ->toContain('Page 1 of 1')
        ->not->toContain('Tax')
        ->not->toContain('Discount')
        ->not->toContain('Payment History');
});

test('payment document download route does not exist', function () {
    $admin = pdfDownloadAdmin();

    $this->actingAs($admin, 'sanctum')
        ->get('/api/payments/1/document/download')
        ->assertNotFound();
});

test('receipt document html uses rosewood receipt template fields', function () {
    $admin = pdfDownloadAdmin();
    ['room' => $room, 'contract' => $contract] = pdfDownloadStack($admin);
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $utilityCharge = ChargeType::query()->where('slug', 'utility-charges')->firstOrFail();
    $paymentMethod = PaymentMethod::query()->where('status', 'active')->firstOrFail();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => '2027-07-01',
        'status' => 'approved',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utility->items()->create([
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'utility_id' => $utility->id,
        'invoice_number' => 'INV-000164',
        'type' => 'utility',
        'status' => 'paid',
        'issued_date' => '2027-07-15',
        'due_date' => '2027-07-30',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => $utilityCharge->id,
        'description' => 'Electricity',
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => 10000,
        'payment_date' => '2027-07-20',
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCP-000154',
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => '2027-07-20 10:00:00',
        'approved_at' => now(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/receipts/{$receipt->id}/document/export")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->getContent();

    expect($html)
        ->toContain('RECEIPT')
        ->toContain('Bill To')
        ->toContain('Property')
        ->toContain('Receipt No.')
        ->toContain('Amount Received')
        ->toContain('Previous Unit')
        ->toContain('Notes')
        ->toContain('RCP-000154')
        ->toContain('INV-000164')
        ->toContain('MMK ')
        ->toContain('Confidential')
        ->toContain('Page 1 of 1')
        ->not->toContain('Payment History');
});
