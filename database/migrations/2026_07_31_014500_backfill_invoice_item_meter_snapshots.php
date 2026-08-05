<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $invoiceItems = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNotNull('invoices.utility_id')
            ->where(function ($query): void {
                $query->whereNull('invoice_items.previous_reading')
                    ->orWhereNull('invoice_items.current_reading')
                    ->orWhereNull('invoice_items.usage')
                    ->orWhereNull('invoice_items.unit_price');
            })
            ->orderBy('invoice_items.invoice_id')
            ->orderBy('invoice_items.id')
            ->select([
                'invoice_items.id',
                'invoice_items.invoice_id',
                'invoice_items.description',
                'invoice_items.amount',
                'invoices.utility_id',
            ])
            ->get();

        if ($invoiceItems->isEmpty()) {
            return;
        }

        $utilityItemsByUtility = DB::table('utility_items')
            ->leftJoin('utility_types', 'utility_types.id', '=', 'utility_items.utility_type_id')
            ->whereIn('utility_items.utility_id', $invoiceItems->pluck('utility_id')->unique()->all())
            ->orderBy('utility_items.id')
            ->select([
                'utility_items.id',
                'utility_items.utility_id',
                'utility_items.previous_reading',
                'utility_items.current_reading',
                'utility_items.usage',
                'utility_items.unit_price',
                'utility_items.amount',
                'utility_types.name as utility_type_name',
            ])
            ->get()
            ->groupBy('utility_id');

        $invoiceItemGroups = $invoiceItems->groupBy('invoice_id');

        foreach ($invoiceItemGroups as $invoiceId => $items) {
            $utilityId = $items->first()->utility_id;
            $utilityItems = collect($utilityItemsByUtility->get($utilityId, []))->values();

            if ($utilityItems->isEmpty()) {
                continue;
            }

            $usedUtilityItemIds = [];

            foreach ($items->values() as $index => $invoiceItem) {
                $matched = $this->matchUtilityItem($invoiceItem, $utilityItems, $usedUtilityItemIds, $index);

                if (! $matched) {
                    continue;
                }

                $usedUtilityItemIds[] = $matched->id;

                DB::table('invoice_items')
                    ->where('id', $invoiceItem->id)
                    ->update([
                        'previous_reading' => $matched->previous_reading,
                        'current_reading' => $matched->current_reading,
                        'usage' => $matched->usage,
                        'unit_price' => $matched->unit_price,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Snapshot backfill is not safely reversible.
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $utilityItems
     * @param  list<int>  $usedUtilityItemIds
     */
    private function matchUtilityItem(object $invoiceItem, $utilityItems, array $usedUtilityItemIds, int $index): ?object
    {
        $available = $utilityItems->reject(fn ($item) => in_array($item->id, $usedUtilityItemIds, true))->values();

        if ($available->isEmpty()) {
            return null;
        }

        $typeName = $this->extractUtilityTypeName((string) $invoiceItem->description);

        if ($typeName) {
            $byType = $available->first(function ($item) use ($typeName) {
                return $item->utility_type_name
                    && strcasecmp((string) $item->utility_type_name, $typeName) === 0;
            });

            if ($byType) {
                return $byType;
            }
        }

        $byAmount = $available->first(function ($item) use ($invoiceItem) {
            return (string) $item->amount === (string) $invoiceItem->amount;
        });

        if ($byAmount) {
            return $byAmount;
        }

        return $utilityItems->get($index);
    }

    private function extractUtilityTypeName(string $description): ?string
    {
        if ($description !== '' && str_contains($description, '—')) {
            $name = trim(Str::after($description, '—'));

            return $name !== '' ? $name : null;
        }

        $knownTypes = ['Electricity', 'Water', 'Gas'];

        foreach ($knownTypes as $type) {
            if (strcasecmp($description, $type) === 0) {
                return $type;
            }
        }

        return null;
    }
};
