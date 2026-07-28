<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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
        $query = Payment::query()->with([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'invoice.items.chargeType',
            'invoice.payments',
            'paymentMethod',
            'receipt',
        ]);

        if (! empty($params['invoice_id'])) {
            $query->where('invoice_id', $params['invoice_id']);
        }

        $this->applyBillingStatusFilter($query, $params);
        $this->applyPaymentTypeFilter($query, $params);
        $this->applyStatusFilter($query, $params);
        $this->applyPaymentSearch($query, $params);
        $this->applyListQuery($query, $params, []);

        return $query->latest('payment_date')->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Payment>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyPaymentSearch($query, array $params): void
    {
        if (empty($params['search'])) {
            return;
        }

        $search = $params['search'];

        $query->where(function ($builder) use ($search): void {
            $builder->where('note', 'like', '%'.$search.'%')
                ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                    ->where('invoice_number', 'like', '%'.$search.'%'))
                ->orWhereHas('invoice.contract.user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))
                ->orWhereHas('invoice.contract.room', fn ($roomQuery) => $roomQuery
                    ->where('room_number', 'like', '%'.$search.'%')
                    ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery
                        ->where('building_name', 'like', '%'.$search.'%')))
                ->orWhereHas('paymentMethod', fn ($methodQuery) => $methodQuery
                    ->where('name', 'like', '%'.$search.'%'));
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Payment>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyBillingStatusFilter($query, array $params): void
    {
        if (empty($params['billing_status'])) {
            return;
        }

        $billingStatus = $params['billing_status'];

        if ($billingStatus === 'pending') {
            $query->where('status', 'pending');

            return;
        }

        $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', $billingStatus));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Payment>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyPaymentTypeFilter($query, array $params): void
    {
        if (empty($params['payment_type'])) {
            return;
        }

        $paymentType = $params['payment_type'];

        if ($paymentType === 'rent') {
            $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('type', 'rent'));

            return;
        }

        if ($paymentType === 'utility') {
            $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('type', 'utility'));

            return;
        }

        if ($paymentType === 'maintenance') {
            $query->whereHas('invoice.items.chargeType', fn ($chargeQuery) => $chargeQuery
                ->where('slug', 'maintenance-fee'));

            return;
        }

        $query->whereHas('invoice', function ($invoiceQuery): void {
            $invoiceQuery
                ->whereNotIn('type', ['rent', 'utility'])
                ->whereDoesntHave('items.chargeType', fn ($chargeQuery) => $chargeQuery
                    ->where('slug', 'maintenance-fee'));
        });
    }

    public function find(int $id): Payment
    {
        return Payment::query()
            ->with(['invoice.contract.user.profile', 'paymentMethod', 'receipt'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Payment
    {
        return Payment::query()->create([
            ...$data,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);
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

        return $payment->fresh(['invoice.contract.user.profile', 'paymentMethod', 'receipt']);
    }

    public function delete(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw new InvalidArgumentException('Only pending payments can be deleted.');
        }

        $payment->delete();
    }

    public function uploadProof(Payment $payment, UploadedFile $file): Payment
    {
        $path = $file->store('payment-proofs', 'public');
        $payment->update(['proof_image_path' => $path]);

        return $payment->fresh(['invoice', 'paymentMethod', 'receipt']);
    }

    public function approve(Payment $payment): Payment
    {
        $payment = $this->approvalService->approve($payment);
        $this->syncInvoicePaymentStatus($payment->invoice);
        $this->ensureReceiptForPayment($payment);

        return $payment->fresh(['invoice', 'paymentMethod', 'receipt']);
    }

    public function reject(Payment $payment): Payment
    {
        $payment->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->syncInvoicePaymentStatus($payment->invoice);

        return $payment->fresh(['invoice', 'paymentMethod', 'receipt']);
    }

    private function ensureReceiptForPayment(Payment $payment): void
    {
        $payment->loadMissing('receipt');

        if ($payment->receipt) {
            return;
        }

        $this->receiptService->createDraftForPayment($payment);
    }

    public function syncInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('payments');
        $approvedTotal = (float) $invoice->payments
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');

        $total = (float) $invoice->total_amount;

        if ($approvedTotal <= 0) {
            if (! in_array($invoice->status, ['draft'], true)) {
                $invoice->update(['status' => 'issued']);
            }
        } elseif ($approvedTotal + 0.009 >= $total) {
            $invoice->update(['status' => 'paid']);
        } else {
            $invoice->update(['status' => 'partial']);
        }

        return $invoice->fresh();
    }
}
