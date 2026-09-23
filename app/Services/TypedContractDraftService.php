<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use App\Support\ContractDraftProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TypedContractDraftService
{
    use AppliesListQuery;

    private ContractDraftProfile $profile;

    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {
        $this->profile = ContractDraftProfile::sale();
    }

    public function for(string $type): self
    {
        $service = clone $this;
        $service->profile = ContractDraftProfile::fromType($type);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        return $this->paginateByStatuses($params, [Contract::STATUS_PENDING, Contract::STATUS_REJECTED]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateActive(array $params): LengthAwarePaginator
    {
        return $this->paginateByStatuses($params, [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_COMPLETED,
            Contract::STATUS_TERMINATED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function paginateByStatuses(array $params, array $statuses): LengthAwarePaginator
    {
        $query = Contract::query()
            ->with(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->whereIn('status', $statuses);

        if (! empty($params['status']) && in_array($params['status'], $statuses, true)) {
            $query->where('status', $params['status']);
        }

        $this->applyContractDraftSearch($query, $params);
        $this->applyCreatedDateFilter($query, $params);
        $this->applyListQuery($query, $params, [], AdminListSorts::contracts());

        if (! empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        if (! empty($params['payment_type'])) {
            $query->where('payment_type', $params['payment_type']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  Builder<Contract>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyContractDraftSearch(Builder $query, array $params): void
    {
        if (empty($params['search'])) {
            return;
        }

        $search = trim((string) $params['search']);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder->where('contract_number', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                    ->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('room', fn (Builder $roomQuery) => $roomQuery
                    ->where('room_number', 'like', '%'.$search.'%'));
        });
    }

    /**
     * @param  Builder<Contract>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyCreatedDateFilter(Builder $query, array $params): void
    {
        $from = $params['date_from'] ?? $params['created_from'] ?? null;
        $to = $params['date_to'] ?? $params['created_to'] ?? null;

        if (! empty($from)) {
            $query->whereDate('created_at', '>=', (string) $from);
        }

        if (! empty($to)) {
            $query->whereDate('created_at', '<=', (string) $to);
        }
    }

    public function find(int $id): Contract
    {
        return $this->findByStatuses($id, [Contract::STATUS_PENDING, Contract::STATUS_REJECTED]);
    }

    public function findActive(int $id): Contract
    {
        return $this->findByStatuses($id, [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_COMPLETED,
            Contract::STATUS_TERMINATED,
        ]);
    }

    public function findForDeletion(int $id): Contract
    {
        return Contract::query()
            ->with(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->findOrFail($id);
    }

    private function findByStatuses(int $id, array $statuses): Contract
    {
        return Contract::query()
            ->with(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->whereIn('status', $statuses)
            ->findOrFail($id);
    }

    public function approve(Contract $contract): Contract
    {
        return DB::transaction(function () use ($contract): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraftContract($locked);
            $this->assertCustomerActive((int) $locked->user_id);

            /** @var Room $room */
            $room = Room::query()
                ->with('building')
                ->whereKey($locked->room_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveContract = Contract::query()
                ->where('room_id', $room->id)
                ->whereKeyNot($locked->id)
                ->where('status', Contract::STATUS_ACTIVE)
                ->lockForUpdate()
                ->exists();

            if ($hasActiveContract) {
                throw new ConcurrentConflictException('This room already has an active approved contract.');
            }

            $locked = $this->approvalService->transition(
                $locked,
                $this->profile->activeStatus,
                [Contract::STATUS_PENDING],
            );

            $room->update(['status' => $this->profile->roomStatusOnApprove]);

            return $locked->fresh(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
        });
    }

    public function reject(Contract $contract, ?string $reason = null): Contract
    {
        return DB::transaction(function () use ($contract, $reason): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraftContract($locked);

            if ($reason !== null) {
                $locked->update(['remark' => $reason]);
            }

            return $this->approvalService->reject($locked, null, [Contract::STATUS_PENDING])
                ->fresh(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data): Contract {
            /** @var Room $room */
            $room = Room::query()
                ->with('building')
                ->whereKey($data['room_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRoomAvailable($room);
            $this->assertCustomerActive((int) $data['user_id']);
            if (! empty($data['second_user_id'])) {
                $this->assertCustomerActive((int) $data['second_user_id']);
                if ((int) $data['second_user_id'] === (int) $data['user_id']) {
                    throw new InvalidArgumentException('Second customer must be different from the primary customer.');
                }
            } else {
                $data['second_user_id'] = null;
            }
            $this->assertRoomType($room);
            $this->assertNoActiveContractForRoom($room->id);

            $data = $this->applyRoomDefaults($data, $room);
            $data = $this->normalizeInstallmentFields($data);
            $this->assertContractTotal($data['contract_total']);

            $data['type'] = $this->profile->type;
            $data['status'] = Contract::STATUS_PENDING;
            $data['contract_number'] = $this->generateContractNumber();
            $data['created_by'] = Auth::id();
            $data['approved_by'] = null;
            $data['approved_at'] = null;

            return Contract::query()
                ->create($data)
                ->load(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        $this->assertDraftContract($contract);
        $room = null;

        if (isset($data['user_id'])) {
            $this->assertCustomerActive((int) $data['user_id']);
        }

        if (array_key_exists('second_user_id', $data)) {
            if ($data['second_user_id'] === null || $data['second_user_id'] === '') {
                $data['second_user_id'] = null;
            } else {
                $this->assertCustomerActive((int) $data['second_user_id']);
                $primaryUserId = (int) ($data['user_id'] ?? $contract->user_id);
                if ((int) $data['second_user_id'] === $primaryUserId) {
                    throw new InvalidArgumentException('Second customer must be different from the primary customer.');
                }
            }
        }

        if (isset($data['room_id'])) {
            $room = Room::query()->with('building')->findOrFail($data['room_id']);
            $this->assertRoomAvailable($room, $contract->id);
            $this->assertRoomType($room);
            $data = $this->applyRoomDefaults(
                $data,
                $room,
                preserveContractTotal: array_key_exists('contract_total', $data),
            );
            $data['deposit_amount'] = $room->{$this->profile->depositColumn};
        }

        $paymentType = $data['payment_type'] ?? $contract->payment_type;
        // Recurring due day is derived from start_date; clear any stale billing_day.
        $data['billing_day'] = null;
        if ($paymentType === 'full') {
            $data['duration_months'] = null;
        }

        if (
            $this->profile->type === 'rent'
            && array_intersect(array_keys($data), ['room_id', 'duration_months', 'payment_type', 'contract_total'])
        ) {
            $data = $this->applyRoomDefaults($data, $room ?? $contract->room()->firstOrFail());
        }

        if (isset($data['contract_total'])) {
            $this->assertContractTotal($data['contract_total']);
        }

        $contract->update($data);

        return $contract->fresh(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator']);
    }

    public function delete(Contract $contract): void
    {
        $this->assertDraftContract($contract);

        if ($contract->invoices()->exists()) {
            throw new InvalidArgumentException('This record cannot be deleted because related history exists.');
        }

        $contract->delete();
    }

    public function cancel(Contract $contract, string $reason, string $terminationDate): Contract
    {
        if ($contract->status !== Contract::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only active contracts can be terminated.');
        }

        return DB::transaction(function () use ($contract, $reason, $terminationDate): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->with('room')
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== Contract::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Only active contracts can be terminated.');
            }

            $locked->update([
                'status' => Contract::STATUS_TERMINATED,
                'termination_date' => $terminationDate,
                'termination_reason' => $reason,
            ]);

            $locked->room?->update(['status' => Room::STATUS_AVAILABLE]);

            return $locked->fresh(['user.profile', 'secondUser.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
        });
    }

    public function generateContractNumber(): string
    {
        $prefix = $this->profile->numberPrefix;

        $lastSequence = Contract::withTrashed()
            ->where('contract_number', 'like', $prefix.'%')
            ->pluck('contract_number')
            ->map(fn (string $number): int => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRoomDefaults(array $data, Room $room, bool $preserveContractTotal = false): array
    {
        if ($this->profile->type === 'rent') {
            $monthlyRent = (float) $room->{$this->profile->priceColumn};
            $durationMonths = max((int) ($data['duration_months'] ?? 1), 1);
            $data['contract_total'] = $monthlyRent * $durationMonths;
        } elseif (! $preserveContractTotal) {
            $priceColumn = $this->profile->priceColumn;
            $data['contract_total'] = $data['contract_total'] ?? $room->{$priceColumn};
        }

        $depositColumn = $this->profile->depositColumn;
        $data['deposit_amount'] = $room->{$depositColumn};

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeInstallmentFields(array $data): array
    {
        // Recurring due day is derived from start_date; never persist billing_day.
        $data['billing_day'] = null;

        if (($data['payment_type'] ?? null) === 'full') {
            $data['duration_months'] = null;
        }

        return $data;
    }

    private function assertContractTotal(mixed $total): void
    {
        if ((float) $total <= 0) {
            throw new InvalidArgumentException('Contract total must be greater than zero.');
        }
    }

    private function assertDraftContract(Contract $contract): void
    {
        if ($contract->type !== $this->profile->type || $contract->status !== Contract::STATUS_PENDING) {
            throw new ConcurrentConflictException($this->profile->draftOnlyMessage);
        }
    }

    private function assertRoomType(Room $room): void
    {
        if (! in_array($room->type, $this->profile->roomTypes, true)) {
            throw new InvalidArgumentException($this->profile->unavailableRoomMessage);
        }
    }

    private function assertNoActiveContractForRoom(int $roomId, ?int $ignoreContractId = null): void
    {
        $query = Contract::query()
            ->where('room_id', $roomId)
            ->where('status', Contract::STATUS_ACTIVE);

        if ($ignoreContractId) {
            $query->whereKeyNot($ignoreContractId);
        }

        if ($query->exists()) {
            throw new ConcurrentConflictException('This room already has an active approved contract.');
        }
    }

    private function assertRoomAvailable(Room $room, ?int $ignoreContractId = null): void
    {
        if ($room->building?->status !== 'active') {
            throw new InvalidArgumentException('The selected building is archived and cannot be used for a new contract.');
        }

        $this->assertNoActiveContractForRoom($room->id, $ignoreContractId);

        if ($room->status !== 'available') {
            if ($ignoreContractId) {
                $ownsRoom = Contract::query()
                    ->where('id', $ignoreContractId)
                    ->where('room_id', $room->id)
                    ->exists();

                if ($ownsRoom) {
                    return;
                }
            }

            throw new InvalidArgumentException('Room is not available for contract.');
        }
    }

    private function assertCustomerActive(int $userId): void
    {
        $customer = User::query()->findOrFail($userId);

        if ($customer->status !== User::STATUS_ACTIVE || ! $customer->isCustomer()) {
            throw new InvalidArgumentException('Inactive customers cannot be assigned to new contracts.');
        }
    }
}
