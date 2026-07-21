<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $salePrice = (float) $this->sale_price;
        $depositAmount = (float) $this->deposit_amount;
        $paidAmount = (float) $this->paid_amount;
        $remainingAmount = max($salePrice - $paidAmount, 0);
        $interestPercentage = (float) ($this->paymentPlan?->interest_percentage ?? 0);
        $remainingBalance = max($salePrice - $depositAmount, 0);
        $interestAmount = $remainingBalance * ($interestPercentage / 100);
        $totalInstallmentAmount = $this->payment_type === 'installment'
            ? $remainingBalance + $interestAmount
            : 0;
        $estimatedMonthlyPayment = $this->payment_type === 'installment' && $this->duration_months
            ? (int) ceil($totalInstallmentAmount / $this->duration_months)
            : 0;

        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'contract_no' => $this->sale_number,
            'user_id' => $this->user_id,
            'customer_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'customer_email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'customer_phone' => $this->whenLoaded('user', fn () => $this->user?->profile?->phone),
            'customer_nrc' => $this->whenLoaded('user', fn () => $this->user?->profile?->nrc),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->profile?->phone,
                'nrc' => $this->user?->profile?->nrc,
            ]),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn () => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room', fn () => $this->room?->building?->building_name),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room?->id,
                'building_id' => $this->room?->building_id,
                'room_number' => $this->room?->room_number,
                'sale_price' => $this->room?->sale_price,
                'booking_deposit_price' => $this->room?->booking_deposit_price,
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
            'room_price' => $this->whenLoaded('room', fn () => $this->room?->sale_price),
            'payment_plan_id' => $this->payment_plan_id,
            'payment_plan_name' => $this->whenLoaded('paymentPlan', fn () => $this->paymentPlan?->name),
            'payment_plan' => $this->whenLoaded('paymentPlan', fn () => $this->paymentPlan?->name),
            'sale_price' => $this->sale_price,
            'deposit' => $this->deposit_amount,
            'deposit_amount' => $this->deposit_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => number_format($remainingAmount, 2, '.', ''),
            'contract_total' => $this->sale_price,
            'remaining_balance' => number_format($remainingBalance, 2, '.', ''),
            'interest_percentage' => number_format($interestPercentage, 2, '.', ''),
            'total_installment_amount' => number_format($totalInstallmentAmount, 2, '.', ''),
            'estimated_monthly_payment' => number_format($estimatedMonthlyPayment, 2, '.', ''),
            'payment_type' => $this->payment_type,
            'duration_months' => $this->duration_months,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'billing_day' => $this->billing_day,
            'status' => $this->mapStatusForApi(),
            'remark' => $this->remark,
            'remarks' => $this->remark,
            'rejection_reason' => $this->rejection_reason,
            'contract_id' => $this->contract_id,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'submitted_by' => $this->whenLoaded('submitter', fn () => $this->submitter?->name),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'activated_by' => $this->whenLoaded('activator', fn () => $this->activator?->name),
            'activated_at' => $this->activated_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function mapStatusForApi(): string
    {
        return $this->status === 'pending' ? 'pending_approval' : $this->status;
    }
}
