<?php

namespace App\Services;

use App\Models\ChargeType;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ChargeTypeService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = ChargeType::query();
        $this->applyListQuery($query, $params, ['name', 'slug', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): ChargeType
    {
        return ChargeType::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ChargeType
    {
        $data = $this->ensureSlug($data);

        return ChargeType::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ChargeType $chargeType, array $data): ChargeType
    {
        $data = $this->ensureSlug($data);

        $chargeType->update($data);

        return $chargeType->fresh();
    }

    public function delete(ChargeType $chargeType): void
    {
        $chargeType->delete();
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
