<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Room;
use App\Models\Sale;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SaleService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Sale::query()->with([
            'user.profile',
            'room.building',
            'paymentPlan',
            'creator',
            'submitter',
            'approver',
        ]);

        $this->applyListQuery($query, $params, ['sale_number']);

        if (! empty($params['status'])) {
            $status = $this->normalizeStatusFilter((string) $params['status']);
            $query->where('status', $status);
        }

        if (! empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Sale
    {
        return Sale::query()
            ->with([
                'user.profile',
                'room.building',
                'paymentPlan',
                'creator',
                'submitter',
                'approver',
                'activator',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sale
    {
        $room = Room::query()->findOrFail($data['room_id']);
        $this->assertSaleRoomType($room);
        $this->assertNoConflictingSale((int) $data['room_id']);

        $data = $this->applyRoomDefaults($data, $room);
        $data = $this->normalizeInstallmentFields($data);
        $this->assertSalePrice($data['sale_price']);

        $data['sale_number'] = $this->generateSaleNumber();
        $data['status'] = Sale::STATUS_PENDING;
        $data['created_by'] = Auth::id();
        $data['submitted_by'] = Auth::id();
        $data['submitted_at'] = now();

        return Sale::query()
            ->create($data)
            ->load(['user.profile', 'room.building', 'paymentPlan', 'creator', 'submitter']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Sale $sale, array $data): Sale
    {
        $this->assertPendingSale($sale);

        if (isset($data['room_id'])) {
            $room = Room::query()->findOrFail($data['room_id']);
            $this->assertSaleRoomType($room);
            $this->assertNoConflictingSale((int) $data['room_id'], $sale->id);
            $data = $this->applyRoomDefaults(
                $data,
                $room,
                preserveSalePrice: array_key_exists('sale_price', $data),
            );
            $data['deposit_amount'] = $room->booking_deposit_price;
        }

        $paymentType = $data['payment_type'] ?? $sale->payment_type;
        if ($paymentType === 'full') {
            $data['duration_months'] = null;
            $data['billing_day'] = null;
        }

        if (isset($data['sale_price'])) {
            $this->assertSalePrice($data['sale_price']);
        }

        $sale->update($data);

        return $sale->fresh(['user.profile', 'room.building', 'paymentPlan', 'creator', 'submitter']);
    }

    public function delete(Sale $sale): void
    {
        if (! in_array($sale->status, [Sale::STATUS_PENDING, Sale::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException('Only pending or rejected sales can be deleted.');
        }

        $sale->delete();
    }

    public function approve(Sale $sale): Sale
    {
        $this->assertStatus($sale, Sale::STATUS_PENDING, 'approve');

        $sale->update([
            'status' => Sale::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $sale->room()->update(['status' => 'reserved']);

        return $sale->fresh(['user.profile', 'room.building', 'paymentPlan', 'approver']);
    }

    public function reject(Sale $sale, ?string $reason = null): Sale
    {
        $this->assertStatus($sale, Sale::STATUS_PENDING, 'reject');

        $sale->update([
            'status' => Sale::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $sale->fresh(['user.profile', 'room.building', 'paymentPlan', 'approver']);
    }

    public function activate(Sale $sale): Sale
    {
        if (! in_array($sale->status, [Sale::STATUS_APPROVED, Sale::STATUS_INACTIVE], true)) {
            throw new InvalidArgumentException('Only approved or inactive sales can be activated.');
        }

        $sale->update([
            'status' => Sale::STATUS_ACTIVE,
            'activated_by' => Auth::id(),
            'activated_at' => now(),
        ]);

        if ($sale->room?->status === 'available') {
            $sale->room()->update(['status' => 'reserved']);
        }

        return $sale->fresh(['user.profile', 'room.building', 'paymentPlan', 'activator']);
    }

    public function deactivate(Sale $sale): Sale
    {
        $this->assertStatus($sale, Sale::STATUS_ACTIVE, 'deactivate');

        $sale->update([
            'status' => Sale::STATUS_INACTIVE,
        ]);

        return $sale->fresh(['user.profile', 'room.building', 'paymentPlan']);
    }

    public function assertApprovedForContract(int $roomId, int $userId): Sale
    {
        $sale = Sale::query()
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->whereIn('status', [Sale::STATUS_APPROVED, Sale::STATUS_ACTIVE])
            ->whereNull('contract_id')
            ->first();

        if (! $sale) {
            throw new InvalidArgumentException('An approved sale must exist before creating a sale contract.');
        }

        return $sale;
    }

    public function linkContract(Sale $sale, Contract $contract): Sale
    {
        $sale->update([
            'contract_id' => $contract->id,
            'status' => Sale::STATUS_COMPLETED,
        ]);

        return $sale->fresh();
    }

    public function generateSaleNumber(): string
    {
        $lastSequence = Sale::withTrashed()
            ->where('sale_number', 'like', 'RR-S-%')
            ->pluck('sale_number')
            ->map(fn (string $number): int => (int) substr($number, 5))
            ->max() ?? 0;

        return 'RR-S-'.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    private function normalizeStatusFilter(string $status): string
    {
        return $status === 'pending_approval' ? Sale::STATUS_PENDING : $status;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRoomDefaults(array $data, Room $room, bool $preserveSalePrice = false): array
    {
        if (! $preserveSalePrice) {
            $data['sale_price'] = $data['sale_price'] ?? $room->sale_price;
        }

        $data['deposit_amount'] = $room->booking_deposit_price;

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

    private function assertSalePrice(mixed $price): void
    {
        if ((float) $price <= 0) {
            throw new InvalidArgumentException('Sale price must be greater than zero.');
        }
    }

    private function assertPendingSale(Sale $sale): void
    {
        if ($sale->status !== Sale::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending sales can be modified.');
        }
    }

    private function assertSaleRoomType(Room $room): void
    {
        if (! in_array($room->type, ['sale', 'both'], true)) {
            throw new InvalidArgumentException('Selected room is not available for sale.');
        }
    }

    private function assertNoConflictingSale(int $roomId, ?int $ignoreSaleId = null): void
    {
        $query = Sale::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', [Sale::STATUS_REJECTED, Sale::STATUS_COMPLETED, Sale::STATUS_CANCELLED]);

        if ($ignoreSaleId) {
            $query->where('id', '!=', $ignoreSaleId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('An active sale application already exists for this room.');
        }
    }

    private function assertStatus(Sale $sale, string $expectedStatus, string $action): void
    {
        if ($sale->status !== $expectedStatus) {
            throw new InvalidArgumentException("Cannot {$action} sale in status [{$sale->status}].");
        }
    }
}
