<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' '.fake()->randomElement(['Transfer', 'Pay', 'Cash']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement([
                PaymentMethod::TYPE_WALLET,
                PaymentMethod::TYPE_CASH,
                PaymentMethod::TYPE_BANK_TRANSFER,
                PaymentMethod::TYPE_CHEQUE,
                PaymentMethod::TYPE_OTHER,
            ]),
            'status' => fake()->randomElement([
                PaymentMethod::STATUS_ACTIVE,
                PaymentMethod::STATUS_INACTIVE,
            ]),
            'is_customer_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => PaymentMethod::STATUS_ACTIVE,
            'is_customer_visible' => true,
        ]);
    }

    public function wallet(): static
    {
        return $this->state(fn () => [
            'type' => PaymentMethod::TYPE_WALLET,
            'status' => PaymentMethod::STATUS_ACTIVE,
            'is_customer_visible' => true,
            'phone_number' => '09779959901',
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'type' => PaymentMethod::TYPE_CASH,
            'status' => PaymentMethod::STATUS_ACTIVE,
            'is_customer_visible' => false,
            'phone_number' => null,
        ]);
    }
}
