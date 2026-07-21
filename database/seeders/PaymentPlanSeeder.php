<?php

namespace Database\Seeders;

use App\Models\PaymentPlan;
use Illuminate\Database\Seeder;

class PaymentPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Full Payment',
                'payment_type' => 'full',
                'duration_months' => null,
                'interest_percentage' => 0,
                'status' => 'active',
            ],
            [
                'name' => '3-Month Installment',
                'payment_type' => 'installment',
                'duration_months' => 3,
                'interest_percentage' => 0,
                'status' => 'active',
            ],
            [
                'name' => '6-Month Installment',
                'payment_type' => 'installment',
                'duration_months' => 6,
                'interest_percentage' => 3.5,
                'status' => 'active',
            ],
            [
                'name' => '12-Month Installment',
                'payment_type' => 'installment',
                'duration_months' => 12,
                'interest_percentage' => 5,
                'status' => 'active',
            ],
            [
                'name' => 'Legacy 6-Month Plan',
                'payment_type' => 'installment',
                'duration_months' => 6,
                'interest_percentage' => 4,
                'status' => 'inactive',
            ],
        ];

        foreach ($plans as $plan) {
            PaymentPlan::query()->create($plan);
        }
    }
}
