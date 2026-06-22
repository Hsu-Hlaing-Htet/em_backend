<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
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
            'phone' => fake()->numerify('+95 9 ### #####'),
            'nrc' =>
                fake()->numberBetween(1, 14)
                . '/'
                . fake()->randomElement([
                    'BaKaTa',
                    'LaKaNa',
                    'MaNyaTa',
                    'PaTaAh',
                    'KaMaTa',
                ])
                . '(N)'
                . fake()->numberBetween(100000, 999999),
            'dob' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => fake()->streetAddress().', '.fake()->city().', Myanmar',
            'avatar_path' => null,
        ];
    }
}
