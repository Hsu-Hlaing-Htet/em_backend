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
        $contract = $invoice && $invoice->relationLoaded('contract') ? $invoice->contract : null;
        $user = $contract && $contract->relationLoaded('user') ? $contract->user : null;
        $room = $contract && $contract->relationLoaded('room') ? $contract->room : null;
        $building = $room && $room->relationLoaded('building') ? $room->building : null;
        $profile = $user && $user->relationLoaded('profile') ? $user->profile : null;

        $invoiceAmount = $invoice
            ? round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2)
            : 0.0;
        $approvedPaidAmount = $this->resolveInvoicePaidAmount($invoice);
        $balance = max(round($invoiceAmount - $approvedPaidAmount, 2), 0);

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            // Always emit display keys (null when genuinely absent) so Vue does not treat MissingValue as "—".
            'invoice_number' => $invoice?->invoice_number,
            'invoice_type' => $invoice?->type,
            'payment_type' => $this->resolvePaymentType($invoice),
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->relationLoaded('paymentMethod')
                ? $this->paymentMethod?->name
                : null,
            'payment_method_type' => $this->relationLoaded('paymentMethod')
                ? $this->paymentMethod?->type
                : null,
            'amount' => $this->amount,
            'invoice_amount' => $invoiceAmount,
            'paid_amount' => $approvedPaidAmount,
            'balance' => $balance,
            'current_balance' => $balance,
            'display_status' => $this->resolveDisplayStatus($invoice),
            'property_unit' => $this->resolvePropertyUnit($building?->building_name, $room?->room_number),
            'reference_number' => $invoice?->invoice_number,
            'proof_image_path' => $this->proof_image_path,
            'proof_image_url' => $this->proof_image_path
                ? Storage::disk('public')->url($this->proof_image_path)
                : null,
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'customer_name' => $user?->name,
            'customer_email' => $user?->email,
            'customer_phone' => $profile?->phone,
            'customer_nrc' => $profile?->nrc,
            'building_name' => $building?->building_name,
            'room_number' => $room?->room_number,
            'created_by_name' => $this->relationLoaded('creator') ? $this->creator?->name : null,
            'approved_by_name' => $this->relationLoaded('approver') ? $this->approver?->name : null,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'receipt_id' => $this->relationLoaded('receipt') ? $this->receipt?->id : null,
            'receipt_number' => $this->relationLoaded('receipt') ? $this->receipt?->receipt_number : null,
            'receipt_status' => $this->relationLoaded('receipt') ? $this->receipt?->status : null,
        ];
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

        if ($this->status === 'approved') {
            return (float) ($this->amount ?? 0);
        }

        return 0.0;
    }

    private function resolveDisplayStatus($invoice): string
    {
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

        if ($this->status === 'rejected') {
            return 'rejected';
        }

        if ($this->status === 'approved') {
            return $invoice?->status === 'partial' ? 'partial' : 'paid';
        }

        return (string) $this->status;
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

        if ($invoice->type === 'sale') {
            return 'sale';
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
