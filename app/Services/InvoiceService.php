<?php

namespace App\Services;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Utility;
use App\Services\Concerns\AppliesBillingPropertyFilters;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class InvoiceService
{
    use AppliesBillingPropertyFilters;
    use AppliesListQuery;

    public function __construct(private readonly InvoiceDocumentService $invoiceDocumentService) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Invoice::query()->with([
            'contract.user',
            'contract.room.building',
            'items.chargeType',
            'payments',
            'creator',
            'approver',
        ]);

        $this->applyInvoiceSearch($query, $params);
        $this->applyBuildingRoomFilters($query, $params, 'contract.room');
        $this->applyDateRangeFilter($query, $params, 'issued_date', 'issued_from', 'issued_to');
        $this->applyDateRangeFilter($query, $params, 'due_date', 'due_from', 'due_to');
        $this->applyInvoicePaymentStatusFilter($query, $params);
        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyInvoiceSearch($query, array $params): void
    {
        if (empty($params['search'])) {
            return;
        }

        $search = $params['search'];

        $query->where(function ($builder) use ($search): void {
            $builder->where('invoice_number', 'like', '%'.$search.'%')
                ->orWhereHas('contract.user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyInvoicePaymentStatusFilter($query, array $params): void
    {
        $paymentStatus = $params['payment_status'] ?? $params['status'] ?? null;

        if ($paymentStatus === 'draft') {
            $query->where('status', 'draft');

            return;
        }

        $query->where('status', '!=', 'draft');

        if (empty($paymentStatus) || $paymentStatus === 'all_approved') {
            return;
        }

        if ($paymentStatus === 'unpaid') {
            $query->where('status', 'issued');

            return;
        }

        $query->where('status', $paymentStatus);
    }

    public function find(int $id): Invoice
    {
        return Invoice::query()
            ->with([
                'contract.user.profile',
                'contract.room.building',
                'items.chargeType',
                'utility.items.utilityType',
                'payments.paymentMethod',
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be issued.');
        }

        $invoice->update([
            'status' => 'issued',
            'issued_date' => now()->toDateString(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $invoice = $invoice->fresh([
            'contract.user',
            'items.chargeType',
            'utility.items.utilityType',
            'creator',
            'approver',
        ]);

        if ($invoice->contract?->user?->email) {
            $this->invoiceDocumentService->sendEmail($invoice, [
                'email' => $invoice->contract->user->email,
            ]);
        }

        return $invoice;
    }

    public function generateFromContract(Contract $contract): Invoice
    {
        if (! in_array($contract->status, ['active', 'approved'], true)) {
            throw new InvalidArgumentException('Invoices can only be generated for active contracts.');
        }

        $contract->loadMissing('room');

        $invoice = Invoice::query()->create([
            'contract_id' => $contract->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'type' => $contract->type === 'sale' ? 'sale' : 'rent',
            'status' => 'draft',
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => $contract->contract_total,
            'created_by' => Auth::id(),
        ]);

        $chargeTypeSlug = $contract->type === 'sale' ? 'sale-installment' : 'monthly-rent';
        $chargeType = ChargeType::query()->where('slug', $chargeTypeSlug)->first();
        $unitPrice = $contract->type === 'sale'
            ? ($contract->room?->sale_price ?? $contract->contract_total)
            : ($contract->room?->rent_price ?? $contract->contract_total);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'charge_type_id' => $chargeType?->id,
            'description' => $contract->type === 'sale' ? ($chargeType?->name ?: 'Sale') : 'Rent',
            'unit_price' => $unitPrice,
            'amount' => $contract->contract_total,
        ]);

        return $invoice->fresh(['contract.user', 'contract.room', 'items.chargeType']);
    }

    public function generateInvoiceNumber(): string
    {
        $lastSequence = Invoice::query()
            ->where('invoice_number', 'like', 'INV-%')
            ->pluck('invoice_number')
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        return 'INV-'.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice
    {
        return Invoice::query()->create([
            ...$data,
            'invoice_number' => $data['invoice_number'] ?? $this->generateInvoiceNumber(),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ])->load(['contract.user', 'items.chargeType']);
    }

    public function generateFromUtility(Utility $utility): Invoice
    {
        $utility->load(['room', 'items.utilityType']);

        if (Invoice::query()->where('utility_id', $utility->id)->exists()) {
            throw new InvalidArgumentException('An invoice already exists for this utility bill.');
        }

        $contract = Contract::query()
            ->where('room_id', $utility->room_id)
            ->where('type', 'rent')
            ->whereIn('status', ['active', 'completed'])
            ->latest('id')
            ->first();

        if (! $contract) {
            throw new InvalidArgumentException('No active rent contract found for this room.');
        }

        $chargeType = ChargeType::query()->where('slug', 'utility-charges')->first();

        $invoice = Invoice::query()->create([
            'contract_id' => $contract->id,
            'utility_id' => $utility->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'type' => 'utility',
            'status' => 'draft',
            'due_date' => now()->addDays(14)->toDateString(),
            'total_amount' => $utility->total_amount,
            'created_by' => Auth::id(),
        ]);

        foreach ($utility->items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'charge_type_id' => $chargeType?->id,
                'description' => $item->utilityType?->name ?? 'Utility',
                'previous_reading' => $item->previous_reading,
                'current_reading' => $item->current_reading,
                'usage' => $item->usage,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ]);
        }

        return $invoice->fresh([
            'contract.user',
            'items.chargeType',
            'utility.items.utilityType',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be updated.');
        }

        $invoice->update($data);

        return $invoice->fresh(['contract.user.profile', 'contract.room.building', 'items.chargeType']);
    }

    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be deleted.');
        }

        $invoice->delete();
    }
}
