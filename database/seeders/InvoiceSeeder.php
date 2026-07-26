<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use App\Models\Utility;
use Carbon\Carbon;
use Database\Seeders\Support\BillingSeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
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

        foreach ($contracts as $contract) {
            match (true) {
                $contract->type === 'rent' && $contract->status === 'active' => $this->seedActiveRentInvoices($admin, $contract, $chargeTypes, $utilities),
                $contract->type === 'rent' && $contract->status === 'completed' => $this->seedCompletedRentInvoices($admin, $contract, $chargeTypes, $utilities),
                $contract->type === 'sale' && in_array($contract->status, ['approved', 'completed'], true) => $this->seedSaleInvoices($admin, $contract, $chargeTypes),
                default => null,
            };
        }
    }

    /**
     * @param  Collection<string, \App\Models\ChargeType>  $chargeTypes
     * @param  Collection<int|string, Collection<int, Utility>>  $utilities
     */
    private function seedActiveRentInvoices(
        User $admin,
        Contract $contract,
        Collection $chargeTypes,
        Collection $utilities,
    ): void {
        $start = Carbon::parse($contract->start_date)->startOfMonth();
        $months = collect();

        for ($index = 0; $index < 6; $index++) {
            $months->push($start->copy()->addMonths($index));
        }

        foreach ($months as $index => $month) {
            $issuedDate = $month->copy()->day(min((int) $contract->billing_day, 28));
            $dueDate = $issuedDate->copy()->addDays(7);
            $status = match ($index) {
                0, 1, 2, 3 => 'paid',
                4 => 'issued',
                default => 'overdue',
            };

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: null,
                type: 'rent',
                status: $status,
                issuedDate: $issuedDate,
                dueDate: $dueDate,
                items: [[
                    'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                    'description' => 'Monthly rent — '.$month->format('F Y'),
                    'amount' => (float) $contract->room->rent_price,
                ]],
                lateFee: $status === 'overdue' ? 25000 : 0,
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
            $issuedDate = $cursor->copy()->day(min((int) $contract->billing_day, 28));
            $dueDate = $issuedDate->copy()->addDays(7);

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: null,
                type: 'rent',
                status: 'paid',
                issuedDate: $issuedDate,
                dueDate: $dueDate,
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

            $issuedDate = $billingMonth->copy()->day(10);
            $dueDate = $issuedDate->copy()->addDays(10);
            $status = $onlyPaid ? 'paid' : ($billingMonth->isCurrentMonth() ? 'issued' : 'paid');

            BillingSeederSupport::createInvoice(
                admin: $admin,
                contractId: $contract->id,
                utilityId: $utility->id,
                type: 'utility',
                status: $status,
                issuedDate: $issuedDate,
                dueDate: $dueDate,
                items: [[
                    'charge_type_id' => $chargeTypes->get('utility-charges')?->id,
                    'description' => 'Utility bill for '.$billingMonth->format('F Y'),
                    'amount' => (float) $utility->total_amount,
                ]],
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
                $dueDate = $issuedDate->copy()->addDays(14);
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
                    status: $isHistorical ? 'paid' : ($index === $contract->duration_months - 1 ? 'issued' : 'paid'),
                    issuedDate: $issuedDate,
                    dueDate: $dueDate,
                    items: [[
                        'charge_type_id' => $chargeTypes->get('sale-installment')?->id,
                        'description' => 'Sale installment '.($index + 1).' — '.$contract->contract_number,
                        'amount' => $amount,
                    ]],
                );
            }

            return;
        }

        $balanceStatus = $contract->status === 'completed' ? 'paid' : 'issued';

        BillingSeederSupport::createInvoice(
            admin: $admin,
            contractId: $contract->id,
            utilityId: null,
            type: 'other',
            status: $balanceStatus,
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
