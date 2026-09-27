<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Services\Concerns\AppliesBillingPropertyFilters;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use App\Support\BillingEagerLoads;
use App\Support\CustomerNotificationRecipients;
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
        private readonly ContractLifecycleService $contractLifecycleService,
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

        $this->applyListQuery($query, $params, [], AdminListSorts::payments());

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
        unset(
            $data['payment_date'],
            $data['status'],
            $data['created_by'],
            $data['approved_by'],
            $data['approved_at'],
        );

        return DB::transaction(function () use ($data): Payment {
            $method = PaymentMethod::query()->findOrFail((int) $data['payment_method_id']);

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey((int) $data['invoice_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($invoice->status, ['paid', 'cancelled', 'draft'], true)) {
                if ($invoice->status === 'paid') {
                    throw new ConcurrentConflictException('Invoice has already been fully paid.');
                }

                throw new InvalidArgumentException('This invoice is not open for payment.');
            }

            // Re-read outstanding under the invoice row lock so concurrent Admin/Customer
            // creates never validate against a stale balance.
            $invoice->unsetRelation('payments');
            $balance = $this->invoiceCurrentBalance($invoice);

            if ($balance <= 0) {
                throw new ConcurrentConflictException('Invoice has already been fully paid.');
            }

            $hasPending = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_PENDING)
                ->exists();

            if ($hasPending) {
                throw new ConcurrentConflictException('This invoice already has a pending payment.');
            }

            $hasAppliedAmount = array_key_exists('amount', $data)
                && $data['amount'] !== null
                && $data['amount'] !== '';

            $applied = null;
            $amountReceived = null;

            if ($hasAppliedAmount) {
                $requestedApplied = round((float) $data['amount'], 2);

                if ($requestedApplied <= 0) {
                    throw new InvalidArgumentException('Paid amount must be greater than zero.');
                }

                // Whole-MMK UI can round remaining balance up by < 1; settle exact balance instead.
                // Clamp before the over-balance check so a rounded 693681 vs 693680.50 does not 500.
                $applied = abs($requestedApplied - $balance) < 1.0
                    ? $balance
                    : $requestedApplied;

                if ($applied > $balance + 0.009) {
                    throw new InvalidArgumentException('Paid amount cannot exceed the current balance.');
                }

                if ($method->isCash()) {
                    if (! array_key_exists('amount_received', $data) || $data['amount_received'] === null || $data['amount_received'] === '') {
                        throw new InvalidArgumentException('Received amount is required for cash payments.');
                    }

                    $amountReceived = round((float) $data['amount_received'], 2);

                    // Cash over-tender is allowed: validate applied amount vs balance, not received.
                    if ($amountReceived < $applied) {
                        throw new InvalidArgumentException(
                            'Received amount cannot be less than the amount applied to the invoice.',
                        );
                    }
                }
            } elseif ($method->isCash()) {
                throw new InvalidArgumentException('Paid amount is required for cash payments.');
            } else {
                // Customer wallet/bank submissions cannot send amount; store the applied
                // settlement (current balance) so Approval List / exports show Payment (MMK).
                $applied = $balance;
            }

            unset($data['amount_received']);

            return Payment::query()->create([
                ...$data,
                'invoice_id' => $invoice->id,
                'amount' => $applied,
                'amount_received' => $amountReceived,
                // Authoritative payment date comes from the server clock, not the client.
                'payment_date' => now()->toDateString(),
                'status' => Payment::STATUS_PENDING,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending payments can be updated.');
        }

        $payment->update($data);

        return $payment->fresh(BillingEagerLoads::payment());
    }

    public function delete(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending payments can be rejected.');
        }

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'rejection_reason' => $payment->rejection_reason ?: 'Cancelled by administrator.',
        ]);
    }

    public function uploadProof(Payment $payment, UploadedFile $file): Payment
    {
        return DB::transaction(function () use ($payment, $file): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                throw new ConcurrentConflictException('Only pending payments can receive proof uploads.');
            }

            $path = $file->store('payment-proofs', 'public');
            $lockedPayment->update(['proof_image_path' => $path]);

            return $lockedPayment->fresh(BillingEagerLoads::payment());
        });
    }

    public function approve(Payment $payment, float|int|string|null $amount = null): Payment
    {
        $approved = DB::transaction(function () use ($payment, $amount): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
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

            $invoice->unsetRelation('payments');
            $currentBalance = $this->invoiceCurrentBalance($invoice);

            if ($currentBalance <= 0) {
                throw new ConcurrentConflictException('Invoice has already been fully paid.');
            }

            $paidAmount = $this->resolveApprovalAmount($lockedPayment, $amount, $currentBalance);

            if ($paidAmount <= 0) {
                throw new InvalidArgumentException('Paid amount must be greater than zero.');
            }

            // Clamp tiny whole-MMK rounding overages (< 1 MMK) to the exact remaining balance.
            if ($paidAmount > $currentBalance && ($paidAmount - $currentBalance) < 1.0) {
                $paidAmount = $currentBalance;
            }

            if ($paidAmount > $currentBalance + 0.009) {
                throw new ConcurrentConflictException(
                    'Outstanding balance changed. Paid amount cannot exceed the current balance.',
                );
            }

            $lockedPayment->update([
                'amount' => $paidAmount,
                'status' => Payment::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $invoice = $this->syncInvoicePaymentStatus($invoice->fresh(['payments']));
            $this->receiptService->finalizeForApprovedPayment($lockedPayment->fresh());
            $invoice->loadMissing('contract.room');

            if ($invoice->contract) {
                $this->contractLifecycleService->syncAfterPayment($invoice->contract);
            }

            return $this->find($lockedPayment->id);
        });

        $this->notifyCustomerOfPaymentDecision($approved, 'approved');

        return $approved;
    }

    /**
     * Resolve the amount applied on approve.
     * Prefer an explicit request amount, then a pre-stored payment amount (Admin Cash),
     * then the current invoice balance (full settlement for customer submissions).
     */
    private function resolveApprovalAmount(Payment $payment, float|int|string|null $amount, float $currentBalance): float
    {
        if ($amount !== null && $amount !== '') {
            return round((float) $amount, 2);
        }

        if ($payment->amount !== null && $payment->amount !== '') {
            return round((float) $payment->amount, 2);
        }

        if ($currentBalance <= 0) {
            throw new InvalidArgumentException('Paid amount is required.');
        }

        return $currentBalance;
    }

    public function reject(Payment $payment, ?string $reason = null): Payment
    {
        $rejected = DB::transaction(function () use ($payment, $reason): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                throw new ConcurrentConflictException('Only pending payments can be rejected.');
            }

            $lockedPayment->update([
                'status' => Payment::STATUS_REJECTED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Pending payments never affect invoice totals; skip sync.

            return $this->find($lockedPayment->id);
        });

        $this->notifyCustomerOfPaymentDecision($rejected, 'rejected');

        return $rejected;
    }

    private function notifyCustomerOfPaymentDecision(Payment $payment, string $decision): void
    {
        try {
            $payment->loadMissing(['invoice.contract', 'creator', 'receipt']);
            $contract = $payment->invoice?->contract;

            if (! $contract) {
                return;
            }

            // Refresh party users so email reflects CURRENT users.email after any account update.
            $recipients = CustomerNotificationRecipients::usersForContract($contract);

            // Ensure the actual payer is notified even if relations were incomplete.
            if ($payment->created_by) {
                $creator = $payment->creator()->first() ?? $payment->creator;
                if ($creator && ! $recipients->contains('id', $creator->id)) {
                    $recipients = $recipients->push($creator)->values();
                }
            }

            $notified = [];

            foreach ($recipients as $customer) {
                if (! $customer?->email) {
                    continue;
                }

                $emailKey = strtolower(trim((string) $customer->email));
                if (isset($notified[$emailKey])) {
                    continue;
                }
                $notified[$emailKey] = true;

                if ($decision === 'approved') {
                    $customer->notify(new PaymentApprovedNotification($payment));
                } else {
                    $customer->notify(new PaymentRejectedNotification($payment));
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function invoiceTotalDue(Invoice $invoice): float
    {
        return round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2);
    }

    public function invoiceApprovedPaidAmount(Invoice $invoice): float
    {
        $invoice->loadMissing('payments');

        return round((float) $invoice->payments
            ->where('status', Payment::STATUS_APPROVED)
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
