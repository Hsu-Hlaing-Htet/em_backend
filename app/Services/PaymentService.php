<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Concerns\AppliesBillingPropertyFilters;
use App\Services\Concerns\AppliesListQuery;
use App\Support\BillingEagerLoads;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    use AppliesBillingPropertyFilters;
    use AppliesListQuery;

    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Payment::query()->with(BillingEagerLoads::paymentList());

        if (! empty($params['invoice_id'])) {
            $query->where('invoice_id', $params['invoice_id']);
        }

        if (! empty($params['payment_method_id'])) {
            $query->where('payment_method_id', $params['payment_method_id']);
        }

        $this->applyBuildingRoomFilters($query, $params, 'invoice.contract.room');
        $this->applyDateRangeFilter($query, $params, 'payment_date', 'payment_date_from', 'payment_date_to');
        $this->applyBillingStatusFilter($query, $params);
        $this->applyPaymentTypeFilter($query, $params);

        if (! empty($params['status'])) {
            $this->applyStatusFilter($query, $params);
        } else {
            // Official payment list excludes pending customer submissions.
            $query->where($query->getModel()->getTable().'.status', '!=', 'pending');
        }

        $this->applyPaymentSearch($query, $params);
        if (empty($params['order'])) {
            $params['order'] = 'payment_date|desc';
        }

        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
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

        $search = trim((string) $params['search']);
        $normalizedRef = strtoupper($search);
        $paymentId = null;

        if (preg_match('/^PAY-0*(\d+)$/', $normalizedRef, $matches)) {
            $paymentId = (int) $matches[1];
        } elseif (ctype_digit($search)) {
            $paymentId = (int) $search;
        }

        $query->where(function ($builder) use ($search, $paymentId): void {
            if ($paymentId) {
                $builder->where('id', $paymentId);
            }

            $builder->orWhere('note', 'like', '%'.$search.'%')
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
            ->with(BillingEagerLoads::payment())
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

        return $payment->fresh(BillingEagerLoads::payment());
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
        return DB::transaction(function () use ($payment, $file): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending payments can receive proof uploads.');
            }

            $path = $file->store('payment-proofs', 'public');
            $lockedPayment->update(['proof_image_path' => $path]);

            return $lockedPayment->fresh(BillingEagerLoads::payment());
        });
    }

    public function approve(Payment $payment, float|int|string $amount): Payment
    {
        $paidAmount = round((float) $amount, 2);

        return DB::transaction(function () use ($payment, $paidAmount): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending payments can be approved.');
            }

            if ($lockedPayment->receipt()->exists()) {
                throw new ConcurrentConflictException('This payment already has a receipt.');
            }

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey($lockedPayment->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->load('payments');
            $currentBalance = $this->invoiceCurrentBalance($invoice);

            if ($paidAmount <= 0) {
                throw new InvalidArgumentException('Paid amount must be greater than zero.');
            }

            if ($paidAmount > $currentBalance) {
                throw new InvalidArgumentException('Paid amount cannot exceed the current balance.');
            }

            $lockedPayment->update([
                'amount' => $paidAmount,
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->syncInvoicePaymentStatus($invoice->fresh(['payments']));
            $this->ensureReceiptForPayment($lockedPayment->fresh());

            return $this->find($lockedPayment->id);
        });
    }

    public function reject(Payment $payment, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending payments can be rejected.');
            }

            $lockedPayment->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Pending payments never affect invoice totals; skip sync.

            return $this->find($lockedPayment->id);
        });
    }

    private function ensureReceiptForPayment(Payment $payment): void
    {
        $this->receiptService->createDraftForPayment($payment);
    }

    public function invoiceTotalDue(Invoice $invoice): float
    {
        return round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2);
    }

    public function invoiceApprovedPaidAmount(Invoice $invoice): float
    {
        $invoice->loadMissing('payments');

        return round((float) $invoice->payments
            ->whereIn('status', ['approved', 'completed'])
            ->sum(fn (Payment $payment) => (float) ($payment->amount ?? 0)), 2);
    }

    public function invoiceCurrentBalance(Invoice $invoice): float
    {
        return max(round($this->invoiceTotalDue($invoice) - $this->invoiceApprovedPaidAmount($invoice), 2), 0);
    }

    public function syncInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('payments');
        $approvedTotal = $this->invoiceApprovedPaidAmount($invoice);
        $total = $this->invoiceTotalDue($invoice);

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
