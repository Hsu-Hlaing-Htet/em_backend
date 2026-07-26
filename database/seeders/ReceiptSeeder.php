<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Support\BillingSeederSupport;
use Illuminate\Database\Seeder;

class ReceiptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        if (! $admin) {
            $this->command?->warn('Admin user is required. Run UserSeeder first.');

            return;
        }

        $approvedPayments = Payment::query()
            ->where('status', 'approved')
            ->whereDoesntHave('receipt')
            ->with('invoice')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        foreach ($approvedPayments as $payment) {
            if (! $payment->invoice) {
                continue;
            }

            $invoice = $payment->invoice;
            $approvedTotal = (float) Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', 'approved')
                ->sum('amount');

            if ($approvedTotal + 0.009 >= (float) $invoice->total_amount) {
                $invoice->update(['status' => 'paid']);
            } elseif ($approvedTotal > 0) {
                $invoice->update(['status' => 'partial']);
            }

            BillingSeederSupport::createIssuedReceipt(
                $admin,
                $payment,
                Carbon::parse($payment->payment_date)->addDay(),
            );
        }
    }
}
