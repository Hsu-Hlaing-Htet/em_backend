<?php

namespace App\Services;

use App\Mail\ReceiptDocumentMail;
use App\Models\Receipt;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use App\Support\CustomerPortalUrl;
use App\Support\CustomerNotificationRecipients;
use App\Support\DocumentFilename;
use App\Support\InvoiceLateFeePolicy;
use App\Support\PaymentFinancialSummary;
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
                'payment.invoice.contract.secondUser.profile',
                'payment.invoice.contract.room.building',
                'payment.invoice.items.chargeType',
                'payment.invoice.utility.items.utilityType',
                'payment.invoice.payments',
                'payment.paymentMethod',
                'payment.creator',
                'payment.approver',
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Receipt $receipt): string
    {
        $receipt->loadMissing([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.secondUser.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'payment.creator',
            'payment.approver',
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
            throw new InvalidArgumentException('Only approved receipts can be emailed.');
        }

        $receipt->loadMissing([
            'payment.invoice.contract',
            'payment.invoice.contract.room',
        ]);

        $contract = $receipt->payment?->invoice?->contract;
        $partyUsers = CustomerNotificationRecipients::usersForContract($contract);
        $emails = CustomerNotificationRecipients::emailsForContract($contract);

        if ($emails === []) {
            throw new InvalidArgumentException('Customer email is required to send the receipt document.');
        }

        foreach ($emails as $email) {
            $this->sendEmailToRecipient($receipt, $email, CustomerPortalUrl::customerNameForEmail(
                $partyUsers,
                $email,
                $contract?->user?->name,
            ));
        }
    }

    public function sendEmailToRecipient(Receipt $receipt, string $email, ?string $customerName = null): void
    {
        $receipt->loadMissing([
            'payment.invoice.contract',
        ]);

        $contract = $receipt->payment?->invoice?->contract;
        $partyUsers = CustomerNotificationRecipients::usersForContract($contract);

        $name = $customerName
            ?: CustomerPortalUrl::customerNameForEmail(
                $partyUsers,
                $email,
                $contract?->user?->name,
            );

        Mail::to($email)->send(new ReceiptDocumentMail(
            $receipt,
            $name,
        ));
    }

    /**
     * Canonical Receipt document payload — matches browser Preview (useReceiptDocument).
     *
     * @return array<string, mixed>
     */
    public function buildDocumentData(Receipt $receipt): array
    {
        $payment = $receipt->payment;
        $invoice = $payment?->invoice;
        $contract = $invoice?->contract;
        $room = $contract?->room;
        $summary = PaymentFinancialSummary::fromPayment($payment, $invoice);
        $receiptNumber = $receipt->receipt_number ?: '—';
        $invoiceNumber = $invoice?->invoice_number ?: '—';
        $receiptDate = $this->formatDisplayDate($receipt->issued_at ?? $receipt->created_at);
        $paymentDate = $this->formatDisplayDate($payment?->payment_date);
        $paymentMethod = $payment?->paymentMethod?->name ?: '—';
        $building = $room?->building?->building_name ?: '—';
        $roomNumber = $room?->room_number ?: '—';
        $paidBy = $payment?->relationLoaded('creator')
            ? ($payment->creator?->name ?: '—')
            : '—';
        $approvedBy = $payment?->relationLoaded('approver')
            ? ($payment->approver?->name ?: '—')
            : '—';
        $customerName = $contract
            ? $contract->partyDisplayName()
            : ($contract?->user?->name ?: '—');

        $totals = [
            'subtotal' => $this->formatReceiptCurrency($summary['subtotal']),
            'late_fee' => $this->formatReceiptCurrency($summary['late_fee']),
            'total' => $this->formatReceiptCurrency($summary['total']),
            'paid' => $this->formatReceiptCurrency($summary['paid']),
            'show_change' => $summary['show_change'],
            'change' => $summary['show_change']
                ? $this->formatReceiptCurrency((float) $summary['change'])
                : null,
            'balance' => $summary['show_change']
                ? null
                : $this->formatReceiptCurrency((float) ($summary['balance'] ?? 0)),
        ];

        $lateFeeNotes = $invoice
            ? InvoiceLateFeePolicy::receiptDocumentNotes($invoice)
            : null;

        return [
            'title' => 'PAYMENT RECEIPT',
            'subtitle' => 'THANK YOU FOR YOUR PAYMENT',
            'company' => [
                'name' => 'Rosewood Royale Residences',
                'tagline' => 'Residences & Property Management',
                'address' => $this->footerAddress(),
                'phone' => '+95 9 123 456 789',
                'email' => 'contracts@rosewoodroyale.com',
                'website' => 'www.rosewoodroyale.com',
            ],
            'header' => [
                'receipt_number' => $receiptNumber,
                'date' => $receiptDate,
            ],
            'info' => [
                'customer_name' => $customerName !== '' ? $customerName : '—',
                'building' => $building !== '' ? $building : '—',
                'room' => $roomNumber !== '' ? $roomNumber : '—',
                'paid_by' => $paidBy !== '' ? $paidBy : '—',
                'invoice_number' => $invoiceNumber,
                'approved_by' => $approvedBy !== '' ? $approvedBy : '—',
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
            ],
            'items' => $this->resolveChargeItems($invoice),
            'totals' => $totals,
            'late_fee_notes' => $lateFeeNotes,
            'confirmation' => [
                'title' => 'Payment received successfully.',
                'message' => 'This receipt confirms that the payment has been recorded successfully.',
            ],
            'footer' => [
                'confidential_notice' => 'System-generated receipt · No signature required',
            ],
            'financial_summary' => $summary,
        ];
    }

    /**
     * @return list<array{description: string, amount: string}>
     */
    private function resolveChargeItems($invoice): array
    {
        if (! $invoice || ! $invoice->relationLoaded('items') || $invoice->items->isEmpty()) {
            return [];
        }

        return $invoice->items
            ->filter(function ($item): bool {
                $slug = $item->relationLoaded('chargeType')
                    ? $item->chargeType?->slug
                    : null;

                return $slug !== 'late-fee';
            })
            ->map(function ($item): array {
                return [
                    'description' => $this->resolveItemDescription($item),
                    'amount' => $this->formatReceiptCurrency((float) ($item->amount ?? 0)),
                ];
            })
            ->filter(fn (array $row): bool => trim($row['description']) !== '')
            ->values()
            ->all();
    }

    private function resolveItemDescription($item): string
    {
        $slug = $item->relationLoaded('chargeType') ? $item->chargeType?->slug : null;
        $description = (string) ($item->description ?? '');
        $chargeName = $item->relationLoaded('chargeType') ? ($item->chargeType?->name ?? '') : '';

        if ($slug === 'monthly-rent') {
            return 'Rent';
        }

        if ($slug === 'utility-charges') {
            return $this->extractUtilityType($description) ?: ($chargeName !== '' ? $chargeName : 'Utility');
        }

        return $this->extractUtilityType($description)
            ?: ($chargeName !== '' ? $chargeName : ($description !== '' ? $description : 'Charge'));
    }

    private function extractUtilityType(string $description): ?string
    {
        if ($description !== '' && str_contains($description, '—')) {
            $name = trim((string) substr($description, strpos($description, '—') + strlen('—')));

            return $name !== '' ? $name : null;
        }

        return null;
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

    public function pdfFilename(Receipt $receipt): string
    {
        return $this->filename($receipt);
    }

    private function filename(Receipt $receipt): string
    {
        return DocumentFilename::pdf($receipt->receipt_number, 'RCP-000000');
    }

    private function htmlFilename(Receipt $receipt): string
    {
        return ($receipt->receipt_number ?: 'receipt').'.html';
    }
}
