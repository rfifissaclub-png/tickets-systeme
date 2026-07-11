<?php

namespace Database\Factories;

use App\Models\Tenant;
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
            'tenant_id' => Tenant::factory(),
            // Not fake()->unique(): the pool is only 5 words and uniqueness
            // is per-tenant (DB constraint), not global — tests needing
            // several distinct types for one tenant pass an explicit 'name'.
            'name' => fake()->randomElement(['Bug', 'Feature Request', 'Support', 'Billing', 'Incident']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
