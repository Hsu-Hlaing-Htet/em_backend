<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait GuardsDeletion
{
    protected function guardNoChildren(Model $model, string $relation, string $message): void
    {
        if ($model->{$relation}()->exists()) {
            throw ValidationException::withMessages([
                'delete' => $message,
            ]);
        }
    }
}
