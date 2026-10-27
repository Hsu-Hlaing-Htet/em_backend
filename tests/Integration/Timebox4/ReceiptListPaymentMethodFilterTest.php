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

function receiptListFilterAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new PaymentMethodSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

/**
 * @return array{receipt: Receipt, method: PaymentMethod}
 */
function seedIssuedReceiptForMethod(User $admin, User $customer, PaymentMethod $method, string $suffix): array
{
    static $sequence = 0;
    $sequence++;

    $building = \App\Models\Building::query()->create([
        'building_name' => "Receipt Filter Tower {$suffix}",
        'location' => 'Yangon',
    ]);

    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => "RF-{$suffix}",
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 800,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 800000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => sprintf('R-RF-%04d', $sequence),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
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
        'invoice_number' => sprintf('INV-RF-%04d', $sequence),
        'type' => 'rent',
        'status' => 'issued',
        'issued_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 400000,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'charge_type_id' => ChargeType::query()->where('slug', 'monthly-rent')->value('id'),
        'description' => 'Monthly rent',
        'amount' => 400000,
    ]);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $method->id,
        'created_by' => $customer->id,
        'amount' => 400000,
        'proof_image_path' => "payments/rf-{$suffix}.jpg",
        'payment_date' => now()->toDateString(),
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $receipt = Receipt::query()->create([
        'payment_id' => $payment->id,
        'receipt_number' => sprintf('RCP-RF-%04d', $sequence),
        'status' => Receipt::STATUS_ISSUED,
        'approval_status' => Receipt::APPROVAL_APPROVED,
        'issued_at' => now(),
        'sent_at' => now(),
        'sent_by' => $admin->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    return compact('receipt', 'method');
}

test('receipt list payment_method_id filters by related payment method only', function () {
    $admin = receiptListFilterAdmin();
    $customer = User::query()->where('email', 'hsuhtet562@gmail.com')->firstOrFail();

    $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    $kbz = PaymentMethod::query()->where('slug', 'kbz-pay')->firstOrFail();
    $aya = PaymentMethod::query()->where('slug', 'aya-pay')->firstOrFail();

    expect($cash->name)->toBe('Cash')
        ->and($kbz->name)->toBe('KBZ Pay')
        ->and($aya->name)->toBe('AYA Pay');

    seedIssuedReceiptForMethod($admin, $customer, $cash, 'CASH');
    seedIssuedReceiptForMethod($admin, $customer, $kbz, 'KBZ');
    ['receipt' => $ayaReceipt] = seedIssuedReceiptForMethod($admin, $customer, $aya, 'AYA');

    $ayaResponse = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?'.http_build_query([
            'payment_method_id' => $aya->id,
            'status' => 'issued',
            'delivery_status' => 'sent',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->json('data.data');

    expect($ayaResponse)->toHaveCount(1)
        ->and(collect($ayaResponse)->pluck('payment_method_name')->unique()->values()->all())
        ->toBe(['AYA Pay'])
        ->and(collect($ayaResponse)->pluck('id')->all())
        ->toBe([$ayaReceipt->id]);

    $kbzResponse = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?'.http_build_query([
            'payment_method_id' => $kbz->id,
            'status' => 'issued',
            'delivery_status' => 'sent',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->json('data.data');

    expect($kbzResponse)->toHaveCount(1)
        ->and(collect($kbzResponse)->pluck('payment_method_name')->unique()->values()->all())
        ->toBe(['KBZ Pay']);

    $cashResponse = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?'.http_build_query([
            'payment_method_id' => $cash->id,
            'status' => 'issued',
            'delivery_status' => 'sent',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->json('data.data');

    expect($cashResponse)->toHaveCount(1)
        ->and(collect($cashResponse)->pluck('payment_method_name')->unique()->values()->all())
        ->toBe(['Cash']);

    $allResponse = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/receipts?'.http_build_query([
            'status' => 'issued',
            'delivery_status' => 'sent',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->json('data.data');

    expect($allResponse)->toHaveCount(3)
        ->and(collect($allResponse)->pluck('payment_method_name')->sort()->values()->all())
        ->toBe(['AYA Pay', 'Cash', 'KBZ Pay']);
});
