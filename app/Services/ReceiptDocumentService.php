<?php

namespace App\Services;

use App\Http\Resources\Admin\InvoiceItemResource;
use App\Mail\ReceiptDocumentMail;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use App\Support\DocumentFilename;
use Carbon\CarbonInterface;
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
                'payment.invoice.items.chargeType',
                'payment.invoice.utility.items.utilityType',
                'payment.invoice.payments',
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
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
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
        return $this->downloadPdfResponse(
            $this->renderHtml($receipt),
            $this->filename($receipt),
        );
    }

    public function exportResponse(Receipt $receipt): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($receipt),
            $this->htmlFilename($receipt),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Receipt $receipt, array $data): void
    {
        if (! $receipt->canBeEmailed()) {
            throw new InvalidArgumentException('Only approved receipts awaiting delivery can be emailed.');
        }

        $receipt->loadMissing([
            'payment.invoice.contract.user',
            'payment.invoice.contract.room',
        ]);
        $email = $data['email'] ?? $receipt->payment?->invoice?->contract?->user?->email;

        if (! $email) {
            throw new InvalidArgumentException('Customer email is required to send the receipt document.');
        }

        $this->sendEmailToRecipient($receipt, $email);
    }

    public function sendEmailToRecipient(Receipt $receipt, string $email): void
    {
        $filename = $this->filename($receipt);

        Mail::to($email)->send(new ReceiptDocumentMail(
            $receipt,
            $this->renderPdfBinary($this->renderHtml($receipt)),
            $filename,
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
        $invoiceAmount = $invoice ? (float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0) : 0.0;
        $paidAmount = (float) ($payment?->amount ?? 0);
        $approvedPaid = 0.0;

        if ($invoice?->relationLoaded('payments')) {
            $approvedPaid = (float) $invoice->payments
                ->where('status', Payment::STATUS_APPROVED)
                ->sum(fn ($item) => (float) ($item->amount ?? 0));
        }

        $balance = max(round($invoiceAmount - $approvedPaid, 2), 0);
        $receiptNumber = $receipt->receipt_number ?: '—';
        $invoiceNumber = $invoice?->invoice_number ?: '—';
        $issueDate = $this->formatDisplayDate($receipt->issued_at ?? $receipt->created_at);
        $paymentDate = $this->formatDisplayDate($payment?->payment_date);
        $paymentMethod = $payment?->paymentMethod?->name ?: '—';

        $itemRows = $this->resolveLineItems($invoice);

        return [
            'title' => 'RECEIPT',
            'company' => [
                'name' => 'Rosewood Royale Residences',
                'tagline' => 'Residences & Property Management',
                'address' => $this->footerAddress(),
                'phone' => '+95 9 123 456 789',
                'email' => 'contracts@rosewoodroyale.com',
                'website' => 'www.rosewoodroyale.com',
            ],
            'billTo' => [
                'name' => $user?->name ?: '—',
                'email' => $user?->email ?: '—',
                'phone' => $user?->profile?->phone ?: '—',
            ],
            'property' => [
                'building' => $room?->building?->building_name ?: '—',
                'room' => $room?->room_number ?: '—',
            ],
            'summary' => [
                'receipt_number' => $receiptNumber,
                'issue_date' => $issueDate,
                'invoice_number' => $invoiceNumber,
                'payment_date' => $paymentDate,
                'amount_received' => $this->formatReceiptCurrency($paidAmount),
            ],
            'items' => $itemRows,
            'totals' => [
                'invoice_total' => $this->formatReceiptCurrency($invoiceAmount),
                'amount_received' => $this->formatReceiptCurrency($paidAmount),
                'balance' => $this->formatReceiptCurrency($balance),
            ],
            'notes' => $this->buildNotes($invoiceNumber, $paymentMethod, $paymentDate),
            'confidentialNotice' => 'This receipt is intended solely for the named recipient and may contain confidential information.',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function resolveLineItems($invoice): array
    {
        if (! $invoice || ! $invoice->relationLoaded('items') || $invoice->items->isEmpty()) {
            return [];
        }

        $invoice->items->each(function ($item) use ($invoice): void {
            $item->setRelation('invoice', $invoice);

            if ($invoice->relationLoaded('utility')) {
                $item->invoice->setRelation('utility', $invoice->utility);
            }

            $item->invoice->setRelation('items', $invoice->items);
        });

        return collect(InvoiceItemResource::collection($invoice->items)->resolve())
            ->map(function (array $item): array {
                $isMetered = (bool) ($item['is_metered'] ?? false);

                return [
                    'description' => (string) ($item['description'] ?? '—'),
                    'previous_reading' => $isMetered ? $this->formatReading($item['previous_reading'] ?? null) : '—',
                    'current_reading' => $isMetered ? $this->formatReading($item['current_reading'] ?? null) : '—',
                    'usage' => $isMetered ? $this->formatReading($item['usage'] ?? null) : '—',
                    'unit_price' => $isMetered ? $this->formatUnitPrice($item['unit_price'] ?? null) : '—',
                    'amount' => $this->formatReceiptCurrency((float) ($item['amount'] ?? 0)),
                ];
            })
            ->values()
            ->all();
    }

    private function formatReading(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function formatUnitPrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $this->formatReceiptCurrency((float) $value, 2);
    }

    private function formatReceiptCurrency(float $amount, int $decimals = 0): string
    {
        return 'MMK '.number_format($amount, $decimals, '.', ',');
    }

    private function formatDisplayDate(mixed $date): string
    {
        if (! $date) {
            return '—';
        }

        if ($date instanceof CarbonInterface) {
            return $date->format('d M Y');
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function buildNotes(string $invoiceNumber, string $paymentMethod, string $paymentDate): string
    {
        return "Payment received for invoice {$invoiceNumber} via {$paymentMethod} on {$paymentDate}. Thank you for your payment.";
    }

    public function pdfFilename(Receipt $receipt): string
    {
        return $this->filename($receipt);
    }

    private function filename(Receipt $receipt): string
    {
        $receipt->loadMissing(['payment.invoice.contract.room']);

        return DocumentFilename::receipt(
            $receipt->issued_at ?? $receipt->created_at,
            $receipt->payment?->invoice?->contract?->room?->room_number,
            $receipt->receipt_number,
        );
    }

    private function htmlFilename(Receipt $receipt): string
    {
        return ($receipt->receipt_number ?: 'receipt').'.html';
    }
}
