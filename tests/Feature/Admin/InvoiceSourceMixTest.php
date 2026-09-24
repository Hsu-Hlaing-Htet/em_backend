<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityType;
use App\Services\InvoiceService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function seedInvoiceSourceMixStack(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    Auth::login($admin);

    $building = Building::query()->create([
        'building_name' => 'Source Mix Residence',
        'location' => 'Yangon',
    ]);

    $saleRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-701',
        'floor_number' => 7,
        'type' => 'sale',
        'status' => 'occupied',
        'area_sqft' => 1100,
        'sale_price' => 90000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 9000000,
    ]);

    $rentRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'B-701',
        'floor_number' => 7,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 775000,
        'rent_deposit_price' => 1550000,
        'booking_deposit_price' => 0,
    ]);

    $utilityRoom = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'C-701',
        'floor_number' => 7,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 850,
        'sale_price' => 0,
        'rent_price' => 650000,
        'rent_deposit_price' => 1300000,
        'booking_deposit_price' => 0,
    ]);

    $fullPlan = PaymentPlan::query()->firstOrCreate(
        ['name' => 'Source Mix Full'],
        ['payment_type' => 'full', 'duration_months' => null, 'interest_percentage' => 0, 'status' => 'active'],
    );

    $installmentPlan = PaymentPlan::query()->firstOrCreate(
        ['name' => 'Source Mix 12m'],
        ['payment_type' => 'installment', 'duration_months' => 12, 'interest_percentage' => 0, 'status' => 'active'],
    );

    foreach ([
        ['name' => 'Monthly Rent', 'slug' => 'monthly-rent'],
        ['name' => 'Booking Deposit', 'slug' => 'booking-deposit'],
        ['name' => 'Sale Installment', 'slug' => 'sale-installment'],
        ['name' => 'Utility Charges', 'slug' => 'utility-charges'],
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

    $utilityType = UtilityType::query()->firstOrCreate(
        ['slug' => 'electricity'],
        ['name' => 'Electricity', 'status' => 'active'],
    );

    $sale = Contract::query()->create([
        'contract_number' => 'S-910001',
        'user_id' => $customer->id,
        'room_id' => $saleRoom->id,
        'payment_plan_id' => $installmentPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 90000000,
        'deposit_amount' => 9000000,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 10,
        'start_date' => '2026-03-10',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo sale contract (active).',
    ]);

    $rent = Contract::query()->create([
        'contract_number' => 'R-910001',
        'user_id' => $customer->id,
        'room_id' => $rentRoom->id,
        'payment_plan_id' => $fullPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 9300000,
        'deposit_amount' => 1550000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 20,
        'start_date' => '2026-05-20',
        'end_date' => '2027-05-19',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo rent contract (active).',
    ]);

    $utilityContract = Contract::query()->create([
        'contract_number' => 'R-910002',
        'user_id' => $customer->id,
        'room_id' => $utilityRoom->id,
        'payment_plan_id' => $fullPlan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 7800000,
        'deposit_amount' => 1300000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 15,
        'start_date' => '2026-06-15',
        'end_date' => '2027-06-14',
        'status' => Contract::STATUS_ACTIVE,
        'remark' => 'Bulk demo rent contract (active).',
    ]);

    $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
    $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
    $support = new ContractFinancialHistorySeederSupport(
        $admin,
        $chargeTypes,
        $paymentMethods,
        Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF),
    );
    $support->reconcileContracts(collect([
        $sale->fresh(['room']),
        $rent->fresh(['room']),
        $utilityContract->fresh(['room']),
    ]));

    $paidHost = Invoice::query()
        ->where('contract_id', $utilityContract->id)
        ->where('status', Invoice::STATUS_PAID)
        ->whereNotNull('billing_month')
        ->orderBy('id')
        ->firstOrFail();

    $utility = Utility::query()->create([
        'room_id' => $utilityRoom->id,
        'contract_id' => $utilityContract->id,
        'invoice_id' => $paidHost->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'billing_month' => $paidHost->billing_month->toDateString(),
        'reading_date' => Carbon::parse($paidHost->billing_month)->endOfMonth()->toDateString(),
        'status' => 'approved',
        'total_amount' => 85000,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $utility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 100,
        'current_reading' => 185,
        'usage' => 85,
        'unit_price' => 1000,
        'amount' => 85000,
    ]);

    $utilityCharge = ChargeType::query()->where('slug', 'utility-charges')->first();
    InvoiceItem::query()->create([
        'invoice_id' => $paidHost->id,
        'charge_type_id' => $utilityCharge?->id,
        'description' => 'Electricity',
        'previous_reading' => 100,
        'current_reading' => 185,
        'usage' => 85,
        'unit_price' => 1000,
        'amount' => 85000,
    ]);
    $paidHost->update([
        'utility_id' => $utility->id,
        'total_amount' => round((float) $paidHost->total_amount + 85000, 2),
    ]);
    $utilityInvoice = $paidHost->fresh();

    $draftHost = Invoice::query()
        ->with('contract')
        ->where('contract_id', $utilityContract->id)
        ->where('status', Invoice::STATUS_DRAFT)
        ->whereNotNull('billing_month')
        ->orderByDesc('id')
        ->first();

    if (! $draftHost) {
        $draftHost = Invoice::query()
            ->with('contract')
            ->where('contract_id', $rent->id)
            ->where('status', Invoice::STATUS_DRAFT)
            ->whereNotNull('billing_month')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    $pendingUtility = Utility::query()->create([
        'room_id' => $draftHost->contract->room_id,
        'contract_id' => $draftHost->contract_id,
        'created_by' => $admin->id,
        'billing_month' => $draftHost->billing_month->toDateString(),
        'reading_date' => Carbon::parse($draftHost->billing_month)->endOfMonth()->toDateString(),
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'total_amount' => 72000,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $pendingUtility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 185,
        'current_reading' => 257,
        'usage' => 72,
        'unit_price' => 1000,
        'amount' => 72000,
    ]);

    $draftUtilityInvoice = app(InvoiceService::class)->generateFromUtility(
        $pendingUtility->fresh(['items.utilityType', 'room'])
    );

    return compact(
        'admin',
        'customer',
        'building',
        'sale',
        'rent',
        'utilityContract',
        'utility',
        'utilityInvoice',
        'draftUtilityInvoice',
        'saleRoom',
        'rentRoom',
        'utilityRoom',
        'support',
    );
}

test('global invoice list includes sale rent and utility invoices', function (): void {
    $stack = seedInvoiceSourceMixStack();
    $service = app(InvoiceService::class);

    $page = $service->paginate(['per_page' => 100]);
    $ids = collect($page->items())->pluck('id');

    expect($ids)->toContain($stack['utilityInvoice']->id);

    $salePresent = Invoice::query()
        ->whereIn('id', $ids)
        ->where('type', 'sale')
        ->where('contract_id', $stack['sale']->id)
        ->exists();
    $rentPresent = Invoice::query()
        ->whereIn('id', $ids)
        ->where('type', 'rent')
        ->where('contract_id', $stack['rent']->id)
        ->where('status', '!=', Invoice::STATUS_DRAFT)
        ->exists();

    expect($salePresent)->toBeTrue()
        ->and($rentPresent)->toBeTrue();
});

test('building and room filters include utility-linked invoices', function (): void {
    $stack = seedInvoiceSourceMixStack();
    $service = app(InvoiceService::class);

    $byBuilding = $service->paginate([
        'building_id' => $stack['building']->id,
        'per_page' => 100,
    ]);
    $byRoom = $service->paginate([
        'room_id' => $stack['utilityRoom']->id,
        'per_page' => 100,
    ]);

    expect(collect($byBuilding->items())->pluck('id'))->toContain($stack['utilityInvoice']->id)
        ->and(collect($byRoom->items())->pluck('id'))->toContain($stack['utilityInvoice']->id);
});

test('paid utility and rent invoices remain visible in the global list', function (): void {
    $stack = seedInvoiceSourceMixStack();
    $service = app(InvoiceService::class);

    $paidRent = Invoice::query()
        ->where('contract_id', $stack['rent']->id)
        ->where('status', Invoice::STATUS_PAID)
        ->first();

    expect($paidRent)->not->toBeNull();

    $page = $service->paginate(['per_page' => 200]);
    $ids = collect($page->items())->pluck('id');

    expect($ids)->toContain($stack['utilityInvoice']->id)
        ->and($ids)->toContain($paidRent->id)
        ->and($stack['utilityInvoice']->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('invoice approval queue supports sale rent and utility drafts', function (): void {
    $stack = seedInvoiceSourceMixStack();
    $service = app(InvoiceService::class);

    $drafts = $service->paginate([
        'payment_status' => 'draft',
        'per_page' => 200,
    ]);
    $draftIds = collect($drafts->items())->pluck('id');

    expect($draftIds)->toContain($stack['draftUtilityInvoice']->id);

    $saleDraft = Invoice::query()
        ->where('contract_id', $stack['sale']->id)
        ->where('status', Invoice::STATUS_DRAFT)
        ->exists();
    $rentDraft = Invoice::query()
        ->where('contract_id', $stack['rent']->id)
        ->where('status', Invoice::STATUS_DRAFT)
        ->exists();

    expect($saleDraft || $rentDraft)->toBeTrue();
});

test('no logical duplicate billing invoices for the same contract period', function (): void {
    $stack = seedInvoiceSourceMixStack();

    $duplicates = Invoice::query()
        ->whereIn('contract_id', [
            $stack['sale']->id,
            $stack['rent']->id,
            $stack['utilityContract']->id,
        ])
        ->whereNotNull('billing_month')
        ->where('status', '!=', Invoice::STATUS_CANCELLED)
        ->selectRaw('contract_id, billing_month, count(*) as c')
        ->groupBy('contract_id', 'billing_month')
        ->having('c', '>', 1)
        ->count();

    expect($duplicates)->toBe(0);
});

test('canonical invoice numbers stay unique and seeding is idempotent', function (): void {
    $stack = seedInvoiceSourceMixStack();

    $numbers = Invoice::query()->pluck('invoice_number');
    expect($numbers->unique()->count())->toBe($numbers->count())
        ->and($numbers->every(fn (string $number) => (bool) preg_match('/^INV-\d{6}$/', $number)))->toBeTrue();

    $before = Invoice::query()->where('contract_id', $stack['rent']->id)->orderBy('id')->pluck('invoice_number')->all();
    $stack['support']->reconcileContracts(collect([$stack['rent']->fresh(['room'])]));
    $after = Invoice::query()->where('contract_id', $stack['rent']->id)->orderBy('id')->pluck('invoice_number')->all();

    expect($after)->toBe($before);
});

test('completed sale installments do not pre-generate future months past demo as-of', function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Future Cap Tower',
        'location' => 'Yangon',
    ]);
    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'D-701',
        'floor_number' => 7,
        'type' => 'sale',
        'status' => 'sold',
        'area_sqft' => 1000,
        'sale_price' => 80000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 8000000,
    ]);

    $plan = PaymentPlan::query()->firstOrCreate(
        ['name' => 'Future Cap 12m'],
        ['payment_type' => 'installment', 'duration_months' => 12, 'interest_percentage' => 0, 'status' => 'active'],
    );

    foreach (['booking-deposit', 'sale-installment'] as $slug) {
        ChargeType::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'status' => 'active'],
        );
    }
    PaymentMethod::query()->firstOrCreate(['slug' => 'kbz-pay'], ['name' => 'KBZ Pay', 'status' => 'active']);

    $contract = Contract::query()->create([
        'contract_number' => 'S-910099',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'payment_plan_id' => $plan->id,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'contract_total' => 80000000,
        'deposit_amount' => 8000000,
        'type' => 'sale',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 14,
        'start_date' => '2026-01-14',
        'status' => Contract::STATUS_COMPLETED,
        'remark' => 'Bulk demo sale contract (completed).',
    ]);

    $support = new ContractFinancialHistorySeederSupport(
        $admin,
        ChargeType::query()->where('status', 'active')->get()->keyBy('slug'),
        PaymentMethod::query()->orderBy('id')->get(),
        Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF),
    );
    $support->reconcileContracts(collect([$contract->fresh(['room'])]));

    $asOf = Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF);
    $futureGenerated = Invoice::query()
        ->where('contract_id', $contract->id)
        ->whereNotNull('billing_month')
        ->get()
        ->filter(function (Invoice $invoice) use ($asOf): bool {
            $generate = Carbon::parse($invoice->due_date)->subDays(7);

            return $generate->gt($asOf);
        });

    expect($futureGenerated)->toBeEmpty();

    expect(
        Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereHas('items', fn ($q) => $q->where('description', 'Sale installment early settlement'))
            ->exists()
    )->toBeTrue();
});
