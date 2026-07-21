<?php

namespace Database\Factories;

use App\Models\PaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentPlan>
 */
class PaymentPlanFactory extends Factory
{
    protected $model = PaymentPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentType = fake()->randomElement(['full', 'installment']);

        return [
            'name' => fake()->words(3, true),
            'payment_type' => $paymentType,
            'duration_months' => $paymentType === 'installment'
                ? fake()->randomElement([3, 6, 12])
                : null,
            'interest_percentage' => $paymentType === 'installment'
                ? fake()->randomFloat(2, 0, 10)
                : 0,
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function installment(): static
    {
        return $this->state(fn () => [
            'payment_type' => 'installment',
            'duration_months' => fake()->randomElement([3, 6, 12]),
            'interest_percentage' => fake()->randomFloat(2, 0, 8),
        ]);
    }
}
