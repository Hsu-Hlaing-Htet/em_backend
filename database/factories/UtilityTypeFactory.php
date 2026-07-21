<?php

namespace Database\Factories;

use App\Models\UtilityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UtilityType>
 */
class UtilityTypeFactory extends Factory
{
    protected $model = UtilityType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Electricity',
            'Water',
            'Gas',
            'Internet',
            'Generator',
        ]).' '.fake()->numerify('##');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
