<?php

namespace Database\Seeders;

use App\Models\LateFee;
use Illuminate\Database\Seeder;

class LateFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lateFees = [
            [
                'name' => 'Standard Late Fee',
                'type' => 'fixed',
                'value' => 10000,
                'per' => 'day',
                'grace_days' => 3,
                'status' => 'active',
            ],
            [
                'name' => 'Monthly Percentage Penalty',
                'type' => 'percentage',
                'value' => 2.5,
                'per' => 'month',
                'grace_days' => 5,
                'status' => 'active',
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
