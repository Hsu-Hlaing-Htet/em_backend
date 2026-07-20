<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PaymentService
{
    use AppliesListQuery;

    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly ReceiptService $receiptService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Payment::query()->with(['invoice.contract', 'paymentMethod']);

        $this->applyListQuery($query, $params, ['note']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['invoice_id'])) {
            $query->where('invoice_id', $params['invoice_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Payment
    {
        return Payment::query()
            ->with(['invoice.contract.user', 'invoice.contract.room', 'paymentMethod', 'creator', 'approver', 'receipt'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Payment
    {
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'pending';

        return Payment::query()->create($data)->load(['invoice', 'paymentMethod']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        if ($payment->status !== 'pending') {
            throw new InvalidArgumentException('Only pending payments can be updated.');
        }

        $payment->update($data);

        return $payment->fresh(['invoice', 'paymentMethod']);
    }

    public function delete(Payment $payment): void
    {
        if ($payment->proof_image_path) {
            Storage::disk('public')->delete($payment->proof_image_path);
        }

        $payment->delete();
    }

    public function approve(Payment $payment): Payment
    {
        $payment = $this->approvalService->approve($payment);
        $this->syncInvoicePaymentStatus($payment->invoice);
        $this->ensureReceiptForPayment($payment);

        return $payment->fresh(['invoice', 'paymentMethod', 'approver', 'receipt']);
    }

    public function reject(Payment $payment): Payment
    {
        $payment = $this->approvalService->reject($payment)
            ->load(['invoice', 'paymentMethod', 'approver']);

        $this->syncInvoicePaymentStatus($payment->invoice);

        return $payment;
    }

    public function uploadProof(Payment $payment, UploadedFile $file): Payment
    {
        if ($payment->proof_image_path) {
            Storage::disk('public')->delete($payment->proof_image_path);
        }

        $path = $file->store('payments', 'public');
        $payment->update(['proof_image_path' => $path]);

        return $payment->fresh(['invoice', 'paymentMethod']);
    }

    private function ensureReceiptForPayment(Payment $payment): void
    {
        $payment->loadMissing('receipt');

        if ($payment->receipt) {
            return;
        }

        $this->receiptService->create([
            'payment_id' => $payment->id,
        ]);
    }

    public function syncInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $totalPaid = (float) $invoice->payments()
            ->where('status', 'approved')
            ->sum('amount');

        $status = 'issued';

        if ($totalPaid >= (float) $invoice->total_amount && $invoice->total_amount > 0) {
            $status = 'paid';
        } elseif ($totalPaid > 0) {
            $status = 'partial';
        }

        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }
}
