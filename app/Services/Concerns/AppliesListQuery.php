<?php

namespace App\Services\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait AppliesListQuery
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     * @param  list<string>  $searchable
     * @param  array<string, string|Closure>  $sortable  UI sort key => qualified column or callback(Builder, string $direction)
     * @param  (Closure(Builder<\Illuminate\Database\Eloquent\Model>): void)|null  $defaultOrder
     */
    protected function applyListQuery(
        Builder $query,
        array $params,
        array $searchable = [],
        array $sortable = [],
        ?Closure $defaultOrder = null,
    ): void {
        if (! empty($params['search']) && $searchable !== []) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    $builder->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        $table = $query->getModel()->getTable();

        if (! empty($params['order']) && $sortable !== []) {
            $applied = false;

            foreach (explode(',', (string) $params['order']) as $sort) {
                [$field, $direction] = array_pad(explode('|', trim($sort)), 2, 'asc');
                $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

                if (! array_key_exists($field, $sortable)) {
                    continue;
                }

                $mapping = $sortable[$field];

                if ($mapping instanceof Closure) {
                    $mapping($query, $direction);
                } else {
                    $column = (string) $mapping;

                    if (! str_contains($column, '.') && ! str_contains($column, '(')) {
                        $column = $table.'.'.$column;
                    }

                    $query->orderBy($column, $direction);
                }

                $applied = true;
            }

            if ($applied) {
                return;
            }
        }

        if ($defaultOrder instanceof Closure) {
            $defaultOrder($query);

            return;
        }

        $query->latest($table.'.id');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     */
    protected function applyStatusFilter(Builder $query, array $params): void
    {
        if (! empty($params['status'])) {
            $query->where($query->getModel()->getTable().'.status', $params['status']);
        }
    }

    /**
     * Order by a related table column via correlated subquery.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function orderByRelated(
        Builder $query,
        string $direction,
        string $relatedTable,
        string $relatedColumn,
        string $foreignKeyColumn,
        string $ownerKey = 'id',
    ): void {
        $query->orderBy(
            DB::table($relatedTable)
                ->select($relatedColumn)
                ->whereColumn("{$relatedTable}.{$ownerKey}", $foreignKeyColumn)
                ->limit(1),
            $direction
        );
    }
}
