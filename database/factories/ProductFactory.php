<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'type' => ProductType::Product,
            'name' => rtrim(fake()->sentence(3), '.'),
            'code' => strtoupper(fake()->unique()->bothify('??-####')),
            'unit' => 'pcs',
            'sale_price' => fake()->randomFloat(2, 100, 50000),
            'purchase_price' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is a service.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Service,
            'unit' => 'hour',
        ]);
    }
}
