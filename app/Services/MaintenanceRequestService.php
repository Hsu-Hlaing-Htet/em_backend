<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class MaintenanceRequestService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = MaintenanceRequest::query()->with(['room.building', 'user']);

        $this->applyStatusFilter($query, $params);

        if (! empty($params['search'])) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('room', fn (Builder $roomQuery) => $roomQuery->where('room_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): MaintenanceRequest
    {
        return MaintenanceRequest::query()
            ->with(['room.building', 'user.profile', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MaintenanceRequest
    {
        return MaintenanceRequest::query()->create([
            ...$data,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ])->load(['room.building', 'user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceRequest $maintenanceRequest, array $data): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'pending') {
            throw new InvalidArgumentException('Only pending maintenance requests can be updated.');
        }

        $maintenanceRequest->update($data);

        return $maintenanceRequest->fresh(['room.building', 'user']);
    }

    public function delete(MaintenanceRequest $maintenanceRequest): void
    {
        if ($maintenanceRequest->status !== 'pending') {
            throw new InvalidArgumentException('Only pending maintenance requests can be deleted.');
        }

        $maintenanceRequest->delete();
    }

    public function start(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'pending') {
            throw new InvalidArgumentException('Only pending maintenance requests can be started.');
        }

        $maintenanceRequest->update([
            'status' => 'in_progress',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $maintenanceRequest->fresh(['room.building', 'user']);
    }

    public function complete(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'in_progress') {
            throw new InvalidArgumentException('Only in-progress maintenance requests can be completed.');
        }

        $maintenanceRequest->update([
            'status' => 'completed',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $maintenanceRequest->fresh(['room.building', 'user']);
    }

    public function reject(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        if (! in_array($maintenanceRequest->status, ['pending', 'in_progress'], true)) {
            throw new InvalidArgumentException('Only pending or in-progress maintenance requests can be rejected.');
        }

        $maintenanceRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $maintenanceRequest->fresh(['room.building', 'user']);
    }
}
