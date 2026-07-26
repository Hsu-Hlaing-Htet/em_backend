<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            BuildingSeeder::class,
            RoomSeeder::class,
            PaymentPlanSeeder::class,
            ChargeTypeSeeder::class,
            PaymentMethodSeeder::class,
            LateFeeSeeder::class,
            UtilityTypeSeeder::class,
            UtilityRateSeeder::class,
            ContractSeeder::class,
            UtilitySeeder::class,
            InvoiceSeeder::class,
            PaymentSeeder::class,
            ReceiptSeeder::class,
            MaintenanceRequestSeeder::class,
        ]);

        $this->command?->info('Seeded login credentials:');
        $this->command?->table(
            ['Role', 'Name', 'Email', 'Password'],
            collect(UserSeeder::credentials())->take(5)->all()
        );

        $this->command?->info('Customer history personas (password: p@ssword for all):');
        $this->command?->table(
            ['Persona', 'Email'],
            [
                ['Active rent', 'mgmg@rosewoodroyale.com'],
                ['Active sale', 'susu@rosewoodroyale.com'],
                ['Former rent (completed)', 'zawzaw@rosewoodroyale.com'],
                ['Former sale (completed)', 'nwenwe@rosewoodroyale.com'],
                ['Registered only', 'tuntun@rosewoodroyale.com'],
            ]
        );
    }
}
