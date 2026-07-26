<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Support\BillingSeederSupport;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $paymentMethods = BillingSeederSupport::paymentMethods();

        if (! $admin || $paymentMethods->isEmpty()) {
            $this->command?->warn('Admin user and payment methods are required. Run UserSeeder and PaymentMethodSeeder first.');

            return;
        }

        $invoices = Invoice::query()
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->orderBy('issued_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->command?->warn('Payable invoices are required. Run InvoiceSeeder first.');

            return;
        }

        $methodIndex = 0;

        foreach ($invoices as $invoice) {
            $paymentMethod = $paymentMethods[$methodIndex % $paymentMethods->count()];
            $methodIndex++;
            $issuedDate = Carbon::parse($invoice->issued_date ?? now()->toDateString());

            if ($invoice->status === 'paid') {
                BillingSeederSupport::settleInvoice(
                    $admin,
                    $invoice,
                    $paymentMethod,
                    $issuedDate->copy()->addDays(3),
                );

                continue;
            }

            if ($invoice->status === 'partial') {
                $partialAmount = round((float) $invoice->total_amount * 0.45, 2);
                BillingSeederSupport::createApprovedPayment(
                    $admin,
                    $invoice,
                    $paymentMethod,
                    $partialAmount,
                    $issuedDate->copy()->addDays(5),
                );
                $invoice->update(['status' => 'partial']);

                continue;
            }

            if ($invoice->status === 'overdue') {
                BillingSeederSupport::createPendingPayment(
                    $admin,
                    $invoice,
                    $paymentMethod,
                    (float) $invoice->total_amount,
                    now()->subDays(2),
                );

                continue;
            }

            if ($invoice->status === 'issued') {
                if ($invoice->due_date?->isFuture()) {
                    BillingSeederSupport::createPendingPayment(
                        $admin,
                        $invoice,
                        $paymentMethod,
                        (float) $invoice->total_amount,
                        now()->subDay(),
                    );
                } else {
                    BillingSeederSupport::createApprovedPayment(
                        $admin,
                        $invoice,
                        $paymentMethod,
                        (float) $invoice->total_amount,
                        $issuedDate->copy()->addDays(4),
                    );
                    $invoice->update(['status' => 'paid']);
                }
            }
        }
    }
}
