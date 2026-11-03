<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
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
            'date' => now()->toDateString(),
            'duration_minutes' => 60,
            'is_billable' => true,
            'hourly_rate' => '5000.00',
        ];
    }
}
