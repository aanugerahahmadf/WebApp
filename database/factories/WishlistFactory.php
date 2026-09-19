<?php

namespace Database\Factories;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use App\Models\Wishlist\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => null,
            'product_id' => Product::factory(),
        ];
    }

    public function forPackage(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'package_id' => Package::factory(),
        ]);
    }
}
