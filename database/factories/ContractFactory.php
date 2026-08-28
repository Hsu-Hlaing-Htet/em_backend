<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\Support\SeedNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentType = fake()->randomElement(['full', 'installment']);
        $contractTotal = fake()->randomFloat(2, 500000, 1500000000);
        $depositAmount = round($contractTotal * fake()->randomFloat(2, 0.05, 0.15), 2);

        return [
            'contract_number' => SeedNumberGenerator::nextRentContractNumber(),
            'user_id' => User::factory()->customer(),
            'room_id' => Room::factory()->forRent(),
            'payment_plan_id' => null,
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
            'contract_total' => $contractTotal,
            'deposit_amount' => $depositAmount,
            'type' => 'rent',
            'payment_type' => $paymentType,
            'duration_months' => $paymentType === 'installment'
                ? fake()->randomElement([3, 6, 12])
                : null,
            'start_date' => fake()->dateTimeBetween('now', '+1 month'),
            'end_date' => fake()->optional(0.5)->dateTimeBetween('+6 months', '+2 years'),
            'billing_day' => $paymentType === 'installment'
                ? fake()->numberBetween(1, 28)
                : null,
            'status' => Contract::STATUS_PENDING,
            'remark' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'contract_number' => SeedNumberGenerator::nextSaleContractNumber(),
            'room_id' => Room::factory()->forSale(),
        ]);
    }

    public function rent(): static
    {
        return $this->state(fn () => [
            'type' => 'rent',
            'contract_number' => SeedNumberGenerator::nextRentContractNumber(),
            'room_id' => Room::factory()->forRent(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function rentActive(): static
    {
        return $this->state(fn () => [
            'type' => 'rent',
            'status' => Contract::STATUS_ACTIVE,
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function saleApproved(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'status' => Contract::STATUS_ACTIVE,
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_REJECTED,
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->rentActive();
    }

    public function withPaymentPlan(): static
    {
        return $this->state(fn () => [
            'payment_plan_id' => PaymentPlan::factory()->active(),
        ]);
    }
}
