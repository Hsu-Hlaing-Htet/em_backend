<?php

namespace Database\Seeders;

use App\Models\MaintenanceCategory;
use Illuminate\Database\Seeder;

class MaintenanceCategorySeeder extends Seeder
{
    /**
     * Canonical maintenance categories used across Admin + Customer flows.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Plumbing', 'slug' => 'plumbing', 'status' => MaintenanceCategory::STATUS_ACTIVE],
            ['name' => 'Electrical', 'slug' => 'electrical', 'status' => MaintenanceCategory::STATUS_ACTIVE],
            ['name' => 'HVAC', 'slug' => 'hvac', 'status' => MaintenanceCategory::STATUS_ACTIVE],
            ['name' => 'Appliance', 'slug' => 'appliance', 'status' => MaintenanceCategory::STATUS_ACTIVE],
            ['name' => 'General', 'slug' => 'general', 'status' => MaintenanceCategory::STATUS_ACTIVE],
        ];

        foreach ($categories as $category) {
            MaintenanceCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'status' => $category['status'],
                ]
            );
        }
    }
}
