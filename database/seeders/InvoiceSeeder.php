<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Models\Utility;
use Carbon\Carbon;
use Database\Seeders\Support\BillingSeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        BillingSeederSupport::resetSequences();

        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        if (! $admin) {
            $this->command?->warn('Admin user is required. Run UserSeeder first.');

            return;
        }

        $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');

        $contracts = Contract::query()
            ->with('room')
            ->orderBy('id')
            ->get();

        if ($contracts->isEmpty()) {
            $this->command?->warn('Contracts are required. Run ContractSeeder first.');

            return;
        }

        $utilities = Utility::query()
            ->where('status', 'approved')
            ->orderBy('billing_month')
            ->get()
            ->groupBy('room_id');

        $activeRentSeeded = false;

        foreach ($contracts as $contract) {
            if ($contract->type === 'rent' && $contract->status === 'active') {
                if (! $activeRentSeeded) {
                    $this->seedPrimaryActiveRentScenario($admin, $contract, $chargeTypes, $utilities);
                    $activeRentSeeded = true;
                } else {
                    $this->seedSecondaryActiveRentInvoices($admin, $contract, $chargeTypes, $utilities);
                }

                continue;
            }

            if ($contract->type === 'rent' && $contract->status === 'completed') {
                $this->seedCompletedRentInvoices($admin, $contract, $chargeTypes, $utilities);

                continue;
            }

            if ($contract->type === 'sale' && in_array($contract->status, ['approved', 'completed'], true)) {
                $this->seedSaleInvoices($admin, $contract, $chargeTypes);
            }
        }
    }

    /**
     * Guarantees unpaid, partial, and paid invoices for the primary occupied rental.
     *
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     * @param  Collection<int|string, Collection<int, Utility>>  $utilities
     */
    private function seedPrimaryActiveRentScenario(
        User $admin,
        Contract $contract,
        Collection $chargeTypes,
        Collection $utilities,
    ): void {
        $rent = (float) $contract->room->rent_price;
        $start = Carbon::parse($contract->start_date)->startOfMonth();

        // Paid historical months
        foreach ([0, 1, 2] as $index) {
            $month = $start->copy()->addMonths($index);
            $issuedDate = $month->copy()->day(min((int) ($contract->billing_day ?: 5), 28));

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: null,
                type: 'rent',
                status: 'paid',
                issuedDate: $issuedDate,
                dueDate: $issuedDate->copy()->addDays(7),
                items: [[
                    'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                    'description' => 'Monthly rent — '.$month->format('F Y'),
                    'amount' => $rent,
                ]],
            );
        }

        // Partially paid invoice
        $partialMonth = $start->copy()->addMonths(3);
        $partialIssued = $partialMonth->copy()->day(min((int) ($contract->billing_day ?: 5), 28));
        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'rent',
            status: 'partial',
            issuedDate: $partialIssued,
            dueDate: $partialIssued->copy()->addDays(7),
            items: [[
                'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                'description' => 'Monthly rent — '.$partialMonth->format('F Y'),
                'amount' => $rent,
            ]],
        );

        // Unpaid issued invoice (no payment created later for this one except optional rejected)
        $unpaidMonth = $start->copy()->addMonths(4);
        $unpaidIssued = $unpaidMonth->copy()->day(min((int) ($contract->billing_day ?: 5), 28));
        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'rent',
            status: 'issued',
            issuedDate: $unpaidIssued,
            dueDate: $unpaidIssued->copy()->addDays(14),
            items: [[
                'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                'description' => 'Monthly rent — '.$unpaidMonth->format('F Y'),
                'amount' => $rent,
            ]],
        );

        // Current overdue-style open invoice used for rejected payment scenario
        $openMonth = $start->copy()->addMonths(5);
        $openIssued = $openMonth->copy()->day(min((int) ($contract->billing_day ?: 5), 28));
        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'rent',
            status: 'issued',
            issuedDate: $openIssued,
            dueDate: now()->subDays(3),
            items: [[
                'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                'description' => 'Monthly rent — '.$openMonth->format('F Y'),
                'amount' => $rent,
            ]],
            lateFee: 25000,
        );

        $this->seedUtilityInvoices($admin, $contract, $chargeTypes, $utilities, onlyPaid: false);
    }

    /**
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     * @param  Collection<int|string, Collection<int, Utility>>  $utilities
     */
    private function seedSecondaryActiveRentInvoices(
        User $admin,
        Contract $contract,
        Collection $chargeTypes,
        Collection $utilities,
    ): void {
        $rent = (float) $contract->room->rent_price;
        $start = Carbon::parse($contract->start_date)->startOfMonth();

        foreach ([0, 1, 2] as $index) {
            $month = $start->copy()->addMonths($index);
            $issuedDate = $month->copy()->day(min((int) ($contract->billing_day ?: 5), 28));
            $status = $index < 2 ? 'paid' : 'issued';

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: null,
                type: 'rent',
                status: $status,
                issuedDate: $issuedDate,
                dueDate: $issuedDate->copy()->addDays(7),
                items: [[
                    'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                    'description' => 'Monthly rent — '.$month->format('F Y'),
                    'amount' => $rent,
                ]],
            );
        }

        $this->seedUtilityInvoices($admin, $contract, $chargeTypes, $utilities, onlyPaid: false);
    }

    /**
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     * @param  Collection<int|string, Collection<int, Utility>>  $utilities
     */
    private function seedCompletedRentInvoices(
        User $admin,
        Contract $contract,
        Collection $chargeTypes,
        Collection $utilities,
    ): void {
        $start = Carbon::parse($contract->start_date)->startOfMonth();
        $end = Carbon::parse($contract->end_date)->startOfMonth();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $issuedDate = $cursor->copy()->day(min((int) ($contract->billing_day ?: 5), 28));

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: null,
                type: 'rent',
                status: 'paid',
                issuedDate: $issuedDate,
                dueDate: $issuedDate->copy()->addDays(7),
                items: [[
                    'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                    'description' => 'Monthly rent — '.$cursor->format('F Y'),
                    'amount' => (float) $contract->room->rent_price,
                ]],
            );

            $cursor->addMonth();
        }

        $this->seedUtilityInvoices($admin, $contract, $chargeTypes, $utilities, onlyPaid: true);
    }

    /**
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     * @param  Collection<int|string, Collection<int, Utility>>  $utilities
     */
    private function seedUtilityInvoices(
        User $admin,
        Contract $contract,
        Collection $chargeTypes,
        Collection $utilities,
        bool $onlyPaid,
    ): void {
        $roomUtilities = $utilities->get($contract->room_id, collect());

        foreach ($roomUtilities as $utility) {
            $billingMonth = Carbon::parse($utility->billing_month);
            $contractStart = Carbon::parse($contract->start_date)->startOfMonth();
            $contractEnd = Carbon::parse($contract->end_date)->startOfMonth();

            if ($billingMonth->lt($contractStart) || $billingMonth->gt($contractEnd)) {
                continue;
            }

            if (Invoice::query()->where('utility_id', $utility->id)->exists()) {
                continue;
            }

            $issuedDate = $billingMonth->copy()->day(10);
            $status = $onlyPaid ? 'paid' : ($billingMonth->isCurrentMonth() ? 'issued' : 'paid');

            $items = $utility->items()->with('utilityType')->get()->map(function ($item) use ($chargeTypes) {
                return [
                    'charge_type_id' => $chargeTypes->get('utility-charges')?->id,
                    'description' => ($item->utilityType?->name ?? 'Utility').' — meter snapshot',
                    'previous_reading' => $item->previous_reading,
                    'current_reading' => $item->current_reading,
                    'usage' => $item->usage,
                    'unit_price' => $item->unit_price,
                    'amount' => (float) $item->amount,
                ];
            })->all();

            if ($items === []) {
                $items = [[
                    'charge_type_id' => $chargeTypes->get('utility-charges')?->id,
                    'description' => 'Utility bill for '.$billingMonth->format('F Y'),
                    'amount' => (float) $utility->total_amount,
                ]];
            }

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: $utility->id,
                type: 'utility',
                status: $status,
                issuedDate: $issuedDate,
                dueDate: $issuedDate->copy()->addDays(10),
                items: $items,
            );
        }
    }

    /**
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     */
    private function seedSaleInvoices(User $admin, Contract $contract, Collection $chargeTypes): void
    {
        $startDate = Carbon::parse($contract->start_date);
        $depositAmount = (float) $contract->deposit_amount;
        $balanceAmount = max((float) $contract->contract_total - $depositAmount, 0);

        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'other',
            status: 'paid',
            issuedDate: $startDate,
            dueDate: $startDate->copy()->addDays(7),
            items: [[
                'charge_type_id' => $chargeTypes->get('booking-deposit')?->id,
                'description' => 'Booking deposit — '.$contract->contract_number,
                'amount' => $depositAmount,
            ]],
        );

        if ($contract->payment_type === 'installment' && $contract->duration_months) {
            $installmentAmount = round($balanceAmount / $contract->duration_months, 2);
            $remaining = $balanceAmount;

            for ($index = 0; $index < $contract->duration_months; $index++) {
                $issuedDate = $startDate->copy()->addMonths($index + 1)->day(5);
                $amount = $index === $contract->duration_months - 1
                    ? round($remaining, 2)
                    : $installmentAmount;
                $remaining -= $amount;

                $isHistorical = $contract->status === 'completed'
                    || $issuedDate->lt(now()->startOfMonth());

                BillingSeederSupport::createInvoice(
                    admin: $admin,
                    contractId: $contract->id,
                    utilityId: null,
                    type: 'other',
                    status: $isHistorical ? 'paid' : 'issued',
                    issuedDate: $issuedDate,
                    dueDate: $issuedDate->copy()->addDays(14),
                    items: [[
                        'charge_type_id' => $chargeTypes->get('sale-installment')?->id,
                        'description' => 'Sale installment '.($index + 1).' — '.$contract->contract_number,
                        'amount' => $amount,
                    ]],
                );
            }

            return;
        }

        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'other',
            status: $contract->status === 'completed' ? 'paid' : 'issued',
            issuedDate: $startDate->copy()->addDays(14),
            dueDate: $startDate->copy()->addDays(30),
            items: [[
                'charge_type_id' => $chargeTypes->get('sale-installment')?->id,
                'description' => 'Sale balance — '.$contract->contract_number,
                'amount' => $balanceAmount,
            ]],
        );
    }
}
