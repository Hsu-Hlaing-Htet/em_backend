<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MaintenanceRequestService
{
    use AppliesListQuery;

    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = MaintenanceRequest::query()->with(['room', 'user']);

        $this->applyListQuery($query, $params, ['title', 'description']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        if (! empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): MaintenanceRequest
    {
        return MaintenanceRequest::query()
            ->with(['room.building', 'user', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MaintenanceRequest
    {
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'pending';

        return MaintenanceRequest::query()->create($data)->load(['room', 'user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceRequest $maintenanceRequest, array $data): MaintenanceRequest
    {
        $maintenanceRequest->update($data);

        return $maintenanceRequest->fresh(['room', 'user']);
    }

    public function delete(MaintenanceRequest $maintenanceRequest): void
    {
        $maintenanceRequest->delete();
    }

    public function start(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        return $this->approvalService->transition($maintenanceRequest, 'in_progress', ['pending'])
            ->load(['room', 'user']);
    }

    public function complete(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        $maintenanceRequest = $this->approvalService->transition($maintenanceRequest, 'completed', ['in_progress']);
        $maintenanceRequest->update([
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $maintenanceRequest->fresh(['room', 'user', 'approver']);
    }

    public function reject(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        return $this->approvalService->reject($maintenanceRequest)
            ->load(['room', 'user', 'approver']);
    }
}
