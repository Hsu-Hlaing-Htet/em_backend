<?php

namespace App\Services;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Utility;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    use AppliesListQuery;

    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InvoiceDocumentService $invoiceDocumentService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['contract.user', 'contract.room', 'items.chargeType', 'creator', 'approver']);

        $this->applyListQuery($query, $params, ['invoice_number']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['contract_id'])) {
            $query->where('contract_id', $params['contract_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Invoice
    {
        return Invoice::query()
            ->with(['contract.user', 'contract.room', 'utility', 'items.chargeType', 'payments', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice
    {
        $items = $data['invoice_items'] ?? [];
        unset($data['invoice_items']);

        $data['invoice_number'] = $data['invoice_number'] ?? $this->generateInvoiceNumber();
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        return DB::transaction(function () use ($data, $items): Invoice {
            $invoice = Invoice::query()->create($data);

            foreach ($items as $itemData) {
                $invoice->items()->create($itemData);
            }

            return $this->recalculateTotal($invoice)->load(['contract.user', 'contract.room', 'items.chargeType']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be updated.');
        }

        $items = $data['invoice_items'] ?? null;
        unset($data['invoice_items']);

        return DB::transaction(function () use ($invoice, $data, $items): Invoice {
            $invoice->update($data);

            if (is_array($items)) {
                $this->syncItems($invoice, $items);
            }

            return $this->recalculateTotal($invoice)->load(['contract.user', 'contract.room', 'items.chargeType']);
        });
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function issue(Invoice $invoice): Invoice
    {
        $invoice = $this->approvalService->transition($invoice, 'issued', ['draft']);
        $invoice->update([
            'issued_date' => $invoice->issued_date ?? now()->toDateString(),
        ]);

        $invoice = $invoice->fresh(['contract.user', 'contract.room', 'items.chargeType', 'approver']);
        $this->notifyCustomerOfIssuedInvoice($invoice);

        return $invoice;
    }

    private function notifyCustomerOfIssuedInvoice(Invoice $invoice): void
    {
        try {
            $this->invoiceDocumentService->sendEmail($invoice, []);
        } catch (\Throwable) {
            // Email delivery should not block invoice issuance.
        }
    }

    public function mergeUtilityCharges(Utility $utility): Invoice
    {
        $utility->load(['items.utilityType', 'room']);

        $contract = Contract::query()
            ->where('room_id', $utility->room_id)
            ->where('status', 'active')
            ->first();

        if (! $contract) {
            return $this->createStandaloneUtilityInvoice($utility);
        }

        return DB::transaction(function () use ($utility, $contract): Invoice {
            $invoice = Invoice::query()
                ->where('contract_id', $contract->id)
                ->where('status', 'draft')
                ->where(function ($query) use ($utility): void {
                    $query->where('utility_id', $utility->id)
                        ->orWhereNull('utility_id');
                })
                ->first();

            if (! $invoice) {
                $invoice = Invoice::query()->create([
                    'contract_id' => $contract->id,
                    'utility_id' => $utility->id,
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'type' => 'combined',
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);
            } else {
                $invoice->update([
                    'utility_id' => $utility->id,
                    'type' => $invoice->type === 'rent' ? 'combined' : $invoice->type,
                ]);
            }

            return $this->syncUtilityLineItems($utility, $invoice);
        });
    }

    private function createStandaloneUtilityInvoice(Utility $utility): Invoice
    {
        return DB::transaction(function () use ($utility): Invoice {
            $invoice = Invoice::query()->create([
                'contract_id' => Contract::query()
                    ->where('room_id', $utility->room_id)
                    ->latest('id')
                    ->value('id') ?? throw new InvalidArgumentException('No contract found for utility billing.'),
                'utility_id' => $utility->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'type' => 'utility',
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            return $this->syncUtilityLineItems($utility, $invoice);
        });
    }

    private function syncUtilityLineItems(Utility $utility, Invoice $invoice): Invoice
    {
        $chargeType = ChargeType::query()->where('slug', 'utility')->first()
            ?? ChargeType::query()->first();

        $invoice->items()
            ->where('description', 'like', '%'.$utility->billing_month->format('Y-m').'%')
            ->delete();

        foreach ($utility->items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'charge_type_id' => $chargeType?->id,
                'description' => ($item->utilityType?->name ?? 'Utility').' ('.$utility->billing_month->format('Y-m').')',
                'amount' => $item->amount,
            ]);
        }

        return $this->recalculateTotal($invoice)->load(['contract.user', 'contract.room', 'items.chargeType']);
    }

    public function applyLateFees(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
            return $invoice;
        }

        if (! $invoice->due_date || $invoice->due_date->isFuture()) {
            return $invoice;
        }

        $daysLate = (int) $invoice->due_date->diffInDays(now(), false);

        if ($daysLate <= 0) {
            return $invoice;
        }

        $rule = \App\Models\LateFee::query()
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            return $invoice;
        }

        $graceDays = (int) ($rule->grace_days ?? 0);

        if ($daysLate <= $graceDays) {
            return $invoice;
        }

        $chargeableDays = $daysLate - $graceDays;
        $baseAmount = (float) $invoice->items()->sum('amount');
        $lateFee = match ($rule->type) {
            'percentage' => round($baseAmount * ((float) $rule->value / 100) * ($rule->per === 'month' ? ceil($chargeableDays / 30) : $chargeableDays), 2),
            default => round((float) $rule->value * ($rule->per === 'month' ? ceil($chargeableDays / 30) : $chargeableDays), 2),
        };

        $invoice->update([
            'late_fee' => $lateFee,
            'status' => 'overdue',
        ]);

        return $this->recalculateTotal($invoice);
    }

    /**
     * Generate monthly rent invoices for active installment contracts on their billing day.
     */
    public function generateRentInvoicesForToday(): int
    {
        $billingDay = (int) now()->format('j');
        $count = 0;

        $contracts = Contract::query()
            ->where('status', 'active')
            ->where('payment_type', 'installment')
            ->where('billing_day', $billingDay)
            ->get();

        foreach ($contracts as $contract) {
            $exists = Invoice::query()
                ->where('contract_id', $contract->id)
                ->where('type', 'rent')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();

            if ($exists) {
                continue;
            }

            $rentCharge = ChargeType::query()->where('slug', 'rent')->first()
                ?? ChargeType::query()->first();

            $monthlyAmount = $contract->duration_months > 0
                ? round((float) $contract->contract_total / $contract->duration_months, 2)
                : (float) $contract->contract_total;

            $this->create([
                'contract_id' => $contract->id,
                'type' => 'rent',
                'due_date' => now()->addDays(7)->toDateString(),
                'invoice_items' => [
                    [
                        'charge_type_id' => $rentCharge?->id,
                        'description' => 'Monthly rent — '.now()->format('F Y'),
                        'amount' => $monthlyAmount,
                    ],
                ],
            ]);

            $count++;
        }

        return $count;
    }

    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $last = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function recalculateTotal(Invoice $invoice): Invoice
    {
        $itemsTotal = (float) $invoice->items()->sum('amount');
        $total = $itemsTotal + (float) $invoice->late_fee;
        $invoice->update(['total_amount' => round($total, 2)]);

        return $invoice->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncItems(Invoice $invoice, array $items): void
    {
        $itemIds = [];

        foreach ($items as $itemData) {
            if (! empty($itemData['id'])) {
                $item = InvoiceItem::query()
                    ->where('invoice_id', $invoice->id)
                    ->findOrFail($itemData['id']);
                $item->update($itemData);
                $itemIds[] = $item->id;
            } else {
                $item = $invoice->items()->create($itemData);
                $itemIds[] = $item->id;
            }
        }

        $invoice->items()->whereNotIn('id', $itemIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(Invoice $invoice, array $data): InvoiceItem
    {
        $item = $invoice->items()->create($data);
        $this->recalculateTotal($invoice);

        return $item->load('chargeType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(InvoiceItem $item, array $data): InvoiceItem
    {
        $item->update($data);
        $this->recalculateTotal($item->invoice);

        return $item->fresh('chargeType');
    }

    public function deleteItem(InvoiceItem $item): void
    {
        $invoice = $item->invoice;
        $item->delete();
        $this->recalculateTotal($invoice);
    }

    public function generateFromContract(Contract $contract): Invoice
    {
        if ($contract->status !== 'active') {
            throw new InvalidArgumentException('Invoices can only be generated for active contracts.');
        }

        $rentCharge = ChargeType::query()->where('slug', 'rent')->first()
            ?? ChargeType::query()->first();

        $monthlyAmount = $contract->duration_months > 0
            ? round((float) $contract->contract_total / $contract->duration_months, 2)
            : (float) $contract->contract_total;

        return $this->create([
            'contract_id' => $contract->id,
            'type' => 'rent',
            'due_date' => now()->addDays(7)->toDateString(),
            'invoice_items' => [
                [
                    'charge_type_id' => $rentCharge?->id,
                    'description' => 'Rent — '.now()->format('F Y'),
                    'amount' => $monthlyAmount,
                ],
            ],
        ]);
    }
}
