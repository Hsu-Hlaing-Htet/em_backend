<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
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
        $query = Receipt::query()->with([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);

        $this->applyReceiptSearch($query, $params);
        $this->applyBuildingRoomFilters($query, $params, 'payment.invoice.contract.room');
        $this->applyDateRangeFilter($query, $params, 'issued_at', 'issued_from', 'issued_to');
        $this->applyApprovalStatusFilter($query, $params);
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

    public function find(int $id): Receipt
    {
        return Receipt::query()
            ->with([
                'payment.invoice.contract.user.profile',
                'payment.invoice.contract.room.building',
                'payment.invoice.items.chargeType',
                'payment.invoice.utility.items.utilityType',
                'payment.invoice.payments',
                'payment.paymentMethod',
                'creator',
                'approver',
            ])
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
        if (! $receipt->isPendingApproval()) {
            throw new InvalidArgumentException('Only pending receipts can be approved.');
        }

        $receipt->update([
            'approval_status' => Receipt::APPROVAL_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $receipt->fresh([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);
    }

    public function reject(Receipt $receipt): Receipt
    {
        if (! $receipt->isPendingApproval()) {
            throw new InvalidArgumentException('Only pending receipts can be rejected.');
        }

        $receipt->update([
            'approval_status' => Receipt::APPROVAL_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $receipt->fresh([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);
    }

    public function issue(Receipt $receipt): Receipt
    {
        if (! $receipt->canBeIssued()) {
            throw new InvalidArgumentException('Only approved draft receipts can be issued.');
        }

        $path = $this->generatePdf($receipt);

        $receipt->update([
            'status' => Receipt::STATUS_ISSUED,
            'issued_at' => now(),
            'receipt_pdf_path' => $path,
        ]);

        return $receipt->fresh([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);
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
