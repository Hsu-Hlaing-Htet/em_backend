<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Cash', 'slug' => 'cash', 'status' => 'active'],
            ['name' => 'KBZ Bank Transfer', 'slug' => 'kbz-bank-transfer', 'status' => 'active'],
            ['name' => 'AYA Bank Transfer', 'slug' => 'aya-bank-transfer', 'status' => 'active'],
            ['name' => 'KBZ Pay', 'slug' => 'kbz-pay', 'status' => 'active'],
            ['name' => 'Wave Pay', 'slug' => 'wave-pay', 'status' => 'active'],
            ['name' => 'Cheque', 'slug' => 'cheque', 'status' => 'inactive'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['slug' => $method['slug']],
                [
                    'name' => $method['name'],
                    'status' => $method['status'],
                ]
            );
        }
    }
}
