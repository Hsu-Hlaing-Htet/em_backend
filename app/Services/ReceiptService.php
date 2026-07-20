<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReceiptService
{
    use AppliesListQuery;

    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly ReceiptDocumentService $receiptDocumentService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Receipt::query()->with(['payment.invoice', 'payment.paymentMethod']);

        $this->applyListQuery($query, $params, ['receipt_number']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['payment_id'])) {
            $query->where('payment_id', $params['payment_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Receipt
    {
        return Receipt::query()
            ->with(['payment.invoice.contract.user', 'payment.paymentMethod', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Receipt
    {
        $payment = Payment::query()->findOrFail($data['payment_id']);

        if ($payment->status !== 'approved') {
            throw new InvalidArgumentException('Receipt can only be created from an approved payment.');
        }

        $data['receipt_number'] = $data['receipt_number'] ?? $this->generateReceiptNumber();
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        return Receipt::query()->create($data)->load(['payment.invoice', 'payment.paymentMethod']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Receipt $receipt, array $data): Receipt
    {
        if ($receipt->status !== 'draft') {
            throw new InvalidArgumentException('Only draft receipts can be updated.');
        }

        $receipt->update($data);

        return $receipt->fresh(['payment.invoice', 'payment.paymentMethod']);
    }

    public function delete(Receipt $receipt): void
    {
        $receipt->delete();
    }

    public function issue(Receipt $receipt): Receipt
    {
        if ($receipt->payment->status !== 'approved') {
            throw new InvalidArgumentException('Receipt can only be issued for an approved payment.');
        }

        $receipt = $this->approvalService->transition($receipt, 'issued', ['draft']);
        $pdfPath = $this->generatePdf($receipt);
        $receipt->update([
            'issued_at' => now(),
            'receipt_pdf_path' => $pdfPath,
        ]);

        $receipt = $receipt->fresh(['payment.invoice.contract.user', 'payment.paymentMethod', 'approver']);
        $this->notifyCustomerOfIssuedReceipt($receipt);

        return $receipt;
    }

    private function notifyCustomerOfIssuedReceipt(Receipt $receipt): void
    {
        try {
            $this->receiptDocumentService->sendEmail($receipt, []);
        } catch (\Throwable) {
            // Email delivery should not block receipt issuance.
        }
    }

    public function generatePdf(Receipt $receipt): string
    {
        $html = $this->receiptDocumentService->renderHtml($receipt);
        $path = 'receipts/'.$receipt->receipt_number.'.html';
        Storage::disk('public')->put($path, $html);

        return $path;
    }

    public function generateReceiptNumber(): string
    {
        $prefix = 'REC-'.now()->format('Ymd').'-';
        $last = Receipt::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
