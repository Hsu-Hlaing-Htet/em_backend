<?php

namespace App\Services;

use App\Models\UtilityType;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UtilityTypeService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = UtilityType::query();

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $this->applyListQuery($query, $params, ['name', 'slug', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): UtilityType
    {
        return UtilityType::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UtilityType
    {
        $data = $this->ensureSlug($data);

        return UtilityType::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(UtilityType $utilityType, array $data): UtilityType
    {
        $data = $this->ensureSlug($data);

        $utilityType->update($data);

        return $utilityType->fresh();
    }

    public function delete(UtilityType $utilityType): void
    {
        $utilityType->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ensureSlug(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
