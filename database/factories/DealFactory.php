<?php

namespace Database\Factories;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\DealPipeline;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'value' => fake()->randomFloat(2, 10000, 5000000),
            'currency' => 'PKR',
            'status' => DealStatus::Open,
        ];
    }

    /**
     * Place the deal in the first phase of the team's default pipeline.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(function () use ($team) {
            $pipeline = DealPipeline::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->orderByDesc('is_default')
                ->firstOrFail();

            return [
                'team_id' => $team->id,
                'pipeline_id' => $pipeline->id,
                'phase_id' => $pipeline->phases()->withoutGlobalScopes()->firstOrFail()->id,
            ];
        });
    }
}
