<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\ContractInvoiceSchedule;
use Database\Seeders\ContractFinancialConsistencySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\BillingSeederSupport;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedInvoiceNumberFixtures(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $second = User::query()->where('email', 'susu@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Invoice Number Tower',
        'location' => 'Yangon',
    ]);

    $saleRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-501',
        'floor_number' => 5,
        'type' => 'sale',
        'status' => 'occupied',
        'area_sqft' => 1100,
        'sale_price' => 120000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 12000000,
    ]);

    $rentRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'B-501',
        'floor_number' => 5,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 1000000,
        'booking_deposit_price' => 0,
    ]);

    $fullPlan = PaymentPlan::query()->where('payment_type', 'full')->first()
        ?? PaymentPlan::query()->create([
            'name' => 'Full Payment',
            'payment_type' => 'full',
            'duration_months' => null,
            'interest_percentage' => 0,
            'status' => 'active',
        ]);

    $installmentPlan = PaymentPlan::query()
        ->where('payment_type', 'installment')
        ->where('duration_months', 12)
        ->first()
        ?? PaymentPlan::query()->create([
            'name' => '12-Month Installment',
            'payment_type' => 'installment',
            'duration_months' => 12,
            'interest_percentage' => 0,
            'status' => 'active',
        ]);

    foreach ([
        ['name' => 'Monthly Rent', 'slug' => 'monthly-rent'],
        ['name' => 'Booking Deposit', 'slug' => 'booking-deposit'],
        ['name' => 'Sale Installment', 'slug' => 'sale-installment'],
    ] as $charge) {
        ChargeType::query()->firstOrCreate(
            ['slug' => $charge['slug']],
            ['name' => $charge['name'], 'status' => 'active'],
        );
    }

    PaymentMethod::query()->firstOrCreate(
        ['slug' => 'kbz-pay'],
        ['name' => 'KBZ Pay', 'status' => 'active'],
    );

    $saleFull = Contract::query()->create([
        'contract_number' => 'S-900001',
        'user_id' => $customer->id,
        'room_id' => $saleRoom->id,
        'payment_plan_id' => $fullPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 120000000,
        'deposit_amount' => 12000000,
        'type' => 'sale',
        'payment_type' => 'full',
        'start_date' => '2026-06-01',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo sale contract (active).',
    ]);

    $saleInstallment = Contract::query()->create([
        'contract_number' => 'S-900002',
        'user_id' => $second->id,
        'room_id' => $saleRoom->id,
        'payment_plan_id' => $installmentPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 120000000,
        'deposit_amount' => 12000000,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 5,
        'start_date' => '2026-06-01',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo sale contract (active).',
    ]);

    $rent = Contract::query()->create([
        'contract_number' => 'R-900001',
        'user_id' => $customer->id,
        'room_id' => $rentRoom->id,
        'payment_plan_id' => $fullPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 6000000,
        'deposit_amount' => 1000000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2027-05-31',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo rent contract (active).',
    ]);

    return compact('admin', 'saleFull', 'saleInstallment', 'rent');
}

test('production invoice numbers use INV-000001 zero-padded format', function () {
    ['admin' => $admin, 'rent' => $rent] = seedInvoiceNumberFixtures();

    $service = app(InvoiceService::class);
    $first = $service->generateInvoiceNumber();

    Invoice::query()->create([
        'contract_id' => $rent->id,
        'created_by' => $admin->id,
        'invoice_number' => $first,
        'type' => 'rent',
        'issued_date' => '2026-09-01',
        'due_date' => '2026-09-05',
        'late_fee' => 0,
        'total_amount' => 1000,
        'status' => 'draft',
    ]);

    $second = $service->generateInvoiceNumber();

    expect($first)->toBe('INV-000001')
        ->and($second)->toBe('INV-000002')
        ->and($first)->toMatch('/^INV-\d{6}$/');
});

test('seed charge invoices use canonical numbers for rent deposit installment and full', function () {
    ['admin' => $admin, 'saleFull' => $saleFull, 'saleInstallment' => $saleInstallment, 'rent' => $rent] = seedInvoiceNumberFixtures();

    $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
    $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
    $support = new ContractFinancialHistorySeederSupport(
        $admin,
        $chargeTypes,
        $paymentMethods,
        Carbon::parse('2026-09-24'),
    );

    $support->reconcileContracts(collect([$saleFull->fresh(['room']), $saleInstallment->fresh(['room']), $rent->fresh(['room'])]));

    $numbers = Invoice::query()
        ->whereIn('contract_id', [$saleFull->id, $saleInstallment->id, $rent->id])
        ->orderBy('id')
        ->pluck('invoice_number');

    expect($numbers)->not->toBeEmpty()
        ->and($numbers->every(fn (string $number) => (bool) preg_match('/^INV-\d{6}$/', $number)))->toBeTrue()
        ->and($numbers->unique()->count())->toBe($numbers->count())
        ->and(Invoice::query()->where('invoice_number', 'like', 'INV-CF-%')->count())->toBe(0);

    expect(
        Invoice::query()
            ->where('contract_id', $saleFull->id)
            ->whereHas('items', fn ($q) => $q->where('description', 'Sale booking deposit'))
            ->exists()
    )->toBeTrue();

    expect(
        Invoice::query()
            ->where('contract_id', $saleFull->id)
            ->whereHas('items', fn ($q) => $q->where('description', 'Sale purchase settlement'))
            ->exists()
    )->toBeTrue();

    expect(
        Invoice::query()
            ->where('contract_id', $saleInstallment->id)
            ->whereNotNull('billing_month')
            ->exists()
    )->toBeTrue();

    expect(
        Invoice::query()
            ->where('contract_id', $rent->id)
            ->whereNotNull('billing_month')
            ->exists()
    )->toBeTrue();
});

