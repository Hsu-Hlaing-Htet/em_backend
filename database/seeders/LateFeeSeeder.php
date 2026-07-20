<?php

namespace Database\Seeders;

use App\Models\LateFee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LateFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        if (! $admin) {
            $this->command?->warn('No admin user found. Run UserSeeder first.');

            return;
        }

        $lateFees = [
            [
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'name' => 'Standard Late Fee',
                'type' => 'fixed',
                'value' => 10000,
                'per' => 'day',
                'grace_days' => 3,
                'status' => 'active',
            ],
            [
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'name' => 'Monthly Percentage Penalty',
                'type' => 'percentage',
                'value' => 2.5,
                'per' => 'month',
                'grace_days' => 5,
                'status' => 'active',
            ],
        ];

        foreach ($lateFees as $lateFee) {
            LateFee::query()->create($lateFee);
        }
    }
}
