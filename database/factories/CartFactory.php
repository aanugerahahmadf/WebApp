<?php

namespace Database\Factories;

use App\Models\Cart\Cart;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'package_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            'meta' => [
                'notes' => fake()->optional()->sentence(),
            ],
        ];
    }

    public function withPackage(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'package_id' => Package::factory(),
        ]);
    }
}
