<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractLifecycleService;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Rebuilds Invoice/Payment/Receipt histories for Bulk demo contracts
 * so Paid Amount and Remaining Amount reconcile with ContractLifecycleService.
 *
 * Safe / idempotent: only rewrites billing rows for Bulk demo contracts.
 * Does not truncate tables or wipe Workflow/manual data.
 */
class ContractFinancialConsistencySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->command?->warn('Admin user required before financial consistency seeding.');

            return;
        }

        $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');
        $paymentMethods = PaymentMethod::query()->orderBy('id')->get();
        $asOf = Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF)->startOfDay();

        $renamed = \Database\Seeders\Support\BillingSeederSupport::normalizeLegacySeedInvoiceNumbers();
        if ($renamed > 0) {
            $this->command?->info("Normalized {$renamed} legacy INV-CF-* invoice numbers to INV-000000 format.");
        }

        // Ensure some active/completed bulk sales use installment plans.
        $installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->orderByDesc('duration_months')
            ->first();

        if ($installmentPlan) {
            $fullPlanId = PaymentPlan::query()->where('payment_type', 'full')->value('id');

            foreach ([Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED] as $status) {
                Contract::query()
                    ->where('type', 'sale')
                    ->where('status', $status)
                    ->where('remark', 'like', 'Bulk demo%')
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->each(function (Contract $contract, int $index) use ($installmentPlan, $fullPlanId): void {
                        if ($index % 2 === 0) {
                            $contract->update([
                                'payment_type' => 'installment',
                                'payment_plan_id' => $installmentPlan->id,
                                'duration_months' => $installmentPlan->duration_months ?? 12,
                                'billing_day' => $contract->billing_day ?: 5,
                            ]);

                            return;
                        }

                        $contract->update([
                            'payment_type' => 'full',
                            'payment_plan_id' => $fullPlanId,
                            'duration_months' => null,
                            'billing_day' => null,
                        ]);
                    });
            }
        }

        $contracts = Contract::query()
            ->with(['room', 'user', 'paymentPlan'])
            ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
            ->where('remark', 'like', 'Bulk demo%')
            ->orderBy('id')
            ->get();

        $support = new ContractFinancialHistorySeederSupport(
            $admin,
            $chargeTypes,
            $paymentMethods,
            $asOf,
        );

        $stats = $support->reconcileContracts($contracts);

        // Sync lifecycle completion only for bulk contracts that are fully settled.
        $lifecycle = app(ContractLifecycleService::class);
        Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('remark', 'like', 'Bulk demo%')
            ->orderBy('id')
            ->get()
            ->each(fn (Contract $contract) => $lifecycle->syncAfterPayment($contract, $asOf));

        $this->command?->info(sprintf(
            'Financial consistency: %d contracts, %d invoices, %d payments, %d receipts.',
            $stats['contracts'],
            $stats['invoices'],
            $stats['payments'],
            $stats['receipts'],
        ));

        // Rent/sale financial rebuild can leave pending utilities against finalized
        // period invoices — reconcile Utility ↔ Invoice approval lifecycle next.
        $this->call(UtilityInvoiceConsistencySeeder::class);
    }
}
