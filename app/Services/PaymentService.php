<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Invoice $invoice, array $attributes): Payment
    {
        return DB::transaction(function () use ($invoice, $attributes): Payment {
            $amount = (float) $attributes['amount'];

            if ($amount <= 0) {
                abort(422, 'Payment amount must be greater than zero.');
            }

            if ($amount > $invoice->outstandingBalance()) {
                abort(422, 'Payment amount cannot exceed outstanding balance.');
            }

            $payment = $invoice->payments()->create([
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'payment_method' => $attributes['payment_method'],
                'reference_note' => $attributes['reference_note'] ?? null,
                'slip_upload' => $attributes['slip_upload'] ?? null,
                'recorded_by_user_id' => $attributes['recorded_by_user_id'] ?? null,
            ]);

            Receipt::create([
                'receipt_number' => $this->nextReceiptNumber(),
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'issued_date' => $attributes['payment_date'],
                'amount' => $amount,
                'file_path' => null,
            ]);

            $this->syncInvoiceTotals($invoice->fresh());

            return $payment->fresh(['receipt', 'invoice']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Payment $payment, array $attributes): Payment
    {
        return DB::transaction(function () use ($payment, $attributes): Payment {
            $payment = Payment::query()->lockForUpdate()->with(['invoice', 'receipt'])->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $amount = (float) $attributes['amount'];

            if ($amount <= 0) {
                abort(422, 'Payment amount must be greater than zero.');
            }

            $otherPaymentsTotal = (float) $invoice->payments()
                ->where('id', '!=', $payment->id)
                ->sum('amount');

            if (round($otherPaymentsTotal + $amount, 2) > (float) $invoice->total_amount) {
                abort(422, 'Updated payment amount cannot exceed invoice total.');
            }

            $payment->update([
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'payment_method' => $attributes['payment_method'],
                'reference_note' => $attributes['reference_note'] ?? null,
                'slip_upload' => $attributes['slip_upload'] ?? null,
                'recorded_by_user_id' => $attributes['recorded_by_user_id'] ?? $payment->recorded_by_user_id,
            ]);

            if ($payment->receipt) {
                $payment->receipt->update([
                    'issued_date' => $attributes['payment_date'],
                    'amount' => $amount,
                ]);
            }

            $this->syncInvoiceTotals($invoice->fresh());

            return $payment->fresh(['receipt', 'invoice']);
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $payment->delete();
            $this->syncInvoiceTotals($invoice->fresh());
        });
    }

    protected function syncInvoiceTotals(Invoice $invoice): void
    {
        $paidAmount = round((float) $invoice->payments()->sum('amount'), 2);

        $invoice->paid_amount = $paidAmount;
        $invoice->status = $this->resolveInvoiceStatus(
            paidAmount: $paidAmount,
            totalAmount: (float) $invoice->total_amount,
            dueDate: $invoice->due_date?->toDateString()
        );
        $invoice->save();
    }

    protected function resolveInvoiceStatus(float $paidAmount, float $totalAmount, ?string $dueDate = null): string
    {
        $isPastDue = $dueDate ? Carbon::parse($dueDate)->isBefore(now()->startOfDay()) : false;

        if ($isPastDue && $paidAmount < $totalAmount) {
            return Invoice::STATUS_OVERDUE;
        }

        if ($paidAmount <= 0.0) {
            return Invoice::STATUS_UNPAID;
        }

        if ($paidAmount < $totalAmount) {
            return Invoice::STATUS_PARTIAL;
        }

        return Invoice::STATUS_PAID;
    }

    protected function nextReceiptNumber(): string
    {
        return 'RCT-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
