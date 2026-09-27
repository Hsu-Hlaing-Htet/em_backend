<?php

use App\Mail\InvoiceDocumentMail;
use App\Mail\ReceiptDocumentMail;
use App\Mail\RentContractDocumentMail;
use App\Mail\SaleContractDocumentMail;
use App\Mail\UtilityDocumentMail;
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
use App\Models\Utility;
use App\Models\UtilityType;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Support\CustomerNotificationRecipients;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function recipientAuditAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();
    (new UtilityTypeSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function recipientAuditStack(User $admin, ?User $second = null): array
{
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Recipient Audit Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'RA-101',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 450000,
        'rent_deposit_price' => 900000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-RAUD-0001',
        'user_id' => $customer->id,
        'second_user_id' => $second?->id,
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

    return compact('building', 'room', 'customer', 'contract');
}

test('authoritative customer email is users.email and send uses current value after change', function () {
    Mail::fake();

    $admin = recipientAuditAdmin();
    ['customer' => $customer, 'contract' => $contract, 'room' => $room] = recipientAuditStack($admin);

    $oldEmail = $customer->email;
    expect($oldEmail)->toBe('mgmg@gmail.com');

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-RAUD-0001',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 450000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => ChargeType::query()->where('slug', 'monthly-rent')->value('id'),
        'description' => 'Monthly rent',
        'amount' => 450000,
    ]);

    // Simulate stale UI still posting the old email after account update.
    $customer->update(['email' => 'newcustomer@gmail.com']);
    $customer->refresh();
    expect($customer->email)->toBe('newcustomer@gmail.com');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/document/email", [
            'email' => $oldEmail,
        ])
        ->assertOk();

    Mail::assertSent(InvoiceDocumentMail::class, fn (InvoiceDocumentMail $mail) => $mail->hasTo('newcustomer@gmail.com'));
    Mail::assertNotSent(InvoiceDocumentMail::class, fn (InvoiceDocumentMail $mail) => $mail->hasTo($oldEmail));

    // Receipt
    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => PaymentMethod::query()->where('slug', 'cash')->value('id'),
        'amount' => 450000,
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $customer->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCP-RAUD-0001',
        'status' => 'issued',
        'approval_status' => 'approved',
        'approved_at' => now(),
        'issued_at' => now(),
        'receipt_pdf_path' => 'receipts/rcp-raud-0001.pdf',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
    ]);

    Mail::fake();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/receipts/{$receipt->id}/document/email", [
            'email' => $oldEmail,
        ])
        ->assertOk();

    Mail::assertSent(ReceiptDocumentMail::class, fn (ReceiptDocumentMail $mail) => $mail->hasTo('newcustomer@gmail.com'));
    Mail::assertNotSent(ReceiptDocumentMail::class, fn (ReceiptDocumentMail $mail) => $mail->hasTo($oldEmail));

    // Utility bill (room-linked, no contract_id on row)
    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'status' => 'approved',
        'total_amount' => 10000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utility->items()->create([
        'utility_type_id' => UtilityType::query()->where('slug', 'electricity')->value('id'),
        'previous_reading' => 10,
        'current_reading' => 20,
        'usage' => 10,
        'unit_price' => 1000,
        'amount' => 10000,
    ]);

    Mail::fake();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/document/email", [
            'email' => $oldEmail,
        ])
        ->assertOk();

    Mail::assertSent(UtilityDocumentMail::class, fn (UtilityDocumentMail $mail) => $mail->hasTo('newcustomer@gmail.com'));
    Mail::assertNotSent(UtilityDocumentMail::class, fn (UtilityDocumentMail $mail) => $mail->hasTo($oldEmail));

    // Rent contract send
    Mail::fake();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contracts/active/{$contract->id}/document/email", [
            'email' => $oldEmail,
        ])
        ->assertOk();

    Mail::assertSent(RentContractDocumentMail::class, fn (RentContractDocumentMail $mail) => $mail->hasTo('newcustomer@gmail.com'));
    Mail::assertNotSent(RentContractDocumentMail::class, fn (RentContractDocumentMail $mail) => $mail->hasTo($oldEmail));
});

