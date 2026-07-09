<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Utility
 */
class UtilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'billing_month' => $this->billing_month?->toDateString(),
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'items' => UtilityItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
