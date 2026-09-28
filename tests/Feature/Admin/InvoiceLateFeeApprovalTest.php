<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceLateFeeService;
use App\Support\InvoiceLateFeePolicy;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function lateFeeAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function lateFeeCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function lateFeeDraftInvoice(User $admin, User $customer): Invoice
{
    $building = Building::query()->create([
        'building_name' => 'Late Fee Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'LF-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 50000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-LF-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 6000000,
        'deposit_amount' => 50000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2026-08-01',
        'end_date' => '2027-07-31',
    ]);

    return Invoice::query()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
        'invoice_number' => 'INV-LF-'.fake()->unique()->numerify('######'),
        'type' => 'rent',
        'issued_date' => null,
        'due_date' => '2026-10-03',
        'billing_month' => '2026-10-01',
        'late_fee' => 0,
        'total_amount' => 500000,
        'status' => 'draft',
    ]);
}

test('active late fee options exclude inactive rules and include no default concept', function (): void {
    $admin = lateFeeAdmin();

    $activeA = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);
    $activeB = LateFee::factory()->active()->create([
        'name' => 'Monthly Percentage Penalty',
        'type' => 'percentage',
        'value' => 2.5,
        'per' => 'month',
        'grace_days' => 5,
    ]);
    LateFee::factory()->create([
        'name' => 'Retired Rule',
        'status' => 'inactive',
        'type' => 'fixed',
        'value' => 999,
        'per' => 'day',
        'grace_days' => 1,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/late-fees/options')
        ->assertOk()
        ->json('data');

    $ids = collect($response)->pluck('id')->all();

    expect($ids)->toContain($activeA->id, $activeB->id)
        ->and($ids)->not->toContain(
            LateFee::query()->where('name', 'Retired Rule')->value('id')
        )
        ->and(collect($response)->every(fn (array $row) => ! array_key_exists('is_default', $row) || $row['is_default'] === null))
        ->toBeTrue();
});

test('invoice approval list and detail share one late fee selection field', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}/late-fee-policy", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.late_fee_selection', $rule->id)
        ->assertJsonPath('data.late_fee_policy.mode', 'rule')
        ->assertJsonPath('data.late_fee_policy.locked', false);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.late_fee_selection', $rule->id)
        ->assertJsonPath('data.late_fee_rule_id', $rule->id);
});

test('approve requires explicit late fee selection and snapshots atomically', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertStatus(422);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.late_fee', '0.00')
        ->assertJsonPath('data.late_fee_policy.locked', true)
        ->assertJsonPath('data.late_fee_policy.name', 'Standard Late Fee')
        ->assertJsonPath('data.late_fee_policy.value', 10000)
        ->assertJsonPath('data.late_fee_policy.grace_days', 3);

    $invoice->refresh();

    expect($invoice->late_fee_policy_locked)->toBeTrue()
        ->and((float) $invoice->late_fee)->toBe(0.0)
        ->and($invoice->late_fee_policy_name)->toBe('Standard Late Fee');
});

test('no late fee selection is rejected for approval', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => 'none',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Please select a Late Fee Rule.');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}/late-fee-policy", [
            'late_fee_selection' => 'none',
        ])
        ->assertStatus(422);

    expect($invoice->fresh()->status)->toBe('draft');
});

test('overdue accrual uses snapshotted policy and ignores later settings edits', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    // Settings change after approval must not rewrite the invoice snapshot.
    $rule->update([
        'value' => 15000,
        'grace_days' => 5,
        'status' => 'inactive',
        'name' => 'Changed Standard Late Fee',
    ]);

    $invoice->refresh();
    expect($invoice->late_fee_policy_name)->toBe('Standard Late Fee')
        ->and((float) $invoice->late_fee_policy_value)->toBe(10000.0)
        ->and((int) $invoice->late_fee_policy_grace_days)->toBe(3);

    // Due 03 Oct + 3 grace => fees begin 07 Oct.
    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-10-06')))->toBe(0.0);
    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-10-07')))->toBe(10000.0);
    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-10-09')))->toBe(30000.0);

    app(InvoiceLateFeeService::class)->applyDueLateFees(Carbon::parse('2026-10-09'));
    $invoice->refresh();

    expect((float) $invoice->late_fee)->toBe(30000.0)
        ->and($invoice->status)->toBe('overdue');
});

