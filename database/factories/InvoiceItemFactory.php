<?php

namespace Database\Factories;

use App\Models\ChargeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'charge_type_id' => ChargeType::factory(),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 10000, 500000),
        ];
    }
}
