<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaymentPlan */
class PaymentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'payment_type' => $this->payment_type,
            'duration_months' => $this->duration_months,
            'interest_percentage' => $this->interest_percentage,
            'status' => $this->status,
        ];
    }
}
