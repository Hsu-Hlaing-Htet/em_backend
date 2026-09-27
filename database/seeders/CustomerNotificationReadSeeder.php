<?php

namespace Database\Seeders;

use App\Models\CustomerNotificationRead;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Idempotent customer notification read markers for portal badge testing.
 *
 * Uses updateOrCreate on (user_id, notification_key). Never truncates.
 * Does not create or modify contracts, invoices, payments, or receipts.
 */
class CustomerNotificationReadSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->orderBy('id')
            ->limit(80)
            ->get();

        $created = 0;

        foreach ($customers as $customerIndex => $customer) {
            $invoiceIds = Invoice::query()
                ->whereHas('contract', fn ($query) => $query->accessibleBy($customer))
                ->orderByDesc('id')
                ->limit(8)
                ->pluck('id');

            $paymentIds = Payment::query()
                ->whereHas('invoice.contract', fn ($query) => $query->accessibleBy($customer))
                ->orderByDesc('id')
                ->limit(8)
                ->pluck('id');

            $receiptIds = Receipt::query()
                ->whereHas('payment.invoice.contract', fn ($query) => $query->accessibleBy($customer))
                ->orderByDesc('id')
                ->limit(6)
                ->pluck('id');

            $keys = collect()
                ->merge($invoiceIds->map(fn ($id) => 'invoice-'.$id))
                ->merge($paymentIds->map(fn ($id) => 'payment-'.$id))
                ->merge($receiptIds->map(fn ($id) => 'receipt-'.$id))
                ->values();

            foreach ($keys as $keyIndex => $key) {
                // Leave roughly half unread for portal badge testing.
                if (($customerIndex + $keyIndex) % 2 !== 0) {
                    continue;
                }

                CustomerNotificationRead::query()->updateOrCreate(
                    [
                        'user_id' => $customer->id,
                        'notification_key' => $key,
                    ],
                    [
                        'read_at' => Carbon::parse('2026-09-20')
                            ->subDays(($customerIndex + $keyIndex) % 40)
                            ->setTime(10, ($keyIndex * 7) % 60),
                    ],
                );
                $created++;
            }
        }

        $this->command?->info("Customer notification read markers upserted: {$created}.");
    }
}
