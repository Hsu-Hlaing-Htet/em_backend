<?php

namespace App\Support;

final class MaintenanceRequestOptions
{
    public const CATEGORIES = [
        'plumbing',
        'electrical',
        'hvac',
        'appliance',
        'general',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
    ];

    public const STATUSES = [
        'pending',
        'in_progress',
        'completed',
        'rejected',
    ];
}
