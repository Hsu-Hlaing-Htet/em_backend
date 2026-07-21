<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->approved(),
            'receipt_number' => fake()->unique()->numerify('RCP-######'),
            'receipt_pdf_path' => fake()->optional(0.5)->filePath(),
            'status' => fake()->randomElement(['draft', 'issued']),
            'issued_at' => fake()->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
