<?php

namespace App\Services;

use App\Mail\ReceiptDocumentMail;
use App\Models\Receipt;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class ReceiptDocumentService
{
    use BuildsBillingDocumentData;
    use ServesHtmlDocument;

    public function find(int $id): Receipt
    {
        return Receipt::query()
            ->with([
                'payment.invoice.contract.user.profile',
                'payment.invoice.contract.room.building',
                'payment.paymentMethod',
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Receipt $receipt): string
    {
        $receipt->loadMissing([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);

        return view('receipts.document', [
            'document' => $this->buildDocumentData($receipt),
        ])->render();
    }

    public function downloadResponse(Receipt $receipt): Response
    {
        return $this->downloadHtmlResponse(
            $this->renderHtml($receipt),
            $this->filename($receipt),
        );
    }

    public function exportResponse(Receipt $receipt): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($receipt),
            $this->filename($receipt),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Receipt $receipt, array $data): void
    {
        $receipt->loadMissing(['payment.invoice.contract.user']);
        $email = $data['email'] ?? $receipt->payment?->invoice?->contract?->user?->email;

        if (! $email) {
            throw new InvalidArgumentException('Customer email is required to send the receipt document.');
        }

        Mail::to($email)->send(new ReceiptDocumentMail(
            $receipt,
            $this->renderHtml($receipt),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Receipt $receipt): array
    {
        $payment = $receipt->payment;
        $invoice = $payment?->invoice;
        $user = $invoice?->contract?->user;
        $room = $invoice?->contract?->room;

        return [
            'header' => [
                'referenceNo' => $receipt->receipt_number,
                'issuedDate' => optional($receipt->issued_at ?? $receipt->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'footerAddress' => $this->footerAddress(),
            'details' => [
                ['label' => 'Customer', 'value' => $this->customerName($user)],
                ['label' => 'Receipt Number', 'value' => $receipt->receipt_number],
                ['label' => 'Invoice Number', 'value' => $invoice?->invoice_number ?? '-'],
                ['label' => 'Payment Date', 'value' => optional($payment?->payment_date)->toDateString() ?? '-'],
                ['label' => 'Payment Method', 'value' => $payment?->paymentMethod?->name ?? '-'],
                ['label' => 'Status', 'value' => $this->statusLabel($receipt->status)],
                ['label' => 'Building', 'value' => $room?->building?->building_name ?? '-'],
                ['label' => 'Room / Unit', 'value' => $room?->room_number ?? '-'],
            ],
            'amountReceived' => [
                'label' => 'Amount Received',
                'amount' => $this->formatCurrency((float) ($payment?->amount ?? 0)),
            ],
            'acknowledgement' => [
                'customerName' => $user?->name ?? '________________',
                'representativeName' => $receipt->approver?->name ?? '________________',
            ],
        ];
    }

    private function filename(Receipt $receipt): string
    {
        return ($receipt->receipt_number ?: 'receipt').'.html';
    }
}
