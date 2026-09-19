<?php

namespace Database\Factories;

use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Buket Bunga', 'Dekorasi Panggung', 'Dekorasi Pelaminan',
            'Dekorasi Gedung', 'Bunga Meja', 'Dekorasi Outdoor',
            'Bunga Tangan', 'Hiasan Mobil', 'Souvenir Pernikahan',
            'Dekorasi Aula', 'Tenda & Lighting',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'icon' => 'heroicon-m-squares-plus',
            'color' => fake()->hexColor(),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['package', 'product']),
        ];
    }
}
