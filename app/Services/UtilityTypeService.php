<?php

namespace App\Services;

use App\Models\UtilityType;
use App\Services\Concerns\AppliesListQuery;
use App\Services\Concerns\GuardsDeletion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UtilityTypeService
{
    use AppliesListQuery, GuardsDeletion;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = UtilityType::query();
        $this->applyStatusFilter($query, $params);
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
        return UtilityType::query()->create($this->prepareData($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(UtilityType $utilityType, array $data): UtilityType
    {
        $utilityType->update($this->prepareData($data, $utilityType));

        return $utilityType->fresh();
    }

    public function delete(UtilityType $utilityType): void
    {
        $this->guardNoChildren(
            $utilityType,
            'utilityRates',
            'Cannot delete this utility type because it has utility rates. Remove all utility rates first.',
        );

        $utilityType->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?UtilityType $utilityType = null): array
    {
        unset($data['slug']);

        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
