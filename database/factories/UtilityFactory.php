<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Utility>
 */
class UtilityFactory extends Factory
{
    protected $model = Utility::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $billingMonth = Carbon::parse(fake()->dateTimeBetween('-6 months', 'now'))->startOfMonth();

        return [
            'room_id' => Room::factory()->occupied(),
            'contract_id' => null,
            'billing_month' => $billingMonth->toDateString(),
            'reading_date' => $billingMonth->copy()->addMonth()->startOfMonth()->toDateString(),
            'total_amount' => 0,
            'status' => fake()->randomElement(['draft', 'pending', 'approved', 'rejected']),
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function forContract(Contract $contract): static
    {
        $billingMonth = Carbon::parse($contract->start_date)->startOfMonth();

        return $this->state(fn () => [
            'room_id' => $contract->room_id,
            'contract_id' => $contract->id,
            'billing_month' => $billingMonth->toDateString(),
            'reading_date' => $billingMonth->copy()->addMonth()->startOfMonth()->toDateString(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }
}
