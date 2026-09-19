<?php

namespace Database\Factories;

use App\Models\Category\Category;
use App\Models\Package\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Paket Silver', 'Paket Gold', 'Paket Platinum',
            'Paket Diamond', 'Paket Minimalis', 'Paket Mewah',
            'Paket Outdoor Garden', 'Paket Indoor Elegan', 'Paket Hemat',
            'Paket Premium', 'Paket VIP', 'Paket Eksklusif',
        ]);

        $price = fake()->randomFloat(2, 1000000, 15000000);
        $hasDiscount = fake()->boolean(25);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => fake()->paragraphs(4, true),
            'price' => $price,
            'discount_price' => $hasDiscount ? $price * fake()->randomFloat(2, 0.7, 0.95) : null,
            'stock' => fake()->numberBetween(1, 20),
            'is_active' => true,
            'is_featured' => fake()->boolean(30),
            'features' => [
                'decoration' => fake()->boolean(),
                'lighting' => fake()->boolean(),
                'sound_system' => fake()->boolean(),
                'tenda' => fake()->boolean(),
                'pelaminan' => fake()->boolean(),
                'rias_pengantin' => fake()->boolean(),
            ],
            'theme' => fake()->randomElement(['modern', 'classic', 'rustic', 'tropical', 'minimalis', 'mewah']),
            'color' => fake()->randomElement(['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#e056a0', '#a29bfe']),
            'min_capacity' => fake()->numberBetween(10, 100),
            'max_capacity' => fake()->numberBetween(101, 1000),
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
}
