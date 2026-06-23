<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Services\ChargeTypeService;
use Illuminate\Database\Seeder;

class ChargeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ChargeTypeService::class);

        $items = [
            ['name' => 'Rent', 'status' => 'active'],
            ['name' => 'Utility', 'status' => 'active'],
            ['name' => 'Deposit', 'status' => 'active'],
            ['name' => 'Maintenance Fee', 'status' => 'active'],
            ['name' => 'Late Fee', 'status' => 'active'],
            ['name' => 'Other Charges', 'status' => 'active'],
        ];

        foreach ($items as $item) {
            $existing = ChargeType::query()->where('name', $item['name'])->first();

            if ($existing) {
                $service->update($existing, $item);
            } else {
                $service->create($item);
            }
        }
    }
}
