<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
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
            'number' => fake()->unique()->numerify('QT-2627-####'),
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'status' => QuotationStatus::Draft,
            'currency' => 'PKR',
            'exchange_rate' => 1,
            'discount_percent' => 0,
        ];
    }

    /**
     * Indicate that the quotation was sent to the customer.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
