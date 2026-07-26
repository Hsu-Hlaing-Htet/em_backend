<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ApprovalService
{
    /**
     * @param  list<string>  $fromStatuses
     */
    public function transition(Model $model, string $toStatus, array $fromStatuses = []): Model
    {
        if ($fromStatuses !== [] && ! in_array($model->status, $fromStatuses, true)) {
            throw new InvalidArgumentException(
                "Cannot transition from status '{$model->status}' to '{$toStatus}'."
            );
        }

        $model->update([
            'status' => $toStatus,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $model->fresh();
    }

    public function approve(Model $model): Model
    {
        return $this->transition($model, 'approved');
    }

    public function reject(Model $model, ?string $reason = null): Model
    {
        $attributes = [
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ];

        if ($reason !== null && in_array('rejection_reason', $model->getFillable(), true)) {
            $attributes['rejection_reason'] = $reason;
        }

        $model->update($attributes);

        return $model->fresh();
    }
}
