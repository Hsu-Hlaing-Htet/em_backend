<?php

namespace App\Services;

use App\Models\MaintenanceCategory;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MaintenanceCategoryService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = MaintenanceCategory::query();
        $this->applyStatusFilter($query, $params);
        $this->applyListQuery($query, $params, ['name', 'slug', 'status'], AdminListSorts::namedSettings());

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @return Collection<int, MaintenanceCategory>
     */
    public function active(): Collection
    {
        return MaintenanceCategory::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Admin form options (active categories only).
     *
     * @return Collection<int, MaintenanceCategory>
     */
    public function options(): Collection
    {
        return $this->active();
    }

    public function find(int $id): MaintenanceCategory
    {
        return MaintenanceCategory::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MaintenanceCategory
    {
        return MaintenanceCategory::query()->create($this->prepareData($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceCategory $maintenanceCategory, array $data): MaintenanceCategory
    {
        $maintenanceCategory->update($this->prepareData($data));

        return $maintenanceCategory->fresh();
    }

    public function delete(MaintenanceCategory $maintenanceCategory): void
    {
        $maintenanceCategory->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        unset($data['slug']);

        if (! empty($data['name'])) {
            $data['slug'] = Str::slug((string) $data['name']);
        }

        return $data;
    }
}
