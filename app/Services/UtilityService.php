<?php

namespace App\Services;

use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UtilityService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Utility::query()
            ->with(['room.building', 'items.utilityType', 'creator'])
            ->latest('billing_month')
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('room', fn ($roomQuery) => $roomQuery
                    ->where('room_number', 'like', "%{$search}%")
                    ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery
                        ->where('name', 'like', "%{$search}%")));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Utility
    {
        return Utility::query()
            ->with(['room.building', 'items.utilityType', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<int>  $roomIds
     * @return array{unit_price: float, rooms: array<int, array{room_id: int, previous_reading: float, has_previous_data: bool}>}
     */
    public function formData(int $utilityTypeId, string $billingMonth, array $roomIds): array
    {
        $month = Carbon::parse($billingMonth)->startOfMonth();
        $unitPrice = $this->activeRateForType($utilityTypeId);

        $rooms = collect($roomIds)->map(function (int $roomId) use ($utilityTypeId, $month) {
            $previous = $this->previousReadingData($roomId, $utilityTypeId, $month);

            return [
                'room_id' => $roomId,
                'previous_reading' => $previous['previous_reading'],
                'has_previous_data' => $previous['has_previous_data'],
            ];
        })->values()->all();

        return [
            'unit_price' => $unitPrice,
            'rooms' => $rooms,
        ];
    }

    /**
     * @return array{previous_reading: float, has_previous_data: bool}
     */
    public function previousReadingData(int $roomId, int $utilityTypeId, Carbon $billingMonth): array
    {
        $previousMonth = $billingMonth->copy()->subMonth()->startOfMonth();

        $utility = Utility::query()
            ->where('room_id', $roomId)
            ->whereDate('billing_month', $previousMonth)
            ->whereHas('items', fn ($query) => $query->where('utility_type_id', $utilityTypeId))
            ->with(['items' => fn ($query) => $query->where('utility_type_id', $utilityTypeId)])
            ->first();

        if (! $utility || $utility->items->isEmpty()) {
            return [
                'previous_reading' => 0.0,
                'has_previous_data' => false,
            ];
        }

        return [
            'previous_reading' => (float) $utility->items->first()->current_reading,
            'has_previous_data' => true,
        ];
    }

    public function activeRateForType(int $utilityTypeId): float
    {
        $rate = UtilityRate::query()
            ->where('utility_type_id', $utilityTypeId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $rate) {
            throw new InvalidArgumentException('No active utility rate found for the selected utility type.');
        }

        return (float) $rate->unit_price;
    }

    public function previousReading(int $roomId, int $utilityTypeId, Carbon $billingMonth): float
    {
        return $this->previousReadingData($roomId, $utilityTypeId, $billingMonth)['previous_reading'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, Utility>
     */
    public function createBatch(array $data): array
    {
        $billingMonth = Carbon::parse($data['billing_month'])->startOfMonth()->toDateString();
        $utilityTypeId = (int) $data['utility_type_id'];
        $defaultUnitPrice = $this->activeRateForType($utilityTypeId);

        return DB::transaction(function () use ($data, $billingMonth, $utilityTypeId, $defaultUnitPrice) {
            $created = [];

            foreach ($data['entries'] as $entry) {
                $roomId = (int) $entry['room_id'];
                $currentReading = (float) $entry['current_reading'];
                $previousReading = array_key_exists('previous_reading', $entry) && $entry['previous_reading'] !== null
                    ? (float) $entry['previous_reading']
                    : $this->previousReading($roomId, $utilityTypeId, Carbon::parse($billingMonth));
                $unitPrice = array_key_exists('unit_price', $entry) && $entry['unit_price'] !== null
                    ? (float) $entry['unit_price']
                    : $defaultUnitPrice;

                if ($currentReading < $previousReading) {
                    throw new InvalidArgumentException('Current reading cannot be less than previous reading.');
                }

                $usage = $currentReading - $previousReading;
                $amount = round($usage * $unitPrice, 2);

                if (Utility::query()
                    ->where('room_id', $roomId)
                    ->whereDate('billing_month', $billingMonth)
                    ->whereHas('items', fn ($query) => $query->where('utility_type_id', $utilityTypeId))
                    ->exists()) {
                    throw new InvalidArgumentException('A utility bill already exists for one of the selected rooms in this billing month.');
                }

                $utility = Utility::query()->create([
                    'room_id' => $roomId,
                    'billing_month' => $billingMonth,
                    'status' => 'pending',
                    'total_amount' => $amount,
                    'created_by' => Auth::id(),
                ]);

                UtilityItem::query()->create([
                    'utility_id' => $utility->id,
                    'utility_type_id' => $utilityTypeId,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);

                $created[] = $utility->fresh(['room.building', 'items.utilityType', 'creator']);
            }

            return $created;
        });
    }

    public function create(array $data): Utility
    {
        return DB::transaction(function () use ($data) {
            $utility = Utility::query()->create([
                'room_id' => $data['room_id'],
                'billing_month' => Carbon::parse($data['billing_month'])->startOfMonth()->toDateString(),
                'status' => 'draft',
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);

            $total = $this->syncItems($utility, $data['utility_items'] ?? $data['items'] ?? []);

            $utility->update(['total_amount' => $total]);

            return $utility->fresh(['room.building', 'items.utilityType', 'creator']);
        });
    }

    public function update(Utility $utility, array $data): Utility
    {
        if (! in_array($utility->status, ['draft'], true)) {
            throw new InvalidArgumentException('Only draft utility bills can be edited.');
        }

        return DB::transaction(function () use ($utility, $data) {
            $utility->update([
                'room_id' => $data['room_id'],
                'billing_month' => Carbon::parse($data['billing_month'])->startOfMonth()->toDateString(),
            ]);

            $total = $this->syncItems($utility, $data['utility_items'] ?? $data['items'] ?? []);
            $utility->update(['total_amount' => $total]);

            return $utility->fresh(['room.building', 'items.utilityType', 'creator']);
        });
    }

    public function submit(Utility $utility): Utility
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be submitted.');
        }

        if ($utility->items()->count() === 0) {
            throw new InvalidArgumentException('Add at least one utility item before submitting.');
        }

        $utility->update(['status' => 'pending']);

        return $utility->fresh(['room.building', 'items.utilityType', 'creator']);
    }

    public function approve(Utility $utility): Utility
    {
        if ($utility->status !== 'pending') {
            throw new InvalidArgumentException('Only pending utility bills can be approved.');
        }

        $utility = $this->approvalService->approve($utility);
        $this->invoiceService->generateFromUtility($utility);

        return $utility->fresh(['room.building', 'items.utilityType', 'creator', 'approver']);
    }

    public function reject(Utility $utility, ?string $reason = null): Utility
    {
        if ($utility->status !== 'pending') {
            throw new InvalidArgumentException('Only pending utility bills can be rejected.');
        }

        return $this->approvalService->reject($utility, $reason)
            ->fresh(['room.building', 'items.utilityType', 'creator', 'approver']);
    }

    public function delete(Utility $utility): void
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be deleted.');
        }

        $utility->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Utility $utility, array $items): float
    {
        $utility->items()->delete();

        $total = 0.0;

        foreach ($items as $item) {
            $previous = (float) ($item['previous_reading'] ?? 0);
            $current = (float) ($item['current_reading'] ?? 0);

            if ($current < $previous) {
                throw new InvalidArgumentException('Current reading cannot be less than previous reading.');
            }

            $usage = $current - $previous;
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($usage * $unitPrice, 2);

            UtilityItem::query()->create([
                'utility_id' => $utility->id,
                'utility_type_id' => $item['utility_type_id'],
                'previous_reading' => $previous,
                'current_reading' => $current,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);

            $total += $amount;
        }

        return round($total, 2);
    }
}
