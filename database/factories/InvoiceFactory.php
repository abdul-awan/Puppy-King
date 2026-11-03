<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'number' => null,
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => InvoiceStatus::Draft,
            'currency' => 'PKR',
            'exchange_rate' => 1,
            'discount_percent' => 0,
        ];
    }

    /**
     * Indicate that the invoice was issued.
     */
    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => fake()->unique()->numerify('INV-2627-####'),
            'status' => InvoiceStatus::Open,
            'issued_at' => now(),
        ]);
    }
}
