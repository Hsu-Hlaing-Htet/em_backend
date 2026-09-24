<?php

namespace Database\Seeders\Support;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ContractLifecycleService;
use App\Support\ContractInvoiceSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds coherent Invoice → Payment → Receipt histories for demo contracts
 * using the same charge amounts as InvoiceService / ContractLifecycleService.
 */
final class ContractFinancialHistorySeederSupport
{
    public const DEMO_AS_OF = '2026-09-24';

    private readonly Carbon $asOf;

    /** @var list<int> */
    private array $touchedInvoiceIds = [];

    /**
     * @param  Collection<string, ChargeType>  $chargeTypes
     * @param  Collection<int, PaymentMethod>  $paymentMethods
     */
    public function __construct(
        private readonly User $admin,
        private readonly Collection $chargeTypes,
        private readonly Collection $paymentMethods,
        ?Carbon $asOf = null,
    ) {
        $this->asOf = ($asOf ?? Carbon::parse(self::DEMO_AS_OF))->copy()->startOfDay();
    }

    /**
     * Rebuild financial history for billable demo contracts.
     *
     * @param  Collection<int, Contract>  $contracts
     */
    public function reconcileContracts(Collection $contracts): array
    {
        BillingSeederSupport::resetSequences();

        $billable = $contracts
            ->filter(fn (Contract $contract) => in_array($contract->status, [
                Contract::STATUS_ACTIVE,
                Contract::STATUS_COMPLETED,
            ], true))
            ->values();

        $stats = [
            'contracts' => 0,
            'invoices' => 0,
            'payments' => 0,
            'receipts' => 0,
        ];

        foreach ($billable as $index => $contract) {
            $contract->loadMissing(['room', 'user', 'paymentPlan']);
            $this->touchedInvoiceIds = [];
            $this->clearPaymentsAndReceiptsForContract($contract);

            if ($contract->type === 'sale') {
                $created = $this->seedSaleHistory($contract, $index);
            } else {
                $created = $this->seedRentHistory($contract, $index);
            }

            $this->deleteUntouchedInvoices($contract);

            $stats['contracts']++;
            $stats['invoices'] += $created['invoices'];
            $stats['payments'] += $created['payments'];
            $stats['receipts'] += $created['receipts'];

            if ($contract->status === Contract::STATUS_COMPLETED) {
                app(ContractLifecycleService::class)->syncAfterPayment($contract->fresh(), $this->asOf);
            }
        }

        return $stats;
    }

    private function clearPaymentsAndReceiptsForContract(Contract $contract): void
    {
        DB::transaction(function () use ($contract): void {
            $invoiceIds = Invoice::query()
                ->where('contract_id', $contract->id)
                ->pluck('id');

            if ($invoiceIds->isEmpty()) {
                return;
            }

            $paymentIds = Payment::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->pluck('id');

            if ($paymentIds->isNotEmpty()) {
                Receipt::query()->whereIn('payment_id', $paymentIds)->delete();
                Payment::query()->whereIn('id', $paymentIds)->delete();
            }

            DB::table('utilities')
                ->whereIn('invoice_id', $invoiceIds)
                ->update(['invoice_id' => null]);
        });
    }

