<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->issued(),
            'payment_method_id' => PaymentMethod::factory()->active(),
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
            'amount' => fake()->randomFloat(2, 50000, 2000000),
            'proof_image_path' => fake()->optional(0.3)->filePath(),
            'note' => fake()->optional(0.5)->sentence(),
            'payment_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'amount' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }
}
