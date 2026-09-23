<?php

use App\Mail\ReceiptDocumentMail;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function paymentNotificationAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function paymentNotificationCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function seedPaymentNotificationPayment(User $admin, User $customer): array
{
    $building = \App\Models\Building::query()->create([
        'building_name' => 'Notify Tower',
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'NTF-101',
        'floor_number' => 8,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 450000,
        'rent_deposit_price' => 900000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-NTF-0001',
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
        'invoice_number' => 'INV-NTF-0001',
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

    $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'amount' => null,
        'proof_image_path' => 'payments/notify-proof.jpg',
        'payment_date' => now()->toDateString(),
        'status' => 'pending',
        'note' => 'Notification workflow payment',
    ]);

    return compact('invoice', 'payment', 'method', 'customer');
}

test('payment approval notifies customer and creates exactly one issued receipt', function () {
    Mail::fake();
    Notification::fake();

    $admin = paymentNotificationAdmin();
    $customer = paymentNotificationCustomer();
    ['payment' => $payment] = seedPaymentNotificationPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);
    expect(Receipt::query()->where('payment_id', $payment->id)->first()?->status)->toBe('issued');

    Notification::assertSentTo($customer, PaymentApprovedNotification::class);
    Notification::assertNotSentTo($customer, PaymentRejectedNotification::class);
    Mail::assertNotSent(ReceiptDocumentMail::class);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Payment Approved',
            'message' => 'Your payment has been approved. Your receipt is now available in your Customer Portal.',
        ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/approve", ['amount' => 450000])
        ->assertStatus(409);

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1);
    Notification::assertSentToTimes($customer, PaymentApprovedNotification::class, 1);
});

test('payment rejection notifies customer and never creates a receipt', function () {
    Mail::fake();
    Notification::fake();

    $admin = paymentNotificationAdmin();
    $customer = paymentNotificationCustomer();
    ['payment' => $payment] = seedPaymentNotificationPayment($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/reject", [
            'rejection_reason' => 'Proof image is unreadable.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Proof image is unreadable.');

    expect(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(0);
    Notification::assertSentTo($customer, PaymentRejectedNotification::class);
    Notification::assertNotSentTo($customer, PaymentApprovedNotification::class);
    Mail::assertNotSent(ReceiptDocumentMail::class);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Payment Rejected',
            'message' => 'Your payment has been rejected. Please check the details and submit again.',
        ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/reject", [
            'rejection_reason' => 'Proof image is unreadable.',
        ])
        ->assertStatus(409);

    Notification::assertSentToTimes($customer, PaymentRejectedNotification::class, 1);
});
