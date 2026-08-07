<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MaintenanceRequest */
class MaintenanceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_number' => $this->relationLoaded('room') ? $this->room?->room_number : null,
            'building_name' => $this->relationLoaded('room') ? $this->room?->building?->building_name : null,
            'user_id' => $this->user_id,
            'user_name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'customer_name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'title' => $this->title,
            'category' => $this->category,
            'priority' => $this->priority,
            'description' => $this->description,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'resolution_note' => $this->resolution_note,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
