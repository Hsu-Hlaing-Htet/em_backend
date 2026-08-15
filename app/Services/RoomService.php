<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Room::query()
            ->with(['building', 'primaryRoomImage'])
            ->withCount([
                'contracts as contracts_count' => fn (Builder $query) => $query->withTrashed(),
                'utilities',
                'maintenanceRequests',
            ]);

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('room_number', 'like', '%'.$search.'%')
                    ->orWhereHas('building', fn (Builder $q) => $q->where('building_name', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($params['building_id'])) {
            $query->where('building_id', (int) $params['building_id']);
        }

        if (! empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $this->applyListQuery($query, $params, []);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Room
    {
        return Room::query()
            ->with([
                'building',
                'roomImages' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Room
    {
        return Room::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Room $room, array $data): Room
    {
        if ((int) $data['building_id'] !== (int) $room->building_id) {
            $buildingIsActive = Building::query()
                ->whereKey($data['building_id'])
                ->where('status', Building::STATUS_ACTIVE)
                ->exists();

            if (! $buildingIsActive) {
                throw ValidationException::withMessages([
                    'building_id' => 'Archived buildings cannot be used for new room assignments.',
                ]);
            }
        }

        $room->update($data);

        return $room->fresh()->load(['building', 'primaryRoomImage']);
    }

    public function delete(Room $room): void
    {
        if ($this->hasHistory($room)) {
            $this->deactivate($room);

            return;
        }

        $this->assertRoomCanBeDeleted($room->loadCount('contracts'));

        $room->delete();
    }

    public function deactivate(Room $room): Room
    {
        $room->update(['status' => Room::STATUS_INACTIVE]);

        return $room->fresh()->load(['building', 'primaryRoomImage']);
    }

    public function activate(Room $room): Room
    {
        if ($room->contracts()->whereIn('status', ['active', 'approved'])->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This room has an active contract and cannot be made available.',
            ]);
        }

        $room->update(['status' => Room::STATUS_AVAILABLE]);

        return $room->fresh()->load(['building', 'primaryRoomImage']);
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($ids): int {
            $rooms = Room::query()
                ->withCount([
                    'contracts as contracts_count' => fn (Builder $query) => $query->withTrashed(),
                    'utilities',
                    'maintenanceRequests',
                ])
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($rooms->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'One or more selected rooms could not be found.',
                ]);
            }

            $blockedRooms = $rooms
                ->filter(fn (Room $room): bool => ! $this->canDeleteRoom($room))
                ->map(fn (Room $room): string => (string) $room->room_number)
                ->values();

            if ($blockedRooms->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'Cannot delete protected, occupied, sold, or contracted rooms: '.$blockedRooms->join(', '),
                ]);
            }

            $rooms->each->delete();

            return $rooms->count();
        });
    }

    private function assertRoomCanBeDeleted(Room $room): void
    {
        if ($this->canDeleteRoom($room)) {
            return;
        }

        throw ValidationException::withMessages([
            'delete' => 'Cannot delete protected, occupied, sold, or contracted rooms.',
        ]);
    }

    private function canDeleteRoom(Room $room): bool
    {
        return $room->status === Room::STATUS_AVAILABLE && ! $this->hasHistory($room);
    }

    private function hasHistory(Room $room): bool
    {
        $contractsCount = $room->getAttribute('contracts_count');
        $utilitiesCount = $room->getAttribute('utilities_count');
        $maintenanceRequestsCount = $room->getAttribute('maintenance_requests_count');

        return ($contractsCount !== null
                ? (int) $contractsCount > 0
                : $room->contracts()->withTrashed()->exists())
            || ($utilitiesCount !== null
                ? (int) $utilitiesCount > 0
                : $room->utilities()->exists())
            || ($maintenanceRequestsCount !== null
                ? (int) $maintenanceRequestsCount > 0
                : $room->maintenanceRequests()->exists());
    }
}