test('repeated financial seeding does not duplicate invoices or break payment links', function () {
    ['admin' => $admin, 'rent' => $rent] = seedInvoiceNumberFixtures();

    $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
    $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
    $support = new ContractFinancialHistorySeederSupport(
        $admin,
        $chargeTypes,
        $paymentMethods,
        Carbon::parse('2026-09-24'),
    );

    $support->reconcileContracts(collect([$rent->fresh(['room'])]));
    $firstCount = Invoice::query()->where('contract_id', $rent->id)->count();
    $firstIds = Invoice::query()->where('contract_id', $rent->id)->orderBy('id')->pluck('id')->all();
    $firstNumbers = Invoice::query()->where('contract_id', $rent->id)->orderBy('id')->pluck('invoice_number')->all();

    $support->reconcileContracts(collect([$rent->fresh(['room'])]));

    $secondCount = Invoice::query()->where('contract_id', $rent->id)->count();
    $secondIds = Invoice::query()->where('contract_id', $rent->id)->orderBy('id')->pluck('id')->all();
    $secondNumbers = Invoice::query()->where('contract_id', $rent->id)->orderBy('id')->pluck('invoice_number')->all();

    expect($secondCount)->toBe($firstCount)
        ->and($secondIds)->toBe($firstIds)
        ->and($secondNumbers)->toBe($firstNumbers);

    $hasDraft = Invoice::query()->where('contract_id', $rent->id)->where('status', 'draft')->exists();
    $hasPaidHistory = Invoice::query()->where('contract_id', $rent->id)->where('status', 'paid')->exists();
    expect($hasDraft || $hasPaidHistory)->toBeTrue();

    $invoice = Invoice::query()->where('contract_id', $rent->id)->where('status', '!=', 'draft')->first()
        ?? Invoice::query()->where('contract_id', $rent->id)->first();
    if ($invoice && $invoice->status !== 'draft') {
        expect(Payment::query()->where('invoice_id', $invoice->id)->exists())->toBeTrue();

        $payment = Payment::query()->where('invoice_id', $invoice->id)->where('status', 'approved')->first();
        if ($payment) {
            expect(Receipt::query()->where('payment_id', $payment->id)->exists())->toBeTrue();
        }
    }
});

test('latest generated rent cycle can remain draft for invoice approval queue', function () {
    ['admin' => $admin, 'saleInstallment' => $saleInstallment, 'rent' => $rent] = seedInvoiceNumberFixtures();

    $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
    $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
    $support = new ContractFinancialHistorySeederSupport(
        $admin,
        $chargeTypes,
        $paymentMethods,
        Carbon::parse('2026-09-24'),
    );

    $contracts = collect([$saleInstallment->fresh(['room']), $rent->fresh(['room'])]);
    $support->reconcileContracts($contracts);

    $asOf = Carbon::parse('2026-09-24');
    $drafts = Invoice::query()
        ->whereIn('contract_id', $contracts->pluck('id'))
        ->where('status', 'draft')
        ->whereNotNull('billing_month')
        ->get();

    // At least one of the fixture contracts falls into a draft-latest scenario bucket.
    expect($drafts->count())->toBeGreaterThan(0);

    foreach ($drafts as $draft) {
        expect($draft->issued_date)->toBeNull()
            ->and(Payment::query()->where('invoice_id', $draft->id)->count())->toBe(0);

        $due = Carbon::parse($draft->due_date);
        $generate = ContractInvoiceSchedule::generateDateForDueDate($due);
        expect($generate->lessThanOrEqualTo($asOf))->toBeTrue()
            ->and((int) $generate->diffInDays($due))->toBe(ContractInvoiceSchedule::GENERATE_DAYS_BEFORE_DUE);
    }
});

test('legacy INV-CF numbers are normalized in place without changing money', function () {
    seedInvoiceNumberFixtures();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $contract = Contract::query()->where('contract_number', 'R-900001')->firstOrFail();

    $invoice = Invoice::query()->create([
        'contract_id' => $contract->id,
        'created_by' => $admin->id,
        'invoice_number' => 'INV-CF-R-900001-RENT-202609',
        'type' => 'rent',
        'issued_date' => '2026-09-01',
        'due_date' => '2026-09-05',
        'late_fee' => 0,
        'total_amount' => 500000,
        'status' => 'issued',
        'billing_month' => '2026-09-01',
    ]);

    $renamed = BillingSeederSupport::normalizeLegacySeedInvoiceNumbers();

    expect($renamed)->toBeGreaterThan(0);

    $invoice->refresh();

    expect($invoice->invoice_number)->toMatch('/^INV-\d{6}$/')
        ->and((float) $invoice->total_amount)->toBe(500000.0)
        ->and($invoice->id)->toBe($invoice->id);
});
