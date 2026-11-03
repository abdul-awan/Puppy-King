<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\CalendarEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'team_id' => Team::factory(),
            'type' => EventType::Meeting,
            'title' => 'Meeting with '.fake()->company(),
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'status' => EventStatus::Planned,
        ];
    }
}