test('inactive rule cannot be newly selected but historical snapshot remains', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Soon Inactive',
        'type' => 'fixed',
        'value' => 5000,
        'per' => 'day',
        'grace_days' => 0,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    $rule->update(['status' => 'inactive']);

    $draft = lateFeeDraftInvoice($admin, $customer);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/invoices/{$draft->id}/late-fee-policy", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertStatus(422);

    $invoice->refresh();
    expect($invoice->late_fee_policy_name)->toBe('Soon Inactive')
        ->and($invoice->late_fee_policy_locked)->toBeTrue();
});

test('invoice document notes include read-only late fee policy label', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}/late-fee-policy", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/preview")
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Late Fee Rule')
        ->and($html)->toContain('Standard Late Fee — MMK 10,000 per day · 3 grace days')
        ->and($html)->not->toContain('Calculation')
        ->and($html)->not->toContain('Overdue Days')
        // Approval draft preview: no redundant Status row; Late Fee amount stays 0.
        ->and($html)->not->toContain('>Status<')
        ->and($html)->not->toContain('>Draft<')
        ->and($html)->toContain('Late Fee')
        ->and($html)->toMatch('/Late Fee[\s\S]*?MMK 0/');
});

test('issued invoice document still shows status and late fee amount', function (): void {
    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/preview")
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Status')
        ->and($html)->toContain('Issued')
        ->and($html)->toContain('Late Fee')
        ->and($html)->toMatch('/Late Fee[\s\S]*?MMK 0/')
        ->and($html)->toContain('Late Fee Rule')
        ->and($html)->not->toContain('Calculation')
        ->and($html)->not->toContain('Overdue Days');
});

test('overdue invoice document moves late fee calculation into notes', function (): void {
    Carbon::setTestNow('2026-10-09');

    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    app(InvoiceLateFeeService::class)->applyDueLateFees(Carbon::parse('2026-10-09'));
    $invoice->refresh();

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/preview")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Overdue Days')
        ->and($html)->toContain('Status')
        ->and($html)->toContain('Overdue')
        ->and($html)->toContain('Late Fee Rule')
        ->and($html)->toContain('Standard Late Fee — MMK 10,000 per day · 3 grace days')
        ->and($html)->toContain('Calculation')
        ->and($html)->toContain('6 overdue days − 3 grace days = 3 chargeable days')
        ->and($html)->toContain('3 days × MMK 10,000 = MMK 30,000')
        ->and($html)->toMatch('/Late Fee[\s\S]*?MMK 30,000/');

    Carbon::setTestNow();
});

