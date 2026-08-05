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
            'status' => 'draft',
            'approval_status' => 'pending',
            'issued_at' => null,
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'approval_status' => 'approved',
            'issued_at' => now(),
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'approval_status' => 'approved',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }
}
