<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariation>
 */
class ProductVariationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'price' => fake()->randomFloat(2, 1, 200),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'product_id' => Product::factory(),
        ];
    }
}
