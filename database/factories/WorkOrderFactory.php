<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Models\Team;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
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
            'number' => fake()->unique()->numerify('WO-####'),
            'title' => fake()->sentence(4),
            'date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'location' => fake()->address(),
            'description' => fake()->paragraph(),
            'status' => WorkOrderStatus::Open,
        ];
    }

    /**
     * A completed work order.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
