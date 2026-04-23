<?php

namespace Database\Factories;

use App\Models\Design;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Design>
 */
class DesignFactory extends Factory
{
    public function definition(): array
    {
        $ext = fake()->randomElement(['png', 'jpg']);

        return [
            'name' => fake()->words(2, true),
            'file_path' => 'designs/'.fake()->uuid().'.'.$ext,
            'file_url' => fake()->imageUrl(),
            'file_extension' => $ext,
            'is_active' => true,
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
        ];
    }
}
