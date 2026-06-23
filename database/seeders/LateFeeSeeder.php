<?php

namespace Database\Seeders;

use App\Models\LateFee;
use Illuminate\Database\Seeder;

class LateFeeSeeder extends Seeder
{
    public function run(): void
    {
        $lateFees = [
            [
                'name' => 'Standard Daily Fee',
                'type' => LateFee::TYPE_FIXED,
                'value' => 5000,
                'per' => LateFee::PER_DAY,
                'grace_days' => 3,
                'status' => LateFee::STATUS_ACTIVE,
            ],
            [
                'name' => 'Standard Monthly Fee',
                'type' => LateFee::TYPE_FIXED,
                'value' => 50000,
                'per' => LateFee::PER_MONTH,
                'grace_days' => 7,
                'status' => LateFee::STATUS_ACTIVE,
            ],
            [
                'name' => 'Premium Daily Fee',
                'type' => LateFee::TYPE_PERCENTAGE,
                'value' => 2,
                'per' => LateFee::PER_DAY,
                'grace_days' => 3,
                'status' => LateFee::STATUS_ACTIVE,
            ],
            [
                'name' => 'Premium Monthly Fee',
                'type' => LateFee::TYPE_PERCENTAGE,
                'value' => 5,
                'per' => LateFee::PER_MONTH,
                'grace_days' => 7,
                'status' => LateFee::STATUS_INACTIVE,
            ],
        ];

        foreach ($lateFees as $lateFee) {
            LateFee::query()->updateOrCreate(
                ['name' => $lateFee['name']],
                $lateFee,
            );
        }
    }
}