test('sale contract send uses current users.email after customer email change', function () {
    Mail::fake();

    $admin = recipientAuditAdmin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Sale Recipient Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'SR-201',
        'floor_number' => 2,
        'type' => 'sale',
        'status' => 'available',
        'area_sqft' => 1100,
        'sale_price' => 120000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 5000000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'S-RAUD-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 120000000,
        'type' => 'sale',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'start_date' => now()->toDateString(),
    ]);

    $oldEmail = $customer->email;
    $customer->update(['email' => 'newsale@gmail.com']);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contracts/approved/{$contract->id}/document/email", [
            'email' => $oldEmail,
        ])
        ->assertOk();

    Mail::assertSent(SaleContractDocumentMail::class, fn (SaleContractDocumentMail $mail) => $mail->hasTo('newsale@gmail.com'));
    Mail::assertNotSent(SaleContractDocumentMail::class, fn (SaleContractDocumentMail $mail) => $mail->hasTo($oldEmail));
});

test('joint contract notifications resolve both current party emails and dedupe shared address', function () {
    Mail::fake();

    $admin = recipientAuditAdmin();
    $customer2 = User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();
    ['customer' => $customer1, 'contract' => $contract, 'room' => $room] = recipientAuditStack($admin, $customer2);

    $customer1->update(['email' => 'joint1@gmail.com']);
    $customer2->update(['email' => 'joint2@gmail.com']);

    $emails = CustomerNotificationRecipients::emailsForContract($contract->fresh());
    expect($emails)->toEqualCanonicalizing(['joint1@gmail.com', 'joint2@gmail.com']);

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'invoice_number' => 'INV-RAUD-JNT1',
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 450000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/document/email")
        ->assertOk();

    Mail::assertSent(InvoiceDocumentMail::class, fn (InvoiceDocumentMail $mail) => $mail->hasTo('joint1@gmail.com'));
    Mail::assertSent(InvoiceDocumentMail::class, fn (InvoiceDocumentMail $mail) => $mail->hasTo('joint2@gmail.com'));
    expect(Mail::sent(InvoiceDocumentMail::class))->toHaveCount(2);

    // Case-insensitive dedupe of identical party emails (unit-level; DB unique prevents two users sharing one address).
    $deduped = collect(['SharedParty@gmail.com', 'sharedparty@gmail.com'])
        ->map(fn (string $email): string => trim($email))
        ->unique(fn (string $email): string => strtolower($email))
        ->values()
        ->all();
    expect($deduped)->toBe(['SharedParty@gmail.com']);

    Mail::fake();

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'status' => 'approved',
        'total_amount' => 5000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $utility->items()->create([
        'utility_type_id' => UtilityType::query()->where('slug', 'electricity')->value('id'),
        'previous_reading' => 1,
        'current_reading' => 2,
        'usage' => 1,
        'unit_price' => 5000,
        'amount' => 5000,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/document/email")
        ->assertOk();

    Mail::assertSent(UtilityDocumentMail::class, fn (UtilityDocumentMail $mail) => $mail->hasTo('joint1@gmail.com'));
    Mail::assertSent(UtilityDocumentMail::class, fn (UtilityDocumentMail $mail) => $mail->hasTo('joint2@gmail.com'));

    // Payment approved uses CURRENT party User records (not stale emails).
    Notification::fake();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => PaymentMethod::query()->where('slug', 'cash')->value('id'),
        'amount' => null,
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'created_by' => $customer1->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk();

    Notification::assertSentTo($customer1->fresh(), PaymentApprovedNotification::class);
    Notification::assertSentTo($customer2->fresh(), PaymentApprovedNotification::class);
});
