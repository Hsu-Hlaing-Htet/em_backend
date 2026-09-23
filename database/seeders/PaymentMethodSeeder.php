<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    private const WALLET_PHONE = '09779959901';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Cash',
                'slug' => 'cash',
                'type' => PaymentMethod::TYPE_CASH,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'phone_number' => null,
                'account_name' => null,
                'account_number' => null,
                'instructions' => null,
                'is_customer_visible' => false,
                'sort_order' => 100,
            ],
            [
                'name' => 'KBZ Pay',
                'slug' => 'kbz-pay',
                'type' => PaymentMethod::TYPE_WALLET,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'phone_number' => self::WALLET_PHONE,
                'is_customer_visible' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'AYA Pay',
                'slug' => 'aya-pay',
                'type' => PaymentMethod::TYPE_WALLET,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'phone_number' => self::WALLET_PHONE,
                'is_customer_visible' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'uab Pay',
                'slug' => 'uab-pay',
                'type' => PaymentMethod::TYPE_WALLET,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'phone_number' => self::WALLET_PHONE,
                'is_customer_visible' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Wave Pay',
                'slug' => 'wave-pay',
                'type' => PaymentMethod::TYPE_WALLET,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'phone_number' => self::WALLET_PHONE,
                'is_customer_visible' => true,
                'sort_order' => 40,
            ],
            // Legacy office methods — keep IDs, hide from Customer Portal.
            [
                'name' => 'KBZ Bank Transfer',
                'slug' => 'kbz-bank-transfer',
                'type' => PaymentMethod::TYPE_BANK_TRANSFER,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'is_customer_visible' => false,
                'sort_order' => 110,
            ],
            [
                'name' => 'AYA Bank Transfer',
                'slug' => 'aya-bank-transfer',
                'type' => PaymentMethod::TYPE_BANK_TRANSFER,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'is_customer_visible' => false,
                'sort_order' => 120,
            ],
            [
                'name' => 'Cheque',
                'slug' => 'cheque',
                'type' => PaymentMethod::TYPE_CHEQUE,
                'status' => PaymentMethod::STATUS_INACTIVE,
                'is_customer_visible' => false,
                'sort_order' => 130,
            ],
        ];

        foreach ($methods as $method) {
            $slug = $method['slug'];
            unset($method['slug']);

            PaymentMethod::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                array_merge($method, [
                    'deleted_at' => null,
                ])
            );
        }
    }
}
