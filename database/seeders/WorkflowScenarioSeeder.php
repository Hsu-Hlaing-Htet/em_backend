<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
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
use Illuminate\Database\Seeder;

/**
 * Idempotent end-to-end workflow scenarios using only project-supported statuses.
 *
 * Contract: draft | pending | active | approved | completed | rejected
 * Room: available | reserved | occupied | sold | maintenance
 * Invoice: draft | issued | partial | paid | overdue
 * Payment: pending | approved | rejected
 * Receipt status: draft | issued ; approval: pending | approved | rejected
 * Utility: draft | pending | approved | rejected
 * Maintenance: pending | in_progress | completed | rejected
 */
class WorkflowScenarioSeeder extends Seeder
{
    /** @var array<string, array<string, mixed>> */
    private array $matrix = [];

    public function run(): void
    {
        BillingSeederSupport::resetSequences();

        $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->first()
            ?? User::query()->whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))->first();

        if (! $admin) {
            $this->command?->warn('Admin user required. Run UserSeeder first.');

            return;
        }

        $customers = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))
            ->get()
            ->keyBy('email');

        $fullPlan = PaymentPlan::query()->where('payment_type', 'full')->first();
        $installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->orderBy('duration_months')
            ->first();
        $methods = BillingSeederSupport::paymentMethods();
        $cash = $methods->firstWhere('slug', 'cash') ?? $methods->first();
        $kbz = $methods->firstWhere('slug', 'kbz-pay') ?? $methods->first();
        $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
        $utilityTypes = UtilityType::query()->where('status', 'active')->orderBy('id')->get();

        $buildings = $this->seedBuildings();
        $rooms = $this->seedRooms($buildings);

        // --- Sale contracts ---
        $this->seedSaleDraft($admin, $customers->get('yeyee@rosewoodroyale.com'), $rooms['S-DRAFT'], $fullPlan);
        $this->seedSalePending($admin, $customers->get('seinsein@rosewoodroyale.com'), $rooms['S-PENDING'], $fullPlan);
        $this->seedSaleApproved($admin, $customers->get('susu@rosewoodroyale.com'), $rooms['S-APPROVED'], $installmentPlan ?? $fullPlan);
        $this->seedSaleCompleted($admin, $customers->get('nwenwe@rosewoodroyale.com'), $rooms['S-COMPLETED'], $fullPlan);
        $this->seedSaleRejected($admin, $customers->get('hninhnin@rosewoodroyale.com'), $rooms['S-REJECTED'], $fullPlan);

        // --- Rent contracts ---
        $this->seedRentDraft($admin, $customers->get('waiwai@rosewoodroyale.com'), $rooms['R-DRAFT'], $fullPlan);
        $this->seedRentPending($admin, $customers->get('thandar@rosewoodroyale.com'), $rooms['R-PENDING'], $fullPlan);
        $activeRent = $this->seedRentActive(
            $admin,
            $customers->get('mgmg@rosewoodroyale.com'),
            $rooms['R-ACTIVE'],
            $fullPlan,
            $chargeTypes,
            $utilityTypes,
            $cash,
            $kbz,
        );
        $this->seedRentCompleted($admin, $customers->get('zawzaw@rosewoodroyale.com'), $rooms['R-COMPLETED'], $fullPlan, $chargeTypes);
        $this->seedRentRejected($admin, $customers->get('eiei@rosewoodroyale.com'), $rooms['R-REJECTED'], $fullPlan);

        // Secondary occupied rental (utilities history, no maintenance for tuntun = no-request customer)
        $this->seedSecondaryOccupiedRent(
            $admin,
            $customers->get('hlahla@rosewoodroyale.com'),
            $rooms['R-ACTIVE-2'],
            $fullPlan,
            $chargeTypes,
            $utilityTypes,
            $cash,
        );

        // Available room with images only — no contract
        $this->matrix['available-no-contract'] = [
            'demo_account' => 'tuntun@rosewoodroyale.com (registered only, no contract/maintenance)',
            'room' => $rooms['AVAILABLE']->room_number.' @ '.$buildings['royal']->building_name,
            'contract' => '—',
            'invoice' => '—',
            'payment' => '—',
            'receipt' => '—',
            'maintenance' => 'none',
        ];

        // Maintenance room (status maintenance, no active billing)
        $rooms['MAINTENANCE']->update(['status' => 'maintenance']);
        $this->matrix['room-maintenance'] = [
            'demo_account' => '—',
            'room' => $rooms['MAINTENANCE']->room_number.' (maintenance)',
            'contract' => '—',
            'invoice' => '—',
            'payment' => '—',
            'receipt' => '—',
            'maintenance' => '—',
        ];

        $this->seedMaintenanceBundle($admin, $activeRent, $customers);

        $this->printMatrix();
    }

    /**
     * @return array<string, Building>
     */
    private function seedBuildings(): array
    {
        $defs = [
            'royal' => ['Rosewood Royal Tower', 'Kamayut Township, Yangon, Myanmar', 'Flagship tower near Inya Lake with 24-hour security and covered parking.'],
            'inya' => ['Inya Lake View Condominium', 'Bahan Township, Yangon, Myanmar', 'Lake-facing residences with clubhouse and swimming pool.'],
            'kabar' => ['Kabar Aye Premium Homes', 'Mayangone Township, Yangon, Myanmar', 'Quiet mid-rise near Kabar Aye Pagoda with lift access.'],
            'pyay' => ['Pyay Road Residences', 'Hlaing Township, Yangon, Myanmar', 'Convenient Pyay Road address for professionals.'],
        ];

        $buildings = [];
        foreach ($defs as $key => [$name, $location, $description]) {
            $buildings[$key] = Building::query()->updateOrCreate(
                ['building_name' => $name],
                ['location' => $location, 'description' => $description],
            );
        }

        return $buildings;
    }

    /**
     * @param  array<string, Building>  $buildings
     * @return array<string, Room>
     */
    private function seedRooms(array $buildings): array
    {
        $defs = [
            'AVAILABLE' => [$buildings['royal'], 'A-101', 1, 'rent', 850, 450000, 0],
            'S-DRAFT' => [$buildings['inya'], 'B-201', 2, 'sale', 1100, 0, 185000000],
            'S-PENDING' => [$buildings['inya'], 'B-305', 3, 'sale', 1180, 0, 248000000],
            'S-APPROVED' => [$buildings['kabar'], 'C-301', 3, 'sale', 1250, 0, 275000000],
            'S-COMPLETED' => [$buildings['kabar'], 'C-701', 7, 'sale', 1400, 0, 320000000],
            'S-REJECTED' => [$buildings['pyay'], 'D-110', 1, 'sale', 990, 0, 198000000],
            'R-DRAFT' => [$buildings['royal'], 'A-210', 2, 'rent', 780, 420000, 0],
            'R-PENDING' => [$buildings['pyay'], 'D-220', 2, 'rent', 860, 490000, 0],
            'R-ACTIVE' => [$buildings['royal'], 'A-501', 5, 'rent', 1050, 650000, 0],
            'R-ACTIVE-2' => [$buildings['pyay'], 'D-402', 4, 'rent', 880, 480000, 0],
            'R-COMPLETED' => [$buildings['inya'], 'B-410', 4, 'rent', 920, 520000, 0],
            'R-REJECTED' => [$buildings['kabar'], 'C-120', 1, 'rent', 800, 400000, 0],
            'MAINTENANCE' => [$buildings['royal'], 'M-001', 9, 'both', 700, 380000, 150000000],
            'EXTRA-AVAIL' => [$buildings['inya'], 'B-102', 1, 'both', 980, 600000, 210000000],
        ];

        $rooms = [];
        foreach ($defs as $key => [$building, $number, $floor, $type, $area, $rent, $sale]) {
            $rooms[$key] = Room::query()->updateOrCreate(
                [
                    'building_id' => $building->id,
                    'room_number' => $number,
                ],
                [
                    'floor_number' => $floor,
                    'width_ft' => round(sqrt($area) * 0.9, 2),
                    'length_ft' => round(sqrt($area) * 1.1, 2),
                    'area_sqft' => $area,
                    'description' => sprintf('%s unit on floor %d of %s.', ucfirst($type), $floor, $building->building_name),
                    'type' => $type,
                    'status' => 'available',
                    'sale_price' => $sale,
                    'rent_price' => $rent,
                    'rent_deposit_price' => $rent > 0 ? round($rent * 2, 2) : 0,
                    'booking_deposit_price' => $sale > 0 ? round($sale * 0.1, 2) : 0,
                ],
            );
        }

        return $rooms;
    }

    private function upsertContract(string $number, array $attributes): Contract
    {
        return Contract::query()->updateOrCreate(
            ['contract_number' => $number],
            $attributes,
        );
    }

    private function seedSaleDraft(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('S-WF-000001', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => 'full',
            'duration_months' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'billing_day' => null,
            'status' => 'draft',
            'remark' => 'Sale draft awaiting customer confirmation.',
        ]);
        $room->update(['status' => 'available']);

        $this->matrix['sale-draft'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    private function seedSalePending(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('S-WF-000002', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => 'full',
            'status' => 'pending',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => null,
            'billing_day' => null,
            'duration_months' => null,
            'remark' => 'Submitted for management approval.',
        ]);
        $room->update(['status' => 'reserved']);

        $this->matrix['sale-pending'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    private function seedSaleApproved(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $useInstallment = $plan && $plan->payment_type === 'installment';
        $contract = $this->upsertContract('S-WF-000003', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonths(2),
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => $useInstallment ? 'installment' : 'full',
            'duration_months' => $useInstallment ? $plan->duration_months : null,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => $useInstallment ? now()->addMonths(10)->toDateString() : null,
            'billing_day' => $useInstallment ? 5 : null,
            'status' => 'approved',
            'remark' => 'Approved sale contract. Unit reserved pending completion.',
        ]);
        $room->update(['status' => 'reserved']);

        $deposit = (float) $room->booking_deposit_price;
        $depositInvoice = BillingSeederSupport::upsertInvoice(
            'INV-WF-SALE-DEP',
            $admin,
            $contract->id,
            null,
            'sale',
            'paid',
            now()->subMonths(2)->day(6),
            now()->subMonths(2)->day(13),
            [['charge_type_id' => ChargeType::query()->where('slug', 'booking-deposit')->value('id'), 'description' => 'Sale booking deposit', 'amount' => $deposit]],
        );
        $this->replacePayments($depositInvoice);
        $methods = BillingSeederSupport::paymentMethods();
        $cash = $methods->firstWhere('slug', 'cash') ?? $methods->first();
        $depPayment = BillingSeederSupport::upsertPayment($depositInvoice, '[WF:sale-deposit]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonths(2)->day(8),
            'amount' => $deposit,
            'proof_image_path' => 'payments/wf-sale-deposit.jpg',
            'rejection_reason' => null,
            'payment_date' => now()->subMonths(2)->day(7)->toDateString(),
            'status' => 'approved',
            'note' => 'Booking deposit approved.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $depPayment,
            $admin,
            Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED,
            now()->subMonths(2)->day(9),
        );

        $installmentMonth = now()->subMonth()->startOfMonth();
        $utilityTypes = UtilityType::query()->where('status', 'active')->orderBy('id')->get();
        $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
        $saleUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $installmentMonth, 'approved', $admin);

        BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-SALE-BAL',
            $admin,
            $contract,
            $installmentMonth,
            'sale',
            'issued',
            $installmentMonth->copy()->day(5),
            now()->addMonth()->day(5),
            ConsolidatedBillingSeederSupport::buildSaleConsolidatedItems($contract, $saleUtility, $chargeTypes, $installmentMonth),
            0,
            [$saleUtility->id],
        );

        $this->matrix['sale-approved'] = $this->row($customer->email, $room, $contract, 'deposit paid + consolidated installment unpaid', 'approved deposit / none on balance', 'issued/approved on deposit', '—');
    }

    private function seedSaleCompleted(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('S-WF-000004', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonths(18),
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => 'full',
            'duration_months' => null,
            'start_date' => now()->subMonths(18)->toDateString(),
            'end_date' => now()->subMonths(12)->toDateString(),
            'billing_day' => null,
            'status' => 'completed',
            'remark' => 'Sale completed. Full purchase amount received and title transferred.',
        ]);
        $room->update(['status' => 'sold']);

        $saleTotal = (float) $room->sale_price;
        $saleInvoice = BillingSeederSupport::upsertInvoice(
            'INV-WF-SALE-FULL',
            $admin,
            $contract->id,
            null,
            'sale',
            'paid',
            now()->subMonths(14)->day(5),
            now()->subMonths(14)->day(20),
            [['charge_type_id' => ChargeType::query()->where('slug', 'booking-deposit')->value('id'), 'description' => 'Full sale purchase settlement', 'amount' => $saleTotal]],
        );
        $this->replacePayments($saleInvoice);
        $methods = BillingSeederSupport::paymentMethods();
        $cash = $methods->firstWhere('slug', 'cash') ?? $methods->first();
        $salePayment = BillingSeederSupport::upsertPayment($saleInvoice, '[WF:sale-full]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonths(14)->day(10),
            'amount' => $saleTotal,
            'proof_image_path' => 'payments/wf-sale-full.jpg',
            'rejection_reason' => null,
            'payment_date' => now()->subMonths(14)->day(8)->toDateString(),
            'status' => 'approved',
            'note' => 'Full sale amount settled.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $salePayment,
            $admin,
            Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED,
            now()->subMonths(14)->day(11),
        );

        $this->matrix['sale-completed'] = $this->row($customer->email, $room, $contract, 'paid', 'approved', 'issued/approved', '—');
    }

    private function seedSaleRejected(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('S-WF-000005', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subWeeks(1),
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => 'full',
            'status' => 'rejected',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => null,
            'billing_day' => null,
            'duration_months' => null,
            'remark' => 'Rejected due to incomplete buyer documentation.',
        ]);
        $room->update(['status' => 'available']);

        $this->matrix['sale-rejected'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    private function seedRentDraft(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('R-WF-000001', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'billing_day' => 5,
            'status' => 'draft',
            'remark' => 'Rent draft pending tenant review.',
        ]);
        $room->update(['status' => 'available']);

        $this->matrix['rent-draft'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    private function seedRentPending(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('R-WF-000002', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addMonths(12)->toDateString(),
            'billing_day' => 5,
            'status' => 'pending',
            'remark' => 'Awaiting lease approval.',
        ]);
        $room->update(['status' => 'available']);

        $this->matrix['rent-pending'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    /**
     * Primary occupied rental with full billing/payment/receipt/utility coverage.
     *
     * @param  \Illuminate\Support\Collection<string, ChargeType>  $chargeTypes
     * @param  \Illuminate\Support\Collection<int, UtilityType>  $utilityTypes
     */
    private function seedRentActive(
        User $admin,
        ?User $customer,
        Room $room,
        ?PaymentPlan $plan,
        $chargeTypes,
        $utilityTypes,
        PaymentMethod $cash,
        PaymentMethod $kbz,
    ): ?Contract {
        if (! $customer) {
            return null;
        }

        $start = now()->subMonths(6)->startOfMonth();
        $contract = $this->upsertContract('R-WF-000003', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => $start,
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'billing_day' => 5,
            'status' => 'active',
            'remark' => 'Active rental agreement with monthly MMK billing.',
        ]);
        $room->update(['status' => 'occupied']);

        $rent = (float) $room->rent_price;

        // Draft consolidated invoice (current month)
        $draftMonth = now()->startOfMonth();
        $draftUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $draftMonth, 'approved', $admin);
        BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-DRAFT1',
            $admin,
            $contract,
            $draftMonth,
            'rent',
            'draft',
            now(),
            now()->addDays(7),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $draftUtility, $chargeTypes, $draftMonth),
            0,
            [$draftUtility->id],
        );

        // Paid consolidated invoice + approved payment + issued/sent receipt
        $paidMonth = $start->copy();
        $paidUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $paidMonth, 'approved', $admin);
        $paidInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-PAID01',
            $admin,
            $contract,
            $paidMonth,
            'rent',
            'paid',
            $paidMonth->copy()->day(5),
            $paidMonth->copy()->day(12),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $paidUtility, $chargeTypes, $paidMonth),
            0,
            [$paidUtility->id],
        );
        $this->replacePayments($paidInvoice);
        $paidDue = BillingSeederSupport::invoiceAmountDue($paidInvoice);
        $paidPayment = BillingSeederSupport::upsertPayment($paidInvoice, '[WF:paid-full]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $paidMonth->copy()->day(8),
            'amount' => $paidDue,
            'proof_image_path' => 'payments/wf-paid.jpg',
            'rejection_reason' => null,
            'payment_date' => $paidMonth->copy()->day(7)->toDateString(),
            'status' => 'approved',
            'note' => 'Full MMK consolidated rent + utilities settled via cash.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $paidPayment,
            $admin,
            Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED,
            $paidMonth->copy()->day(9),
        );

        // Partial consolidated invoice + approved partial payment + draft receipt
        $partialMonth = $start->copy()->addMonths(1);
        $partialUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $partialMonth, 'approved', $admin);
        $partialInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-PART01',
            $admin,
            $contract,
            $partialMonth,
            'rent',
            'partial',
            $partialMonth->copy()->day(5),
            $partialMonth->copy()->day(12),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $partialUtility, $chargeTypes, $partialMonth),
            0,
            [$partialUtility->id],
        );
        $this->replacePayments($partialInvoice);
        $partialAmount = round(BillingSeederSupport::invoiceAmountDue($partialInvoice) * 0.4, 2);
        $partialPayment = BillingSeederSupport::upsertPayment($partialInvoice, '[WF:partial]', [
            'payment_method_id' => $kbz->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $partialMonth->copy()->day(10),
            'amount' => $partialAmount,
            'proof_image_path' => 'payments/wf-partial.jpg',
            'rejection_reason' => null,
            'payment_date' => $partialMonth->copy()->day(9)->toDateString(),
            'status' => 'approved',
            'note' => 'Partial KBZ Pay transfer received against consolidated invoice.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $partialPayment,
            $admin,
            Receipt::STATUS_DRAFT,
            Receipt::APPROVAL_PENDING,
        );

        // Unpaid issued consolidated invoice (rent + electricity + water + service charge)
        $unpaidMonth = $start->copy()->addMonths(2);
        $unpaidUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $unpaidMonth, 'approved', $admin);
        $unpaidInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-UNPAID1',
            $admin,
            $contract,
            $unpaidMonth,
            'rent',
            'issued',
            $unpaidMonth->copy()->day(5),
            $unpaidMonth->copy()->day(19),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $unpaidUtility, $chargeTypes, $unpaidMonth),
            0,
            [$unpaidUtility->id],
        );
        $this->replacePayments($unpaidInvoice);

        // Overdue consolidated invoice + pending payment awaiting review
        $overdueMonth = $start->copy()->addMonths(3);
        $overdueUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $overdueMonth, 'approved', $admin);
        $overdueInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-OVERDUE',
            $admin,
            $contract,
            $overdueMonth,
            'rent',
            'overdue',
            $overdueMonth->copy()->day(5),
            now()->subDays(10),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $overdueUtility, $chargeTypes, $overdueMonth),
            25000,
            [$overdueUtility->id],
        );
        $this->replacePayments($overdueInvoice);
        BillingSeederSupport::upsertPayment($overdueInvoice, '[WF:pending-review]', [
            'payment_method_id' => $kbz->id,
            'created_by' => $customer->id,
            'approved_by' => null,
            'approved_at' => null,
            'amount' => null,
            'proof_image_path' => 'payments/wf-pending.jpg',
            'rejection_reason' => null,
            'payment_date' => now()->subDay()->toDateString(),
            'status' => 'pending',
            'note' => '[WF:pending-review] KBZ ref 482910553 — customer submitted proof; awaiting admin review.',
        ]);

        // Issued consolidated invoice with rejected payment
        $rejectMonth = $start->copy()->addMonths(4);
        $rejectUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $rejectMonth, 'approved', $admin);
        $rejectInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-REJECT1',
            $admin,
            $contract,
            $rejectMonth,
            'rent',
            'issued',
            $rejectMonth->copy()->day(5),
            $rejectMonth->copy()->day(12),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $rejectUtility, $chargeTypes, $rejectMonth),
            0,
            [$rejectUtility->id],
        );
        $this->replacePayments($rejectInvoice);
        BillingSeederSupport::upsertPayment($rejectInvoice, '[WF:rejected]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subDays(2),
            'amount' => null,
            'proof_image_path' => 'payments/wf-rejected.jpg',
            'rejection_reason' => 'Bank transfer proof is unclear. Please resubmit a clearer screenshot showing the full MMK amount.',
            'payment_date' => now()->subDays(3)->toDateString(),
            'status' => 'rejected',
            'note' => 'Rejected payment attempt against consolidated invoice.',
        ]);

        // Approved payment with draft approved receipt (not yet emailed to customer)
        $receiptMonth = $start->copy()->addMonths(5);
        $receiptUtility = $this->seedUtilityForRoom($admin, $room, $utilityTypes, $receiptMonth, 'approved', $admin);
        $receiptInvoice = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-RCPT01',
            $admin,
            $contract,
            $receiptMonth,
            'rent',
            'paid',
            $receiptMonth->copy()->day(5),
            $receiptMonth->copy()->day(12),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $receiptUtility, $chargeTypes, $receiptMonth),
            0,
            [$receiptUtility->id],
        );
        $this->replacePayments($receiptInvoice);
        $awaitingIssuePayment = BillingSeederSupport::upsertPayment($receiptInvoice, '[WF:receipt-awaiting-issue]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $receiptMonth->copy()->day(8),
            'amount' => BillingSeederSupport::invoiceAmountDue($receiptInvoice),
            'proof_image_path' => 'payments/wf-receipt-await.jpg',
            'rejection_reason' => null,
            'payment_date' => $receiptMonth->copy()->day(7)->toDateString(),
            'status' => 'approved',
            'note' => 'Approved consolidated payment; receipt approved but not emailed yet.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $awaitingIssuePayment,
            $admin,
            Receipt::STATUS_DRAFT,
            Receipt::APPROVAL_APPROVED,
            null,
        );

        // Approved payment with rejected receipt approval (maintenance fee invoice kept separate)
        $maintCharge = $chargeTypes->get('maintenance-fee')?->id ?? $chargeTypes->get('monthly-rent')?->id;
        $rejReceiptInvoice = BillingSeederSupport::upsertInvoice(
            'INV-WF-RCPT02',
            $admin,
            $contract->id,
            null,
            'other',
            'paid',
            now()->subMonths(1)->day(3),
            now()->subMonths(1)->day(10),
            [['charge_type_id' => $maintCharge, 'description' => 'One-time maintenance fee', 'amount' => 50000]],
        );
        $this->replacePayments($rejReceiptInvoice);
        $rejReceiptPayment = BillingSeederSupport::upsertPayment($rejReceiptInvoice, '[WF:receipt-rejected]', [
            'payment_method_id' => $kbz->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonths(1)->day(5),
            'amount' => 50000,
            'proof_image_path' => 'payments/wf-receipt-rej.jpg',
            'rejection_reason' => null,
            'payment_date' => now()->subMonths(1)->day(4)->toDateString(),
            'status' => 'approved',
            'note' => 'Approved payment with rejected receipt draft.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $rejReceiptPayment,
            $admin,
            Receipt::STATUS_DRAFT,
            Receipt::APPROVAL_REJECTED,
            now()->subMonths(1)->day(6),
        );

        // Pending utility bill for a future month without invoice yet
        $this->seedUtilityForRoom($admin, $room, $utilityTypes, $start->copy()->addMonths(6), 'pending', null);

        $this->matrix['rent-active-primary'] = $this->row(
            $customer->email,
            $room,
            $contract,
            'consolidated draft / unpaid / partial / paid / overdue',
            'pending / approved / rejected / partial',
            'issued+sent / draft+pending / draft+approved / draft+rejected / none',
            'see maintenance bundle',
        );

        return $contract;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, ChargeType>  $chargeTypes
     * @param  \Illuminate\Support\Collection<int, UtilityType>  $utilityTypes
     */
    private function seedSecondaryOccupiedRent(
        User $admin,
        ?User $customer,
        Room $room,
        ?PaymentPlan $plan,
        $chargeTypes,
        $utilityTypes,
        PaymentMethod $cash,
    ): void {
        if (! $customer) {
            return;
        }

        $start = now()->subMonths(3)->startOfMonth();
        $contract = $this->upsertContract('R-WF-000004', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => $start,
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'billing_day' => 5,
            'status' => 'active',
            'remark' => 'Secondary active rental for listing demos.',
        ]);
        $room->update(['status' => 'occupied']);

        // Consolidated paid invoice with approved utility charges
        $this->seedUtilityForRoom($admin, $room, $utilityTypes, $start->copy(), 'approved', $admin);
        $utility = Utility::query()
            ->where('room_id', $room->id)
            ->whereDate('billing_month', $start->toDateString())
            ->firstOrFail();

        $paid = BillingSeederSupport::upsertConsolidatedInvoice(
            'INV-WF-SEC-PAID',
            $admin,
            $contract,
            $start,
            'rent',
            'paid',
            $start->copy()->day(5),
            $start->copy()->day(12),
            ConsolidatedBillingSeederSupport::buildRentConsolidatedItems($contract, $utility, $chargeTypes, $start),
            0,
            [$utility->id],
        );
        $this->replacePayments($paid);
        $payment = BillingSeederSupport::upsertPayment($paid, '[WF:sec-paid]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $start->copy()->day(8),
            'amount' => BillingSeederSupport::invoiceAmountDue($paid),
            'proof_image_path' => 'payments/wf-sec-paid.jpg',
            'rejection_reason' => null,
            'payment_date' => $start->copy()->day(7)->toDateString(),
            'status' => 'approved',
            'note' => 'Secondary tenant consolidated payment.',
        ]);
        // Approved payment intentionally without receipt yet (pending receipt processing)
        Receipt::query()->where('payment_id', $payment->id)->delete();

        $this->matrix['rent-active-secondary'] = $this->row(
            $customer->email,
            $room,
            $contract,
            'paid',
            'approved (no receipt yet)',
            'none — demonstrates pending receipt creation',
            'none',
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<string, ChargeType>  $chargeTypes
     */
    private function seedRentCompleted(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan, $chargeTypes): void
    {
        if (! $customer) {
            return;
        }

        $start = now()->subMonths(18)->startOfMonth();
        $end = now()->subMonths(6)->startOfMonth();
        $contract = $this->upsertContract('R-WF-000005', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => $start,
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'billing_day' => 5,
            'status' => 'completed',
            'remark' => 'Lease completed. Security deposit returned and unit released.',
        ]);
        $room->update(['status' => 'available']);

        $invoice = BillingSeederSupport::upsertInvoice(
            'INV-WF-COMP01',
            $admin,
            $contract->id,
            null,
            'rent',
            'paid',
            $end->copy()->day(5),
            $end->copy()->day(12),
            [['charge_type_id' => $chargeTypes->get('monthly-rent')?->id, 'description' => 'Final month rent — '.$end->format('F Y'), 'amount' => (float) $room->rent_price]],
        );
        $this->replacePayments($invoice);
        $methods = BillingSeederSupport::paymentMethods();
        $cash = $methods->firstWhere('slug', 'cash') ?? $methods->first();
        $payment = BillingSeederSupport::upsertPayment($invoice, '[WF:rent-completed]', [
            'payment_method_id' => $cash->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $end->copy()->day(8),
            'amount' => (float) $room->rent_price,
            'proof_image_path' => 'payments/wf-rent-completed.jpg',
            'rejection_reason' => null,
            'payment_date' => $end->copy()->day(7)->toDateString(),
            'status' => 'approved',
            'note' => 'Final rent month settled.',
        ]);
        BillingSeederSupport::upsertReceipt(
            $payment,
            $admin,
            Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED,
            $end->copy()->day(9),
        );

        $this->matrix['rent-completed'] = $this->row($customer->email, $room, $contract, 'paid', 'approved', 'issued/approved', '—');
    }

    private function seedRentRejected(User $admin, ?User $customer, Room $room, ?PaymentPlan $plan): void
    {
        if (! $customer) {
            return;
        }

        $contract = $this->upsertContract('R-WF-000006', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subDays(4),
            'contract_total' => round($room->rent_price * 12, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
            'billing_day' => 5,
            'status' => 'rejected',
            'remark' => 'Rejected due to failed background check.',
        ]);
        $room->update(['status' => 'available']);

        $this->matrix['rent-rejected'] = $this->row($customer->email, $room, $contract, '—', '—', '—', '—');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UtilityType>  $utilityTypes
     */
    private function seedUtilityForRoom(
        User $admin,
        Room $room,
        $utilityTypes,
        Carbon $billingMonth,
        string $status,
        ?User $approver,
    ): ?Utility {
        $month = $billingMonth->copy()->startOfMonth();

        $utility = Utility::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'billing_month' => $month->toDateString(),
            ],
            [
                'total_amount' => 0,
                'status' => $status,
                'created_by' => $admin->id,
                'approved_by' => $approver?->id,
                'approved_at' => $approver ? $month->copy()->endOfMonth() : null,
            ],
        );

        $utility->items()->delete();
        $total = 0.0;
        $base = 1000 + ($room->id * 15) + ((int) $month->format('n') * 20);

        foreach ($utilityTypes->take(3) as $index => $type) {
            $rate = UtilityRate::query()
                ->where('utility_type_id', $type->id)
                ->where('status', 'active')
                ->latest('effective_date')
                ->first();
            $previous = $base + ($index * 300);
            $usage = match ($index) {
                0 => 110.0,
                1 => 175.0,
                default => 28.0,
            };
            $unitPrice = (float) ($rate?->unit_price ?? [120, 85, 450][$index]);
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

        return $utility->fresh('items');
    }

    private function seedMaintenanceBundle(User $admin, ?Contract $activeRent, $customers): void
    {
        if (! $activeRent) {
            return;
        }

        $defs = [
            ['title' => 'WF Pending — leaking kitchen faucet', 'category' => 'plumbing', 'priority' => 'medium', 'status' => 'pending', 'description' => 'Tap drips continuously in the kitchen.', 'resolution_note' => null, 'rejection_reason' => null],
            ['title' => 'WF In Progress — AC not cooling', 'category' => 'hvac', 'priority' => 'high', 'status' => 'in_progress', 'description' => 'Bedroom AC runs without cold air during Yangon heat.', 'resolution_note' => null, 'rejection_reason' => null],
            ['title' => 'WF Completed — balcony lock fixed', 'category' => 'general', 'priority' => 'medium', 'status' => 'completed', 'description' => 'Balcony sliding door lock stuck.', 'resolution_note' => 'Lock assembly replaced and tested with tenant.', 'rejection_reason' => null],
            ['title' => 'WF Rejected — outlet sparking duplicate', 'category' => 'electrical', 'priority' => 'high', 'status' => 'rejected', 'description' => 'Living room outlet sparks when plugging appliances.', 'resolution_note' => null, 'rejection_reason' => 'Duplicate of an earlier ticket already scheduled with the building electrician.'],
        ];

        foreach ($defs as $def) {
            MaintenanceRequest::query()->updateOrCreate(
                [
                    'title' => $def['title'],
                    'room_id' => $activeRent->room_id,
                    'user_id' => $activeRent->user_id,
                ],
                [
                    'created_by' => $activeRent->user_id,
                    'approved_by' => in_array($def['status'], ['in_progress', 'completed', 'rejected'], true) ? $admin->id : null,
                    'approved_at' => in_array($def['status'], ['in_progress', 'completed', 'rejected'], true) ? now()->subDays(2) : null,
                    'category' => $def['category'],
                    'priority' => $def['priority'],
                    'description' => $def['description'],
                    'status' => $def['status'],
                    'resolution_note' => $def['resolution_note'],
                    'rejection_reason' => $def['rejection_reason'],
                ],
            );
        }

        if (isset($this->matrix['rent-active-primary'])) {
            $this->matrix['rent-active-primary']['maintenance'] = 'pending / in_progress / completed(+note) / rejected(+reason)';
        }
    }

    private function replacePayments(Invoice $invoice): void
    {
        $paymentIds = Payment::query()->where('invoice_id', $invoice->id)->pluck('id');
        Receipt::query()->whereIn('payment_id', $paymentIds)->delete();
        Payment::query()->where('invoice_id', $invoice->id)->delete();
    }

    /**
     * @return array<string, string>
     */
    private function row(
        string $email,
        Room $room,
        Contract $contract,
        string $invoice,
        string $payment,
        string $receipt,
        string $maintenance,
    ): array {
        return [
            'demo_account' => $email,
            'room' => $room->room_number.' ('.$room->status.')',
            'contract' => $contract->type.'/'.$contract->status.' #'.$contract->contract_number,
            'invoice' => $invoice,
            'payment' => $payment,
            'receipt' => $receipt,
            'maintenance' => $maintenance,
        ];
    }

    private function printMatrix(): void
    {
        $rows = [];
        foreach ($this->matrix as $key => $row) {
            $rows[] = [
                $key,
                $row['demo_account'],
                $row['room'],
                $row['contract'],
                $row['invoice'],
                $row['payment'],
                $row['receipt'],
                $row['maintenance'],
            ];
        }

        $this->command?->info('Workflow scenario matrix:');
        $this->command?->table(
            ['Scenario', 'Demo account', 'Room', 'Contract', 'Invoice', 'Payment', 'Receipt', 'Maintenance'],
            $rows,
        );
    }
}
