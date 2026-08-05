<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Models\InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meter = $this->resolveMeterSnapshot();

        return [
            'id' => $this->id,
            'charge_type_id' => $this->charge_type_id,
            'charge_type_name' => $this->whenLoaded('chargeType', fn () => $this->chargeType?->name),
            'charge_type_slug' => $this->whenLoaded('chargeType', fn () => $this->chargeType?->slug),
            'description' => $this->resolveLineDescription($meter),
            'previous_reading' => $meter['previous_reading'],
            'current_reading' => $meter['current_reading'],
            'usage' => $meter['usage'],
            'unit_price' => $meter['unit_price'],
            'is_metered' => $meter['is_metered'],
            'amount' => $this->amount,
        ];
    }

    /**
     * @return array{
     *     is_metered: bool,
     *     previous_reading: float|string|null,
     *     current_reading: float|string|null,
     *     usage: float|string|null,
     *     unit_price: float|string|null,
     *     utility_type_name: string|null
     * }
     */
    private function resolveMeterSnapshot(): array
    {
        if ($this->resource->isMetered()) {
            return [
                'is_metered' => true,
                'previous_reading' => $this->previous_reading,
                'current_reading' => $this->current_reading,
                'usage' => $this->usage,
                'unit_price' => $this->unit_price,
                'utility_type_name' => $this->extractUtilityTypeFromDescription(),
            ];
        }

        $utilityItem = $this->matchUtilityItem();

        if ($utilityItem) {
            return [
                'is_metered' => true,
                'previous_reading' => $utilityItem->previous_reading,
                'current_reading' => $utilityItem->current_reading,
                'usage' => $utilityItem->usage,
                'unit_price' => $utilityItem->unit_price,
                'utility_type_name' => $utilityItem->utilityType?->name,
            ];
        }

        return [
            'is_metered' => false,
            'previous_reading' => null,
            'current_reading' => null,
            'usage' => null,
            'unit_price' => $this->unit_price,
            'utility_type_name' => null,
        ];
    }

    /**
     * @param  array{
     *     is_metered: bool,
     *     previous_reading: float|string|null,
     *     current_reading: float|string|null,
     *     usage: float|string|null,
     *     unit_price: float|string|null,
     *     utility_type_name: string|null
     * }  $meter
     */
    private function resolveLineDescription(array $meter): string
    {
        if (! empty($meter['utility_type_name'])) {
            return $meter['utility_type_name'];
        }

        $slug = $this->relationLoaded('chargeType') ? $this->chargeType?->slug : null;

        if ($slug === 'monthly-rent') {
            return 'Rent';
        }

        if ($slug === 'sale-installment') {
            return $this->chargeType?->name ?: 'Sale';
        }

        $fromDescription = $this->extractUtilityTypeFromDescription();

        if ($fromDescription) {
            return $fromDescription;
        }

        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

        if ($invoice && $invoice->type === 'rent' && ! $invoice->utility_id) {
            return 'Rent';
        }

        return $this->description;
    }

    private function extractUtilityTypeFromDescription(): ?string
    {
        $description = (string) $this->description;

        if ($description !== '' && str_contains($description, '—')) {
            $name = trim(Str::after($description, '—'));

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function matchUtilityItem(): mixed
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

        if (! $invoice) {
            return null;
        }

        if (! $invoice->relationLoaded('utility') && $invoice->utility_id) {
            $invoice->load('utility.items.utilityType');
        }

        if (! $invoice->utility) {
            return null;
        }

        $utility = $invoice->utility;

        if (! $utility->relationLoaded('items')) {
            $utility->load('items.utilityType');
        }

        $utilityItems = $utility->items;

        if ($utilityItems->isEmpty()) {
            return null;
        }

        $typeFromDescription = $this->extractUtilityTypeFromDescription();

        if ($typeFromDescription) {
            $matched = $utilityItems->first(function ($item) use ($typeFromDescription) {
                $typeName = $item->relationLoaded('utilityType')
                    ? $item->utilityType?->name
                    : null;

                if (! $typeName && ! $item->relationLoaded('utilityType')) {
                    $item->load('utilityType');
                    $typeName = $item->utilityType?->name;
                }

                return $typeName
                    && strcasecmp((string) $typeName, $typeFromDescription) === 0;
            });

            if ($matched) {
                return $matched;
            }
        }

        $invoiceItems = $invoice->relationLoaded('items') ? $invoice->items : null;

        if ($invoiceItems) {
            $index = $invoiceItems->values()->search(fn ($item) => $item->id === $this->id);

            if ($index !== false && $utilityItems->values()->has($index)) {
                return $utilityItems->values()->get($index);
            }
        }

        return $utilityItems->firstWhere('amount', $this->amount);
    }
}
