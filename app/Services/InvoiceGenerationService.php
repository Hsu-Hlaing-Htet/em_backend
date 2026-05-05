<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceGenerationService
{
    /**
     * Generate invoices for full-payment, installment, or monthly rent billing flows.
     *
     * @param  array<int, array{item_type:string,description:string,quantity:float|int,unit_price:float|int}>  $lineItems
     */
    public function generate(
        Contract $contract,
        string $mode,
        float $totalAmount,
        Carbon $firstDueDate,
        int $numberOfMonths = 1,
        int $monthlyDueDay = 1,
        array $lineItems = []
    ): Collection {
        return DB::transaction(function () use (
            $contract,
            $mode,
            $totalAmount,
            $firstDueDate,
            $numberOfMonths,
            $monthlyDueDay,
            $lineItems
        ): Collection {
            if ($mode === 'full') {
                return collect([$this->createSingleInvoice($contract, $totalAmount, $firstDueDate, $lineItems ?: [[
                    'item_type' => 'installment_payment',
                    'description' => 'Full payment settlement',
                    'quantity' => 1,
                    'unit_price' => $totalAmount,
                ]])]);
            }

            if ($mode === 'installment') {
                $months = max($numberOfMonths, 1);
                $monthlyAmount = round($totalAmount / $months, 2);
                $regularTotal = round($monthlyAmount * ($months - 1), 2);
                $finalAmount = round($totalAmount - $regularTotal, 2);

                return collect(range(1, $months))->map(function (int $sequence) use (
                    $contract,
                    $firstDueDate,
                    $monthlyDueDay,
                    $monthlyAmount,
                    $finalAmount,
                    $months
                ) {
                    $dueDate = $firstDueDate->copy()->addMonthsNoOverflow($sequence - 1);
                    $dueDate->day(min($monthlyDueDay, 28));

                    $amount = $sequence === $months
                        ? $finalAmount
                        : $monthlyAmount;

                    return $this->createSingleInvoice($contract, $amount, $dueDate, [[
                        'item_type' => 'installment_payment',
                        'description' => sprintf('Installment %d of %d', $sequence, $months),
                        'quantity' => 1,
                        'unit_price' => $amount,
                    ]]);
                });
            }

            // Mode: rent (monthly billing)
            return collect([$this->createSingleInvoice($contract, $totalAmount, $firstDueDate, $lineItems ?: [[
                'item_type' => 'monthly_rent',
                'description' => 'Monthly rental invoice',
                'quantity' => 1,
                'unit_price' => $totalAmount,
            ]])]);
        });
    }

    /**
     * @param  array<int, array{item_type:string,description:string,quantity:float|int,unit_price:float|int}>  $items
     */
    protected function createSingleInvoice(Contract $contract, float $amount, Carbon $dueDate, array $items): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => $this->nextInvoiceNumber(),
            'property_id' => $contract->related_property_id,
            'contract_id' => $contract->id,
            'user_id' => $contract->owner_id,
            'tenant_id' => $contract->tenant_id,
            'customer_name' => $contract->owner?->name ?? $contract->tenant?->tenant_name ?? 'Customer',
            'issued_date' => now()->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
            'notes' => sprintf('Generated for %s contract %s', $contract->contract_type, $contract->contract_code),
        ]);

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            $invoice->items()->create([
                'item_type' => $item['item_type'] ?? 'other',
                'description' => $item['description'] ?? 'Charge',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
            ]);
        }

        return $invoice->fresh(['items']);
    }

    protected function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
