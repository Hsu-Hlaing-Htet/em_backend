<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuildingService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Building::query()->withCount([
            'rooms as rooms_count' => fn ($query) => $query->withTrashed(),
        ]);
        $this->applyListQuery($query, $params, ['building_name', 'location', 'description']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Building
    {
        return Building::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Building
    {
        return Building::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Building $building, array $data): Building
    {
        $building->update($data);

        return $building->fresh();
    }

    public function delete(Building $building): void
    {
        if ($building->rooms()->withTrashed()->exists()) {
            $this->archive($building);

            return;
        }

        $building->delete();
    }

    public function archive(Building $building): Building
    {
        $building->update(['status' => Building::STATUS_ARCHIVED]);

        return $building->fresh();
    }

    public function activate(Building $building): Building
    {
        $building->update(['status' => Building::STATUS_ACTIVE]);

        return $building->fresh();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($ids): int {
            $buildings = Building::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($buildings->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'One or more selected buildings could not be found.',
                ]);
            }

            $blockedBuildingNames = Room::withTrashed()
                ->whereIn('building_id', $ids)
                ->with('building:id,building_name')
                ->get()
                ->pluck('building.building_name')
                ->filter()
                ->unique()
                ->values();

            if ($blockedBuildingNames->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'Cannot delete buildings that still have rooms: '.$blockedBuildingNames->join(', '),
                ]);
            }

            $buildings->each->delete();

            return $buildings->count();
        });
    }
}
