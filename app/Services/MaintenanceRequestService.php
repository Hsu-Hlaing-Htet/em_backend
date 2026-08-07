<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\MaintenanceRequest;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                    ->orWhere('category', 'like', '%'.$search.'%')
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
            'rejection_reason' => null,
            'resolution_note' => null,
            'created_by' => Auth::id(),
        ])->load(['room.building', 'user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceRequest $maintenanceRequest, array $data): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenanceRequest, $data): MaintenanceRequest {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending maintenance requests can be updated.');
            }

            $locked->update($data);

            return $locked->fresh(['room.building', 'user']);
        });
    }

    public function delete(MaintenanceRequest $maintenanceRequest): void
    {
        DB::transaction(function () use ($maintenanceRequest): void {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending maintenance requests can be deleted.');
            }

            $locked->delete();
        });
    }

    public function start(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenanceRequest): MaintenanceRequest {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending maintenance requests can be started.');
            }

            $locked->update([
                'status' => 'in_progress',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => null,
                'resolution_note' => null,
            ]);

            return $locked->fresh(['room.building', 'user', 'approver']);
        });
    }

    public function complete(MaintenanceRequest $maintenanceRequest, ?string $resolutionNote = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenanceRequest, $resolutionNote): MaintenanceRequest {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw new ConcurrentConflictException('Only in-progress maintenance requests can be completed.');
            }

            $locked->update([
                'status' => 'completed',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'resolution_note' => $resolutionNote,
                'rejection_reason' => null,
            ]);

            return $locked->fresh(['room.building', 'user', 'approver']);
        });
    }

    public function reject(MaintenanceRequest $maintenanceRequest, string $rejectionReason): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenanceRequest, $rejectionReason): MaintenanceRequest {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['pending', 'in_progress'], true)) {
                throw new ConcurrentConflictException('Only pending or in-progress maintenance requests can be rejected.');
            }

            $locked->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $rejectionReason,
                'resolution_note' => null,
            ]);

            return $locked->fresh(['room.building', 'user', 'approver']);
        });
    }
}
