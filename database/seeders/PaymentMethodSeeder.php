<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PaymentMethodService::class);

        $items = [
            ['name' => 'Cash', 'status' => PaymentMethod::STATUS_ACTIVE],
            ['name' => 'KBZ Pay', 'status' => PaymentMethod::STATUS_ACTIVE],
            ['name' => 'AYA Pay', 'status' => PaymentMethod::STATUS_ACTIVE],
            ['name' => 'CB Pay', 'status' => PaymentMethod::STATUS_ACTIVE],
            ['name' => 'Wave Pay', 'status' => PaymentMethod::STATUS_ACTIVE],
            ['name' => 'Bank Transfer', 'status' => PaymentMethod::STATUS_ACTIVE],
        ];

        foreach ($items as $item) {
            $existing = PaymentMethod::query()->where('name', $item['name'])->first();

            if ($existing) {
                $service->update($existing, $item);
            } else {
                $service->create($item);
            }
        }
    }
}
