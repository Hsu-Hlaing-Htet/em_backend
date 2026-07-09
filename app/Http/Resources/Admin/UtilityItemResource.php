<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UtilityItem
 */
class UtilityItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'utility_id' => $this->utility_id,
            'utility_type_id' => $this->utility_type_id,
            'utility_type_name' => $this->whenLoaded('utilityType', fn () => $this->utilityType?->name),
            'previous_reading' => $this->previous_reading,
            'current_reading' => $this->current_reading,
            'usage' => $this->usage,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
