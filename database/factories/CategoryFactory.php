<?php

namespace Database\Factories;

use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            ['name' => 'Buket Bunga Tangan', 'icon' => 'heroicon-o-sparkles', 'color' => '#ec4899', 'type' => 'catalog'],
            ['name' => 'Dekorasi Pelaminan Utama', 'icon' => 'heroicon-o-home', 'color' => '#8b5cf6', 'type' => 'package'],
            ['name' => 'Dekorasi Panggung & Lorong', 'icon' => 'heroicon-o-star', 'color' => '#3b82f6', 'type' => 'package'],
            ['name' => 'Bunga Meja & Centerpiece', 'icon' => 'heroicon-o-squares-plus', 'color' => '#10b981', 'type' => 'catalog'],
            ['name' => 'Dekorasi Outdoor & Garden', 'icon' => 'heroicon-o-sun', 'color' => '#84cc16', 'type' => 'package'],
            ['name' => 'Hiasan Mobil Pengantin', 'icon' => 'heroicon-o-truck', 'color' => '#f59e0b', 'type' => 'catalog'],
            ['name' => 'Souvenir & Hadiah Bunga', 'icon' => 'heroicon-o-gift', 'color' => '#ef4444', 'type' => 'catalog'],
            ['name' => 'Photo Booth & Background Foto', 'icon' => 'heroicon-o-camera', 'color' => '#d946ef', 'type' => 'package'],
            ['name' => 'Tenda & Ambient Lighting', 'icon' => 'heroicon-o-light-bulb', 'color' => '#eab308', 'type' => 'package'],
            ['name' => 'Corsage & Boutonniere Jas', 'icon' => 'heroicon-o-heart', 'color' => '#06b6d4', 'type' => 'catalog'],
        ];

        $cat = fake()->randomElement($categories);

        return [
            'name' => $cat['name'],
            'slug' => Str::slug($cat['name']) . '-' . Str::random(5),
            'icon' => $cat['icon'],
            'color' => $cat['color'],
            'description' => fake()->sentence(12),
            'type' => $cat['type'],
        ];
    }

    /**
     * Tipe katalog tunggal.
     */
    public function catalogType(): static
    {
        return $this->state(fn () => ['type' => 'catalog']);
    }

    /**
     * Tipe paket pernikahan.
     */
    public function packageType(): static
    {
        return $this->state(fn () => ['type' => 'package']);
    }

    /**
     * Kategori Buket Bunga.
     */
    public function buketBunga(): static
    {
        return $this->state(fn () => [
            'name' => 'Buket Bunga Tangan',
            'slug' => 'buket-bunga-tangan-' . Str::random(4),
            'icon' => 'heroicon-o-sparkles',
            'color' => '#ec4899',
            'type' => 'catalog',
        ]);
    }

    /**
     * Kategori Pelaminan.
     */
    public function dekorasiPelaminan(): static
    {
        return $this->state(fn () => [
            'name' => 'Dekorasi Pelaminan Utama',
            'slug' => 'dekorasi-pelaminan-utama-' . Str::random(4),
            'icon' => 'heroicon-o-home',
            'color' => '#8b5cf6',
            'type' => 'package',
        ]);
    }

    /**
     * Kategori Bunga Meja.
     */
    public function bungaMeja(): static
    {
        return $this->state(fn () => [
            'name' => 'Bunga Meja & Centerpiece',
            'slug' => 'bunga-meja-centerpiece-' . Str::random(4),
            'icon' => 'heroicon-o-squares-plus',
            'color' => '#10b981',
            'type' => 'catalog',
        ]);
    }

    /**
     * Kategori Outdoor & Garden.
     */
    public function dekorasiOutdoor(): static
    {
        return $this->state(fn () => [
            'name' => 'Dekorasi Outdoor & Garden',
            'slug' => 'dekorasi-outdoor-garden-' . Str::random(4),
            'icon' => 'heroicon-o-sun',
            'color' => '#84cc16',
            'type' => 'package',
        ]);
    }

    /**
     * Kategori Mobil Pengantin.
     */
    public function hiasanMobil(): static
    {
        return $this->state(fn () => [
            'name' => 'Hiasan Mobil Pengantin',
            'slug' => 'hiasan-mobil-pengantin-' . Str::random(4),
            'icon' => 'heroicon-o-truck',
            'color' => '#f59e0b',
            'type' => 'catalog',
        ]);
    }

    /**
     * Kategori Photo Booth.
     */
    public function photoBooth(): static
    {
        return $this->state(fn () => [
            'name' => 'Photo Booth Pernikahan',
            'slug' => 'photo-booth-pernikahan-' . Str::random(4),
            'icon' => 'heroicon-o-camera',
            'color' => '#d946ef',
            'type' => 'package',
        ]);
    }
}
