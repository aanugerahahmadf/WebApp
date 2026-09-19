<?php

namespace Database\Factories;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => null,
            'product_id' => Product::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional(0.8)->paragraph(),
        ];
    }

    public function forPackage(): static
    {
        return $this->state(fn (array $attributes) => [
            'package_id' => Package::factory(),
            'product_id' => null,
        ]);
    }

    public function forProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => Product::factory(),
            'package_id' => null,
        ]);
    }

    public function highRating(): static
    {
        return $this->state(fn (array $attributes) => ['rating' => fake()->numberBetween(4, 5)]);
    }

    public function lowRating(): static
    {
        return $this->state(fn (array $attributes) => ['rating' => fake()->numberBetween(1, 2)]);
    }
}
