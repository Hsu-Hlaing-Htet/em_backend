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
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $paymentMethods = BillingSeederSupport::paymentMethods();

        if (! $admin || $paymentMethods->isEmpty()) {
            $this->command?->warn('Admin user and payment methods are required.');

            return;
        }

        $invoices = Invoice::query()
            ->with('contract.user')
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->orderBy('issued_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->command?->warn('Payable invoices are required. Run InvoiceSeeder first.');

            return;
        }

        $methodIndex = 0;
        $unpaidLeftAlone = false;
        $rejectedCreated = false;

        foreach ($invoices as $invoice) {
            $paymentMethod = $paymentMethods[$methodIndex % $paymentMethods->count()];
            $methodIndex++;
            $issuedDate = Carbon::parse($invoice->issued_date ?? now()->toDateString());
            $customer = $invoice->contract?->user;

            if ($invoice->status === 'paid') {
                BillingSeederSupport::settleInvoice(
                    $admin,
                    $invoice,
                    $paymentMethod,
                    $issuedDate->copy()->addDays(3),
                    $customer,
                );

                continue;
            }

            if ($invoice->status === 'partial') {
                $partialAmount = round(((float) $invoice->total_amount) * 0.45, 2);
                $payment = BillingSeederSupport::createApprovedPayment(
                    $admin,
                    $invoice,
                    $paymentMethod,
                    $partialAmount,
                    $issuedDate->copy()->addDays(5),
                    note: 'Partial MMK payment received via KBZ Pay.',
                    createdBy: $customer,
                );
                BillingSeederSupport::createIssuedReceipt(
                    $admin,
                    $payment,
                    $issuedDate->copy()->addDays(6),
                );
                $invoice->update(['status' => 'partial']);

                continue;
            }

            // First open issued invoice without payments = unpaid scenario
            if ($invoice->status === 'issued' && ! $unpaidLeftAlone && $invoice->type === 'rent') {
                $unpaidLeftAlone = true;

                continue;
            }

            // Next open issued rent invoice gets a rejected payment attempt
            if ($invoice->status === 'issued' && ! $rejectedCreated && $customer && $invoice->type === 'rent') {
                BillingSeederSupport::createRejectedPayment(
                    $admin,
                    $customer,
                    $invoice,
                    $paymentMethod,
                    now()->subDays(2),
                    'Bank transfer proof is unclear. Please resubmit a clearer screenshot showing the full amount in MMK.',
                );
                $rejectedCreated = true;

                continue;
            }

            if ($invoice->status === 'issued') {
                BillingSeederSupport::createPendingPayment(
                    $customer ?? $admin,
                    $invoice,
                    $paymentMethod,
                    null,
                    now()->subDay(),
                    'Customer submitted payment proof for admin verification.',
                );
            }
        }

        $this->command?->info(sprintf(
            'Payments seeded (unpaid protected: %s, rejected created: %s).',
            $unpaidLeftAlone ? 'yes' : 'no',
            $rejectedCreated ? 'yes' : 'no',
        ));
    }
}
