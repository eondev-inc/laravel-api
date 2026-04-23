<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cart_id' => null,
            'status' => 'pending',
            'subtotal' => fake()->randomFloat(2, 100, 10000),
            'total' => fake()->randomFloat(2, 100, 10000),
            'token_ws' => null,
            'webpay_url' => null,
        ];
    }
}
