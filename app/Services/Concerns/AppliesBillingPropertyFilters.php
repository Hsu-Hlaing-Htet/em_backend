<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesBillingPropertyFilters
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     * @param  string  $roomRelation  Relation path to the room model (e.g. contract.room)
     */
    protected function applyBuildingRoomFilters(Builder $query, array $params, string $roomRelation): void
    {
        if (! empty($params['building_id'])) {
            $query->whereHas($roomRelation, function (Builder $roomQuery) use ($params): void {
                $roomQuery->where('building_id', $params['building_id']);
            });
        }

        if (! empty($params['room_id'])) {
            $roomId = $params['room_id'];
            $segments = explode('.', $roomRelation);
            $roomColumnOwner = array_pop($segments);
            $parentRelation = implode('.', $segments);

            if ($parentRelation === '') {
                $query->where($roomColumnOwner === 'room' ? 'room_id' : 'id', $roomId);

                return;
            }

            $query->whereHas($parentRelation, function (Builder $parentQuery) use ($roomId): void {
                $parentQuery->where('room_id', $roomId);
            });
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     */
    protected function applyDateRangeFilter(
        Builder $query,
        array $params,
        string $column,
        string $fromKey,
        string $toKey,
    ): void {
        if (! empty($params[$fromKey])) {
            $query->whereDate($column, '>=', $params[$fromKey]);
        }

        if (! empty($params[$toKey])) {
            $query->whereDate($column, '<=', $params[$toKey]);
        }
    }
}
