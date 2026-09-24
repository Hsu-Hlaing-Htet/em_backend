<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use App\Services\InvoiceService;
use Database\Seeders\Support\ContractFinancialHistorySeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aligns Utility ↔ Invoice lifecycle for demo data.
 *
 * Pending utilities must not share a billing period with an already-finalized
 * invoice (that blocks Utility Approval). Approved utilities should link to a
 * draft (pending invoice approval) or finalized invoice as appropriate.
 */
class UtilityInvoiceConsistencySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        if (! $admin) {
            $this->command?->warn('UtilityInvoiceConsistencySeeder skipped: admin missing.');

            return;
        }

        Auth::login($admin);

        $stats = [
            'pending_conflict_resolved' => 0,
            'approved_linked' => 0,
            'draft_invoices_created' => 0,
            'pending_kept' => 0,
            'current_month_utilities' => 0,
        ];

        DB::transaction(function () use ($admin, &$stats): void {
            $stats['current_month_utilities'] = $this->ensureDemoMonthUtilities($admin);

            $utilities = Utility::query()
                ->with(['items'])
                ->whereIn('status', ['pending', 'approved'])
                ->orderBy('id')
                ->get();

            foreach ($utilities as $utility) {
                $periodInvoice = $this->periodInvoiceFor($utility);

                if ($utility->status === 'pending') {
                    $linkedInvoice = $utility->invoice_id
                        ? Invoice::query()->find($utility->invoice_id)
                        : null;

                    if ($this->isFinalized($periodInvoice) || $this->isFinalized($linkedInvoice)) {
                        $target = $periodInvoice ?? $linkedInvoice;
                        $added = $this->appendUtilityChargesIfMissing($target, $utility);
                        $this->markApprovedAndLink($utility, $target, $admin);
                        if ($added > 0 && $target->status === Invoice::STATUS_PAID) {
                            $this->settleAddedUtilityAmount($target->fresh(), $added, $admin);
                        }
                        $stats['pending_conflict_resolved']++;

                        continue;
                    }

                    $stats['pending_kept']++;

                    continue;
                }

                // Approved utilities should have an invoice path.
                if ($utility->invoice_id) {
                    $linked = Invoice::query()->find($utility->invoice_id);

                    if ($linked) {
                        $this->syncInvoiceUtilityId($linked, $utility);
                        $stats['approved_linked']++;

                        continue;
                    }

                    $utility->update(['invoice_id' => null]);
                }

                if ($this->isFinalized($periodInvoice)) {
                    $added = $this->appendUtilityChargesIfMissing($periodInvoice, $utility);
                    $utility->update([
                        'invoice_id' => $periodInvoice->id,
                        'status' => 'approved',
                        'approved_by' => $utility->approved_by ?: $admin->id,
                        'approved_at' => $utility->approved_at ?: now(),
                    ]);
                    $this->syncInvoiceUtilityId($periodInvoice, $utility);

                    if ($added > 0 && $periodInvoice->status === Invoice::STATUS_PAID) {
                        $this->settleAddedUtilityAmount($periodInvoice->fresh(), $added, $admin);
                    }

                    $stats['approved_linked']++;

                    continue;
                }

                try {
                    $before = $periodInvoice?->id;
                    app(InvoiceService::class)->generateFromUtility($utility->fresh(['items.utilityType', 'room']));
                    $after = $this->periodInvoiceFor($utility->fresh());

                    if ($after && (! $before || (int) $before !== (int) $after->id)) {
                        $stats['draft_invoices_created']++;
                    } else {
                        $stats['approved_linked']++;
                    }
                } catch (\Throwable $exception) {
                    $this->command?->warn(sprintf(
                        'Utility #%d could not be linked to an invoice: %s',
                        $utility->id,
                        $exception->getMessage(),
                    ));
                }
            }
        });

        Auth::logout();

        $this->command?->info(sprintf(
            'Utility/Invoice consistency: pending_kept=%d conflict_resolved=%d approved_linked=%d drafts_created=%d current_month_utilities=%d remaining_pending=%d',
            $stats['pending_kept'],
            $stats['pending_conflict_resolved'],
            $stats['approved_linked'],
            $stats['draft_invoices_created'],
            $stats['current_month_utilities'],
            Utility::query()->where('status', 'pending')->count(),
        ));
    }

    /**
     * Ensure the demo as-of billing month has Utility records for active rent
     * contracts so the global invoice list includes recent utility-linked bills.
     */
    private function ensureDemoMonthUtilities(User $admin): int
    {
        $month = Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF)->startOfMonth();
        $types = UtilityType::query()->where('status', 'active')->orderBy('id')->get();

        if ($types->isEmpty()) {
            return 0;
        }

        $rates = UtilityRate::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->groupBy('utility_type_id')
            ->map(fn ($group) => (float) ($group->first()->rate ?? 100));

        $created = 0;
        $contracts = Contract::query()
            ->where('type', 'rent')
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('remark', 'like', 'Bulk demo%')
            ->orderBy('id')
            ->get();

        foreach ($contracts as $index => $contract) {
            $exists = Utility::query()
                ->where('room_id', $contract->room_id)
                ->whereDate('billing_month', $month->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            // Realistic mix: pending (no invoice yet), approved (invoice path), draft.
            $status = match ($index % 5) {
                0 => 'pending',
                1, 2, 4 => 'approved',
                default => 'draft',
            };

            $utility = Utility::query()->create([
                'contract_id' => $contract->id,
                'room_id' => $contract->room_id,
                'billing_month' => $month->toDateString(),
                'reading_date' => $month->copy()->endOfMonth()->min(
                    Carbon::parse(ContractFinancialHistorySeederSupport::DEMO_AS_OF)
                )->toDateString(),
                'total_amount' => 0,
                'status' => $status,
                'created_by' => $admin->id,
                'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                'approved_at' => in_array($status, ['approved', 'rejected'], true)
                    ? $month->copy()->addDays(18)
                    : null,
            ]);

            $total = 0.0;
            $base = 900 + ($contract->room_id * 11) + ($month->month * 45);

            foreach ($types->take(2) as $typeIndex => $type) {
                $previous = $base + ($typeIndex * 220);
                $usage = 35 + (($index + $typeIndex * 11) % 120);
                $unitPrice = (float) ($rates[$type->id] ?? 100);
                $amount = round($usage * $unitPrice, 2);
                $total += $amount;

                UtilityItem::query()->create([
                    'utility_id' => $utility->id,
                    'utility_type_id' => $type->id,
                    'previous_reading' => $previous,
                    'current_reading' => $previous + $usage,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);
            }

            $utility->update(['total_amount' => round($total, 2)]);
            $created++;
        }

        return $created;
    }

    private function periodInvoiceFor(Utility $utility): ?Invoice
    {
        if (! $utility->contract_id || ! $utility->billing_month) {
            return null;
        }

        return Invoice::query()
            ->where('contract_id', $utility->contract_id)
            ->whereDate('billing_month', $utility->billing_month->toDateString())
            ->orderBy('id')
            ->first();
    }

    private function isFinalized(?Invoice $invoice): bool
    {
        if (! $invoice) {
            return false;
        }

        return $invoice->status !== Invoice::STATUS_DRAFT
            && $invoice->status !== Invoice::STATUS_CANCELLED;
    }

    private function markApprovedAndLink(Utility $utility, ?Invoice $invoice, User $admin): void
    {
        $updates = [
            'status' => 'approved',
            'approved_by' => $utility->approved_by ?: $admin->id,
            'approved_at' => $utility->approved_at ?: now(),
        ];

        if ($invoice) {
            $updates['invoice_id'] = $invoice->id;
            $this->syncInvoiceUtilityId($invoice, $utility);
        }

        $utility->update($updates);
    }

    private function syncInvoiceUtilityId(Invoice $invoice, Utility $utility): void
    {
        if (! $invoice->utility_id) {
            $invoice->update(['utility_id' => $utility->id]);
        }
    }

    private function appendUtilityChargesIfMissing(Invoice $invoice, Utility $utility): float
    {
        $utility->loadMissing('items.utilityType');
        $added = 0.0;

        foreach ($utility->items as $item) {
            $typeName = $item->utilityType?->name ?? 'Utility';
            $exists = InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->where('description', $typeName)
                ->where('amount', $item->amount)
                ->exists();

            if ($exists) {
                continue;
            }

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'charge_type_id' => \App\Models\ChargeType::query()->where('slug', 'utility-charges')->value('id'),
                'description' => $typeName,
                'previous_reading' => $item->previous_reading,
                'current_reading' => $item->current_reading,
                'usage' => $item->usage,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ]);
            $added += (float) $item->amount;
        }

        if ($added > 0) {
            $invoice->update([
                'total_amount' => round((float) $invoice->total_amount + $added, 2),
            ]);
        }

        return round($added, 2);
    }

    private function settleAddedUtilityAmount(Invoice $invoice, float $amount, User $admin): void
    {
        if ($amount <= 0) {
            return;
        }

        $methodId = \App\Models\PaymentMethod::query()->orderBy('id')->value('id');
        if (! $methodId) {
            return;
        }

        $payment = \Database\Seeders\Support\BillingSeederSupport::upsertPayment(
            $invoice,
            'seed-util-topup-'.$invoice->id,
            [
                'payment_method_id' => $methodId,
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'amount' => $amount,
                'payment_date' => optional($invoice->due_date)?->toDateString() ?? now()->toDateString(),
                'status' => \App\Models\Payment::STATUS_APPROVED,
                'note' => 'seed-util-topup-'.$invoice->id.' Utility charge settlement',
                'proof_image_path' => 'payments/proof-util-'.$invoice->invoice_number.'.jpg',
            ],
        );

        \Database\Seeders\Support\BillingSeederSupport::upsertReceipt(
            $payment,
            $admin,
            \App\Models\Receipt::STATUS_ISSUED,
            \App\Models\Receipt::APPROVAL_APPROVED,
            now(),
        );
    }
}
