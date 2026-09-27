<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;

/**
 * Authoritative payment/receipt financial summary.
 *
 * SUBTOTAL / LATE FEE / TOTAL come from Invoice.
 * PAID is the tendered amount for this payment.
 * CHANGE (cash over-tender) or BALANCE (exact settlement) — mutually exclusive.
 */
final class PaymentFinancialSummary
{
    /**
     * @return array{
     *     subtotal: float,
     *     late_fee: float,
     *     total: float,
     *     paid: float,
     *     applied_amount: float,
     *     change: float|null,
     *     balance: float|null,
     *     show_change: bool,
     *     settlement_label: 'change'|'balance'
     * }
     */
    public static function fromPayment(?Payment $payment, ?Invoice $invoice = null): array
    {
        $invoice ??= $payment?->invoice;

        $subtotal = round((float) ($invoice?->total_amount ?? 0), 2);
        $lateFee = round((float) ($invoice?->late_fee ?? 0), 2);
        $total = round($subtotal + $lateFee, 2);

        $applied = $payment !== null && $payment->amount !== null && $payment->amount !== ''
            ? round((float) $payment->amount, 2)
            : 0.0;

        $paid = self::resolvePaidAmount($payment, $applied);

        // Authoritative cash change = tendered − applied (same as PaymentResource refund_amount).
        // When amount settles the invoice total, this equals PAID − TOTAL.
        $change = self::resolveChange($payment, $paid, $total);
        $showChange = $change > 0;

        return [
            'subtotal' => $subtotal,
            'late_fee' => $lateFee,
            'total' => $total,
            'paid' => $paid,
            'applied_amount' => $applied,
            // Mutually exclusive settlement row for this payment (no partial-payment UI).
            'change' => $showChange ? $change : null,
            'balance' => $showChange ? null : 0.0,
            'show_change' => $showChange,
            'settlement_label' => $showChange ? 'change' : 'balance',
        ];
    }

    private static function resolvePaidAmount(?Payment $payment, float $applied): float
    {
        if ($payment === null) {
            return 0.0;
        }

        // Cash tender is stored on amount_received; non-cash uses the applied amount.
        if ($payment->amount_received !== null && $payment->amount_received !== '') {
            return round((float) $payment->amount_received, 2);
        }

        return $applied;
    }

    private static function resolveChange(?Payment $payment, float $paid, float $total): float
    {
        if ($payment !== null
            && $payment->amount_received !== null
            && $payment->amount_received !== ''
            && $payment->amount !== null
            && $payment->amount !== ''
        ) {
            return max(round((float) $payment->amount_received - (float) $payment->amount, 2), 0);
        }

        return max(round($paid - $total, 2), 0);
    }
}