    private function deleteUntouchedInvoices(Contract $contract): void
    {
        $query = Invoice::query()->where('contract_id', $contract->id);

        if ($this->touchedInvoiceIds !== []) {
            $query->whereNotIn('id', $this->touchedInvoiceIds);
        }

        $query->each(function (Invoice $invoice): void {
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    /**
     * Periods due as of $asOf, ignoring contract status so completed contracts
     * can still receive a full historical schedule.
     *
     * @return list<array{billing_month: Carbon, due_date: Carbon, generate_date: Carbon}>
     */
    private function periodsForSeeding(Contract $contract, Carbon $asOf): array
    {
        $originalStatus = $contract->status;
        $contract->status = Contract::STATUS_ACTIVE;

        try {
            return ContractInvoiceSchedule::periodsDueForGeneration($contract, $asOf);
        } finally {
            $contract->status = $originalStatus;
        }
    }

    /**
     * @return array{invoices: int, payments: int, receipts: int}
     */
    private function seedSaleHistory(Contract $contract, int $index): array
    {
        $stats = ['invoices' => 0, 'payments' => 0, 'receipts' => 0];
        $deposit = round((float) $contract->deposit_amount, 2);
        $total = round((float) $contract->contract_total, 2);
        $scenario = $this->seedScenarioBucket($contract);

        // Booking deposit is credited toward the sale price (WorkflowScenario pattern).
        if ($deposit > 0) {
            $depositInvoice = $this->upsertChargeInvoice(
                $contract,
                'DEP',
                $contract->start_date?->copy()->subDays(3) ?? $this->asOf->copy()->subMonths(2),
                $contract->start_date?->copy() ?? $this->asOf->copy()->subMonths(2),
                [[
                    'charge_type_id' => $this->chargeTypes->get('booking-deposit')?->id,
                    'description' => 'Sale booking deposit',
                    'amount' => $deposit,
                ]],
                'paid',
            );
            $stats['invoices']++;
            $paymentStats = $this->settleInvoice($depositInvoice, 'approved', $contract, 'deposit');
            $stats['payments'] += $paymentStats['payments'];
            $stats['receipts'] += $paymentStats['receipts'];
        }

        if ($contract->payment_type === 'installment' && (int) ($contract->duration_months ?? 0) > 0) {
            // Follow production generation gate (due_date - 7 days ≤ demo as-of).
            // Do NOT pre-issue future installments for completed sales — that inflated
            // the global invoice list with 2027 due dates marked paid.
            $periods = $this->periodsForSeeding($contract, $this->asOf);
            $periodCount = count($periods);
            $installmentAmount = ConsolidatedBillingSeederSupport::installmentAmount($contract);

            foreach ($periods as $periodIndex => $period) {
                $billingMonth = $period['billing_month'];
                $dueDate = $period['due_date'];
                $issued = ($period['generate_date'] ?? $dueDate->copy()->subDays(ContractInvoiceSchedule::GENERATE_DAYS_BEFORE_DUE))->copy();
                $status = $this->installmentInvoiceStatus(
                    $contract->status,
                    $scenario,
                    $periodIndex,
                    $periodCount,
                );

                $invoice = $this->upsertChargeInvoice(
                    $contract,
                    'INS-'.$billingMonth->format('Ym'),
                    $issued,
                    $dueDate,
                    [[
                        'charge_type_id' => $this->chargeTypes->get('sale-installment')?->id,
                        'description' => 'Sale installment — '.$billingMonth->format('F Y'),
                        'amount' => $installmentAmount,
                    ]],
                    $status,
                    $billingMonth,
                    $status === 'overdue' ? 25000.0 : 0.0,
                );
                $stats['invoices']++;
                $paymentStats = $this->applyInvoicePaymentPattern($invoice, $status, $contract, 'ins-'.$billingMonth->format('Ym'));
                $stats['payments'] += $paymentStats['payments'];
                $stats['receipts'] += $paymentStats['receipts'];
            }

            // Completed sales: early-settle remaining schedule past the generation gate
            // as one paid settlement invoice (not individual future months).
            $plannedMonths = max((int) $contract->duration_months, 1);
            $remainingMonths = max($plannedMonths - $periodCount, 0);

            if ($contract->status === Contract::STATUS_COMPLETED && $remainingMonths > 0) {
                $settlementAmount = round($installmentAmount * $remainingMonths, 2);
                // Spread settlement timestamps so completed Sales do not all share
                // the same due_date (which clustered them at the top of the list).
                $offsetDays = 1 + ((crc32((string) $contract->contract_number) & 0x7fffffff) % 20);
                $issued = $this->asOf->copy()->subDays($offsetDays + 2);
                $due = $this->asOf->copy()->subDays($offsetDays);
                $invoice = $this->upsertChargeInvoice(
                    $contract,
                    'SETTLE',
                    $issued,
                    $due,
                    [[
                        'charge_type_id' => $this->chargeTypes->get('sale-installment')?->id
                            ?? $this->chargeTypes->get('booking-deposit')?->id,
                        'description' => 'Sale installment early settlement',
                        'amount' => $settlementAmount,
                    ]],
                    'paid',
                );
                $stats['invoices']++;
                $paymentStats = $this->settleInvoice($invoice, 'approved', $contract, 'settle');
                $stats['payments'] += $paymentStats['payments'];
                $stats['receipts'] += $paymentStats['receipts'];
            }

            return $stats;
        }

        // Full-payment sale: remaining purchase price after deposit.
        $balance = max(round($total - $deposit, 2), 0);
        if ($balance <= 0) {
            return $stats;
        }

        $issued = ($contract->start_date?->copy() ?? $this->asOf->copy())->addDays(2);
        $status = match (true) {
            $contract->status === Contract::STATUS_COMPLETED => 'paid',
            // Keep Active full-payment contracts open with a remaining balance.
            $scenario === 0 => 'partial',
            $scenario === 1 => 'partial',
            $scenario === 2 => 'issued',
            default => 'partial',
        };

        $invoice = $this->upsertChargeInvoice(
            $contract,
            'FULL',
            $issued,
            $issued->copy()->addDays(14),
            [[
                'charge_type_id' => $this->chargeTypes->get('sale-installment')?->id
                    ?? $this->chargeTypes->get('booking-deposit')?->id,
                'description' => 'Sale purchase settlement',
                'amount' => $balance,
            ]],
            $status,
        );
        $stats['invoices']++;
        $paymentStats = $this->applyInvoicePaymentPattern($invoice, $status, $contract, 'full');
        $stats['payments'] += $paymentStats['payments'];
        $stats['receipts'] += $paymentStats['receipts'];

        return $stats;
    }

    /**
     * @return array{invoices: int, payments: int, receipts: int}
     */
    private function seedRentHistory(Contract $contract, int $index): array
    {
        $stats = ['invoices' => 0, 'payments' => 0, 'receipts' => 0];
        $scenario = $this->seedScenarioBucket($contract);
        $asOf = $contract->status === Contract::STATUS_COMPLETED && $contract->end_date
            ? $contract->end_date->copy()
            : $this->asOf->copy();

        $periods = $this->periodsForSeeding($contract, $asOf);
        $periodCount = count($periods);
        $rent = round((float) ($contract->room?->rent_price ?? 0), 2);

        if ($rent <= 0) {
            $rent = round((float) $contract->contract_total / max((int) ($contract->duration_months ?? 12), 1), 2);
        }

        foreach ($periods as $periodIndex => $period) {
            $billingMonth = $period['billing_month'];
            $dueDate = $period['due_date'];
            $issued = ($period['generate_date'] ?? $dueDate->copy()->subDays(ContractInvoiceSchedule::GENERATE_DAYS_BEFORE_DUE))->copy();
            $status = $this->rentInvoiceStatus(
                $contract->status,
                $scenario,
                $periodIndex,
                $periodCount,
            );

            $invoice = $this->upsertChargeInvoice(
                $contract,
                'RENT-'.$billingMonth->format('Ym'),
                $issued,
                $dueDate,
                [[
                    'charge_type_id' => $this->chargeTypes->get('monthly-rent')?->id,
                    'description' => 'Monthly rent — '.$billingMonth->format('F Y'),
                    'amount' => $rent,
                ]],
                $status,
                $billingMonth,
                $status === 'overdue' ? 25000.0 : 0.0,
            );
            $stats['invoices']++;
            $paymentStats = $this->applyInvoicePaymentPattern($invoice, $status, $contract, 'rent-'.$billingMonth->format('Ym'));
            $stats['payments'] += $paymentStats['payments'];
            $stats['receipts'] += $paymentStats['receipts'];
        }

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function upsertChargeInvoice(
        Contract $contract,
        string $suffix,
        Carbon $issued,
        Carbon $due,
        array $items,
        string $status,
        ?Carbon $billingMonth = null,
        float $lateFee = 0,
    ): Invoice {
        $invoice = BillingSeederSupport::upsertSeedChargeInvoice(
            $suffix,
            $this->admin,
            $contract,
            $contract->type === 'sale' ? 'sale' : 'rent',
            $status,
            $issued,
            $due,
            $items,
            $lateFee,
            $billingMonth,
        );

        $this->touchedInvoiceIds[] = (int) $invoice->id;

        return $invoice;
    }

    /**
     * @return array{payments: int, receipts: int}
     */
    private function applyInvoicePaymentPattern(
        Invoice $invoice,
        string $status,
        Contract $contract,
        string $key,
    ): array {
        return match ($status) {
            'paid' => $this->settleInvoice($invoice, 'approved', $contract, $key),
            'partial' => $this->settleInvoice($invoice, 'partial', $contract, $key),
            'overdue' => $this->settleInvoice($invoice, 'pending', $contract, $key),
            'issued' => ($contract->id + strlen($key)) % 5 === 0
                ? $this->settleInvoice($invoice, 'rejected', $contract, $key)
                : ['payments' => 0, 'receipts' => 0],
            // Newly generated invoices await Admin issue/approve — no payment yet.
            'draft' => ['payments' => 0, 'receipts' => 0],
            default => ['payments' => 0, 'receipts' => 0],
        };
    }

    /**
     * @return array{payments: int, receipts: int}
     */
    private function settleInvoice(
        Invoice $invoice,
        string $mode,
        Contract $contract,
        string $key,
    ): array {
        $method = $this->paymentMethods->firstWhere('slug', 'kbz-pay')
            ?? $this->paymentMethods->first();
        $totalDue = round((float) $invoice->total_amount + (float) $invoice->late_fee, 2);
        $noteKey = '[CF:'.$contract->contract_number.':'.$key.']';
        $issued = Carbon::parse($invoice->issued_date ?? $invoice->due_date ?? $this->asOf);
        $payerId = (int) $contract->user_id;
        if (
            $contract->second_user_id
            && (((int) $contract->id + strlen($key)) % 2 === 0)
        ) {
            $payerId = (int) $contract->second_user_id;
        }

        if ($mode === 'pending') {
            BillingSeederSupport::upsertPayment($invoice, $noteKey, [
                'payment_method_id' => $method?->id,
                'created_by' => $payerId,
                'approved_by' => null,
                'approved_at' => null,
                'amount' => null,
                'proof_image_path' => 'payments/cf-'.$contract->contract_number.'-'.$key.'-pending.jpg',
                'rejection_reason' => null,
                'payment_date' => $this->asOf->copy()->subDays(2)->toDateString(),
                'status' => 'pending',
                'note' => $noteKey.' Pending admin review.',
            ]);

            return ['payments' => 1, 'receipts' => 0];
        }

        if ($mode === 'rejected') {
            BillingSeederSupport::upsertPayment($invoice, $noteKey, [
                'payment_method_id' => $method?->id,
                'created_by' => $payerId,
                'approved_by' => $this->admin->id,
                'approved_at' => $issued->copy()->addDays(2),
                'amount' => null,
                'proof_image_path' => 'payments/cf-'.$contract->contract_number.'-'.$key.'-rejected.jpg',
                'rejection_reason' => 'Transfer proof does not show the full MMK amount clearly.',
                'payment_date' => $issued->copy()->addDay()->toDateString(),
                'status' => 'rejected',
                'note' => $noteKey.' Rejected payment attempt.',
            ]);

            return ['payments' => 1, 'receipts' => 0];
        }

        $amount = $mode === 'partial'
            ? round($totalDue * 0.45, 2)
            : $totalDue;

        $payment = BillingSeederSupport::upsertPayment($invoice, $noteKey, [
            'payment_method_id' => $method?->id,
            'created_by' => $payerId,
            'approved_by' => $this->admin->id,
            'approved_at' => $issued->copy()->addDays(2),
            'amount' => $amount,
            'proof_image_path' => 'payments/cf-'.$contract->contract_number.'-'.$key.'-'.$mode.'.jpg',
            'rejection_reason' => null,
            'payment_date' => $issued->copy()->addDay()->toDateString(),
            'status' => 'approved',
            'note' => $noteKey.' '.($mode === 'partial' ? 'Partial' : 'Full').' settlement.',
        ]);

        $receipt = null;
        if ($mode === 'approved' || $mode === 'partial') {
            $receipt = BillingSeederSupport::upsertReceipt(
                $payment,
                $this->admin,
                Receipt::STATUS_ISSUED,
                Receipt::APPROVAL_APPROVED,
                $issued->copy()->addDays(3),
            );
        }

        if ($mode === 'partial') {
            $invoice->update(['status' => 'partial']);
        } elseif ($mode === 'approved') {
            $invoice->update(['status' => 'paid']);
        }

        return [
            'payments' => 1,
            'receipts' => $receipt ? 1 : 0,
        ];
    }

    private function seedScenarioBucket(Contract $contract): int
    {
        // Stable per contract_number so rent and sale both get draft/overdue/issued
        // mixes (sequential numeric suffixes alone cluster on even buckets).
        return (crc32((string) $contract->contract_number) & 0x7fffffff) % 4;
    }

    private function installmentInvoiceStatus(
        string $contractStatus,
        int $scenario,
        int $periodIndex,
        int $periodCount,
    ): string {
        if ($contractStatus === Contract::STATUS_COMPLETED) {
            return 'paid';
        }

        $isLast = $periodIndex === $periodCount - 1;

        // Latest generated cycle often awaits Admin issue (draft → approval queue).
        // Older cycles are settled/issued per scenario for realistic history.
        return match ($scenario) {
            0 => $isLast ? 'draft' : 'paid',
            1 => $isLast ? 'draft' : 'paid',
            2 => $isLast ? 'overdue' : ($periodIndex === $periodCount - 2 ? 'issued' : 'paid'),
            default => $isLast ? 'issued' : 'paid',
        };
    }

    private function rentInvoiceStatus(
        string $contractStatus,
        int $scenario,
        int $periodIndex,
        int $periodCount,
    ): string {
        if ($contractStatus === Contract::STATUS_COMPLETED) {
            return 'paid';
        }

        if ($periodCount <= 1) {
            return match ($scenario) {
                0, 1 => 'draft',
                2 => 'overdue',
                default => 'issued',
            };
        }

        $isLast = $periodIndex === $periodCount - 1;

        return match ($scenario) {
            0 => $isLast ? 'draft' : 'paid',
            1 => $isLast ? 'draft' : 'paid',
            2 => $isLast ? 'overdue' : 'paid',
            default => $isLast ? 'issued' : ($periodIndex === $periodCount - 2 ? 'partial' : 'paid'),
        };
    }
}
