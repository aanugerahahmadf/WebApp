<?php

namespace Database\Factories;

use App\Models\Category\Category;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Buket Mawar Merah', 'Buket Lily Putih', 'Dekorasi Pelaminan Minimalis',
            'Dekorasi Panggung Mewah', 'Bunga Meja Anggrek', 'Dekorasi Outdoor Taman',
            'Buket Campuran', 'Hiasan Mobil Pengantin', 'Souvenir Bunga Kering',
            'Dekorasi Aula Modern', 'Tenda Putih Elegan', 'Lighting Panggung',
        ]);

        $price = fake()->randomFloat(2, 500000, 5000000);
        $hasDiscount = fake()->boolean(30);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => fake()->paragraphs(3, true),
            'price' => $price,
            'discount_price' => $hasDiscount ? $price * fake()->randomFloat(2, 0.7, 0.95) : null,
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'features' => [
                'include_flower' => fake()->boolean(),
                'include_setup' => fake()->boolean(),
                'include_teardown' => fake()->boolean(),
            ],
            'theme' => fake()->randomElement(['modern', 'classic', 'rustic', 'tropical', 'minimalis', 'mewah']),
            'color' => fake()->randomElement(['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#e056a0', '#a29bfe']),
            'min_capacity' => fake()->optional()->numberBetween(10, 50),
            'max_capacity' => fake()->optional()->numberBetween(51, 500),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock' => 0]);
    }
}
