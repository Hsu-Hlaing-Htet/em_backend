<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UtilityRate
 */
class UtilityRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'utility_type_id' => $this->utility_type_id,
            'type_name' => $this->whenLoaded('utilityType', fn () => $this->utilityType?->name),
            'unit_price' => $this->unit_price,
            'effective_date' => $this->effective_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
