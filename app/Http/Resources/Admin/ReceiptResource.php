<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Receipt */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'invoice_number' => $this->whenLoaded('payment', fn () => $this->payment?->invoice?->invoice_number),
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
