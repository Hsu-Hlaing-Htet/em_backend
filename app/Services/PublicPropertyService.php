<?php

namespace App\Services;

use App\Models\Contract;
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
     * Latest public sale listings (no invented "featured" flag exists on Room/Building).
     *
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
     * @return array{total: int, available: int}
     */
    public function stats(): array
    {
        $saleTotal = $this->baseQuery(['purpose' => 'sale'])->count();
        $rentTotal = $this->baseQuery(['purpose' => 'rent'])->count();

        return [
            'total' => $saleTotal + $rentTotal,
            'available' => $rentTotal,
        ];
    }

    public function find(int $id): Room
    {
        return Room::query()
            ->with(['building', 'roomImages', 'contracts'])
            ->where('rooms.id', $id)
            ->where(function (Builder $builder): void {
                $builder
                    ->where(function (Builder $saleQuery): void {
                        $saleQuery
                            ->whereIn('type', ['sale', 'both'])
                            ->whereHas('contracts', function (Builder $contracts): void {
                                $contracts->where('type', 'sale')->where('status', Contract::STATUS_ACTIVE);
                            });
                    })
                    ->orWhere(function (Builder $rentQuery): void {
                        $rentQuery
                            ->whereIn('type', ['rent', 'both'])
                            ->where('status', 'available');
                    });
            })
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
            ->with(['building', 'roomImages', 'contracts']);

        if ($purpose === 'rent') {
            $query
                ->whereIn('type', ['rent', 'both'])
                ->where('status', 'available');
        } else {
            $query
                ->whereIn('type', ['sale', 'both'])
                ->whereHas('contracts', function (Builder $builder): void {
                    $builder->where('type', 'sale')->where('status', Contract::STATUS_ACTIVE);
                });
        }

        if (! empty($params['search'])) {
            $search = trim((string) $params['search']);

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
