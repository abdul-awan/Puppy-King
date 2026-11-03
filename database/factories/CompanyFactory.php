<?php

namespace Database\Factories;

use App\Enums\CompanyRelationship;
use App\Models\Company;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
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
            'name' => fake()->company(),
            'relationship' => CompanyRelationship::Customer,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('042-3#######'),
            'ntn' => fake()->numerify('#######-#'),
            'sales_tax_registered' => false,
            'language' => 'en',
        ];
    }

    /**
     * Indicate that the company is registered for sales tax.
     */
    public function salesTaxRegistered(): static
    {
        return $this->state(fn (array $attributes) => [
            'strn' => fake()->numerify('#############'),
            'sales_tax_registered' => true,
        ]);
    }

    /**
     * Indicate that the company is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => CompanyRelationship::Supplier,
        ]);
    }
}