test('percentage late fee document notes show base amount calculation', function (): void {
    Carbon::setTestNow('2026-10-09');

    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);

    $rule = LateFee::factory()->active()->create([
        'name' => 'Monthly Percentage Penalty',
        'type' => 'percentage',
        'value' => 2.5,
        'per' => 'month',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    app(InvoiceLateFeeService::class)->applyDueLateFees(Carbon::parse('2026-10-09'));
    $invoice->refresh();

    // Due 03 Oct + 3 grace => chargeable from 07 Oct; on 09 Oct = 3 chargeable days => 1 month period.
    // 2.5% × 500,000 = 12,500
    expect((float) $invoice->late_fee)->toBe(12500.0);

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/invoices/{$invoice->id}/document/preview")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Overdue Days')
        ->and($html)->toContain('Late Fee Rule')
        ->and($html)->toContain('Monthly Percentage Penalty — 2.5% per month · 3 grace days')
        ->and($html)->toContain('Calculation')
        ->and($html)->toContain('6 overdue days − 3 grace days = 3 chargeable days')
        ->and($html)->toContain('Base amount: MMK 500,000')
        ->and($html)->toContain('2.5% × MMK 500,000 = MMK 12,500')
        ->and($html)->toContain('Late Fee: MMK 12,500')
        // Late Fee headings must beat .invoice-doc__notes body <p> muted rules.
        ->and($html)->toContain('.invoice-doc__notes .invoice-doc__notes-policy-label')
        ->and($html)->toContain('color: var(--inv-accent, #7a3149)')
        ->and($html)->toContain('font-weight: 600')
        ->and($html)->toContain('invoice-doc__foot-confidential-label')
        ->and($html)->not->toContain('.invoice-doc__notes p {');

    Carbon::setTestNow();
});

test('receipt document shows late fee notes only when invoice late fee accrued', function (): void {
    Carbon::setTestNow('2026-10-09');

    $admin = lateFeeAdmin();
    $customer = lateFeeCustomer();
    $invoice = lateFeeDraftInvoice($admin, $customer);
    $method = PaymentMethod::query()->availableForCustomer()->orderBy('id')->firstOrFail();

    $rule = LateFee::factory()->active()->create([
        'name' => 'Standard Late Fee',
        'type' => 'fixed',
        'value' => 10000,
        'per' => 'day',
        'grace_days' => 3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    app(InvoiceLateFeeService::class)->applyDueLateFees(Carbon::parse('2026-10-09'));
    $invoice->refresh();

    expect((float) $invoice->late_fee)->toBe(30000.0);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'amount' => round((float) $invoice->total_amount + (float) $invoice->late_fee, 2),
        'payment_date' => '2026-10-09',
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCP-LF-0001',
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => now(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $html = $this->actingAs($admin, 'sanctum')
        ->get("/api/receipts/{$receipt->id}/document/export")
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Late Fee Rule')
        ->and($html)->toContain('Standard Late Fee — MMK 10,000 per day · 3 grace days')
        ->and($html)->toContain('Calculation')
        ->and($html)->toContain('3 days × MMK 10,000 = MMK 30,000')
        ->and($html)->toContain('Payment received successfully.')
        ->and($html)->toContain('--inv-accent: #7a3149')
        ->and($html)->toContain('.receipt-doc .receipt-doc__late-fee-label')
        ->and($html)->toContain('.receipt-doc .receipt-doc__foot-company strong')
        ->and($html)->toContain('color: var(--inv-accent, #7a3149)')
        ->and($html)->toContain('.receipt-doc .receipt-doc__foot-confidential-label')
        ->and($html)->toContain('System-generated receipt');

    // Zero late fee receipt stays clean.
    $cleanInvoice = lateFeeDraftInvoice($admin, $customer);
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/invoices/{$cleanInvoice->id}/issue", [
            'late_fee_selection' => $rule->id,
        ])
        ->assertOk();

    $cleanInvoice->refresh();
    expect((float) $cleanInvoice->late_fee)->toBe(0.0);

    $cleanPayment = Payment::query()->create([
        'invoice_id' => $cleanInvoice->id,
        'payment_method_id' => $method->id,
        'amount' => (float) $cleanInvoice->total_amount,
        'payment_date' => '2026-10-03',
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $cleanReceipt = Receipt::query()->create([
        'payment_id' => $cleanPayment->id,
        'receipt_number' => 'RCP-LF-0002',
        'status' => 'issued',
        'approval_status' => 'approved',
        'issued_at' => now(),
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $cleanHtml = $this->actingAs($admin, 'sanctum')
        ->get("/api/receipts/{$cleanReceipt->id}/document/export")
        ->assertOk()
        ->getContent();

    expect($cleanHtml)->not->toContain('Late Fee Rule')
        ->and($cleanHtml)->not->toContain('Calculation')
        ->and($cleanHtml)->toContain('Late Fee')
        ->and($cleanHtml)->toMatch('/Late Fee[\s\S]*?MMK 0/');

    Carbon::setTestNow();
});
