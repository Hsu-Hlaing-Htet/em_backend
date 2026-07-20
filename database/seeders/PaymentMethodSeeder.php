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
            ['name' => 'Cash', 'type' => 'cash', 'status' => 'active'],
            ['name' => 'KBZ Bank Transfer', 'type' => 'bank', 'status' => 'active'],
            ['name' => 'AYA Bank Transfer', 'type' => 'bank', 'status' => 'active'],
            ['name' => 'KBZ Pay', 'type' => 'mobile_wallet', 'status' => 'active'],
            ['name' => 'Wave Pay', 'type' => 'mobile_wallet', 'status' => 'active'],
            ['name' => 'Cheque', 'type' => 'bank', 'status' => 'inactive'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['name' => $method['name']],
                [
                    'type' => $method['type'],
                    'status' => $method['status'],
                ]
            );
        }
    }
}
