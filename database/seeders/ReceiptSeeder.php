<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
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

        $receiptSequence = 1;

        $approvedPayments = Payment::query()
            ->where('status', 'approved')
            ->whereDoesntHave('receipt')
            ->with('invoice')
            ->orderBy('id')
            ->get();

        foreach ($approvedPayments as $payment) {
            if ($receiptSequence > 25) {
                break;
            }

            if (! $payment->invoice) {
                continue;
            }

            if (! in_array($payment->invoice->status, ['paid', 'partial', 'issued', 'overdue'], true)) {
                if ((float) $payment->amount >= (float) $payment->invoice->total_amount) {
                    $payment->invoice->update(['status' => 'paid']);
                } else {
                    continue;
                }
            }

            if ($payment->invoice->status === 'issued' && (float) $payment->amount >= (float) $payment->invoice->total_amount) {
                $payment->invoice->update(['status' => 'paid']);
            }

            Receipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => 'RCP-'.str_pad((string) $receiptSequence, 6, '0', STR_PAD_LEFT),
                'receipt_pdf_path' => 'receipts/RCP-'.str_pad((string) $receiptSequence, 6, '0', STR_PAD_LEFT).'.pdf',
                'status' => 'issued',
                'issued_at' => now()->subDays(fake()->numberBetween(1, 10)),
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
            ]);

            $receiptSequence++;
        }

        while ($receiptSequence <= 25) {
            $payment = Payment::query()
                ->where('status', 'approved')
                ->whereDoesntHave('receipt')
                ->with('invoice')
                ->orderBy('id')
                ->first();

            if (! $payment || ! $payment->invoice) {
                break;
            }

            $payment->invoice->update(['status' => 'paid']);

            Receipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => 'RCP-'.str_pad((string) $receiptSequence, 6, '0', STR_PAD_LEFT),
                'receipt_pdf_path' => 'receipts/RCP-'.str_pad((string) $receiptSequence, 6, '0', STR_PAD_LEFT).'.pdf',
                'status' => 'issued',
                'issued_at' => now(),
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
            ]);

            $receiptSequence++;
        }
    }
}
