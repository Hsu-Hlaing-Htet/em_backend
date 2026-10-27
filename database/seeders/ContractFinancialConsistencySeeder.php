<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentPlan;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractLifecycleService;
use App\Services\InvoiceService;
use Database\Seeders\Support\BillingSeederSupport;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Builds Invoice/Payment/Receipt histories for new Bulk demo contracts
 * so Paid Amount and Remaining Amount reconcile with ContractLifecycleService.
 *
 * Existing financial histories are left intact on subsequent seed runs.
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
            ->orderBy('duration_months')
            ->first();

        if ($installmentPlan) {
            $fullPlanId = PaymentPlan::query()->where('payment_type', 'full')->value('id');

            // Keep plan assignment stable when a sale moves from active to completed
            // during the first seed run. Its position must not depend on status.
            $unbilledSales = Contract::query()
                ->where('type', 'sale')
                ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
                ->where('remark', 'like', 'Bulk demo%')
                ->doesntHave('invoices')
                ->orderBy('id')
                ->get()->values();
            $completedInstallmentId = $unbilledSales->firstWhere('status', Contract::STATUS_COMPLETED)?->id;

            $unbilledSales->each(function (Contract $contract, int $index) use ($installmentPlan, $fullPlanId, $completedInstallmentId): void {
                    if ($index % 2 === 0 || $contract->id === $completedInstallmentId) {
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

        $contracts = Contract::query()
            ->with(['room', 'user', 'paymentPlan'])
            ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
            ->where('remark', 'like', 'Bulk demo%')
            ->doesntHave('invoices')
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
        $newlyCompletedIds = [];
        Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('remark', 'like', 'Bulk demo%')
            ->orderBy('id')
            ->get()
            ->each(function (Contract $contract) use ($lifecycle, $asOf, &$newlyCompletedIds): void {
                $updated = $lifecycle->syncAfterPayment($contract, $asOf);
                if ($updated->status === Contract::STATUS_COMPLETED) {
                    $newlyCompletedIds[] = $contract->id;
                }
            });

        // A completion changes the payment scenario. Rebuild its final history now
        // so a second seed run does not add payments or receipts later.
        if ($newlyCompletedIds !== []) {
            $support->reconcileContracts(Contract::query()
                ->with(['room', 'user', 'paymentPlan'])
                ->whereKey($newlyCompletedIds)
                ->orderBy('id')
                ->get());
        }

        $this->command?->info(sprintf(
            'Financial consistency: %d contracts, %d invoices, %d payments, %d receipts.',
            $stats['contracts'],
            $stats['invoices'],
            $stats['payments'],
            $stats['receipts'],
        ));

        $normalized = \Database\Seeders\Support\LateFeeSeedSupport::normalizeHistoricalInvoicePolicies($asOf);
        if ($normalized > 0) {
            $this->command?->info("Normalized Late Fee policy snapshots on {$normalized} invoices.");
        }

        // Rent/sale financial rebuild can leave pending utilities against finalized
        // period invoices — reconcile Utility ↔ Invoice approval lifecycle next.
        $this->call(UtilityInvoiceConsistencySeeder::class);
        $this->seedPartialSaleExample($admin);
        $this->seedCancelledDraftExamples($admin);
    }

    private function seedPartialSaleExample(User $admin): void
    {
        if (Payment::query()->where('note', 'like', '%[bulk-partial-sale]%')->exists()) {
            return;
        }

        $contract = Contract::query()
            ->where('type', 'sale')
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('payment_type', 'installment')
            ->where('remark', 'like', 'Bulk demo%')
            ->orderBy('id')->first();
        if (! $contract) {
            return;
        }

        $invoice = $contract->invoices()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE, Invoice::STATUS_DRAFT])
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', Payment::STATUS_APPROVED))
            ->orderBy('due_date')->first();
        if (! $invoice) {
            return;
        }

        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $invoice->update([
                'status' => Invoice::STATUS_ISSUED,
                'issued_date' => Carbon::parse($invoice->due_date)->subDays(7)->toDateString(),
                'approved_by' => $admin->id,
                'approved_at' => Carbon::parse($invoice->due_date)->subDays(7),
            ]);
        }

        $method = PaymentMethod::query()->where('status', 'active')->orderBy('id')->first();
        if (! $method) {
            return;
        }
        $amount = round(((float) $invoice->total_amount + (float) $invoice->late_fee) * 0.45, 2);
        $paymentDate = Carbon::parse($invoice->due_date)->subDay();
        $payment = BillingSeederSupport::upsertPayment($invoice, '[bulk-partial-sale]', [
            'payment_method_id' => $method->id,
            'created_by' => $contract->user_id,
            'approved_by' => $admin->id,
            'approved_at' => $paymentDate->copy()->addDay(),
            'amount' => $amount,
            'payment_date' => $paymentDate->toDateString(),
            'status' => Payment::STATUS_APPROVED,
            'proof_image_path' => null,
            'note' => 'First approved installment payment; balance remains outstanding.',
        ]);
        BillingSeederSupport::upsertReceipt($payment, $admin, Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED, $paymentDate->copy()->addDays(2));
        $invoice->update(['status' => Invoice::STATUS_PARTIAL]);
    }

    private function seedCancelledDraftExamples(User $admin): void
    {
        BillingSeederSupport::resetSequences();
        $contracts = Contract::query()->with('room')
            ->where('type', 'rent')->where('status', Contract::STATUS_ACTIVE)
            ->where('remark', 'not like', 'Bulk demo%')
            ->orderBy('id')->get()->values();
        if ($contracts->isEmpty()) {
            return;
        }

        $charge = ChargeType::query()->where('slug', 'monthly-rent')->first();
        if (! $charge) {
            return;
        }

        for ($i = 1; $i <= 3; $i++) {
            $description = sprintf('Cancelled duplicate rent draft %d', $i);
            if (Invoice::query()->whereHas('items', fn ($query) => $query->where('description', $description))->exists()) {
                continue;
            }
            $contract = $contracts[($i - 1) % $contracts->count()];
            $amount = (float) $contract->room->rent_price;
            $invoice = Invoice::query()->create([
                'contract_id' => $contract->id,
                'invoice_number' => BillingSeederSupport::nextInvoiceNumber(),
                'type' => 'rent',
                'created_by' => $admin->id,
                'due_date' => '2026-08-05',
                'total_amount' => $amount,
                'status' => Invoice::STATUS_DRAFT,
            ]);
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'charge_type_id' => $charge->id,
                'description' => $description,
                'amount' => $amount,
            ]);
            app(InvoiceService::class)->delete($invoice);
        }
    }
}
