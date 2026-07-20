<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'utility_id' => $this->utility_id,
            'invoice_number' => $this->invoice_number,
            'type' => $this->type,
            'issued_date' => $this->issued_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'late_fee' => $this->late_fee,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'customer_name' => $this->whenLoaded('contract', fn () => $this->contract?->user?->name),
            'customer_email' => $this->whenLoaded('contract', fn () => $this->contract?->user?->email),
            'customer_phone' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('user'),
                fn () => $this->contract?->user?->profile?->phone,
            ),
            'customer_nrc' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('user'),
                fn () => $this->contract?->user?->profile?->nrc,
            ),
            'building_name' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->building?->building_name,
            ),
            'room_number' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->room_number,
            ),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
