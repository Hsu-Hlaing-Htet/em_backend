<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'payment_plan_id' => $this->payment_plan_id,
            'payment_plan_name' => $this->whenLoaded('paymentPlan', fn () => $this->paymentPlan?->name),
            'contract_total' => $this->contract_total,
            'type' => $this->type,
            'payment_type' => $this->payment_type,
            'duration_months' => $this->duration_months,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'billing_day' => $this->billing_day,
            'status' => $this->status,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
