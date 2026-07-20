<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Receipt
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'receipt_pdf_path' => $this->receipt_pdf_path,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'customer_name' => $this->when(
                $this->relationLoaded('payment') && $this->payment?->relationLoaded('invoice'),
                fn () => $this->payment?->invoice?->contract?->user?->name,
            ),
            'customer_email' => $this->when(
                $this->relationLoaded('payment') && $this->payment?->relationLoaded('invoice'),
                fn () => $this->payment?->invoice?->contract?->user?->email,
            ),
            'customer_phone' => $this->when(
                $this->relationLoaded('payment')
                    && $this->payment?->relationLoaded('invoice')
                    && $this->payment?->invoice?->relationLoaded('contract')
                    && $this->payment?->invoice?->contract?->relationLoaded('user'),
                fn () => $this->payment?->invoice?->contract?->user?->profile?->phone,
            ),
            'customer_nrc' => $this->when(
                $this->relationLoaded('payment')
                    && $this->payment?->relationLoaded('invoice')
                    && $this->payment?->invoice?->relationLoaded('contract')
                    && $this->payment?->invoice?->contract?->relationLoaded('user'),
                fn () => $this->payment?->invoice?->contract?->user?->profile?->nrc,
            ),
            'invoice_number' => $this->whenLoaded('payment', fn () => $this->payment?->invoice?->invoice_number),
            'payment_amount' => $this->whenLoaded('payment', fn () => $this->payment?->amount),
            'payment_method_name' => $this->whenLoaded('payment', fn () => $this->payment?->paymentMethod?->name),
            'payment_date' => $this->whenLoaded('payment', fn () => $this->payment?->payment_date?->toDateString()),
            'building_name' => $this->when(
                $this->relationLoaded('payment')
                    && $this->payment?->relationLoaded('invoice')
                    && $this->payment?->invoice?->relationLoaded('contract')
                    && $this->payment?->invoice?->contract?->relationLoaded('room'),
                fn () => $this->payment?->invoice?->contract?->room?->building?->building_name,
            ),
            'room_number' => $this->when(
                $this->relationLoaded('payment')
                    && $this->payment?->relationLoaded('invoice')
                    && $this->payment?->invoice?->relationLoaded('contract')
                    && $this->payment?->invoice?->contract?->relationLoaded('room'),
                fn () => $this->payment?->invoice?->contract?->room?->room_number,
            ),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
