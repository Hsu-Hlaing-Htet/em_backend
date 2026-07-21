<?php

namespace App\Services;

use App\Mail\PaymentDocumentMail;
use App\Models\Payment;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class PaymentDocumentService
{
    use BuildsBillingDocumentData;
    use ServesHtmlDocument;

    public function find(int $id): Payment
    {
        return Payment::query()
            ->with([
                'invoice.contract.user.profile',
                'invoice.contract.room.building',
                'paymentMethod',
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Payment $payment): string
    {
        $payment->loadMissing([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'creator',
            'approver',
        ]);

        return view('payments.document', [
            'document' => $this->buildDocumentData($payment),
        ])->render();
    }

    public function downloadResponse(Payment $payment): Response
    {
        return $this->downloadHtmlResponse(
            $this->renderHtml($payment),
            $this->filename($payment),
        );
    }

    public function exportResponse(Payment $payment): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($payment),
            $this->filename($payment),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Payment $payment, array $data): void
    {
        $payment->loadMissing(['invoice.contract.user']);
        $email = $data['email'] ?? $payment->invoice?->contract?->user?->email;

        if (! $email) {
            throw new InvalidArgumentException('Customer email is required to send the payment confirmation document.');
        }

        Mail::to($email)->send(new PaymentDocumentMail(
            $payment,
            $this->renderHtml($payment),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Payment $payment): array
    {
        $invoice = $payment->invoice;
        $user = $invoice?->contract?->user;
        $room = $invoice?->contract?->room;

        return [
            'header' => [
                'referenceNo' => $this->referenceNumber($payment),
                'issuedDate' => optional($payment->approved_at ?? $payment->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'footerAddress' => $this->footerAddress(),
            'details' => [
                ['label' => 'Customer', 'value' => $this->customerName($user)],
                ['label' => 'Invoice Number', 'value' => $invoice?->invoice_number ?? '-'],
                ['label' => 'Payment Date', 'value' => optional($payment->payment_date)->toDateString() ?? '-'],
                ['label' => 'Payment Method', 'value' => $payment->paymentMethod?->name ?? '-'],
                ['label' => 'Status', 'value' => $this->statusLabel($payment->status)],
                ['label' => 'Building', 'value' => $room?->building?->building_name ?? '-'],
                ['label' => 'Room / Unit', 'value' => $room?->room_number ?? '-'],
                ['label' => 'Note', 'value' => $payment->note ?: '-'],
                ['label' => 'Submitted By', 'value' => $payment->creator?->name ?? '-'],
                ['label' => 'Approved By', 'value' => $payment->approver?->name ?? 'Pending'],
            ],
            'amountPaid' => [
                'label' => 'Amount Paid',
                'amount' => $this->formatCurrency((float) $payment->amount),
            ],
        ];
    }

    private function referenceNumber(Payment $payment): string
    {
        return sprintf('PAY-%05d', $payment->id);
    }

    private function filename(Payment $payment): string
    {
        return $this->referenceNumber($payment).'.html';
    }
}
