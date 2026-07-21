<?php

namespace App\Services;

use App\Mail\InvoiceDocumentMail;
use App\Models\Invoice;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
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
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'contract.user.profile',
            'contract.room.building',
            'items.chargeType',
            'creator',
            'approver',
        ]);

        return view('invoices.document', [
            'document' => $this->buildDocumentData($invoice),
        ])->render();
    }

    public function downloadResponse(Invoice $invoice): Response
    {
        return $this->downloadHtmlResponse(
            $this->renderHtml($invoice),
            $this->filename($invoice),
        );
    }

    public function exportResponse(Invoice $invoice): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($invoice),
            $this->filename($invoice),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Invoice $invoice, array $data): void
    {
        $invoice->loadMissing(['contract.user']);
        $email = $data['email'] ?? $invoice->contract?->user?->email;

        if (! $email) {
            throw new InvalidArgumentException('Customer email is required to send the invoice document.');
        }

        Mail::to($email)->send(new InvoiceDocumentMail(
            $invoice,
            $this->renderHtml($invoice),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Invoice $invoice): array
    {
        $user = $invoice->contract?->user;
        $room = $invoice->contract?->room;

        return [
            'header' => [
                'referenceNo' => $invoice->invoice_number,
                'issuedDate' => optional($invoice->issued_date ?? $invoice->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'footerAddress' => $this->footerAddress(),
            'details' => [
                ['label' => 'Customer', 'value' => $this->customerName($user)],
                ['label' => 'Building', 'value' => $room?->building?->building_name ?? '-'],
                ['label' => 'Room / Unit', 'value' => $room?->room_number ?? '-'],
                ['label' => 'Invoice Type', 'value' => ucfirst((string) $invoice->type)],
                ['label' => 'Status', 'value' => $this->statusLabel($invoice->status)],
                ['label' => 'Due Date', 'value' => optional($invoice->due_date)->toDateString() ?? '-'],
                ['label' => 'Late Fee', 'value' => $this->formatCurrency((float) $invoice->late_fee)],
                ['label' => 'Prepared By', 'value' => $invoice->creator?->name ?? '-'],
                ['label' => 'Approved By', 'value' => $invoice->approver?->name ?? 'Pending'],
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'charge_type' => $item->chargeType?->name ?? '-',
                'amount' => $this->formatCurrency((float) $item->amount),
            ])->all(),
            'totalDue' => [
                'label' => 'Amount Due',
                'amount' => $this->formatCurrency((float) $invoice->total_amount),
                'details' => [],
            ],
            'paymentTerms' => 'Please settle this invoice by the due date. Late fees may apply after the due date.',
        ];
    }

    private function filename(Invoice $invoice): string
    {
        return ($invoice->invoice_number ?: 'invoice').'.html';
    }
}
