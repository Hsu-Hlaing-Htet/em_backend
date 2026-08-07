<?php

namespace App\Http\Resources\Admin;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Receipt */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->relationLoaded('payment') ? $this->payment : null;
        $invoice = $payment?->relationLoaded('invoice') ? $payment->invoice : null;
        $contract = $invoice?->relationLoaded('contract') ? $invoice->contract : null;
        $user = $contract?->relationLoaded('user') ? $contract->user : null;
        $room = $contract?->relationLoaded('room') ? $contract->room : null;
        $buildingName = $room?->relationLoaded('building') ? $room->building?->building_name : null;
        $roomNumber = $room?->room_number;
        $invoiceAmount = $invoice ? (float) $invoice->total_amount : 0.0;
        $paidAmount = $payment ? (float) $payment->amount : 0.0;
        $approvedPaidAmount = $this->resolveInvoicePaidAmount($invoice);
        $balance = max(round($invoiceAmount - $approvedPaidAmount, 2), 0);

        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'display_status' => $this->resolveDisplayStatus($invoice),
            'delivery_status' => $this->resolveDeliveryStatus(),
            'can_send_email' => $this->canBeEmailed(),
            'is_sent' => $this->isDeliveredToCustomer(),
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'sent_at' => $this->sent_at?->toDateTimeString(),
            'sent_by' => $this->sent_by,
            'sent_by_name' => $this->relationLoaded('sender') ? $this->sender?->name : null,
            'invoice_number' => $invoice?->invoice_number,
            'invoice_amount' => $invoiceAmount,
            'paid_amount' => $paidAmount,
            'amount' => $paidAmount ?: null,
            'balance' => $balance,
            'payment_type' => $this->resolvePaymentType($invoice),
            'payment_date' => $payment?->payment_date?->toDateString(),
            'payment_method_name' => $payment?->relationLoaded('paymentMethod')
                ? $payment->paymentMethod?->name
                : null,
            'payment_method_type' => $payment?->relationLoaded('paymentMethod')
                ? $payment->paymentMethod?->type
                : null,
            'payment_amount' => $paidAmount ?: null,
            'property_unit' => $this->resolvePropertyUnit($buildingName, $roomNumber),
            'customer_name' => $user?->name,
            'customer_email' => $user?->email,
            'customer_phone' => $user?->relationLoaded('profile') ? $user->profile?->phone : null,
            'customer_nrc' => $user?->relationLoaded('profile') ? $user->profile?->nrc : null,
            'building_name' => $buildingName,
            'room_number' => $roomNumber,
            'items' => $this->resolveInvoiceItems($invoice),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by' => $this->when(
                $this->approved_by,
                fn () => [
                    'id' => $this->approver?->id ?? $this->approved_by,
                    'name' => $this->relationLoaded('approver') ? $this->approver?->name : null,
                ],
            ),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveInvoiceItems($invoice): array
    {
        if (! $invoice || ! $invoice->relationLoaded('items')) {
            return [];
        }

        $invoice->items->each(function ($item) use ($invoice): void {
            $item->setRelation('invoice', $invoice);

            if ($invoice->relationLoaded('utility')) {
                $item->invoice->setRelation('utility', $invoice->utility);
            }

            if ($invoice->relationLoaded('items')) {
                $item->invoice->setRelation('items', $invoice->items);
            }
        });

        return InvoiceItemResource::collection($invoice->items)->resolve();
    }

    private function resolveInvoicePaidAmount($invoice): float
    {
        if (! $invoice) {
            return 0.0;
        }

        if ($invoice->relationLoaded('payments')) {
            return (float) $invoice->payments
                ->whereIn('status', ['approved', 'completed'])
                ->sum(fn ($payment) => (float) ($payment->amount ?? 0));
        }

        return 0.0;
    }

    private function resolveDisplayStatus($invoice): string
    {
        if ($this->approval_status === Receipt::APPROVAL_PENDING) {
            return 'pending';
        }

        if ($this->approval_status === Receipt::APPROVAL_REJECTED) {
            return 'rejected';
        }

        if ($this->isDeliveredToCustomer()) {
            return 'sent';
        }

        if ($this->isApproved() && $this->sent_at === null) {
            return 'approved_unsent';
        }

        if ($this->status === Receipt::STATUS_DRAFT) {
            return 'draft';
        }

        if ($invoice?->status === 'overdue') {
            return 'overdue';
        }

        if ($invoice?->status === 'paid') {
            return 'paid';
        }

        if ($invoice?->status === 'partial') {
            return 'partial';
        }

        return $this->status ?: 'issued';
    }

    private function resolveDeliveryStatus(): string
    {
        if ($this->isDeliveredToCustomer()) {
            return 'sent';
        }

        if ($this->isApproved() && $this->sent_at === null) {
            return 'unsent';
        }

        if ($this->isPendingApproval()) {
            return 'pending';
        }

        return 'draft';
    }

    private function resolvePaymentType($invoice): string
    {
        if (! $invoice) {
            return 'other';
        }

        if ($invoice->type === 'rent') {
            return 'rent';
        }

        if ($invoice->type === 'utility') {
            return 'utility';
        }

        if ($invoice->relationLoaded('items')) {
            $hasMaintenance = $invoice->items
                ->contains(fn ($item) => $item->relationLoaded('chargeType')
                    && $item->chargeType?->slug === 'maintenance-fee');

            if ($hasMaintenance) {
                return 'maintenance';
            }
        }

        return 'other';
    }

    private function resolvePropertyUnit(?string $buildingName, ?string $roomNumber): ?string
    {
        if ($buildingName && $roomNumber) {
            return "{$buildingName} · {$roomNumber}";
        }

        return $buildingName ?: $roomNumber;
    }
}
