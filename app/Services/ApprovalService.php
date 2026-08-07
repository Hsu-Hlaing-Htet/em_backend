<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * @param  list<string>  $fromStatuses
     */
    public function transition(Model $model, string $toStatus, array $fromStatuses = []): Model
    {
        return DB::transaction(function () use ($model, $toStatus, $fromStatuses): Model {
            /** @var Model $locked */
            $locked = $model->newQuery()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fromStatuses !== [] && ! in_array($locked->getAttribute('status'), $fromStatuses, true)) {
                throw new ConcurrentConflictException(
                    "Cannot transition from status '{$locked->getAttribute('status')}' to '{$toStatus}'."
                );
            }

            $locked->update([
                'status' => $toStatus,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @param  list<string>  $fromStatuses
     */
    public function approve(Model $model, array $fromStatuses = ['pending']): Model
    {
        return $this->transition($model, 'approved', $fromStatuses);
    }

    public function reject(Model $model, ?string $reason = null, array $fromStatuses = ['pending', 'draft']): Model
    {
        return DB::transaction(function () use ($model, $reason, $fromStatuses): Model {
            /** @var Model $locked */
            $locked = $model->newQuery()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fromStatuses !== [] && ! in_array($locked->getAttribute('status'), $fromStatuses, true)) {
                throw new ConcurrentConflictException(
                    "Cannot reject from status '{$locked->getAttribute('status')}'."
                );
            }

            $attributes = [
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ];

            if ($reason !== null && in_array('rejection_reason', $locked->getFillable(), true)) {
                $attributes['rejection_reason'] = $reason;
            }

            $locked->update($attributes);

            return $locked->fresh();
        });
    }
}
