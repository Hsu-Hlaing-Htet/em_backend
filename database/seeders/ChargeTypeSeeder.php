<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use Illuminate\Database\Seeder;

class ChargeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chargeTypes = [
            ['name' => 'Monthly Rent', 'slug' => 'monthly-rent', 'status' => 'active'],
            ['name' => 'Security Deposit', 'slug' => 'security-deposit', 'status' => 'active'],
            ['name' => 'Utility Charges', 'slug' => 'utility-charges', 'status' => 'active'],
            ['name' => 'Late Payment Fee', 'slug' => 'late-payment-fee', 'status' => 'active'],
            ['name' => 'Maintenance Fee', 'slug' => 'maintenance-fee', 'status' => 'active'],
            ['name' => 'Sale Installment', 'slug' => 'sale-installment', 'status' => 'active'],
            ['name' => 'Booking Deposit', 'slug' => 'booking-deposit', 'status' => 'active'],
        ];

        foreach ($chargeTypes as $chargeType) {
            ChargeType::query()->updateOrCreate(
                ['slug' => $chargeType['slug']],
                [
                    'name' => $chargeType['name'],
                    'status' => $chargeType['status'],
                ]
            );
        }
    }
}
