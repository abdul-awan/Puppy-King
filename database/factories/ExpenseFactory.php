<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'number' => fake()->unique()->numerify('EXP-2627-####'),
            'title' => 'Office rent',
            'supplier_name' => fake()->company(),
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'currency' => 'PKR',
            'exchange_rate' => 1,
            'net_amount' => '100000.00',
            'tax_amount' => '0.00',
            'total' => '100000.00',
            'status' => ExpenseStatus::Pending,
        ];
    }

    /**
     * Indicate that the expense was approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
