<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ApprovalService
{
    /**
     * @param  array<int, string>  $allowedFrom
     */
    public function transition(Model $model, string $toStatus, array $allowedFrom, ?int $actorId = null): Model
    {
        $fromStatus = (string) $model->getAttribute('status');

        if (! in_array($fromStatus, $allowedFrom, true)) {
            throw new InvalidArgumentException("Cannot transition from [{$fromStatus}] to [{$toStatus}].");
        }

        $actorId ??= Auth::id();

        $payload = ['status' => $toStatus];

        if (in_array($toStatus, ['pending', 'issued'], true) && $fromStatus === 'draft') {
            $payload['created_by'] = $payload['created_by'] ?? $actorId;
        }

        if (in_array($toStatus, ['approved', 'issued', 'active', 'paid'], true)) {
            $payload['approved_by'] = $actorId;
            $payload['approved_at'] = now();
        }

        if ($toStatus === 'rejected') {
            $payload['approved_by'] = $actorId;
            $payload['approved_at'] = now();
        }

        $model->update($payload);

        return $model->fresh();
    }

    public function submit(Model $model): Model
    {
        return $this->transition($model, 'pending', ['draft']);
    }

    public function approve(Model $model): Model
    {
        $status = (string) $model->getAttribute('status');

        if ($status === 'pending') {
            return $this->transition($model, 'approved', ['pending']);
        }

        if ($status === 'draft') {
            return $this->transition($model, 'active', ['draft']);
        }

        throw new InvalidArgumentException("Cannot approve record in status [{$status}].");
    }

    public function reject(Model $model): Model
    {
        return $this->transition($model, 'rejected', ['pending', 'draft']);
    }
}
