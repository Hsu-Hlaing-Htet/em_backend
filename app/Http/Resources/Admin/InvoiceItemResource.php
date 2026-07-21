<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charge_type_id' => $this->charge_type_id,
            'charge_type_name' => $this->whenLoaded('chargeType', fn () => $this->chargeType?->name),
            'description' => $this->description,
            'amount' => $this->amount,
        ];
    }
}
