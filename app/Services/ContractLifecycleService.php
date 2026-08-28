<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Support\ContractInvoiceSchedule;
use Illuminate\Support\Carbon;

class ContractLifecycleService
{
    public function syncAfterPayment(Contract $contract, ?Carbon $asOf = null): Contract
    {
        if ($contract->status !== Contract::STATUS_ACTIVE) {
            return $contract->fresh();
        }

        if ($contract->type === 'sale' && $this->saleCanBeCompleted($contract)) {
            return $this->complete($contract);
        }

        if ($contract->type === 'rent' && $this->rentCanBeCompleted($contract, $asOf ?? now())) {
            return $this->complete($contract);
        }

        return $contract->fresh();
    }

    public function syncEndedRentContracts(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? now();
        $completed = 0;

        Contract::query()
            ->where('type', 'rent')
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $asOf->toDateString())
            ->orderBy('id')
            ->get()
            ->each(function (Contract $contract) use ($asOf, &$completed): void {
                if ($this->rentCanBeCompleted($contract, $asOf)) {
                    $this->complete($contract);
                    $completed++;
                }
            });

        return $completed;
    }

    public function contractOutstandingBalance(Contract $contract): float
    {
        if ($contract->type === 'sale') {
            return $this->saleOutstandingBalance($contract);
        }

        return $this->invoiceOutstandingBalance($contract);
    }

    private function saleCanBeCompleted(Contract $contract): bool
    {
        if ($contract->payment_type === 'installment' && (int) ($contract->duration_months ?? 0) <= 0) {
            return false;
        }

        return $this->saleOutstandingBalance($contract) <= 0.009
            && $this->hasIssuedOrPaidInvoice($contract);
    }

    private function rentCanBeCompleted(Contract $contract, Carbon $asOf): bool
    {
        if (! $contract->end_date || $asOf->copy()->startOfDay()->lt($contract->end_date->copy()->startOfDay())) {
            return false;
        }

        if (! $this->allRequiredRentInvoicesExist($contract)) {
            return false;
        }

        return $this->invoiceOutstandingBalance($contract) <= 0.009;
    }

    private function complete(Contract $contract): Contract
    {
        $contract->update(['status' => Contract::STATUS_COMPLETED]);

        if ($contract->type === 'sale') {
            $contract->room?->update(['status' => Room::STATUS_SOLD]);
        }

        return $contract->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
    }

    private function saleOutstandingBalance(Contract $contract): float
    {
        $required = round((float) $contract->contract_total, 2);
        $paid = $this->approvedPaidAmount($contract);
        $contractBalance = max(round($required - $paid, 2), 0);
        $invoiceBalance = $this->invoiceOutstandingBalance($contract);

        return max($contractBalance, $invoiceBalance);
    }

    private function invoiceOutstandingBalance(Contract $contract): float
    {
        return round($contract->invoices()
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->with('payments')
            ->get()
            ->sum(fn (Invoice $invoice): float => $this->invoiceCurrentBalance($invoice)), 2);
    }

    private function approvedPaidAmount(Contract $contract): float
    {
        return round((float) Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereHas('invoice', fn ($query) => $query
                ->where('contract_id', $contract->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED))
            ->sum('amount'), 2);
    }

    private function invoiceCurrentBalance(Invoice $invoice): float
    {
        $invoice->loadMissing('payments');

        $total = round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2);
        $paid = round((float) $invoice->payments
            ->where('status', Payment::STATUS_APPROVED)
            ->sum(fn (Payment $payment): float => (float) ($payment->amount ?? 0)), 2);

        return max(round($total - $paid, 2), 0);
    }

    private function hasIssuedOrPaidInvoice(Contract $contract): bool
    {
        return $contract->invoices()
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->exists();
    }

    private function allRequiredRentInvoicesExist(Contract $contract): bool
    {
        if (! $contract->start_date || ! $contract->end_date) {
            return false;
        }

        $requiredPeriods = ContractInvoiceSchedule::periodsDueForGeneration(
            $contract,
            $contract->end_date->copy()->startOfDay(),
        );

        foreach ($requiredPeriods as $period) {
            $exists = $contract->invoices()
                ->whereDate('billing_month', $period['billing_month']->toDateString())
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->exists();

            if (! $exists) {
                return false;
            }
        }

        return true;
    }
}
