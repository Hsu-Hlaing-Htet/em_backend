<?php

namespace App\Services;

use App\Models\Utility;
use App\Models\UtilityItem;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UtilityService
{
    use AppliesListQuery;

    public function __construct(private readonly ApprovalService $approvalService) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Utility::query()->with(['room.building', 'creator', 'approver']);

        $this->applyStatusFilter($query, $params);

        if (! empty($params['search'])) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('room', fn (Builder $roomQuery) => $roomQuery->where('room_number', 'like', '%'.$search.'%'))
                    ->orWhere('billing_month', 'like', '%'.$search.'%');
            });
        }

        $this->applyListQuery($query, $params, []);

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
        return DB::transaction(function () use ($data): Utility {
            $items = $data['utility_items'] ?? [];
            unset($data['utility_items']);

            $utility = Utility::query()->create([
                ...$data,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'total_amount' => 0,
            ]);

            $this->syncItems($utility, $items);

            return $utility->fresh(['room.building', 'items.utilityType', 'creator', 'approver']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Utility $utility, array $data): Utility
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be updated.');
        }

        return DB::transaction(function () use ($utility, $data): Utility {
            $items = $data['utility_items'] ?? null;
            unset($data['utility_items']);

            $utility->update($data);

            if ($items !== null) {
                $this->syncItems($utility, $items);
            }

            return $utility->fresh(['room.building', 'items.utilityType', 'creator', 'approver']);
        });
    }

    public function delete(Utility $utility): void
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be deleted.');
        }

        $utility->delete();
    }

    public function submit(Utility $utility): Utility
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be submitted.');
        }

        $utility->update(['status' => 'pending']);

        return $utility->fresh(['room.building', 'items.utilityType', 'creator', 'approver']);
    }

    public function approve(Utility $utility): Utility
    {
        if ($utility->status !== 'pending') {
            throw new InvalidArgumentException('Only pending utility bills can be approved.');
        }

        $utility = $this->approvalService->approve($utility);

        return $utility->load(['room.building', 'items.utilityType', 'creator', 'approver']);
    }

    public function reject(Utility $utility): Utility
    {
        if (! in_array($utility->status, ['draft', 'pending'], true)) {
            throw new InvalidArgumentException('Only draft or pending utility bills can be rejected.');
        }

        $utility = $this->approvalService->reject($utility);

        return $utility->load(['room.building', 'items.utilityType', 'creator', 'approver']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Utility $utility, array $items): void
    {
        $keptIds = [];

        foreach ($items as $itemData) {
            $usage = max(0, (float) ($itemData['current_reading'] ?? 0) - (float) ($itemData['previous_reading'] ?? 0));
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);
            $amount = round($usage * $unitPrice, 2);

            $payload = [
                'utility_type_id' => $itemData['utility_type_id'],
                'previous_reading' => $itemData['previous_reading'] ?? 0,
                'current_reading' => $itemData['current_reading'] ?? 0,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];

            if (! empty($itemData['id'])) {
                $item = UtilityItem::query()
                    ->where('utility_id', $utility->id)
                    ->findOrFail($itemData['id']);
                $item->update($payload);
                $keptIds[] = $item->id;
            } else {
                $item = $utility->items()->create($payload);
                $keptIds[] = $item->id;
            }
        }

        $utility->items()->whereNotIn('id', $keptIds)->delete();
        $utility->update(['total_amount' => $utility->items()->sum('amount')]);
    }
}
