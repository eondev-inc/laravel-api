<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_uuid' => null,
            'status' => 'active',
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id, 'session_uuid' => null]);
    }

    public function guest(): static
    {
        return $this->state(['user_id' => null, 'session_uuid' => fake()->uuid()]);
    }
}
