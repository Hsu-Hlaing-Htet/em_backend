<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'invoice_number' => $this->invoice_number,
            'type' => $this->type,
            'status' => $this->status,
            'issued_date' => $this->issued_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'late_fee' => $this->late_fee,
            'total_amount' => $this->total_amount,
            'contract' => $this->whenLoaded('contract', fn () => new ContractResource($this->contract)),
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
            'approved_by' => $this->when(
                $this->approved_by,
                fn () => [
                    'id' => $this->approver?->id ?? $this->approved_by,
                    'name' => $this->relationLoaded('approver') ? $this->approver?->name : null,
                ],
            ),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'customer_name' => $this->whenLoaded('contract', fn () => $this->contract?->user?->name),
            'room_number' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->room_number,
            ),
            'building_name' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->building?->building_name,
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
