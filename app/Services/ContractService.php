<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ContractService
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
        $query = Contract::query()->with(['user', 'room', 'paymentPlan']);

        $this->applyListQuery($query, $params, ['contract_number']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Contract
    {
        return Contract::query()
            ->with(['user', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        $this->assertRoomAvailable((int) $data['room_id']);
        $data = $this->applyPaymentPlanDefaults($data);
        $data['contract_number'] = $this->generateContractNumber();
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        return Contract::query()->create($data)->load(['user', 'room', 'paymentPlan']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        if (isset($data['room_id'])) {
            $this->assertRoomAvailable((int) $data['room_id'], $contract->id);
        }

        if (isset($data['payment_plan_id'])) {
            $data = $this->applyPaymentPlanDefaults($data);
        }

        $contract->update($data);

        return $contract->fresh(['user', 'room', 'paymentPlan']);
    }

    public function delete(Contract $contract): void
    {
        $contract->delete();
    }

    public function submit(Contract $contract): Contract
    {
        return $this->approvalService->submit($contract)
            ->load(['user', 'room', 'paymentPlan']);
    }

    public function approve(Contract $contract): Contract
    {
        $contract = $this->approvalService->transition($contract, 'active', ['pending']);

        $roomStatus = $contract->type === 'sale' ? 'sold' : 'occupied';
        $contract->room()->update(['status' => $roomStatus]);

        return $contract->fresh(['user', 'room', 'paymentPlan', 'approver']);
    }

    public function reject(Contract $contract): Contract
    {
        return $this->approvalService->reject($contract)
            ->load(['user', 'room', 'paymentPlan', 'approver']);
    }

    public function generateContractNumber(): string
    {
        $prefix = 'CTR-'.now()->format('Ymd').'-';
        $last = Contract::query()
            ->where('contract_number', 'like', $prefix.'%')
            ->orderByDesc('contract_number')
            ->value('contract_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyPaymentPlanDefaults(array $data): array
    {
        if (empty($data['payment_plan_id'])) {
            return $data;
        }

        $plan = PaymentPlan::query()->findOrFail($data['payment_plan_id']);
        $data['payment_type'] = $data['payment_type'] ?? $plan->payment_type;
        $data['duration_months'] = $data['duration_months'] ?? $plan->duration_months;

        return $data;
    }

    private function assertRoomAvailable(int $roomId, ?int $ignoreContractId = null): void
    {
        $room = Room::query()->findOrFail($roomId);

        if (in_array($room->status, ['available', 'reserved'], true)) {
            return;
        }

        if ($ignoreContractId) {
            $ownsRoom = Contract::query()
                ->where('id', $ignoreContractId)
                ->where('room_id', $roomId)
                ->exists();

            if ($ownsRoom) {
                return;
            }
        }

        throw new InvalidArgumentException('Room is not available for contract.');
    }
}
