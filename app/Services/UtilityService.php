<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UtilityService
{
    use AppliesListQuery;

    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Utility::query()->with(['room', 'items.utilityType']);

        $this->applyListQuery($query, $params, []);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        if (! empty($params['billing_month'])) {
            $query->whereDate('billing_month', $params['billing_month']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Utility
    {
        return Utility::query()
            ->with(['room.building', 'items.utilityType', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Utility
    {
        $items = $data['utility_items'] ?? [];
        unset($data['utility_items']);

        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        return DB::transaction(function () use ($data, $items): Utility {
            $utility = Utility::query()->create($data);

            if ($items !== []) {
                $this->syncItems($utility, $items);
            }

            return $this->recalculateTotal($utility)->load(['room', 'items.utilityType']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Utility $utility, array $data): Utility
    {
        $items = $data['utility_items'] ?? null;
        unset($data['utility_items']);

        return DB::transaction(function () use ($utility, $data, $items): Utility {
            $utility->update($data);

            if (is_array($items)) {
                $this->syncItems($utility, $items);
            }

            return $this->recalculateTotal($utility)->load(['room', 'items.utilityType']);
        });
    }

    public function delete(Utility $utility): void
    {
        $utility->delete();
    }

    public function submit(Utility $utility): Utility
    {
        return $this->approvalService->submit($utility)
            ->load(['room', 'items.utilityType']);
    }

    public function approve(Utility $utility): Utility
    {
        $utility = $this->approvalService->approve($utility);
        $this->invoiceService->mergeUtilityCharges($utility);

        return $utility->fresh(['room', 'items.utilityType', 'approver']);
    }

    public function reject(Utility $utility): Utility
    {
        return $this->approvalService->reject($utility)
            ->load(['room', 'items.utilityType', 'approver']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncItems(Utility $utility, array $items): void
    {
        $itemIds = [];

        foreach ($items as $itemData) {
            $calculated = $this->calculateItemAmounts($itemData);

            if (! empty($itemData['id'])) {
                $item = UtilityItem::query()
                    ->where('utility_id', $utility->id)
                    ->findOrFail($itemData['id']);
                $item->update($calculated);
                $itemIds[] = $item->id;
            } else {
                $item = $utility->items()->create($calculated);
                $itemIds[] = $item->id;
            }
        }

        $utility->items()->whereNotIn('id', $itemIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function calculateItemAmounts(array $data): array
    {
        $previous = (float) ($data['previous_reading'] ?? 0);
        $current = (float) ($data['current_reading'] ?? 0);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $usage = max(0, $current - $previous);

        return [
            'utility_type_id' => $data['utility_type_id'],
            'previous_reading' => $previous,
            'current_reading' => $current,
            'usage' => $usage,
            'unit_price' => $unitPrice,
            'amount' => round($usage * $unitPrice, 2),
        ];
    }

    public function recalculateTotal(Utility $utility): Utility
    {
        $total = $utility->items()->sum('amount');
        $utility->update(['total_amount' => $total]);

        return $utility->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(Utility $utility, array $data): UtilityItem
    {
        $item = $utility->items()->create($this->calculateItemAmounts($data));
        $this->recalculateTotal($utility);

        return $item->load('utilityType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(UtilityItem $item, array $data): UtilityItem
    {
        $merged = [
            'utility_type_id' => $data['utility_type_id'] ?? $item->utility_type_id,
            'previous_reading' => $data['previous_reading'] ?? $item->previous_reading,
            'current_reading' => $data['current_reading'] ?? $item->current_reading,
            'unit_price' => $data['unit_price'] ?? $item->unit_price,
        ];

        $item->update($this->calculateItemAmounts($merged));
        $this->recalculateTotal($item->utility);

        return $item->fresh('utilityType');
    }

    public function deleteItem(UtilityItem $item): void
    {
        $utility = $item->utility;
        $item->delete();
        $this->recalculateTotal($utility);
    }
}
