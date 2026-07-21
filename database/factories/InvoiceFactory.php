<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 100000, 5000000);

        return [
            'contract_id' => Contract::factory()->rent()->rentActive(),
            'utility_id' => null,
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
            'invoice_number' => fake()->unique()->numerify('INV-######'),
            'type' => fake()->randomElement(['rent', 'utility', 'other']),
            'issued_date' => fake()->optional(0.7)->dateTimeBetween('-3 months', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'late_fee' => 0,
            'total_amount' => $totalAmount,
            'status' => fake()->randomElement(['draft', 'issued', 'partial', 'paid', 'overdue']),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'issued_date' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_date' => now()->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'issued_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function withUtility(): static
    {
        return $this->state(fn () => [
            'utility_id' => Utility::factory()->approved(),
            'type' => 'utility',
        ]);
    }
}
