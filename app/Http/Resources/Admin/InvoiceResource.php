<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidAmount = $this->resolvePaidAmount();
        $totalDue = round((float) $this->total_amount + (float) $this->late_fee, 2);
        $remainingBalance = max(round($totalDue - $paidAmount, 2), 0);

        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'utility_id' => $this->utility_id,
            'invoice_number' => $this->invoice_number,
            'type' => $this->type,
            'invoice_type' => $this->resolveInvoiceType(),
            'status' => $this->status,
            'payment_status' => $this->resolvePaymentStatus(),
            'issued_date' => $this->issued_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'billing_period' => $this->resolveBillingPeriod(),
            'late_fee' => $this->late_fee,
            'total_amount' => $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_balance' => $remainingBalance,
            'property_unit' => $this->resolvePropertyUnit(),
            'payment_method_name' => $this->resolvePaymentMethodName(),
            'notes' => $this->resolveNotes(),
            'contract' => $this->whenLoaded('contract', fn () => new ContractResource($this->contract)),
            'items' => $this->whenLoaded('items', function () {
                $this->items->each(function ($item): void {
                    $item->setRelation('invoice', $this->resource);

                    if ($this->relationLoaded('utility')) {
                        $item->invoice->setRelation('utility', $this->utility);
                    }

                    if ($this->relationLoaded('items')) {
                        $item->invoice->setRelation('items', $this->items);
                    }
                });

                return InvoiceItemResource::collection($this->items);
            }),
            'customer_name' => $this->whenLoaded('contract', fn () => $this->contract?->user?->name),
            'customer_email' => $this->whenLoaded('contract', fn () => $this->contract?->user?->email),
            'customer_phone' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('user'),
                fn () => $this->contract?->user?->profile?->phone,
            ),
            'customer_nrc' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('user'),
                fn () => $this->contract?->user?->profile?->nrc,
            ),
            'building_name' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->building?->building_name,
            ),
            'room_number' => $this->when(
                $this->relationLoaded('contract') && $this->contract?->relationLoaded('room'),
                fn () => $this->contract?->room?->room_number,
            ),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by' => $this->when(
                $this->approved_by,
                fn () => [
                    'id' => $this->approver?->id ?? $this->approved_by,
                    'name' => $this->relationLoaded('approver') ? $this->approver?->name : null,
                ],
            ),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolvePaidAmount(): float
    {
        if (! $this->relationLoaded('payments')) {
            return 0.0;
        }

        return (float) $this->payments
            ->whereIn('status', ['approved', 'completed'])
            ->sum(fn ($payment) => (float) ($payment->amount ?? 0));
    }

    private function resolvePaymentStatus(): string
    {
        return match ($this->status) {
            'paid' => 'paid',
            'partial' => 'partial',
            'overdue' => 'overdue',
            default => 'unpaid',
        };
    }

    private function resolveInvoiceType(): string
    {
        if ($this->type === 'rent') {
            return 'rent';
        }

        if ($this->type === 'utility') {
            return 'utility';
        }

        if ($this->relationLoaded('items')) {
            $hasMaintenance = $this->items
                ->contains(fn ($item) => $item->relationLoaded('chargeType')
                    && $item->chargeType?->slug === 'maintenance-fee');

            if ($hasMaintenance) {
                return 'maintenance';
            }
        }

        return 'other';
    }

    private function resolveBillingPeriod(): ?string
    {
        if ($this->relationLoaded('utility') && $this->utility?->billing_month) {
            return Carbon::parse($this->utility->billing_month)->format('F Y');
        }

        if ($this->issued_date) {
            return $this->issued_date->format('F Y');
        }

        return null;
    }

    private function resolvePropertyUnit(): ?string
    {
        $contract = $this->relationLoaded('contract') ? $this->contract : null;
        $room = $contract?->relationLoaded('room') ? $contract->room : null;
        $buildingName = $room?->relationLoaded('building') ? $room->building?->building_name : null;
        $roomNumber = $room?->room_number;

        if ($buildingName && $roomNumber) {
            return "{$buildingName} · {$roomNumber}";
        }

        return $buildingName ?: $roomNumber;
    }

    private function resolvePaymentMethodName(): ?string
    {
        if (! $this->relationLoaded('payments')) {
            return null;
        }

        $payment = $this->payments
            ->whereIn('status', ['approved', 'completed'])
            ->sortByDesc(fn ($item) => $item->payment_date)
            ->first();

        if (! $payment) {
            return null;
        }

        if ($payment->relationLoaded('paymentMethod')) {
            return $payment->paymentMethod?->name;
        }

        return null;
    }

    private function resolveNotes(): ?string
    {
        if (! $this->relationLoaded('items') || $this->items->isEmpty()) {
            return null;
        }

        return $this->items
            ->pluck('description')
            ->filter()
            ->unique()
            ->implode(' · ');
    }
}
