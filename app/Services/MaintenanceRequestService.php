<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceRequestService
{
    use AppliesListQuery;

    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = MaintenanceRequest::query()->with(['room.building', 'user', 'maintenanceCategory', 'invoice']);

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

        $this->applyListQuery($query, $params, [], AdminListSorts::maintenanceRequests());

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): MaintenanceRequest
    {
        return MaintenanceRequest::query()
            ->with(['room.building', 'user.profile', 'creator', 'approver', 'maintenanceCategory', 'invoice'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MaintenanceRequest
    {
        $data = $this->syncCategoryFields($data);

        return MaintenanceRequest::query()->create([
            ...$data,
            'status' => 'pending',
            'rejection_reason' => null,
            'resolution_note' => null,
            'created_by' => Auth::id(),
        ])->load(['room.building', 'user', 'maintenanceCategory']);
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

            $locked->update($this->syncCategoryFields($data));

            return $locked->fresh(['room.building', 'user', 'maintenanceCategory']);
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(MaintenanceRequest $maintenanceRequest, array $data = []): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenanceRequest, $data): MaintenanceRequest {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw new ConcurrentConflictException('Only in-progress maintenance requests can be completed.');
            }

            $feeAmount = $this->resolveFeeAmount($data);
            $chargeDescription = isset($data['charge_description'])
                ? trim((string) $data['charge_description'])
                : null;
            $resolutionNote = array_key_exists('resolution_note', $data)
                ? $data['resolution_note']
                : null;

            $invoiceId = null;

            if ($feeAmount > 0) {
                if ($chargeDescription === null || $chargeDescription === '') {
                    throw new InvalidArgumentException(
                        'Charge description is required when a maintenance fee is charged.'
                    );
                }

                $invoice = $this->invoiceService->addMaintenanceFeeFromRequest(
                    $locked,
                    $feeAmount,
                    $chargeDescription,
                );
                $invoiceId = $invoice->id;
            }

            $locked->update([
                'status' => 'completed',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'resolution_note' => $resolutionNote,
                'rejection_reason' => null,
                'maintenance_fee_amount' => $feeAmount > 0 ? $feeAmount : null,
                'charge_description' => $feeAmount > 0 ? $chargeDescription : null,
                'invoice_id' => $invoiceId,
            ]);

            return $locked->fresh(['room.building', 'user', 'approver', 'invoice', 'maintenanceCategory']);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveFeeAmount(array $data): float
    {
        $raw = $data['maintenance_fee_amount'] ?? $data['maintenance_fee'] ?? null;

        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return round((float) $raw, 2);
    }

    /**
     * Keep the legacy category slug column and FK in sync without rewriting history.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncCategoryFields(array $data): array
    {
        if (! array_key_exists('category', $data) && ! array_key_exists('maintenance_category_id', $data)) {
            return $data;
        }

        $category = null;

        if (! empty($data['maintenance_category_id'])) {
            $category = MaintenanceCategory::query()->find((int) $data['maintenance_category_id']);
        } elseif (! empty($data['category'])) {
            $category = MaintenanceCategory::query()
                ->where('slug', (string) $data['category'])
                ->first();
        }

        if ($category) {
            $data['maintenance_category_id'] = $category->id;
            $data['category'] = $category->slug;
        }

        return $data;
    }
}
