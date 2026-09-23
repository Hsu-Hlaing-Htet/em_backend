<?php

namespace Database\Factories;

use App\Models\MaintenanceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenanceCategory>
 */
class MaintenanceCategoryFactory extends Factory
{
    protected $model = MaintenanceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'status' => fake()->randomElement([
                MaintenanceCategory::STATUS_ACTIVE,
                MaintenanceCategory::STATUS_INACTIVE,
            ]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => MaintenanceCategory::STATUS_ACTIVE]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => MaintenanceCategory::STATUS_INACTIVE]);
    }
}
