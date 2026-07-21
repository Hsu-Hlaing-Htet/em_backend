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
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'building_name' => $this->whenLoaded('room', fn () => $this->room?->building?->building_name),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
