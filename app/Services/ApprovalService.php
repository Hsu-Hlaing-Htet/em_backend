<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalService
{
    public function approve(Model $model): Model
    {
        $model->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $model->fresh();
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
