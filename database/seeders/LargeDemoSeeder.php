<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\CustomerNotificationRead;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Orchestrates the large Rosewood development/demo dataset.
 *
 * Safe / idempotent: delegates to BulkDemoSeeder + ContractFinancialConsistencySeeder,
 * then enriches joint parties and notification read-state. Never truncates tables.
 */
class LargeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $started = microtime(true);

        $this->call([
            BulkDemoSeeder::class,
        ]);

        $this->ensureJointParties();

        $this->call([
            ContractFinancialConsistencySeeder::class,
            UtilityInvoiceConsistencySeeder::class,
        ]);

        $this->seedNotificationReads();

        $elapsed = round(microtime(true) - $started, 2);
        $this->command?->info("LargeDemoSeeder finished in {$elapsed}s.");
    }

    private function ensureJointParties(): void
    {
        $customers = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->orderBy('id')
            ->get();

        if ($customers->count() < 2) {
            return;
        }

        $updated = 0;

        Contract::query()
            ->where('remark', 'like', 'Bulk demo%')
            ->whereIn('status', [
                Contract::STATUS_ACTIVE,
                Contract::STATUS_COMPLETED,
                Contract::STATUS_PENDING,
            ])
            ->whereNull('second_user_id')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (Contract $contract, int $index) use ($customers, &$updated): void {
                // Aim for ~15% joint coverage among eligible bulk contracts.
                if ($index % 7 !== 0) {
                    return;
                }

                $second = $customers->first(
                    fn (User $user) => (int) $user->id !== (int) $contract->user_id
                        && (int) $user->id === (int) $customers[($index + 23) % $customers->count()]->id
                ) ?? $customers->first(fn (User $user) => (int) $user->id !== (int) $contract->user_id);

                if (! $second) {
                    return;
                }

                $contract->update(['second_user_id' => $second->id]);
                $updated++;
            });

        $this->command?->info("Joint parties ensured on {$updated} bulk contracts.");
    }

    private function seedNotificationReads(): void
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
