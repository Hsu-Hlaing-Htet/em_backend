<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesListQuery
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     */
    protected function applyListQuery(Builder $query, array $params, array $searchable = []): void
    {
        if (! empty($params['search']) && $searchable !== []) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    $builder->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        if (! empty($params['order'])) {
            foreach (explode(',', (string) $params['order']) as $sort) {
                [$field, $direction] = array_pad(explode('|', $sort), 2, 'asc');
                $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                $query->orderBy($field, $direction);
            }
        } else {
            $query->latest('id');
        }
    }
}
