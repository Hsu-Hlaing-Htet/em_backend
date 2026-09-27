<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pending customer/wallet payments historically stored amount=null until approve.
 * Approval List PAYMENT (MMK) reads payments.amount — backfill pending nulls to the
 * invoice remaining balance (same value approve would apply as full settlement).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pending = DB::table('payments')
            ->where('status', 'pending')
            ->whereNull('amount')
            ->select(['id', 'invoice_id'])
            ->get();

        foreach ($pending as $payment) {
            $invoice = DB::table('invoices')
                ->where('id', $payment->invoice_id)
                ->first(['id', 'total_amount', 'late_fee']);

            if (! $invoice) {
                continue;
            }

            $approvedPaid = (float) DB::table('payments')
                ->where('invoice_id', $invoice->id)
                ->where('status', 'approved')
                ->sum('amount');

            $totalDue = round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2);
            $balance = max(round($totalDue - $approvedPaid, 2), 0);

            if ($balance <= 0) {
                continue;
            }

            DB::table('payments')
                ->where('id', $payment->id)
                ->whereNull('amount')
                ->update(['amount' => $balance]);
        }
    }

    public function down(): void
    {
        // Irreversible data correction — amount was incorrectly null for display.
    }
};
