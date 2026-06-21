<?php

namespace App\Services;

use App\Models\Building;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildingService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Building::query();
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
        $building->delete();
    }
}
