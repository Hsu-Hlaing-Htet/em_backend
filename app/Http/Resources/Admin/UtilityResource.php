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
        $occupant = $this->relationLoaded('room') ? $this->resolveOccupant() : null;

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'building_name' => $this->whenLoaded('room', fn () => $this->room?->building?->building_name),
            'billing_month' => $this->billing_month?->toDateString(),
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'items' => UtilityItemResource::collection($this->whenLoaded('items')),
            'customer_name' => $occupant?->name,
            'customer_email' => $occupant?->email,
            'customer_phone' => $occupant?->profile?->phone,
            'customer_nrc' => $occupant?->profile?->nrc,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveOccupant(): ?\App\Models\User
    {
        if (! $this->room_id) {
            return null;
        }

        $contract = \App\Models\Contract::query()
            ->where('room_id', $this->room_id)
            ->whereIn('status', ['active', 'approved'])
            ->with('user.profile')
            ->latest()
            ->first();

        return $contract?->user;
    }
}
