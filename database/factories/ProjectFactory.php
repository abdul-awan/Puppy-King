<?php

namespace Database\Factories;

use App\Enums\BillingMethod;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'number' => fake()->unique()->numerify('PRJ-####'),
            'title' => fake()->sentence(3),
            'status' => ProjectStatus::Active,
            'billing_method' => BillingMethod::TimeAndMaterials,
            'hourly_rate' => '5000.00',
            'currency' => 'PKR',
        ];
    }
}
