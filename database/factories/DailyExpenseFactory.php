<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\DailyExpense;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyExpense>
 */
class DailyExpenseFactory extends Factory
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
            'number' => fake()->unique()->numerify('DE-2627-####'),
            'date' => now()->toDateString(),
            'description' => 'Tea and biscuits for guests',
            'amount' => '850.00',
            'payment_method' => PaymentMethod::Cash,
            'paid_by' => 'Office boy',
        ];
    }
}
