<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\Support\SeedNumberGenerator;
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
            'receipt_number' => SeedNumberGenerator::nextReceiptNumber(),
            'receipt_pdf_path' => 'receipts/'.fake()->uuid().'.pdf',
            'status' => 'issued',
            'approval_status' => 'approved',
            'issued_at' => now(),
            'created_by' => User::factory()->admin(),
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'approval_status' => 'approved',
            'issued_at' => now(),
            'sent_at' => now(),
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'approval_status' => 'approved',
            'issued_at' => now(),
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
            'sent_at' => null,
            'sent_by' => null,
        ]);
    }
}
