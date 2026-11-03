<?php

namespace Database\Factories;

use App\Enums\CashDirection;
use App\Models\PettyCashEntry;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashEntry>
 */
class PettyCashEntryFactory extends Factory
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
            'direction' => CashDirection::In,
            'amount' => '20000.00',
            'description' => 'Cash withdrawn from the bank',
        ];
    }
}
