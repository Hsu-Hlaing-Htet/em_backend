<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicPropertyService
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = $this->baseQuery($params);

        return $query->paginate((int) ($params['per_page'] ?? 12));
    }

    /**
     * @return list<Room>
     */
    public function featured(int $limit = 6): array
    {
        return $this->baseQuery(['purpose' => 'sale'])
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array{total: int, available: int, featured: int}
     */
    public function stats(): array
    {
        $total = $this->baseQuery(['purpose' => 'sale'])->count();

        return [
            'total' => $total,
            'available' => $total,
            'featured' => min($total, 6),
        ];
    }

    public function find(int $id): Room
    {
        return $this->baseQuery(['purpose' => 'sale'])
            ->where('rooms.id', $id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Builder<Room>
     */
    private function baseQuery(array $params): Builder
    {
        $purpose = $params['purpose'] ?? 'sale';

        $query = Room::query()
            ->with(['building', 'roomImages', 'contracts'])
            ->whereHas('contracts', function (Builder $builder): void {
                $builder->where('type', 'sale')->where('status', 'approved');
            });

        if ($purpose === 'sale') {
            $query->whereIn('type', ['sale', 'both']);
        }

        if ($purpose === 'rent') {
            $query->whereIn('type', ['rent', 'both'])
                ->where('status', 'available');
        }

        if (! empty($params['search'])) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('room_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('building', function (Builder $buildingQuery) use ($search): void {
                        $buildingQuery->where('building_name', 'like', '%'.$search.'%')
                            ->orWhere('location', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query->latest('rooms.id');
    }
}
