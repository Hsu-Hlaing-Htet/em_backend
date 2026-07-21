<?php

namespace App\Services;

use App\Mail\InvoiceDocumentMail;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class InvoiceService
{
    use AppliesListQuery;

    public function __construct(private readonly InvoiceDocumentService $invoiceDocumentService) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['contract.user', 'items.chargeType', 'creator', 'approver']);
        $this->applyStatusFilter($query, $params);
        $this->applyListQuery($query, $params, ['invoice_number']);

        return $query->latest('id')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Invoice
    {
        return Invoice::query()
            ->with(['contract.user.profile', 'contract.room.building', 'items.chargeType', 'payments', 'creator', 'approver'])
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

        $invoice = $invoice->fresh(['contract.user', 'items.chargeType', 'creator', 'approver']);

        if ($invoice->contract?->user?->email) {
            Mail::to($invoice->contract->user->email)->send(new InvoiceDocumentMail(
                $invoice,
                $this->invoiceDocumentService->renderHtml($invoice),
            ));
        }

        return $invoice;
    }

    public function generateFromContract(Contract $contract): Invoice
    {
        if (! in_array($contract->status, ['active', 'approved'], true)) {
            throw new InvalidArgumentException('Invoices can only be generated for active contracts.');
        }

        return Invoice::query()->create([
            'contract_id' => $contract->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'type' => $contract->type === 'sale' ? 'sale' : 'rent',
            'status' => 'draft',
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => $contract->contract_total,
            'created_by' => Auth::id(),
        ])->load(['contract.user', 'items.chargeType']);
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
