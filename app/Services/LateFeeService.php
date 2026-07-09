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
        $query = LateFee::query()->with(['creator', 'approver']);
        $this->applyListQuery($query, $params, ['name', 'type', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): LateFee
    {
        return LateFee::query()->with(['creator', 'approver'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LateFee
    {
        if (! isset($data['created_by'])) {
            $data['created_by'] = auth()->id();
        }

        return LateFee::query()->create($data)->load(['creator', 'approver']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LateFee $lateFee, array $data): LateFee
    {
        $lateFee->update($data);

        return $lateFee->fresh(['creator', 'approver']);
    }

    public function delete(LateFee $lateFee): void
    {
        $lateFee->delete();
    }
}
