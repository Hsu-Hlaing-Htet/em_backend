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
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
