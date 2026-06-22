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
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'status' => fake()->randomElement(UtilityType::statuses()),
        ];
    }
}
