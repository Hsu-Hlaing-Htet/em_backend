<?php

namespace App\Services;

use App\Models\LateFee;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LateFeeService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = LateFee::query();
        $this->applyListQuery($query, $params, ['name', 'type', 'value', 'per', 'grace_days', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): LateFee
    {
        return LateFee::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LateFee
    {
        return LateFee::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LateFee $lateFee, array $data): LateFee
    {
        $lateFee->update($data);

        return $lateFee->fresh();
    }

    public function delete(LateFee $lateFee): void
    {
        $lateFee->delete();
    }
}
