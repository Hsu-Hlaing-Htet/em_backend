<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use App\Support\ContractDraftProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
        $this->assertDraftContract($contract);

        $contract = $this->approvalService->transition(
            $contract,
            $this->profile->activeStatus,
            ['draft'],
        );
        $contract->room()->update(['status' => $this->profile->roomStatusOnApprove]);

        return $contract->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
    }

    public function reject(Contract $contract, ?string $reason = null): Contract
    {
        $this->assertDraftContract($contract);

        if ($reason !== null) {
            $contract->update(['remark' => $reason]);
        }

        return $this->approvalService->reject($contract)
            ->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        $room = Room::query()->findOrFail($data['room_id']);
        $this->assertRoomAvailable($room);
        $this->assertRoomType($room);

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
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        $this->assertDraftContract($contract);

        if (isset($data['room_id'])) {
            $room = Room::query()->findOrFail($data['room_id']);
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

        $contract->delete();
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
            throw new InvalidArgumentException($this->profile->draftOnlyMessage);
        }
    }

    private function assertRoomType(Room $room): void
    {
        if (! in_array($room->type, $this->profile->roomTypes, true)) {
            throw new InvalidArgumentException($this->profile->unavailableRoomMessage);
        }
    }

    private function assertRoomAvailable(Room $room, ?int $ignoreContractId = null): void
    {
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
}
