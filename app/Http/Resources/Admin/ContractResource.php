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
        $roomPrice = $this->resolveRoomPrice();
        $depositAmount = (float) $this->deposit_amount;
        $contractTotal = (float) $this->contract_total;
        $remainingBalance = max($contractTotal - $depositAmount, 0);
        $interestPercentage = (float) ($this->paymentPlan?->interest_percentage ?? 0);
        $interestAmount = $remainingBalance * ($interestPercentage / 100);
        $totalInstallmentAmount = $this->payment_type === 'installment'
            ? $remainingBalance + $interestAmount
            : 0;
        $estimatedMonthlyPayment = $this->payment_type === 'installment' && $this->duration_months
            ? (int) ceil($totalInstallmentAmount / $this->duration_months)
            : 0;

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'user_id' => $this->user_id,
            'user_name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'customer_name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->profile?->phone,
                'nrc' => $this->user?->profile?->nrc,
                'address' => $this->user?->profile?->address,
            ]),
            'customer_address' => $this->relationLoaded('user') ? $this->user?->profile?->address : null,
            'room_id' => $this->room_id,
            'room_number' => $this->relationLoaded('room') ? $this->room?->room_number : null,
            'building_id' => $this->relationLoaded('room') ? $this->room?->building_id : null,
            'building_name' => $this->relationLoaded('room') ? $this->room?->building?->building_name : null,
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room?->id,
                'building_id' => $this->room?->building_id,
                'room_number' => $this->room?->room_number,
                'sale_price' => $this->room?->sale_price,
                'rent_price' => $this->room?->rent_price,
                'booking_deposit_price' => $this->room?->booking_deposit_price,
                'rent_deposit_price' => $this->room?->rent_deposit_price,
                'type' => $this->room?->type,
                'status' => $this->room?->status,
            ]),
            'building' => $this->when(
                $this->relationLoaded('room') && $this->room?->relationLoaded('building'),
                fn () => [
                    'id' => $this->room?->building?->id,
                    'building_name' => $this->room?->building?->building_name,
                    'location' => $this->room?->building?->location,
                ],
            ),
            'room_price' => $roomPrice,
            'payment_plan_id' => $this->payment_plan_id,
            'payment_plan_name' => $this->whenLoaded('paymentPlan', fn () => $this->paymentPlan?->name),
            'payment_plan' => $this->whenLoaded('paymentPlan', fn () => new PaymentPlanResource($this->paymentPlan)),
            'contract_total' => $this->contract_total,
            'deposit_amount' => $this->deposit_amount,
            'remaining_balance' => number_format($remainingBalance, 2, '.', ''),
            'interest_percentage' => number_format($interestPercentage, 2, '.', ''),
            'total_installment_amount' => number_format($totalInstallmentAmount, 2, '.', ''),
            'estimated_monthly_payment' => number_format($estimatedMonthlyPayment, 2, '.', ''),
            'type' => $this->type,
            'payment_type' => $this->payment_type,
            'duration_months' => $this->duration_months,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'billing_day' => $this->billing_day,
            'status' => $this->status,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->name,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveRoomPrice(): ?string
    {
        if ($this->relationLoaded('room') && $this->room !== null) {
            if ($this->type === 'rent') {
                return $this->room->rent_price;
            }

            return $this->room->sale_price;
        }

        return null;
    }
}
