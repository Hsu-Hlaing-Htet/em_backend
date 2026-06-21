<?php

namespace Database\Seeders;

use App\Models\Building;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    public function run(): void
    {
        $buildings = [
            [
                'building_name' => 'Rosewood Tower',
                'location' => 'Downtown Yangon, Myanmar',
                'description' => 'Premium luxury tower with skyline views and concierge services.',
            ],
            [
                'building_name' => 'Royale Gardens',
                'location' => 'Chanmyathazi, Mandalay, Myanmar',
                'description' => 'Garden-facing residences with modern amenities and secure parking.',
            ],
            [
                'building_name' => 'Emerald Heights',
                'location' => 'Zabuthiri Township, Naypyidaw, Myanmar',
                'description' => 'Spacious units ideal for families and executive tenants.',
            ],
        ];

        foreach ($buildings as $buildingData) {
            Building::query()->updateOrCreate(
                ['building_name' => $buildingData['building_name']],
                $buildingData,
            );
        }
    }
}
