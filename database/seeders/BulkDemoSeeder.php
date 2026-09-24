<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\Profile;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Carbon\Carbon;
use Database\Seeders\Support\BillingSeederSupport;
use Database\Seeders\Support\ConsolidatedBillingSeederSupport;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Database\Seeders\Support\MyanmarSampleData;
use Database\Seeders\Support\SeedNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Idempotent volume expansion toward ~1000 demo records.
 *
 * Uses deterministic unique keys (emails, building names, room numbers,
 * contract/invoice/receipt numbers, payment note keys) so repeated seeds
 * never duplicate and never touch non-bulk manually entered rows.
 */
class BulkDemoSeeder extends Seeder
{
    private const BULK_IMPORT_READY_THROUGH = '2026-09-01';

    /** Target ~180 customers for pagination and portal variety. */
    private const CUSTOMER_TARGET = 180;

    /** Enough rooms for ~230 contracts without active-room conflicts. */
    private const ROOM_TARGET = 320;

    private const CONTRACT_TARGET = 230;

    /** Legacy volume knobs (financial history now drives invoice/payment counts). */
    private const INVOICE_TARGET = 1000;

    private const PAYMENT_TARGET = 700;

    private const UTILITY_TARGET = 420;

    private const MAINTENANCE_TARGET = 200;

    /** Bulk BL-### demo tickets (workflow WF-* rows are seeded separately). */
    private const BULK_MAINTENANCE_COUNT = 200;

    private User $admin;

    private ?PaymentPlan $fullPlan = null;

    private ?PaymentPlan $installmentPlan = null;

    /** @var Collection<string, ChargeType> */
    private Collection $chargeTypes;

    /** @var Collection<int, PaymentMethod> */
    private Collection $paymentMethods;

    /** @var Collection<int, UtilityType> */
    private Collection $utilityTypes;

    /** @var array<int, float> */
    private array $utilityRatesByType = [];

