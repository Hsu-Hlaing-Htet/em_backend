<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to create update and delete payment records with invoice recalculation', function () {
    $admin = User::factory()->admin()->create();

    $invoice = Invoice::create([
        'invoice_number' => 'INV-T-0001',
        'customer_name' => 'Test Customer',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_UNPAID,
    ]);

    $this->actingAs($admin);

    $storeResponse = $this->postJson("/api/admin/invoices/{$invoice->id}/payments", [
        'payment_date' => now()->toDateString(),
        'amount' => 400,
        'payment_method' => 'cash',
        'reference_note' => 'Initial payment',
    ]);

    $storeResponse
        ->assertCreated()
        ->assertJsonPath('data.amount', '400.00');

    $paymentId = $storeResponse->json('data.id');

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe('400.00')
        ->and($invoice->status)->toBe(Invoice::STATUS_PARTIAL);

    $this->putJson("/api/admin/payments/{$paymentId}", [
        'payment_date' => now()->toDateString(),
        'amount' => 1000,
        'payment_method' => 'bank_transfer',
        'reference_note' => 'Final payment',
    ])->assertOk()->assertJsonPath('data.amount', '1000.00');

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe('1000.00')
        ->and($invoice->status)->toBe(Invoice::STATUS_PAID);

    $this->deleteJson("/api/admin/payments/{$paymentId}")
        ->assertOk();

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe('0.00')
        ->and($invoice->status)->toBe(Invoice::STATUS_UNPAID);

    $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
    $this->assertDatabaseCount('receipts', 0);
});

it('rejects admin payment update that exceeds invoice total', function () {
    $admin = User::factory()->admin()->create();

    $invoice = Invoice::create([
        'invoice_number' => 'INV-T-0002',
        'customer_name' => 'Overflow Customer',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_UNPAID,
    ]);

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'payment_date' => now()->toDateString(),
        'amount' => 300,
        'payment_method' => 'cash',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin/payments/{$payment->id}", [
            'payment_date' => now()->toDateString(),
            'amount' => 1200,
            'payment_method' => 'cash',
        ])
        ->assertStatus(422);
});
