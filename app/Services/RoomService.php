<?php

namespace App\Services;

use App\Models\Room;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RoomService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Room::query()->with(['building', 'primaryRoomImage']);

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
        $room->update($data);

        return $room->fresh()->load(['building', 'primaryRoomImage']);
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }
}
