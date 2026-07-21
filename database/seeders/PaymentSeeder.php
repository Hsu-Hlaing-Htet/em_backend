<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
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

        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('id')->get();

        if (! $admin || $paymentMethods->isEmpty()) {
            $this->command?->warn('Admin user and payment methods are required. Run UserSeeder and PaymentMethodSeeder first.');

            return;
        }

        $payableInvoices = Invoice::query()
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->orderBy('id')
            ->get();

        if ($payableInvoices->isEmpty()) {
            $this->command?->warn('Payable invoices are required. Run InvoiceSeeder first.');

            return;
        }

        $paymentStatuses = array_merge(
            array_fill(0, 8, 'pending'),
            array_fill(0, 12, 'approved'),
            array_fill(0, 5, 'rejected'),
        );

        shuffle($paymentStatuses);

        $created = 0;
        $methodIndex = 0;

        foreach ($payableInvoices as $invoice) {
            if ($created >= 25) {
                break;
            }

            $status = $paymentStatuses[$created] ?? 'pending';
            $amount = match ($invoice->status) {
                'partial' => round((float) $invoice->total_amount * 0.45, 2),
                'paid' => (float) $invoice->total_amount,
                default => round((float) $invoice->total_amount * fake()->randomFloat(2, 0.4, 1), 2),
            };

            Payment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethods[$methodIndex % $paymentMethods->count()]->id,
                'created_by' => $admin->id,
                'approved_by' => $status === 'approved' ? $admin->id : ($status === 'rejected' ? $admin->id : null),
                'approved_at' => in_array($status, ['approved', 'rejected'], true)
                    ? now()->subDays(fake()->numberBetween(1, 10))
                    : null,
                'amount' => $amount,
                'proof_image_path' => $status === 'pending' ? null : 'payments/proof-'.$invoice->invoice_number.'.jpg',
                'note' => match ($status) {
                    'approved' => 'Payment completed successfully.',
                    'rejected' => 'Payment failed verification.',
                    default => 'Payment pending confirmation.',
                },
                'payment_date' => now()->subDays(fake()->numberBetween(1, 15))->toDateString(),
                'status' => $status,
            ]);

            $methodIndex++;
            $created++;
        }

        $fallbackInvoice = $payableInvoices->first();

        while ($created < 25 && $fallbackInvoice) {
            $status = $paymentStatuses[$created] ?? 'pending';

            Payment::query()->create([
                'invoice_id' => $fallbackInvoice->id,
                'payment_method_id' => $paymentMethods[$methodIndex % $paymentMethods->count()]->id,
                'created_by' => $admin->id,
                'approved_by' => $status === 'approved' ? $admin->id : null,
                'approved_at' => $status === 'approved' ? now()->subDay() : null,
                'amount' => round((float) $fallbackInvoice->total_amount * 0.25, 2),
                'proof_image_path' => null,
                'note' => 'Additional payment record.',
                'payment_date' => now()->subDay()->toDateString(),
                'status' => $status,
            ]);

            $methodIndex++;
            $created++;
        }
    }
}
