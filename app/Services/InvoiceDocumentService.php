<?php

namespace App\Services;

use App\Http\Resources\Admin\InvoiceItemResource;
use App\Mail\InvoiceDocumentMail;
use App\Models\Invoice;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use App\Support\DocumentFilename;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class InvoiceDocumentService
{
    use BuildsBillingDocumentData;
    use ServesHtmlDocument;

    public function find(int $id): Invoice
    {
        return Invoice::query()
            ->with([
                'contract.user.profile',
                'contract.room.building',
                'items.chargeType',
                'utility.items.utilityType',
                'utilities.items.utilityType',
                'payments',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'contract.user.profile',
            'contract.room.building',
            'items.chargeType',
            'utility.items.utilityType',
            'utilities.items.utilityType',
            'payments',
        ]);

        return view('invoices.document', [
            'document' => $this->buildDocumentData($invoice),
        ])->render();
    }

    public function downloadResponse(Invoice $invoice): Response
    {
        return $this->downloadPdfResponse(
            $this->renderHtml($invoice),
            $this->filename($invoice),
        );
    }

    public function exportResponse(Invoice $invoice): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($invoice),
            $this->htmlFilename($invoice),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Invoice $invoice, array $data): void
    {
        $invoice->loadMissing(['contract.user', 'contract.room']);
        $email = $data['email'] ?? $invoice->contract?->user?->email;

        if (! $email) {
            throw new InvalidArgumentException('Customer email is required to send the invoice document.');
        }

        $filename = $this->filename($invoice);

        Mail::to($email)->send(new InvoiceDocumentMail(
            $invoice,
            $this->renderPdfBinary($this->renderHtml($invoice)),
            $filename,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Invoice $invoice): array
    {
        $user = $invoice->contract?->user;
        $room = $invoice->contract?->room;
        $subtotal = (float) $invoice->total_amount;
        $lateFee = (float) ($invoice->late_fee ?? 0);
        $amountDue = round($subtotal + $lateFee, 2);
        $invoiceNumber = $invoice->invoice_number ?: '—';
        $dueDate = $this->formatDisplayDate($invoice->due_date);
        $issueDate = $this->formatDisplayDate($invoice->issued_date ?? $invoice->created_at);

        $invoice->items->each(function ($item) use ($invoice): void {
            $item->setRelation('invoice', $invoice);
        });

        $itemRows = collect(InvoiceItemResource::collection($invoice->items)->resolve())
            ->map(fn (array $item) => $this->mapLineItemRow($item))
            ->values()
            ->all();

        return [
            'title' => 'INVOICE',
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
                'invoice_number' => $invoiceNumber,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'billing_period' => $this->billingPeriod($invoice),
                'amount_due' => $this->formatInvoiceCurrency($amountDue),
            ],
            'items' => $itemRows,
            'totals' => [
                'subtotal' => $this->formatInvoiceCurrency($subtotal),
                'late_fee' => $this->formatInvoiceCurrency($lateFee),
                'amount_due' => $this->formatInvoiceCurrency($amountDue),
            ],
            'notes' => $this->buildNotes($invoiceNumber, $dueDate),
            'confidentialNotice' => 'This invoice is intended solely for the named recipient and may contain confidential information.',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function mapLineItemRow(array $item): array
    {
        $isMetered = (bool) ($item['is_metered'] ?? false);

        return [
            'description' => (string) ($item['description'] ?? '—'),
            'previous_reading' => $isMetered ? $this->formatReading($item['previous_reading'] ?? null) : '—',
            'current_reading' => $isMetered ? $this->formatReading($item['current_reading'] ?? null) : '—',
            'usage' => $isMetered ? $this->formatReading($item['usage'] ?? null) : '—',
            'unit_price' => $isMetered ? $this->formatUnitPrice($item['unit_price'] ?? null) : '—',
            'amount' => $this->formatInvoiceCurrency((float) ($item['amount'] ?? 0)),
        ];
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

        return $this->formatInvoiceCurrency((float) $value, 2);
    }

    private function formatInvoiceCurrency(float $amount, int $decimals = 0): string
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

    private function billingPeriod(Invoice $invoice): string
    {
        if ($invoice->billing_month) {
            return $invoice->billing_month->format('F Y');
        }

        if ($invoice->relationLoaded('utility') && $invoice->utility?->billing_month) {
            return $invoice->utility->billing_month->format('F Y');
        }

        if ($invoice->issued_date) {
            return $invoice->issued_date->format('F Y');
        }

        return '—';
    }

    private function buildNotes(string $invoiceNumber, string $dueDate): string
    {
        return "Please reference {$invoiceNumber} when making payment. Payment is due by {$dueDate}. Late fees may apply after the due date.";
    }

    private function filename(Invoice $invoice): string
    {
        $number = DocumentFilename::sanitizeSegment($invoice->invoice_number, 'INV-000000');

        return $number.'.pdf';
    }

    private function htmlFilename(Invoice $invoice): string
    {
        return ($invoice->invoice_number ?: 'invoice').'.html';
    }
}
