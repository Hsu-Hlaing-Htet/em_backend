<?php

namespace Database\Factories;

use App\Models\ChargeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChargeType>
 */
class ChargeTypeFactory extends Factory
{
    protected $model = ChargeType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'status' => fake()->randomElement(ChargeType::statuses()),
        ];
    }
}
