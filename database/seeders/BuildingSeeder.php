<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    public function run(): void
    {
        $buildings = [
            [
                'building_name' => 'Rosewood Royal Tower',
                'location' => 'Kamayut Township, Yangon, Myanmar',
                'description' => 'Flagship tower near Inya Lake with 24-hour security, backup generator, and covered parking.',
            ],
            [
                'building_name' => 'Inya Lake View Condominium',
                'location' => 'Bahan Township, Yangon, Myanmar',
                'description' => 'Lake-facing residences with clubhouse, swimming pool, and family-friendly amenities.',
            ],
            [
                'building_name' => 'Kabar Aye Premium Homes',
                'location' => 'Mayangone Township, Yangon, Myanmar',
                'description' => 'Quiet residential block close to Kabar Aye Pagoda with lift access and CCTV.',
            ],
            [
                'building_name' => 'Pyay Road Residences',
                'location' => 'Hlaing Township, Yangon, Myanmar',
                'description' => 'Convenient Pyay Road address for professionals commuting across Yangon.',
            ],
            [
                'building_name' => 'Shwe Gon Daing Heights',
                'location' => 'Bahan Township, Yangon, Myanmar',
                'description' => 'Boutiqueed mid-rise apartments with generator backup and visitor parking.',
            ],
            [
                'building_name' => 'Mandalay Palace Residences',
                'location' => 'Chanmyathazi Township, Mandalay, Myanmar',
                'description' => 'Upscale Mandalay residences near the palace moat with on-site management.',
            ],
        ];

        foreach ($buildings as $building) {
            Building::query()->create($building);
        }
    }
}
