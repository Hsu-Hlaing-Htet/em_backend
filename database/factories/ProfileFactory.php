<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => MyanmarSampleData::randomPhone(),
            'nrc' => MyanmarSampleData::randomNrc(),
            'dob' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => MyanmarSampleData::randomYangonAddress(),
            'avatar_path' => null,
        ];
    }
}
