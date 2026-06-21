<?php

namespace App\Services;

use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoomService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Room::query()->with(['building', 'primaryRoomImage']);
        $this->applyListQuery($query, $params, ['room_number', 'description', 'type', 'status']);

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
        $room->update($data);

        return $room->fresh()->load(['building', 'primaryRoomImage']);
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }
}
