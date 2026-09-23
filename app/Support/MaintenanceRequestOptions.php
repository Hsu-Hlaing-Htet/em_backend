<?php

namespace App\Support;

use App\Models\MaintenanceCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class MaintenanceRequestOptions
{
    /**
     * Legacy hard-coded slugs kept for reference / display helpers only.
     * New requests must validate against the maintenance_categories table.
     *
     * @var list<string>
     */
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

    /**
     * Validate category slug against Active maintenance categories (new requests).
     */
    public static function activeCategoryRule(): Exists
    {
        return Rule::exists('maintenance_categories', 'slug')
            ->where('status', MaintenanceCategory::STATUS_ACTIVE);
    }

    /**
     * Validate category slug against any existing category (historical edits).
     */
    public static function existingCategoryRule(): Exists
    {
        return Rule::exists('maintenance_categories', 'slug');
    }
}
