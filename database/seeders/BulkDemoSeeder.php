<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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
use Database\Seeders\Support\MyanmarSampleData;
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
    private const CUSTOMER_TARGET = 100;

    private const ROOM_TARGET = 160;

    private const CONTRACT_TARGET = 110;

    private const INVOICE_TARGET = 140;

    private const PAYMENT_TARGET = 90;

    private const UTILITY_TARGET = 55;

    private const MAINTENANCE_TARGET = 55;

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
        $this->fullPlan = PaymentPlan::query()->where('payment_type', 'full')->first();
        $this->installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->orderBy('duration_months')
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
        $this->seedInvoicesPaymentsReceipts($contracts);
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
        $startIndex = 21;
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
            $floor = 1 + intdiv($i, $buildingCount) % 12;
            $unit = 1 + ($i % 8);
            $roomNumber = sprintf('%s-%d%02d', $code, $floor, $unit);

            // Skip if room number already exists in this building (collision).
            if (Room::query()->where('building_id', $building->id)->where('room_number', $roomNumber)->exists()) {
                $roomNumber = sprintf('%s-%d%02dB', $code, $floor, $unit);
            }

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
                $query->where('contract_number', 'like', 'S-WF-%')
                    ->orWhere('contract_number', 'like', 'R-WF-%');
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
            ['type' => 'rent', 'status' => 'draft'],
            ['type' => 'rent', 'status' => 'completed'],
            ['type' => 'rent', 'status' => 'rejected'],
            ['type' => 'sale', 'status' => 'approved'],
            ['type' => 'sale', 'status' => 'pending'],
            ['type' => 'sale', 'status' => 'draft'],
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

            $prefix = $spec['type'] === 'sale' ? 'S-BL-' : 'R-BL-';
            $number = $prefix.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);

            if (Contract::query()->where('contract_number', $number)->exists()) {
                continue;
            }

            // Spread start dates across 14 months; some end within next 60 days.
            $monthsAgo = 1 + ($i % 14);
            $start = now()->subMonths($monthsAgo)->startOfMonth()->addDays($i % 20);
            $isExpiringSoon = $spec['status'] === 'active' && $i % 5 === 0;
            $duration = $spec['type'] === 'rent' ? 12 : null;
            $end = match (true) {
                $spec['status'] === 'completed' && $spec['type'] === 'rent' => $start->copy()->addMonths(12),
                $spec['status'] === 'completed' && $spec['type'] === 'sale' => $start->copy()->addMonths(6),
                $isExpiringSoon => now()->addDays(15 + ($i % 45)),
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

            $useInstallment = $spec['type'] === 'sale' && in_array($spec['status'], ['approved', 'pending'], true) && $this->installmentPlan;
            $approvedStatuses = ['active', 'approved', 'completed', 'rejected'];

            $contract = Contract::query()->create([
                'contract_number' => $number,
                'user_id' => $customer->id,
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

        return Contract::query()->with(['room', 'user'])->orderBy('id')->get();
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

        $activeRooms = $contracts
            ->whereIn('status', ['active', 'approved'])
            ->pluck('room')
            ->filter()
            ->unique('id')
            ->values();

        if ($activeRooms->isEmpty()) {
            $activeRooms = $rooms->whereIn('status', ['occupied', 'reserved'])->values();
        }

        $statuses = ['pending', 'approved', 'approved', 'draft', 'rejected'];
        $created = 0;

        for ($i = 0; $i < $needed; $i++) {
            $room = $activeRooms[$i % max(1, $activeRooms->count())];
            $month = now()->subMonths($i % 12)->startOfMonth();
            $status = $statuses[$i % count($statuses)];

            $utility = Utility::query()->firstOrCreate(
                [
                    'room_id' => $room->id,
                    'billing_month' => $month->toDateString(),
                ],
                [
                    'total_amount' => 0,
                    'status' => $status,
                    'created_by' => $this->admin->id,
                    'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $this->admin->id : null,
                    'approved_at' => in_array($status, ['approved', 'rejected'], true) ? $month->copy()->endOfMonth() : null,
                ],
            );

            if ($utility->items()->exists()) {
                continue;
            }

            $total = 0.0;
            $base = 800 + ($room->id * 11) + ((int) $month->format('n') * 17);

            foreach ($this->utilityTypes->take(2) as $index => $type) {
                $previous = $base + ($index * 250);
                $usage = 40 + (($i + $index * 13) % 160);
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
     * @param  Collection<int, Contract>  $contracts
     */
    private function seedInvoicesPaymentsReceipts(Collection $contracts): void
    {
        $billable = $contracts->whereIn('status', ['active', 'approved', 'completed'])->values();
        if ($billable->isEmpty()) {
            return;
        }

        $existingInvoices = Invoice::query()->count();
        $neededInvoices = max(0, self::INVOICE_TARGET - $existingInvoices);
        $existingPayments = Payment::query()->count();
        $neededPayments = max(0, self::PAYMENT_TARGET - $existingPayments);

        $invoiceStatuses = ['paid', 'paid', 'paid', 'issued', 'partial', 'overdue', 'draft'];

        $cash = $this->paymentMethods->firstWhere('slug', 'cash') ?? $this->paymentMethods->first();
        $kbz = $this->paymentMethods->firstWhere('slug', 'kbz-pay') ?? $this->paymentMethods->first();

        $invoicesCreated = 0;
        $paymentsCreated = 0;

        for ($i = 0; $i < $neededInvoices; $i++) {
            $contract = $billable[$i % $billable->count()];
            $status = $invoiceStatuses[$i % count($invoiceStatuses)];
            $number = 'INV-BL-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);

            if (Invoice::query()->where('invoice_number', $number)->exists()) {
                continue;
            }

            $monthsAgo = $i % 14;
            $billingMonth = now()->subMonths($monthsAgo)->startOfMonth();

            if (Invoice::query()
                ->where('contract_id', $contract->id)
                ->whereDate('billing_month', $billingMonth->toDateString())
                ->exists()) {
                continue;
            }

            $issued = $billingMonth->copy()->day(5);
            $due = match ($status) {
                'overdue' => now()->subDays(10 + ($i % 40)),
                'draft' => now()->addDays(7),
                default => $issued->copy()->addDays(10),
            };

            $isRent = $contract->type === 'rent';
            $lateFee = $status === 'overdue' ? 25000.0 : 0.0;
            $type = $isRent ? 'rent' : 'sale';
            $isIssued = in_array($status, ['issued', 'partial', 'paid', 'overdue'], true);

            $utility = $this->findOrCreateBulkUtility($contract, $billingMonth, $i);
            $items = $this->buildBulkConsolidatedItems($contract, $utility, $billingMonth, $isRent);
            $amount = round(collect($items)->sum('amount'), 2);

            $invoice = Invoice::query()->create([
                'contract_id' => $contract->id,
                'utility_id' => null,
                'billing_month' => $billingMonth->toDateString(),
                'created_by' => $this->admin->id,
                'approved_by' => $isIssued ? $this->admin->id : null,
                'approved_at' => $isIssued ? $issued->copy()->subDay() : null,
                'invoice_number' => $number,
                'type' => $type,
                'issued_date' => $isIssued ? $issued->toDateString() : null,
                'due_date' => $due->toDateString(),
                'late_fee' => $lateFee,
                'total_amount' => $amount,
                'status' => $status,
            ]);

            foreach ($items as $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    ...$item,
                ]);
            }

            if ($utility && $utility->status === 'approved') {
                $utility->update(['invoice_id' => $invoice->id]);
            }

            $invoicesCreated++;

            // Attach payments for non-draft invoices while under payment target.
            if ($status === 'draft' || $paymentsCreated >= $neededPayments) {
                continue;
            }

            $method = $i % 2 === 0 ? $cash : $kbz;
            $noteKey = '[BL:'.$number.':';

            if ($status === 'paid') {
                $payment = $this->createPaymentOnce($invoice, $noteKey.'paid]', [
                    'payment_method_id' => $method->id,
                    'created_by' => $contract->user_id,
                    'approved_by' => $this->admin->id,
                    'approved_at' => $issued->copy()->addDays(3),
                    'amount' => round($amount + $lateFee, 2),
                    'proof_image_path' => 'payments/bl-'.$number.'-paid.jpg',
                    'rejection_reason' => null,
                    'payment_date' => $issued->copy()->addDays(2)->toDateString(),
                    'status' => 'approved',
                    'note' => $noteKey.'paid] Full MMK settlement.',
                ]);
                if ($payment) {
                    $paymentsCreated++;
                    $this->createReceiptOnce($payment, Receipt::STATUS_ISSUED, Receipt::APPROVAL_APPROVED, $issued->copy()->addDays(4));
                }
            } elseif ($status === 'partial') {
                $partial = round($amount * 0.4, 2);
                $payment = $this->createPaymentOnce($invoice, $noteKey.'partial]', [
                    'payment_method_id' => $method->id,
                    'created_by' => $contract->user_id,
                    'approved_by' => $this->admin->id,
                    'approved_at' => $issued->copy()->addDays(3),
                    'amount' => $partial,
                    'proof_image_path' => 'payments/bl-'.$number.'-partial.jpg',
                    'rejection_reason' => null,
                    'payment_date' => $issued->copy()->addDays(2)->toDateString(),
                    'status' => 'approved',
                    'note' => $noteKey.'partial] Partial MMK payment.',
                ]);
                if ($payment) {
                    $paymentsCreated++;
                    // Draft receipt awaiting approval for some partials.
                    $this->createReceiptOnce(
                        $payment,
                        Receipt::STATUS_DRAFT,
                        $i % 2 === 0 ? Receipt::APPROVAL_PENDING : Receipt::APPROVAL_APPROVED,
                    );
                }
            } elseif ($status === 'overdue') {
                // Pending payment awaiting admin review (no receipt).
                $payment = $this->createPaymentOnce($invoice, $noteKey.'pending]', [
                    'payment_method_id' => $method->id,
                    'created_by' => $contract->user_id,
                    'approved_by' => null,
                    'approved_at' => null,
                    'amount' => null,
                    'proof_image_path' => 'payments/bl-'.$number.'-pending.jpg',
                    'rejection_reason' => null,
                    'payment_date' => now()->subDays(2)->toDateString(),
                    'status' => 'pending',
                    'note' => $noteKey.'pending] Awaiting admin review.',
                ]);
                if ($payment) {
                    $paymentsCreated++;
                }
            } elseif ($status === 'issued' && $i % 3 === 0) {
                // Rejected payment, invoice remains unpaid, no receipt.
                $payment = $this->createPaymentOnce($invoice, $noteKey.'rejected]', [
                    'payment_method_id' => $method->id,
                    'created_by' => $contract->user_id,
                    'approved_by' => $this->admin->id,
                    'approved_at' => now()->subDays(1),
                    'amount' => null,
                    'proof_image_path' => 'payments/bl-'.$number.'-rejected.jpg',
                    'rejection_reason' => 'Transfer proof does not show the full MMK amount clearly.',
                    'payment_date' => now()->subDays(3)->toDateString(),
                    'status' => 'rejected',
                    'note' => $noteKey.'rejected] Rejected payment attempt.',
                ]);
                if ($payment) {
                    $paymentsCreated++;
                }
            }
            // Remaining issued invoices stay unpaid with no payment (receivable aging).
        }

        // Extra pending payments on unpaid issued invoices for approval queues.
        if ($paymentsCreated < $neededPayments) {
            $openInvoices = Invoice::query()
                ->where('status', 'issued')
                ->where('invoice_number', 'like', 'INV-BL-%')
                ->whereDoesntHave('payments')
                ->limit($neededPayments - $paymentsCreated)
                ->get();

            foreach ($openInvoices as $index => $invoice) {
                $invoice->loadMissing('contract');
                $noteKey = '[BL:'.$invoice->invoice_number.':extra-pending]';
                $payment = $this->createPaymentOnce($invoice, $noteKey, [
                    'payment_method_id' => ($kbz ?? $cash)->id,
                    'created_by' => $invoice->contract?->user_id ?? $this->admin->id,
                    'approved_by' => null,
                    'approved_at' => null,
                    'amount' => null,
                    'proof_image_path' => 'payments/bl-extra-'.$invoice->invoice_number.'.jpg',
                    'rejection_reason' => null,
                    'payment_date' => now()->subDays(1 + $index)->toDateString(),
                    'status' => 'pending',
                    'note' => $noteKey.' Extra pending approval.',
                ]);
                if ($payment) {
                    $paymentsCreated++;
                }
            }
        }

        // Approved payments intentionally without receipt (pending receipt processing).
        $approvedWithoutReceipt = Payment::query()
            ->where('status', 'approved')
            ->whereDoesntHave('receipt')
            ->count();

        for ($j = 0; $j < max(0, 5 - $approvedWithoutReceipt); $j++) {
            $contract = $billable[$j % $billable->count()];
            $number = 'INV-BL-NR'.str_pad((string) ($j + 1), 4, '0', STR_PAD_LEFT);
            if (Invoice::query()->where('invoice_number', $number)->exists()) {
                continue;
            }
            $billingMonth = now()->subDays(20 + $j)->startOfMonth();
            if (Invoice::query()
                ->where('contract_id', $contract->id)
                ->whereDate('billing_month', $billingMonth->toDateString())
                ->exists()) {
                continue;
            }
            $issued = now()->subDays(20 + $j);
            $utility = $this->findOrCreateBulkUtility($contract, $billingMonth, 1000 + $j);
            $items = $this->buildBulkConsolidatedItems($contract, $utility, $billingMonth, $contract->type === 'rent');
            $amount = round(collect($items)->sum('amount'), 2);
            $invoice = Invoice::query()->create([
                'contract_id' => $contract->id,
                'utility_id' => null,
                'billing_month' => $billingMonth->toDateString(),
                'created_by' => $this->admin->id,
                'approved_by' => $this->admin->id,
                'approved_at' => $issued->copy()->subDay(),
                'invoice_number' => $number,
                'type' => $contract->type === 'rent' ? 'rent' : 'sale',
                'issued_date' => $issued->toDateString(),
                'due_date' => $issued->copy()->addDays(7)->toDateString(),
                'late_fee' => 0,
                'total_amount' => $amount,
                'status' => 'paid',
            ]);
            foreach ($items as $item) {
                InvoiceItem::query()->create(['invoice_id' => $invoice->id, ...$item]);
            }
            if ($utility) {
                $utility->update(['invoice_id' => $invoice->id]);
            }
            $this->createPaymentOnce($invoice, '[BL:'.$number.':paid-no-receipt]', [
                'payment_method_id' => $cash->id,
                'created_by' => $contract->user_id,
                'approved_by' => $this->admin->id,
                'approved_at' => $issued->copy()->addDay(),
                'amount' => $amount,
                'proof_image_path' => 'payments/bl-'.$number.'.jpg',
                'rejection_reason' => null,
                'payment_date' => $issued->toDateString(),
                'status' => 'approved',
                'note' => '[BL:'.$number.':paid-no-receipt] Approved; receipt not generated yet.',
            ]);
        }

        $this->command?->info("Bulk invoices created: {$invoicesCreated}; payments created: {$paymentsCreated}");
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function seedMaintenance(Collection $contracts): void
    {
        $existing = MaintenanceRequest::query()->count();
        $needed = max(0, self::MAINTENANCE_TARGET - $existing);
        if ($needed === 0) {
            return;
        }

        $eligible = $contracts->whereIn('status', ['active', 'approved'])->values();
        if ($eligible->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'pending', 'in_progress', 'completed', 'rejected'];
        $categories = ['plumbing', 'electrical', 'hvac', 'general', 'appliance'];
        $priorities = ['low', 'medium', 'high'];
        $titles = [
            'Leaking kitchen faucet',
            'AC not cooling properly',
            'Corridor light flickering',
            'Balcony door lock stuck',
            'Water heater intermittent',
            'Bathroom drain clogged',
            'Socket sparking near TV',
            'Ceiling paint peeling',
        ];

        $created = 0;
        for ($i = 0; $i < $needed; $i++) {
            $contract = $eligible[$i % $eligible->count()];
            $status = $statuses[$i % count($statuses)];
            $title = sprintf('BL-%03d %s', $i + 1, $titles[$i % count($titles)]);

            MaintenanceRequest::query()->firstOrCreate(
                [
                    'title' => $title,
                    'room_id' => $contract->room_id,
                    'user_id' => $contract->user_id,
                ],
                [
                    'created_by' => $contract->user_id,
                    'approved_by' => in_array($status, ['in_progress', 'completed', 'rejected'], true) ? $this->admin->id : null,
                    'approved_at' => in_array($status, ['in_progress', 'completed', 'rejected'], true) ? now()->subDays(2 + ($i % 10)) : null,
                    'category' => $categories[$i % count($categories)],
                    'priority' => $priorities[$i % count($priorities)],
                    'description' => 'Bulk demo maintenance request for '.$contract->contract_number.'.',
                    'status' => $status,
                    'resolution_note' => $status === 'completed' ? 'Issue resolved and verified with tenant.' : null,
                    'rejection_reason' => $status === 'rejected' ? 'Duplicate ticket already scheduled with building technician.' : null,
                ],
            );
            $created++;
        }

        $this->command?->info("Bulk maintenance ensured: {$created}");
    }

    /**
     * Keep bulk-contracted rooms consistent after re-seed (does not touch WF rooms).
     */
    private function syncBulkRoomStatuses(): void
    {
        $bulkContracts = Contract::query()
            ->where(function ($query) {
                $query->where('contract_number', 'like', 'S-BL-%')
                    ->orWhere('contract_number', 'like', 'R-BL-%');
            })
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
                $query->where(function ($inner) {
                    $inner->where('contract_number', 'like', 'S-WF-%')
                        ->orWhere('contract_number', 'like', 'R-WF-%');
                });
            })->update(['status' => $status]);
        }

        // Dedicated maintenance showcase room (no contracts).
        Room::query()
            ->where('room_number', 'M-001')
            ->whereHas('building', fn ($query) => $query->where('building_name', 'Rosewood Royal Tower'))
            ->whereDoesntHave('contracts')
            ->update(['status' => 'maintenance']);
    }

    private function findOrCreateBulkUtility(Contract $contract, Carbon $billingMonth, int $seed): ?Utility
    {
        if (! $contract->room_id || $this->utilityTypes->isEmpty()) {
            return null;
        }

        $utility = Utility::query()->firstOrCreate(
            [
                'room_id' => $contract->room_id,
                'billing_month' => $billingMonth->toDateString(),
            ],
            [
                'total_amount' => 0,
                'status' => 'approved',
                'created_by' => $this->admin->id,
                'approved_by' => $this->admin->id,
                'approved_at' => $billingMonth->copy()->endOfMonth(),
            ],
        );

        if ($utility->items()->exists()) {
            return $utility->fresh('items.utilityType');
        }

        $total = 0.0;
        $base = 900 + ($contract->room_id * 11) + ((int) $billingMonth->format('n') * 19) + ($seed % 50);

        foreach ($this->utilityTypes->take(2) as $index => $type) {
            $previous = $base + ($index * 220);
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

        $seq = Receipt::query()->where('receipt_number', 'like', 'RCP-BL-%')->count() + 1;
        $number = 'RCP-BL-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        while (Receipt::query()->where('receipt_number', $number)->exists()) {
            $seq++;
            $number = 'RCP-BL-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
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
