<?php

namespace Database\Factories;

use App\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactInquiry>
 */
class ContactInquiryFactory extends Factory
{
    protected $model = ContactInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+95 9 ########'),
            'subject' => fake()->sentence(4),
            'preferred_service' => fake()->randomElement(ContactInquiry::PREFERRED_SERVICES),
            'message' => fake()->paragraph(),
            'status' => ContactInquiry::STATUS_NEW,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactInquiry::STATUS_READ,
            'read_at' => now(),
        ]);
    }
}
