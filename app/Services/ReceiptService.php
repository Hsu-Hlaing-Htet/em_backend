<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\BillingEagerLoads;
use App\Services\Concerns\AppliesBillingPropertyFilters;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReceiptService
{
    use AppliesBillingPropertyFilters;
    use AppliesListQuery;

    public function __construct(
        private readonly ReceiptDocumentService $receiptDocumentService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Receipt::query()->with(BillingEagerLoads::receipt());

        $this->applyReceiptSearch($query, $params);
        $this->applyBuildingRoomFilters($query, $params, 'payment.invoice.contract.room');
        $this->applyDateRangeFilter($query, $params, 'issued_at', 'issued_from', 'issued_to');
        $this->applyApprovalStatusFilter($query, $params);
        $this->applyDeliveryStatusFilter($query, $params);
        $this->applyStatusFilter($query, $params);
        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Receipt>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyReceiptSearch($query, array $params): void
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
            $builder->where('receipt_number', 'like', '%'.$search.'%');

            if ($paymentId) {
                $builder->orWhere('payment_id', $paymentId);
            }

            $builder->orWhereHas('payment.invoice', fn ($invoiceQuery) => $invoiceQuery
                ->where('invoice_number', 'like', '%'.$search.'%'))
                ->orWhereHas('payment.invoice.contract.user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Receipt>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyApprovalStatusFilter($query, array $params): void
    {
        if (empty($params['approval_status'])) {
            return;
        }

        $query->where('receipts.approval_status', $params['approval_status']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Receipt>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyDeliveryStatusFilter($query, array $params): void
    {
        if (empty($params['delivery_status'])) {
            return;
        }

        match ($params['delivery_status']) {
            'sent' => $query->deliveredToCustomer(),
            'unsent' => $query
                ->where('approval_status', Receipt::APPROVAL_APPROVED)
                ->whereNull('sent_at'),
            default => null,
        };
    }

    public function find(int $id): Receipt
    {
        return Receipt::query()
            ->with(BillingEagerLoads::receipt())
            ->findOrFail($id);
    }

    public function createDraftForPayment(Payment $payment): Receipt
    {
        return DB::transaction(function () use ($payment): Receipt {
            $existing = Receipt::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return Receipt::query()->create([
                    'payment_id' => $payment->id,
                    'receipt_number' => $this->generateReceiptNumber(),
                    'status' => Receipt::STATUS_DRAFT,
                    'approval_status' => Receipt::APPROVAL_PENDING,
                    'created_by' => Auth::id(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniquePaymentConstraintViolation($exception)) {
                    return Receipt::query()
                        ->where('payment_id', $payment->id)
                        ->firstOrFail();
                }

                throw $exception;
            }
        });
    }

    public function approve(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt): Receipt {
            /** @var Receipt $locked */
            $locked = Receipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPendingApproval()) {
                throw new ConcurrentConflictException('Only pending receipts can be approved.');
            }

            $locked->update([
                'approval_status' => Receipt::APPROVAL_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $this->find($locked->id);
        });
    }

    public function reject(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt): Receipt {
            /** @var Receipt $locked */
            $locked = Receipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPendingApproval()) {
                throw new ConcurrentConflictException('Only pending receipts can be rejected.');
            }

            $locked->update([
                'approval_status' => Receipt::APPROVAL_REJECTED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $this->find($locked->id);
        });
    }

    public function issue(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt): Receipt {
            /** @var Receipt $locked */
            $locked = Receipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeIssued()) {
                if ($locked->isDeliveredToCustomer()) {
                    throw new ConcurrentConflictException('This receipt has already been sent to the customer.');
                }

                if ($locked->status === Receipt::STATUS_ISSUED && $locked->sent_at === null) {
                    throw new ConcurrentConflictException('This receipt has already been issued.');
                }

                throw new InvalidArgumentException('Only approved draft receipts can be prepared for delivery.');
            }

            $path = $this->generatePdf($locked);

            $locked->update([
                'receipt_pdf_path' => $path,
            ]);

            return $this->find($locked->id);
        });
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function deliverByEmail(Receipt $receipt, array $data = []): Receipt
    {
        return DB::transaction(function () use ($receipt, $data): Receipt {
            /** @var Receipt $locked */
            $locked = Receipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->approval_status === Receipt::APPROVAL_REJECTED) {
                throw new InvalidArgumentException('Rejected receipts cannot be sent to the customer.');
            }

            if (! $locked->isApproved()) {
                throw new InvalidArgumentException('Only approved receipts can be sent to the customer.');
            }

            if ($locked->sent_at !== null) {
                throw new ConcurrentConflictException('This receipt has already been sent to the customer.');
            }

            $locked->loadMissing(['payment.invoice.contract.user.profile']);

            $customer = $locked->payment?->invoice?->contract?->user;

            if (! $customer) {
                throw new InvalidArgumentException('Unable to resolve the receipt customer.');
            }

            $email = isset($data['email']) && $data['email'] !== null && $data['email'] !== ''
                ? (string) $data['email']
                : $customer->email;

            if (! $email) {
                throw new InvalidArgumentException('Customer email is required to send the receipt.');
            }

            if (strcasecmp($email, (string) $customer->email) !== 0) {
                throw new InvalidArgumentException('Receipt can only be sent to the contract customer email.');
            }

            if (! $locked->receipt_pdf_path) {
                $locked->update([
                    'receipt_pdf_path' => $this->generatePdf($locked),
                ]);
            }

            try {
                $this->receiptDocumentService->sendEmailToRecipient($locked->fresh(), $email);
            } catch (\Throwable $exception) {
                throw new InvalidArgumentException('Unable to send receipt email: '.$exception->getMessage());
            }

            $locked->update([
                'status' => Receipt::STATUS_ISSUED,
                'issued_at' => $locked->issued_at ?? now(),
                'sent_at' => now(),
                'sent_by' => Auth::id(),
            ]);

            return $this->find($locked->id);
        });
    }

    public function generatePdf(Receipt $receipt): string
    {
        $pdf = app(DocumentPdfService::class)
            ->renderPdf($this->receiptDocumentService->renderHtml($receipt));
        $path = 'receipts/'.$this->receiptDocumentService->pdfFilename($receipt);

        Storage::disk('public')->put($path, $pdf);

        return $path;
    }

    public function generateReceiptNumber(): string
    {
        $lastSequence = Receipt::query()
            ->where('receipt_number', 'like', 'RCP-%')
            ->pluck('receipt_number')
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        return 'RCP-'.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    private function isUniquePaymentConstraintViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'receipts_payment_id_unique')
            || (str_contains($message, 'UNIQUE constraint failed') && str_contains($message, 'payment_id'))
            || (str_contains($message, 'Duplicate entry') && str_contains($message, 'payment_id'));
    }
}
