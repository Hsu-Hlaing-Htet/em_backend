<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Services\Concerns\AppliesListQuery;
use App\Support\ContractDraftProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return $this->paginateByStatus($params, 'draft');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateActive(array $params): LengthAwarePaginator
    {
        return $this->paginateByStatus($params, $this->profile->activeStatus);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function paginateByStatus(array $params, string $status): LengthAwarePaginator
    {
        $query = Contract::query()
            ->with(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->where('status', $status);

        $this->applyListQuery($query, $params, ['contract_number']);

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

    public function find(int $id): Contract
    {
        return $this->findByStatus($id, 'draft');
    }

    public function findActive(int $id): Contract
    {
        return $this->findByStatus($id, $this->profile->activeStatus);
    }

    public function findForDeletion(int $id): Contract
    {
        return Contract::query()
            ->with(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->findOrFail($id);
    }

    private function findByStatus(int $id, string $status): Contract
    {
        return Contract::query()
            ->with(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('type', $this->profile->type)
            ->where('status', $status)
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
                ->whereIn('status', ['active', 'approved'])
                ->lockForUpdate()
                ->exists();

            if ($hasActiveContract) {
                throw new ConcurrentConflictException('This room already has an active approved contract.');
            }

            $locked = $this->approvalService->transition(
                $locked,
                $this->profile->activeStatus,
                ['draft'],
            );

            $room->update(['status' => $this->profile->roomStatusOnApprove]);

            return $locked->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
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

            return $this->approvalService->reject($locked, null, ['draft'])
                ->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
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
            $this->assertRoomType($room);
            $this->assertNoActiveContractForRoom($room->id);

            $data = $this->applyRoomDefaults($data, $room);
            $data = $this->normalizeInstallmentFields($data);
            $this->assertContractTotal($data['contract_total']);

            $data['type'] = $this->profile->type;
            $data['status'] = 'draft';
            $data['contract_number'] = $this->generateContractNumber();
            $data['created_by'] = Auth::id();
            $data['approved_by'] = null;
            $data['approved_at'] = null;

            return Contract::query()
                ->create($data)
                ->load(['user.profile', 'room.building', 'paymentPlan', 'creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        $this->assertDraftContract($contract);

        if (isset($data['user_id'])) {
            $this->assertCustomerActive((int) $data['user_id']);
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
        if ($paymentType === 'full') {
            $data['duration_months'] = null;
            $data['billing_day'] = null;
        }

        if (isset($data['contract_total'])) {
            $this->assertContractTotal($data['contract_total']);
        }

        $contract->update($data);

        return $contract->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator']);
    }

    public function delete(Contract $contract): void
    {
        $this->assertDraftContract($contract);

        if ($contract->invoices()->exists()) {
            throw new InvalidArgumentException('This record cannot be deleted because related history exists.');
        }

        $contract->delete();
    }

    public function cancel(Contract $contract, string $reason): Contract
    {
        if (! in_array($contract->status, ['approved', 'active'], true)) {
            throw new InvalidArgumentException('Only approved or active contracts can be cancelled.');
        }

        return DB::transaction(function () use ($contract, $reason): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->with('room')
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['approved', 'active'], true)) {
                throw new InvalidArgumentException('Only approved or active contracts can be cancelled.');
            }

            $locked->update([
                'status' => 'cancelled',
                'remark' => $reason,
            ]);

            $locked->room?->update(['status' => Room::STATUS_AVAILABLE]);

            return $locked->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
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
        if (! $preserveContractTotal) {
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
        if (($data['payment_type'] ?? null) === 'full') {
            $data['duration_months'] = null;
            $data['billing_day'] = null;
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
        if ($contract->type !== $this->profile->type || $contract->status !== 'draft') {
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
            ->whereIn('status', ['active', 'approved']);

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
