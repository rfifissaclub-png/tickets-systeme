<?php

namespace Database\Factories;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // fake()->unique(): the name is unique platform-wide now (a
            // single global catalog, not one per tenant — see TicketType's
            // own docblock), so a small fixed word pool would collide
            // across tests almost immediately.
            'name' => ucwords(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
