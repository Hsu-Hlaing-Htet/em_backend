<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'phone_number' => $this->phone_number,
            'qr_image_url' => $this->qrImageUrl(),
            'instructions' => $this->instructions,
            'status' => $this->status,
            'is_customer_visible' => (bool) $this->is_customer_visible,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
