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
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->name),
            'amount' => $this->amount,
            'proof_image_path' => $this->proof_image_path,
            'proof_image_url' => $this->proof_image_path
                ? Storage::disk('public')->url($this->proof_image_path)
                : null,
            'note' => $this->note,
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
}