    public function run(): void
    {
        $started = microtime(true);

        $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->first();
        if (! $admin) {
            $this->command?->warn('BulkDemoSeeder skipped: admin user missing.');

            return;
        }

        $this->admin = $admin;
        BillingSeederSupport::resetSequences();
        $this->fullPlan = PaymentPlan::query()->where('payment_type', 'full')->first();
        $this->installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->orderByDesc('duration_months')
            ->first();
        $this->chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
        $this->paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('id')->get();
        $this->utilityTypes = UtilityType::query()->where('status', 'active')->orderBy('id')->get();
        $this->utilityRatesByType = UtilityRate::query()
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->get()
            ->unique('utility_type_id')
            ->mapWithKeys(fn (UtilityRate $rate) => [$rate->utility_type_id => (float) $rate->unit_price])
            ->all();

        $customers = $this->seedCustomers();
        $buildings = $this->seedBuildings();
        $rooms = $this->seedRooms($buildings);
        $contracts = $this->seedContracts($customers, $rooms);
        $this->seedUtilities($contracts, $rooms);
        $this->normalizeUtilityHistories();
        $this->seedInvoicesPaymentsReceipts($contracts);
        $this->normalizeUtilityHistories();
        (new UtilityInvoiceConsistencySeeder)->run();
        $this->seedMaintenance($contracts);
        $this->syncBulkRoomStatuses();

        $elapsed = round(microtime(true) - $started, 2);
        $this->command?->info(sprintf(
            'BulkDemoSeeder finished in %ss (customers=%d buildings=%d rooms=%d contracts=%d).',
            $elapsed,
            $customers->count(),
            $buildings->count(),
            $rooms->count(),
            $contracts->count(),
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function seedCustomers(): Collection
    {
        $role = Role::findByName(Role::CUSTOMER);
        if (! $role) {
            return collect();
        }

        $password = 'p@ssword';
        $existing = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))
            ->count();

        $needed = max(0, self::CUSTOMER_TARGET - $existing);
        // Continue after previously seeded bulk customers (persona accounts occupy lower indices).
        $personaBaseline = 20;
        $startIndex = 21 + max(0, $existing - $personaBaseline);
        $bulk = MyanmarSampleData::bulkCustomers($needed, $startIndex);

        foreach (array_chunk($bulk, 25) as $chunk) {
            foreach ($chunk as $row) {
                $user = User::query()->firstOrCreate(
                    ['email' => $row['email']],
                    [
                        'role_id' => $role->id,
                        'name' => $row['name'],
                        'password' => $password,
                    ],
                );

                Profile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone' => $row['phone'],
                        'nrc' => $row['nrc'],
                        'dob' => $row['dob'],
                        'gender' => $row['gender'],
                        'address' => $row['address'],
                        'avatar_path' => null,
                    ],
                );
            }
        }

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Building>
     */
    private function seedBuildings(): Collection
    {
        foreach (MyanmarSampleData::bulkBuildings() as $building) {
            Building::query()->firstOrCreate(
                ['building_name' => $building['building_name']],
                [
                    'location' => $building['location'],
                    'description' => $building['description'],
                ],
            );
        }

        return Building::query()->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Building>  $buildings
     * @return Collection<int, Room>
     */
    private function seedRooms(Collection $buildings): Collection
    {
        $existing = Room::query()->count();
        $needed = max(0, self::ROOM_TARGET - $existing);
        if ($needed === 0) {
            return Room::query()->orderBy('id')->get();
        }

        // Prefer bulk buildings for new rooms; fall back to all buildings.
        $bulkNames = collect(MyanmarSampleData::bulkBuildings())->pluck('building_name');
        $targetBuildings = $buildings->filter(fn (Building $b) => $bulkNames->contains($b->building_name))->values();
        if ($targetBuildings->isEmpty()) {
            $targetBuildings = $buildings->values();
        }

        // Varied occupancy targets per building index (dashboard occupancy mix).
        $occupancyBias = [0.85, 0.70, 0.55, 0.40, 0.30, 0.65, 0.50, 0.25];
        $created = 0;
        $buildingCount = $targetBuildings->count();

        for ($i = 0; $i < $needed; $i++) {
            $building = $targetBuildings[$i % $buildingCount];
            $buildingIndex = $i % $buildingCount;
            $code = chr(65 + ($buildingIndex % 26));
            $roomNumber = SeedNumberGenerator::roomNumberForIndex($i, $buildingIndex, $buildingCount);

            // Skip if room number already exists in this building (collision).
            if (Room::query()->where('building_id', $building->id)->where('room_number', $roomNumber)->exists()) {
                $roomNumber = sprintf('%s-%d', $code, 101 + intdiv($i, $buildingCount) + 500);
            }

            $floor = max(1, intdiv((int) substr($roomNumber, strpos($roomNumber, '-') + 1), 100));

            $typeRoll = $i % 10;
            $type = match (true) {
                $typeRoll < 5 => 'rent',
                $typeRoll < 8 => 'sale',
                default => 'both',
            };

            $area = 650 + (($i * 37) % 900);
            $rent = $type === 'sale' ? 0 : round(350000 + (($i * 17500) % 450000), -3);
            $sale = $type === 'rent' ? 0 : round(120000000 + (($i * 3500000) % 280000000), -3);

            // Leave as available here; contracts seeder sets matching statuses.
            Room::query()->firstOrCreate(
                [
                    'building_id' => $building->id,
                    'room_number' => $roomNumber,
                ],
                [
                    'floor_number' => $floor,
                    'width_ft' => round(sqrt($area) * 0.9, 2),
                    'length_ft' => round(sqrt($area) * 1.1, 2),
                    'area_sqft' => $area,
                    'description' => sprintf(
                        '%s unit on floor %d of %s (bulk demo). Occupancy bias %.0f%%.',
                        ucfirst($type),
                        $floor,
                        $building->building_name,
                        ($occupancyBias[$buildingIndex] ?? 0.5) * 100,
                    ),
                    'type' => $type,
                    'status' => 'available',
                    'sale_price' => $sale,
                    'rent_price' => $rent,
                    'rent_deposit_price' => $rent > 0 ? round($rent * 2, 2) : 0,
                    'booking_deposit_price' => $sale > 0 ? round($sale * 0.1, 2) : 0,
                ],
            );
            $created++;
        }

        $this->command?->info("Bulk rooms ensured (+{$created} attempted toward ".self::ROOM_TARGET.').');

        return Room::query()->with('building')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function seedContracts(Collection $customers, Collection $rooms): Collection
    {
        $existing = Contract::query()->count();
        $needed = max(0, self::CONTRACT_TARGET - $existing);
        if ($needed === 0 || $customers->isEmpty()) {
            return Contract::query()->with('room')->orderBy('id')->get();
        }

        // Prefer rooms without active/approved contracts for new placements.
        // Never claim workflow-scenario rooms or rooms marked maintenance.
        $busyRoomIds = Contract::query()
            ->whereIn('status', ['active', 'approved', 'pending'])
            ->pluck('room_id')
            ->unique()
            ->all();

        $workflowRoomIds = Contract::query()
            ->where(function ($query) {
                $query->where('remark', 'not like', 'Bulk demo%');
            })
            ->pluck('room_id')
            ->unique()
            ->all();

        $availableRooms = $rooms
            ->reject(fn (Room $room) => in_array($room->id, $busyRoomIds, true)
                || in_array($room->id, $workflowRoomIds, true)
                || $room->status === 'maintenance')
            ->values();
        if ($availableRooms->isEmpty()) {
            $availableRooms = $rooms
                ->reject(fn (Room $room) => in_array($room->id, $workflowRoomIds, true) || $room->status === 'maintenance')
                ->values();
        }

        $statusCycle = [
            // rent-heavy mix for dashboard occupancy + approvals
            ['type' => 'rent', 'status' => 'active'],
            ['type' => 'rent', 'status' => 'active'],
            ['type' => 'rent', 'status' => 'active'],
            ['type' => 'rent', 'status' => 'pending'],
            ['type' => 'rent', 'status' => 'pending'],
            ['type' => 'rent', 'status' => 'completed'],
            ['type' => 'rent', 'status' => 'rejected'],
            ['type' => 'sale', 'status' => 'active'],
            ['type' => 'sale', 'status' => 'pending'],
            ['type' => 'sale', 'status' => 'completed'],
            ['type' => 'sale', 'status' => 'rejected'],
        ];

        $created = 0;
        $usedRoomIds = [];

        for ($i = 0; $i < $needed; $i++) {
            $spec = $statusCycle[$i % count($statusCycle)];
            $customer = $customers[$i % $customers->count()];

            $candidates = $availableRooms->reject(fn (Room $room) => in_array($room->id, $usedRoomIds, true))->values();
            if ($candidates->isEmpty()) {
                $candidates = $availableRooms->values();
            }

            $room = $candidates[$i % $candidates->count()];

            // Match room type preference when possible.
            if ($spec['type'] === 'rent' && ! in_array($room->type, ['rent', 'both'], true)) {
                $alt = $candidates->first(fn (Room $r) => in_array($r->type, ['rent', 'both'], true));
                if ($alt) {
                    $room = $alt;
                }
            }
            if ($spec['type'] === 'sale' && ! in_array($room->type, ['sale', 'both'], true)) {
                $alt = $candidates->first(fn (Room $r) => in_array($r->type, ['sale', 'both'], true));
                if ($alt) {
                    $room = $alt;
                }
            }

            $number = $spec['type'] === 'sale'
                ? BillingSeederSupport::nextSaleContractNumber()
                : BillingSeederSupport::nextRentContractNumber();

            if (Contract::query()->where('contract_number', $number)->exists()) {
                continue;
            }

            // Spread start dates across 18 months relative to stable demo as-of date.
            $asOf = Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF)->startOfDay();
            $monthsAgo = 2 + ($i % 16);
            $start = $asOf->copy()->subMonths($monthsAgo)->startOfMonth()->addDays($i % 28);
            $isExpiringSoon = $spec['status'] === 'active' && $i % 5 === 0;
            $duration = $spec['type'] === 'rent' ? 12 : null;
            $end = match (true) {
                $spec['status'] === 'completed' && $spec['type'] === 'rent' => $start->copy()->addMonths(12)->min($asOf),
                $spec['status'] === 'completed' && $spec['type'] === 'sale' => $start->copy()->addMonths(6)->min($asOf),
                $isExpiringSoon => $asOf->copy()->addDays(15 + ($i % 45)),
                $spec['type'] === 'rent' => $start->copy()->addMonths(12),
                $spec['type'] === 'sale' && $spec['status'] === 'approved' => $start->copy()->addMonths(18),
                default => null,
            };

            $rent = (float) $room->rent_price;
            $sale = (float) $room->sale_price;
            $total = $spec['type'] === 'rent'
                ? round(max($rent, 300000) * 12, 2)
                : round(max($sale, 100000000), 2);
            $deposit = $spec['type'] === 'rent'
                ? (float) ($room->rent_deposit_price ?: $rent * 2)
                : (float) ($room->booking_deposit_price ?: $sale * 0.1);

            $useInstallment = $spec['type'] === 'sale'
                && $this->installmentPlan
                && (
                    in_array($spec['status'], ['approved', 'pending'], true)
                    || (in_array($spec['status'], ['active', 'completed'], true) && ($i % 2 === 0))
                );
            $approvedStatuses = ['active', 'approved', 'completed', 'rejected'];

            $secondCustomer = null;
            if (
                in_array($spec['status'], ['active', 'completed', 'pending'], true)
                && ($i % 6 === 0)
                && $customers->count() > 1
            ) {
                $candidate = $customers[($i + 19) % $customers->count()];
                if ((int) $candidate->id !== (int) $customer->id) {
                    $secondCustomer = $candidate;
                }
            }

            $contract = Contract::query()->create([
                'contract_number' => $number,
                'user_id' => $customer->id,
                'second_user_id' => $secondCustomer?->id,
                'room_id' => $room->id,
                'payment_plan_id' => $useInstallment ? $this->installmentPlan?->id : $this->fullPlan?->id,
                'created_by' => $this->admin->id,
                'approved_by' => in_array($spec['status'], $approvedStatuses, true) ? $this->admin->id : null,
                'approved_at' => in_array($spec['status'], $approvedStatuses, true) ? $start->copy()->addDay() : null,
                'contract_total' => $total,
                'deposit_amount' => round($deposit, 2),
                'type' => $spec['type'],
                'payment_type' => $useInstallment ? 'installment' : 'full',
                'duration_months' => $spec['type'] === 'rent' ? $duration : ($useInstallment ? $this->installmentPlan?->duration_months : null),
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'billing_day' => $spec['type'] === 'rent' || $useInstallment ? 5 : null,
                'status' => $spec['status'],
                'remark' => sprintf('Bulk demo %s contract (%s).', $spec['type'], $spec['status']),
                'created_at' => $start->copy()->subDays(3 + ($i % 5)),
                'updated_at' => $start->copy()->addDay(),
            ]);

            $roomStatus = match ($spec['status']) {
                'pending' => $spec['type'] === 'sale' ? 'reserved' : 'available',
                'active' => 'occupied',
                'approved' => 'reserved',
                'completed' => $spec['type'] === 'sale' ? 'sold' : 'available',
                'rejected', 'draft' => 'available',
                default => 'available',
            };
            $room->update(['status' => $roomStatus]);
            if (in_array($spec['status'], ['active', 'approved', 'pending'], true)) {
                $usedRoomIds[] = $room->id;
            }
            $created++;
        }

        $this->command?->info("Bulk contracts created: {$created}");

        return Contract::query()->with(['room', 'user', 'secondUser'])->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Room>  $rooms
     */
    private function seedUtilities(Collection $contracts, Collection $rooms): void
    {
        $existing = Utility::query()->count();
        $needed = max(0, self::UTILITY_TARGET - $existing);
        if ($needed === 0 || $this->utilityTypes->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'approved', 'approved', 'draft', 'rejected'];
        $created = 0;
        $activeContracts = $contracts->where('status', Contract::STATUS_ACTIVE)->values();
        $candidates = collect();

        foreach ($activeContracts as $contractIndex => $contract) {
            $room = $contract->room;
            if (! $room) {
                continue;
            }

            foreach ($this->bulkUtilityMonthsForContract($contract) as $monthIndex => $month) {
                $candidates->push([
                    'contract' => $contract,
                    'room' => $room,
                    'month' => $month,
                    'month_index' => $monthIndex,
                    'status' => $statuses[($contractIndex + $monthIndex) % count($statuses)],
                ]);
            }
        }

        $candidates = $candidates->sortBy([
            fn (array $candidate) => $candidate['room']->id,
            fn (array $candidate) => $candidate['month']->toDateString(),
            fn (array $candidate) => $candidate['contract']->id,
        ])->values();

        foreach ($candidates as $candidate) {
            $contract = $candidate['contract'];
            $room = $candidate['room'];
            $month = $candidate['month'];
            $monthIndex = $candidate['month_index'];
            $status = $candidate['status'];

            $utility = Utility::query()
                ->where('room_id', $room->id)
                ->whereDate('billing_month', $month->toDateString())
                ->first();

            if ($utility) {
                $utility->update([
                    'room_id' => $room->id,
                    'reading_date' => $month->copy()->addMonth()->startOfMonth()->toDateString(),
                    'status' => $status,
                    'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $this->admin->id : null,
                    'approved_at' => in_array($status, ['approved', 'rejected'], true) ? $month->copy()->endOfMonth() : null,
                ]);
            } else {
                $utility = Utility::query()->create([
                    'contract_id' => $contract->id,
                    'room_id' => $room->id,
                    'billing_month' => $month->toDateString(),
                    'reading_date' => $month->copy()->addMonth()->startOfMonth()->toDateString(),
                    'total_amount' => 0,
                    'status' => $status,
                    'created_by' => $this->admin->id,
                    'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $this->admin->id : null,
                    'approved_at' => in_array($status, ['approved', 'rejected'], true) ? $month->copy()->endOfMonth() : null,
                ]);
            }

            if ($utility->items()->exists()) {
                continue;
            }

            $total = 0.0;
            $base = 800 + ($room->id * 11) + ($monthIndex * 45);

            foreach ($this->utilityTypes->take(2) as $index => $type) {
                $previous = $this->latestUtilityReadingBefore($room->id, $type->id, $month)
                    ?? $base + ($index * 250);
                $usage = 40 + (($contractIndex + $monthIndex + $index * 13) % 160);
                $unitPrice = $this->utilityRatesByType[$type->id] ?? [120.0, 85.0][$index] ?? 100.0;
                $amount = round($usage * $unitPrice, 2);
                $total += $amount;

                UtilityItem::query()->create([
                    'utility_id' => $utility->id,
                    'utility_type_id' => $type->id,
                    'previous_reading' => $previous,
                    'current_reading' => $previous + $usage,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);
            }

            $utility->update(['total_amount' => round($total, 2)]);
            $created++;
        }

        $this->command?->info("Bulk utilities created: {$created}");
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function bulkUtilityMonthsForContract(Contract $contract): Collection
    {
        $start = Carbon::parse($contract->start_date)->startOfMonth();
        $end = $this->latestSeedableUtilityMonth();

        if ($contract->type === 'rent' && $contract->end_date) {
            $end = $end->min(Carbon::parse($contract->end_date)->startOfMonth());
        }

        $months = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months;
    }

    private function latestSeedableUtilityMonth(): Carbon
    {
        // Include the demo as-of month so current-cycle Rent invoices can carry
        // Utility charges (previously capped at now()-1 month → always August).
        return Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF)
            ->startOfMonth()
            ->min(Carbon::parse(self::BULK_IMPORT_READY_THROUGH)->startOfMonth());
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    /**
     * Rebuild coherent Invoice → Payment → Receipt histories for bulk contracts
     * using ContractLifecycleService-compatible amounts and DEMO_AS_OF timelines.
     *
     * @param  Collection<int, Contract>  $contracts
     */
    private function seedInvoicesPaymentsReceipts(Collection $contracts): void
    {
        $billable = $contracts
            ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
            ->filter(fn (Contract $contract) => str_starts_with((string) $contract->remark, 'Bulk demo'))
            ->values();

        if ($billable->isEmpty() || ! $this->admin) {
            return;
        }

        $support = new ContractFinancialHistorySeederSupport(
            $this->admin,
            $this->chargeTypes,
            $this->paymentMethods,
            \Illuminate\Support\Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF),
        );

        $stats = $support->reconcileContracts($billable);

        $this->command?->info(sprintf(
            'Bulk financial history: contracts=%d invoices=%d payments=%d receipts=%d',
            $stats['contracts'],
            $stats['invoices'],
            $stats['payments'],
            $stats['receipts'],
        ));
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function seedMaintenance(Collection $contracts): void
    {
        $categoriesBySlug = MaintenanceCategory::query()
            ->where('status', MaintenanceCategory::STATUS_ACTIVE)
            ->get()
            ->keyBy('slug');

        if ($categoriesBySlug->isEmpty()) {
            $this->command?->warn('Bulk maintenance skipped: active maintenance categories missing.');

            return;
        }

        $eligible = $contracts
            ->where('status', Contract::STATUS_ACTIVE)
            ->filter(fn (Contract $contract) => $contract->room_id && $contract->user_id)
            ->values();

        if ($eligible->isEmpty()) {
            $this->command?->warn('Bulk maintenance skipped: no active contracts with rooms.');

            return;
        }

        $catalog = $this->bulkMaintenanceCatalog();
        $jointContract = $eligible->first(fn (Contract $contract) => filled($contract->second_user_id))
            ?? Contract::query()
                ->with(['room', 'user', 'secondUser'])
                ->where('status', Contract::STATUS_ACTIVE)
                ->whereNotNull('second_user_id')
                ->whereHas('room')
                ->whereHas('user')
                ->orderBy('id')
                ->first();

        $expectedTitles = [];
        $synced = 0;

        for ($i = 0; $i < self::BULK_MAINTENANCE_COUNT; $i++) {
            $scenario = $catalog[$i % count($catalog)];
            $category = $categoriesBySlug->get($scenario['category']);

            if (! $category) {
                continue;
            }

            $reference = sprintf('BL-%03d', $i + 1);
            $title = $reference.' '.$scenario['title'];
            $expectedTitles[] = $title;

            $contract = ($i === 0 && $jointContract)
                ? $jointContract
                : $eligible[$i % $eligible->count()];

            // Prefer second-party submitter on the joint-contract sample only.
            $submitterId = ($i === 0 && $jointContract?->second_user_id)
                ? (int) $jointContract->second_user_id
                : (int) $contract->user_id;

            $createdAt = Carbon::parse('2026-07-01')
                ->addDays(($i * 2) % 85)
                ->setTime(8 + ($i % 10), ($i * 7) % 60, 0);

            $status = $scenario['status'];
            $approvedAt = in_array($status, ['in_progress', 'completed', 'rejected'], true)
                ? $createdAt->copy()->addDays(1 + ($i % 3))
                : null;

            MaintenanceRequest::query()->updateOrCreate(
                ['title' => $title],
                [
                    'room_id' => $contract->room_id,
                    'user_id' => $contract->user_id,
                    'created_by' => $submitterId,
                    'approved_by' => $approvedAt ? $this->admin->id : null,
                    'approved_at' => $approvedAt,
                    'maintenance_category_id' => $category->id,
                    'category' => $category->slug,
                    'priority' => $scenario['priority'],
                    'description' => $scenario['description'],
                    'status' => $status,
                    'resolution_note' => $status === 'completed'
                        ? ($scenario['resolution_note'] ?? 'Issue resolved and verified with the resident.')
                        : null,
                    'rejection_reason' => $status === 'rejected'
                        ? ($scenario['rejection_reason'] ?? 'Duplicate ticket already scheduled with the building technician.')
                        : null,
                    'created_at' => $createdAt,
                    'updated_at' => $approvedAt ?? $createdAt,
                ],
            );

            $synced++;
        }

        // Remove obsolete bulk BL-* rows from earlier mismatched title/category cycles.
        $removed = MaintenanceRequest::query()
            ->where('title', 'like', 'BL-%')
            ->when(
                $expectedTitles !== [],
                fn ($query) => $query->whereNotIn('title', $expectedTitles),
            )
            ->delete();

        $this->command?->info(
            "Bulk maintenance ensured/synced: {$synced} (BL-001…BL-"
            .str_pad((string) self::BULK_MAINTENANCE_COUNT, 3, '0', STR_PAD_LEFT)
            .'); obsolete BL rows removed: '.$removed
        );
    }

    /**
     * Title/category/priority/status pairs — never cycle category independently of title.
     *
     * @return list<array{
     *     title: string,
     *     category: string,
     *     priority: string,
     *     status: string,
     *     description: string,
     *     resolution_note?: string|null,
     *     rejection_reason?: string|null
     * }>
     */
    private function bulkMaintenanceCatalog(): array
    {
        return [
            [
                'title' => 'Leaking kitchen faucet',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'completed',
                'description' => 'Water drips continuously from the kitchen tap even when fully closed.',
                'resolution_note' => 'Washer replaced and faucet resealed. Resident confirmed no further drip.',
            ],
            [
                'title' => 'AC not cooling properly',
                'category' => 'hvac',
                'priority' => 'high',
                'status' => 'in_progress',
                'description' => 'Bedroom air conditioner runs but does not produce cold air during afternoon heat.',
            ],
            [
                'title' => 'Corridor light flickering',
                'category' => 'electrical',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Common corridor light outside the unit flickers every few seconds after dark.',
            ],
            [
                'title' => 'Balcony door lock stuck',
                'category' => 'general',
                'priority' => 'high',
                'status' => 'pending',
                'description' => 'Balcony sliding door lock is jammed and the door cannot be secured overnight.',
            ],
            [
                'title' => 'Water heater intermittent',
                'category' => 'appliance',
                'priority' => 'medium',
                'status' => 'completed',
                'description' => 'Bathroom water heater works briefly then shuts off before the tank is hot.',
                'resolution_note' => 'Thermostat reset and heating element checked. Hot water restored.',
            ],
            [
                'title' => 'Bathroom drain clogged',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Water drains very slowly and begins backing up after a few minutes of use.',
            ],
            [
                'title' => 'Socket sparking near TV',
                'category' => 'electrical',
                'priority' => 'high',
                'status' => 'in_progress',
                'description' => 'Living room outlet sparks when the TV plug is inserted. Outlet is currently unused.',
            ],
            [
                'title' => 'Ceiling paint peeling',
                'category' => 'general',
                'priority' => 'low',
                'status' => 'pending',
                'description' => 'Paint is peeling near the living room ceiling corner with no active leak visible.',
            ],
            [
                'title' => 'Toilet not flushing',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'in_progress',
                'description' => 'Master bathroom toilet handle moves but the cistern does not release water.',
            ],
            [
                'title' => 'Refrigerator not cooling',
                'category' => 'appliance',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Kitchen refrigerator runs loudly but the freezer compartment is no longer cold.',
            ],
            [
                'title' => 'Air conditioner making noise',
                'category' => 'hvac',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Living room AC makes a repeating rattle when the fan is on medium speed.',
                'resolution_note' => 'Loose outdoor bracket tightened. Noise no longer present.',
            ],
            [
                'title' => 'Power outlet not working',
                'category' => 'electrical',
                'priority' => 'medium',
                'status' => 'rejected',
                'description' => 'Bedroom wall outlet has no power while neighboring outlets still work.',
                'rejection_reason' => 'Duplicate of an earlier ticket already scheduled with the building electrician.',
            ],
            [
                'title' => 'Low water pressure',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Shower water pressure drops sharply between 06:00 and 08:00 each morning.',
            ],
            [
                'title' => 'Washing machine not starting',
                'category' => 'appliance',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Washer powers on but the start button does not begin a wash cycle.',
                'resolution_note' => 'Door latch sensor cleaned and cycle restarted successfully.',
            ],
            [
                'title' => 'AC leaking water',
                'category' => 'hvac',
                'priority' => 'high',
                'status' => 'pending',
                'description' => 'Indoor AC unit drips water onto the bedroom floor after about 30 minutes of use.',
            ],
            [
                'title' => 'Cabinet hinge loose',
                'category' => 'general',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Kitchen cabinet door hangs unevenly and scrapes the frame when opened.',
                'resolution_note' => 'Hinge screws tightened and door realigned.',
            ],
        ];
    }

    /**
     * Keep bulk-contracted rooms consistent after re-seed (does not touch WF rooms).
     */
    private function syncBulkRoomStatuses(): void
    {
        $bulkContracts = Contract::query()
            ->where('remark', 'like', 'Bulk demo%')
            ->orderByDesc('id')
            ->get()
            ->groupBy('room_id');

        foreach ($bulkContracts as $roomId => $contracts) {
            /** @var Contract|null $primary */
            $primary = $contracts->first(fn (Contract $c) => in_array($c->status, ['active', 'approved', 'pending'], true))
                ?? $contracts->first();

            if (! $primary) {
                continue;
            }

            $status = match ($primary->status) {
                'pending' => $primary->type === 'sale' ? 'reserved' : 'available',
                'active' => 'occupied',
                'approved' => 'reserved',
                'completed' => $primary->type === 'sale' ? 'sold' : 'available',
                default => 'available',
            };

            Room::query()->whereKey($roomId)->whereDoesntHave('contracts', function ($query) {
                $query->where('remark', 'not like', 'Bulk demo%');
            })->update(['status' => $status]);
        }

        // Dedicated maintenance showcase room (no contracts).
        Room::query()
            ->where('room_number', 'A-104')
            ->whereHas('building', fn ($query) => $query->where('building_name', MyanmarSampleData::buildingNameForIndex(0)))
            ->whereDoesntHave('contracts')
            ->update(['status' => 'maintenance']);
    }

    private function findOrCreateBulkUtility(Contract $contract, Carbon $billingMonth, int $seed): ?Utility
    {
        if (! $contract->room_id || $this->utilityTypes->isEmpty()) {
            return null;
        }

        $month = $billingMonth->copy()->startOfMonth();
        $contractStart = Carbon::parse($contract->start_date)->startOfMonth();

        if ($month->lt($contractStart) || $month->gt($this->latestSeedableUtilityMonth())) {
            return null;
        }

        if ($contract->type === 'rent' && $contract->end_date && $month->gt(Carbon::parse($contract->end_date)->startOfMonth())) {
            return null;
        }

        $this->backfillBulkUtilityHistory($contract, $month);

        $attributes = [
            'room_id' => $contract->room_id,
            'reading_date' => $billingMonth->copy()->addMonth()->startOfMonth()->toDateString(),
            'total_amount' => 0,
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => $billingMonth->copy()->endOfMonth(),
        ];

        $utility = Utility::query()
            ->where('room_id', $contract->room_id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->first();

        if ($utility) {
            if ((int) $utility->contract_id !== (int) $contract->id) {
                return null;
            }

            $utility->update($attributes);
        } else {
            $utility = Utility::query()->create([
                'contract_id' => $contract->id,
                'billing_month' => $billingMonth->toDateString(),
                ...$attributes,
            ]);
        }

        if ($utility->items()->exists()) {
            return $utility->fresh('items.utilityType');
        }

        $total = 0.0;
        $base = 900 + ($contract->room_id * 11) + ($month->diffInMonths($contractStart) * 45) + ($seed % 50);

        foreach ($this->utilityTypes->take(2) as $index => $type) {
            $previous = $this->latestUtilityReadingBefore($contract->room_id, $type->id, $month)
                ?? $base + ($index * 220);
            $usage = 35 + (($seed + $index * 11) % 120);
            $unitPrice = $this->utilityRatesByType[$type->id] ?? [120.0, 85.0][$index] ?? 100.0;
            $amount = round($usage * $unitPrice, 2);
            $total += $amount;

            UtilityItem::query()->create([
                'utility_id' => $utility->id,
                'utility_type_id' => $type->id,
                'previous_reading' => $previous,
                'current_reading' => $previous + $usage,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);
        }

        $utility->update(['total_amount' => round($total, 2)]);

        return $utility->fresh('items.utilityType');
    }

    private function backfillBulkUtilityHistory(Contract $contract, Carbon $targetMonth): void
    {
        $cursor = Carbon::parse($contract->start_date)->startOfMonth();

        while ($cursor->lt($targetMonth)) {
            if (! Utility::query()
                ->where('contract_id', $contract->id)
                ->whereDate('billing_month', $cursor->toDateString())
                ->exists()) {
                $this->createBulkUtilityRecord($contract, $cursor, 'approved', $this->admin, $cursor->month);
            }

            $cursor->addMonth();
        }
    }

    private function createBulkUtilityRecord(
        Contract $contract,
        Carbon $billingMonth,
        string $status,
        ?User $approver,
        int $seed,
    ): Utility {
        $utility = Utility::query()->create([
            'contract_id' => $contract->id,
            'room_id' => $contract->room_id,
            'billing_month' => $billingMonth->toDateString(),
            'reading_date' => $billingMonth->copy()->addMonth()->startOfMonth()->toDateString(),
            'total_amount' => 0,
            'status' => $status,
            'created_by' => $this->admin->id,
            'approved_by' => $approver?->id,
            'approved_at' => $approver ? $billingMonth->copy()->endOfMonth() : null,
        ]);

        $total = 0.0;
        $contractStart = Carbon::parse($contract->start_date)->startOfMonth();
        $base = 900 + ($contract->room_id * 11) + ($billingMonth->diffInMonths($contractStart) * 45) + ($seed % 50);

        foreach ($this->utilityTypes->take(2) as $index => $type) {
            $previous = $this->latestUtilityReadingBefore($contract->room_id, $type->id, $billingMonth)
                ?? $base + ($index * 220);
            $usage = 35 + (($seed + $index * 11) % 120);
            $unitPrice = $this->utilityRatesByType[$type->id] ?? [120.0, 85.0][$index] ?? 100.0;
            $amount = round($usage * $unitPrice, 2);
            $total += $amount;

            UtilityItem::query()->create([
                'utility_id' => $utility->id,
                'utility_type_id' => $type->id,
                'previous_reading' => $previous,
                'current_reading' => $previous + $usage,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);
        }

        $utility->update(['total_amount' => round($total, 2)]);

        return $utility->fresh('items.utilityType');
    }

    private function latestUtilityReadingBefore(int $roomId, int $utilityTypeId, Carbon $month): ?float
    {
        $utility = Utility::query()
            ->where('room_id', $roomId)
            ->whereDate('billing_month', '<', $month->copy()->startOfMonth()->toDateString())
            ->whereHas('items', fn ($query) => $query->where('utility_type_id', $utilityTypeId))
            ->with(['items' => fn ($query) => $query->where('utility_type_id', $utilityTypeId)])
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        return $utility?->items->first()
            ? (float) $utility->items->first()->current_reading
            : null;
    }

    private function normalizeUtilityHistories(): void
    {
        $this->deduplicateUtilityItems();

        UtilityItem::query()
            ->with('utility')
            ->whereHas('utility')
            ->get()
            ->groupBy(fn (UtilityItem $item) => $item->utility->room_id.'|'.$item->utility_type_id)
            ->each(function (Collection $history) {
                $lastReading = null;

                $history
                    ->sortBy(fn (UtilityItem $item) => sprintf(
                        '%s-%010d',
                        $item->utility->billing_month->toDateString(),
                        $item->utility->id,
                    ))
                    ->values()
                    ->each(function (UtilityItem $item) use (&$lastReading) {
                        $previous = $lastReading ?? (float) $item->previous_reading;
                        $usage = max((float) $item->usage, 1.0);
                        $current = $previous + $usage;
                        $amount = round($usage * (float) $item->unit_price, 2);

                        $item->update([
                            'previous_reading' => $previous,
                            'current_reading' => $current,
                            'usage' => $usage,
                            'amount' => $amount,
                        ]);

                        $item->utility->update([
                            'total_amount' => round((float) $item->utility->items()->sum('amount'), 2),
                        ]);

                        $lastReading = $current;
                    });
            });
    }

    private function deduplicateUtilityItems(): void
    {
        UtilityItem::query()
            ->with('utility')
            ->whereHas('utility')
            ->get()
            ->groupBy(fn (UtilityItem $item) => implode('|', [
                $item->utility->room_id,
                $item->utility_type_id,
                $item->utility->billing_month->toDateString(),
            ]))
            ->each(function (Collection $duplicates) {
                $duplicates = $duplicates
                    ->sortBy(fn (UtilityItem $item) => sprintf('%010d-%010d', $item->utility->id, $item->id))
                    ->values();

                if ($duplicates->count() < 2) {
                    return;
                }

                $duplicates->slice(1)->each(function (UtilityItem $item) {
                    $utility = $item->utility;
                    $item->delete();

                    if (! $utility->items()->exists()) {
                        $utility->delete();
                    }
                });
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildBulkConsolidatedItems(Contract $contract, ?Utility $utility, Carbon $billingMonth, bool $isRent): array
    {
        if ($utility && $utility->status === 'approved') {
            return $isRent
                ? ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $utility, $this->chargeTypes, $billingMonth)
                : ConsolidatedBillingSeederSupport::buildSaleConsolidatedItems($contract, $utility, $this->chargeTypes, $billingMonth);
        }

        $amount = $isRent
            ? (float) ($contract->room?->rent_price ?: 450000)
            : ConsolidatedBillingSeederSupport::installmentAmount($contract);
        $chargeId = $isRent
            ? $this->chargeTypes->get('monthly-rent')?->id
            : $this->chargeTypes->get('sale-installment')?->id;

        return [[
            'charge_type_id' => $chargeId,
            'description' => ($isRent ? 'Monthly rent' : 'Sale installment').' — '.$billingMonth->format('F Y'),
            'amount' => round($amount, 2),
        ]];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPaymentOnce(Invoice $invoice, string $noteKey, array $attributes): ?Payment
    {
        $existing = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('note', 'like', $noteKey.'%')
            ->first();

        if ($existing) {
            return null;
        }

        if (! empty($attributes['proof_image_path'])) {
            $attributes['proof_image_path'] = BillingSeederSupport::storePaymentProof((string) $attributes['proof_image_path']);
        }

        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            ...$attributes,
        ]);
    }

    private function createReceiptOnce(
        Payment $payment,
        string $status,
        string $approvalStatus,
        ?Carbon $issuedAt = null,
    ): ?Receipt {
        if ($payment->status !== 'approved') {
            return null;
        }

        $existing = Receipt::query()->where('payment_id', $payment->id)->first();
        if ($existing) {
            return null;
        }

        $number = BillingSeederSupport::nextReceiptNumber();
        while (Receipt::query()->where('receipt_number', $number)->exists()) {
            $number = BillingSeederSupport::nextReceiptNumber();
        }

        return Receipt::query()->create([
            'payment_id' => $payment->id,
            'receipt_number' => $number,
            'created_by' => $this->admin->id,
            'approved_by' => in_array($approvalStatus, ['approved', 'rejected'], true) ? $this->admin->id : null,
            'approved_at' => in_array($approvalStatus, ['approved', 'rejected'], true) ? ($issuedAt ?? now()) : null,
            'receipt_pdf_path' => $status === Receipt::STATUS_ISSUED ? 'receipts/'.$number.'.pdf' : null,
            'status' => $status,
            'approval_status' => $approvalStatus,
            'issued_at' => $status === Receipt::STATUS_ISSUED ? ($issuedAt ?? now()) : null,
            'sent_at' => $status === Receipt::STATUS_ISSUED ? ($issuedAt ?? now()) : null,
            'sent_by' => $status === Receipt::STATUS_ISSUED ? $this->admin->id : null,
        ]);
    }
}
