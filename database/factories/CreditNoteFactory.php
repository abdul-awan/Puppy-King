<?php

namespace Database\Factories;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->issued(),
            'team_id' => fn (array $attributes) => Invoice::query()->withoutGlobalScopes()->whereKey($attributes['invoice_id'])->value('team_id'),
            'number' => null,
            'date' => now()->toDateString(),
            'status' => CreditNoteStatus::Draft,
            'currency' => 'PKR',
            'exchange_rate' => 1,
            'discount_percent' => 0,
        ];
    }
}
