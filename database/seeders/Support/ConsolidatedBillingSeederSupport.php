<?php

namespace Database\Seeders\Support;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ConsolidatedBillingSeederSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function rentLineItem(ChargeType $chargeType, float $amount, Carbon $billingMonth): array
    {
        return [
            'charge_type_id' => $chargeType->id,
            'description' => 'Monthly rent — '.$billingMonth->format('F Y'),
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function installmentLineItem(ChargeType $chargeType, float $amount, Carbon $billingMonth): array
    {
        return [
            'charge_type_id' => $chargeType->id,
            'description' => 'Sale installment — '.$billingMonth->format('F Y'),
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceChargeLineItem(ChargeType $chargeType, float $amount = 15000): array
    {
        return [
            'charge_type_id' => $chargeType->id,
            'description' => 'Building service charge',
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function utilityLineItems(Utility $utility, ChargeType $utilityCharge): array
    {
        $utility->loadMissing('items.utilityType');

        return $utility->items->map(fn ($item) => [
            'charge_type_id' => $utilityCharge->id,
            'description' => $item->utilityType?->name ?? 'Utility',
            'previous_reading' => $item->previous_reading,
            'current_reading' => $item->current_reading,
            'usage' => $item->usage,
            'unit_price' => $item->unit_price,
            'amount' => (float) $item->amount,
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  ...$groups
     * @return list<array<string, mixed>>
     */
    public static function mergeLineItems(array ...$groups): array
    {
        return collect($groups)->flatten(1)->values()->all();
    }

    public static function installmentAmount(Contract $contract): float
    {
        $months = max((int) ($contract->duration_months ?? 1), 1);

        return round((float) $contract->contract_total / $months, 2);
    }

    /**
     * @param  Collection<string, ChargeType>  $chargeTypes
     * @return list<array<string, mixed>>
     */
    public static function buildRentConsolidatedItems(
        Contract $contract,
        Utility $utility,
        Collection $chargeTypes,
        Carbon $billingMonth,
        bool $includeServiceCharge = true,
    ): array {
        $rent = (float) ($contract->room?->rent_price ?? 0);
        $rentCharge = $chargeTypes->get('monthly-rent');
        $utilityCharge = $chargeTypes->get('utility-charges');
        $serviceCharge = $chargeTypes->get('maintenance-fee');

        $items = [];

        if ($rentCharge) {
            $items[] = self::rentLineItem($rentCharge, $rent, $billingMonth);
        }

        if ($utilityCharge) {
            $items = self::mergeLineItems($items, self::utilityLineItems($utility, $utilityCharge));
        }

        if ($includeServiceCharge && $serviceCharge) {
            $items[] = self::serviceChargeLineItem($serviceCharge);
        }

        return $items;
    }

    /**
     * @param  Collection<string, ChargeType>  $chargeTypes
     * @return list<array<string, mixed>>
     */
    public static function buildSaleConsolidatedItems(
        Contract $contract,
        Utility $utility,
        Collection $chargeTypes,
        Carbon $billingMonth,
        bool $includeServiceCharge = true,
    ): array {
        $installmentCharge = $chargeTypes->get('sale-installment');
        $utilityCharge = $chargeTypes->get('utility-charges');
        $serviceCharge = $chargeTypes->get('maintenance-fee');

        $items = [];

        if ($installmentCharge) {
            $items[] = self::installmentLineItem(
                $installmentCharge,
                self::installmentAmount($contract),
                $billingMonth,
            );
        }

        if ($utilityCharge) {
            $items = self::mergeLineItems($items, self::utilityLineItems($utility, $utilityCharge));
        }

        if ($includeServiceCharge && $serviceCharge) {
            $items[] = self::serviceChargeLineItem($serviceCharge);
        }

        return $items;
    }
}
