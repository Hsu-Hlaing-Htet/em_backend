<?php

namespace Database\Factories;

use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityItem>
 */
class UtilityItemFactory extends Factory
{
    protected $model = UtilityItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $previousReading = fake()->randomFloat(2, 100, 5000);
        $usage = fake()->randomFloat(2, 10, 500);
        $currentReading = $previousReading + $usage;
        $unitPrice = fake()->randomFloat(4, 50, 500);

        return [
            'utility_id' => Utility::factory(),
            'utility_type_id' => UtilityType::factory(),
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'usage' => $usage,
            'unit_price' => $unitPrice,
            'amount' => round($usage * $unitPrice, 2),
        ];
    }
}
