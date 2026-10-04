<?php

namespace App\Http\Resources\Admin;

use App\Support\PublicStorageUrl;
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
            'maintenance_category_id' => $this->maintenance_category_id,
            'maintenance_category_name' => $this->maintenanceCategory?->name,
            'category' => $this->category,
            'priority' => $this->priority,
            'description' => $this->description,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => PublicStorageUrl::for($this->attachment_path),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'resolution_note' => $this->resolution_note,
            'maintenance_fee_amount' => $this->maintenance_fee_amount !== null
                ? (float) $this->maintenance_fee_amount
                : null,
            'charge_description' => $this->charge_description,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
