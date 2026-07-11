<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_type_id' => TicketType::factory(),
            'client_id' => User::factory(),
            'agent_id' => null,
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::Pending,
            'priority' => fake()->randomElement(TicketPriority::cases()),
        ];
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function withStatus(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
