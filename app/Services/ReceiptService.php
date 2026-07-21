<?php

namespace App\Services;

use App\Mail\ReceiptDocumentMail;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
        $query = Receipt::query()->with(['payment.invoice.contract.user', 'payment.paymentMethod']);

        $this->applyStatusFilter($query, $params);
        $this->applyListQuery($query, $params, ['receipt_number']);

        return $query->latest('id')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Receipt
    {
        return Receipt::query()
            ->with(['payment.invoice.contract.user.profile', 'payment.paymentMethod'])
            ->findOrFail($id);
    }

    public function createDraftForPayment(Payment $payment): Receipt
    {
        $payment->loadMissing('receipt');

        if ($payment->receipt) {
            return $payment->receipt;
        }

        return Receipt::query()->create([
            'payment_id' => $payment->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
    }

    public function issue(Receipt $receipt): Receipt
    {
        if ($receipt->status !== 'draft') {
            throw new InvalidArgumentException('Only draft receipts can be issued.');
        }

        $path = $this->generatePdf($receipt);

        $receipt->update([
            'status' => 'issued',
            'issued_at' => now(),
            'receipt_pdf_path' => $path,
            'approved_by' => Auth::id(),
        ]);

        $receipt = $receipt->fresh(['payment.invoice.contract.user', 'payment.paymentMethod']);

        if ($receipt->payment?->invoice?->contract?->user?->email) {
            Mail::to($receipt->payment->invoice->contract->user->email)->send(new ReceiptDocumentMail(
                $receipt,
                $this->receiptDocumentService->renderHtml($receipt),
            ));
        }

        return $receipt;
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
        $lastSequence = Receipt::query()
            ->where('receipt_number', 'like', 'RCP-%')
            ->pluck('receipt_number')
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        return 'RCP-'.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }
}
