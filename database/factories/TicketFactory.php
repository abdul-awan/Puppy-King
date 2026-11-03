<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'number' => fake()->unique()->numerify('TKT-####'),
            'subject' => fake()->sentence(5),
            'status' => TicketStatus::New,
            'priority' => TaskPriority::Normal,
            'channel' => TicketChannel::Email,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
        ];
    }
}
