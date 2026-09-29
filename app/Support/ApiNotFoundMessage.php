<?php

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ApiNotFoundMessage
{
    /**
     * Map Eloquent models to safe, customer-facing 404 copy.
     * Never include class names, IDs, or whether a foreign record exists.
     */
    public static function fromModelNotFound(ModelNotFoundException $exception): string
    {
        $model = class_basename((string) $exception->getModel());

        return match ($model) {
            'Invoice' => 'Invoice not found.',
            'Receipt' => 'Receipt not found.',
            'Payment' => 'Payment not found.',
            'Contract' => 'Contract not found.',
            'Utility' => 'Utility not found.',
            'MaintenanceRequest' => 'Request not found.',
            'Building' => 'Building not found.',
            'Room' => 'Room not found.',
            'User' => 'User not found.',
            default => 'The requested resource could not be found.',
        };
    }

    public static function generic(): string
    {
        return 'The requested resource could not be found.';
    }
}
