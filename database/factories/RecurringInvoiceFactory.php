<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Models\RecurringInvoice;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringInvoice>
 */
class RecurringInvoiceFactory extends Factory
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
            'title' => 'Monthly maintenance',
            'frequency' => RecurringFrequency::Monthly,
            'start_date' => now()->toDateString(),
            'next_date' => now()->toDateString(),
            'is_active' => true,
            'currency' => 'PKR',
            'exchange_rate' => 1,
            'discount_percent' => 0,
        ];
    }
}
