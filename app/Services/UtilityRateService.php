<?php

namespace App\Services;

use App\Models\UtilityRate;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UtilityRateService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = UtilityRate::query()->with('utilityType');

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('unit_price', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhere('effective_date', 'like', '%'.$search.'%')
                    ->orWhereHas('utilityType', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%'));
            });
        }

        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): UtilityRate
    {
        return UtilityRate::query()
            ->with('utilityType')
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UtilityRate
    {
        return UtilityRate::query()
            ->create($data)
            ->load('utilityType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(UtilityRate $utilityRate, array $data): UtilityRate
    {
        $utilityRate->update($data);

        return $utilityRate->fresh()->load('utilityType');
    }

    public function delete(UtilityRate $utilityRate): void
    {
        $utilityRate->delete();
    }
}
