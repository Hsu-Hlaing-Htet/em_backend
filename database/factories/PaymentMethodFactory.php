<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $type = fake()->randomElement(['cash', 'bank', 'mobile_wallet']);

        return [
            'name' => match ($type) {
                'cash' => 'Cash',
                'bank' => fake()->company().' Bank Transfer',
                default => fake()->randomElement(['KBZ Pay', 'Wave Pay', 'AYA Pay']),
            },
            'type' => $type,
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
