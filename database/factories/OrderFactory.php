<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_price' => 0,
            'status' => fake()->randomElement(['pending', 'paid', 'cancelled']),
            'created_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'updated_at' => now(),
        ];
    }
}