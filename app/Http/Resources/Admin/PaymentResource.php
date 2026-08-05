<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;
        $invoiceAmount = $invoice
            ? round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2)
            : 0.0;
        $approvedPaidAmount = $this->resolveInvoicePaidAmount();
        $balance = max(round($invoiceAmount - $approvedPaidAmount, 2), 0);

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'invoice_type' => $this->whenLoaded('invoice', fn () => $this->invoice?->type),
            'payment_type' => $this->resolvePaymentType(),
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->name),
            'payment_method_type' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->type),
            'amount' => $this->amount,
            'invoice_amount' => $invoiceAmount,
            'paid_amount' => $approvedPaidAmount,
            'balance' => $balance,
            'current_balance' => $balance,
            'display_status' => $this->resolveDisplayStatus(),
            'property_unit' => $this->resolvePropertyUnit(),
            'reference_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'proof_image_path' => $this->proof_image_path,
            'proof_image_url' => $this->proof_image_path
                ? Storage::disk('public')->url($this->proof_image_path)
                : null,
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'customer_name' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice?->relationLoaded('contract'),
                fn () => $this->invoice?->contract?->user?->name,
            ),
            'customer_email' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice?->relationLoaded('contract'),
                fn () => $this->invoice?->contract?->user?->email,
            ),
            'customer_phone' => $this->when(
                $this->relationLoaded('invoice')
                    && $this->invoice?->relationLoaded('contract')
                    && $this->invoice?->contract?->relationLoaded('user'),
                fn () => $this->invoice?->contract?->user?->profile?->phone,
            ),
            'customer_nrc' => $this->when(
                $this->relationLoaded('invoice')
                    && $this->invoice?->relationLoaded('contract')
                    && $this->invoice?->contract?->relationLoaded('user'),
                fn () => $this->invoice?->contract?->user?->profile?->nrc,
            ),
            'building_name' => $this->when(
                $this->relationLoaded('invoice')
                    && $this->invoice?->relationLoaded('contract')
                    && $this->invoice?->contract?->relationLoaded('room'),
                fn () => $this->invoice?->contract?->room?->building?->building_name,
            ),
            'room_number' => $this->when(
                $this->relationLoaded('invoice')
                    && $this->invoice?->relationLoaded('contract')
                    && $this->invoice?->contract?->relationLoaded('room'),
                fn () => $this->invoice?->contract?->room?->room_number,
            ),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'receipt_id' => $this->whenLoaded('receipt', fn () => $this->receipt?->id),
            'receipt_number' => $this->whenLoaded('receipt', fn () => $this->receipt?->receipt_number),
            'receipt_status' => $this->whenLoaded('receipt', fn () => $this->receipt?->status),
        ];
    }

    private function resolveInvoicePaidAmount(): float
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

        if (! $invoice) {
            return 0.0;
        }

        if ($invoice->relationLoaded('payments')) {
            return (float) $invoice->payments
                ->whereIn('status', ['approved', 'completed'])
                ->sum(fn ($payment) => (float) ($payment->amount ?? 0));
        }

        if ($this->status === 'approved') {
            return (float) $this->amount;
        }

        return 0.0;
    }

    private function resolveDisplayStatus(): string
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

        if ($invoice) {
            if ($invoice->status === 'overdue') {
                return 'overdue';
            }

            if ($invoice->status === 'paid') {
                return 'paid';
            }

            if ($invoice->status === 'partial') {
                return 'partial';
            }
        }

        if ($this->status === 'pending') {
            return 'pending';
        }

        if ($this->status === 'approved') {
            return $invoice?->status === 'partial' ? 'partial' : 'paid';
        }

        return 'pending';
    }

    private function resolvePaymentType(): string
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

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

    private function resolvePropertyUnit(): ?string
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;
        $contract = $invoice?->relationLoaded('contract') ? $invoice->contract : null;
        $room = $contract?->relationLoaded('room') ? $contract->room : null;
        $buildingName = $room?->relationLoaded('building') ? $room->building?->building_name : null;
        $roomNumber = $room?->room_number;

        if ($buildingName && $roomNumber) {
            return "{$buildingName} · {$roomNumber}";
        }

        return $buildingName ?: $roomNumber;
    }
}
