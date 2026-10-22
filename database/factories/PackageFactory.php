<?php

namespace Database\Factories;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tiers = [
            [
                'name' => 'Bronze Tier Package',
                'name_id' => 'Paket Bronze (Esensial)',
                'desc_id' => 'Paket dasar dekorasi pernikahan esensial untuk intimate wedding atau akad nikah sederhana.',
                'desc_en' => 'Essential bronze wedding package ideal for intimate ceremonies and simple akad nikah.',
                'price' => 3500000,
                'min_cap' => 30,
                'max_cap' => 100,
                'color' => '#cd7f32',
                'sort_order' => 1,
            ],
            [
                'name' => 'Silver Tier Package',
                'name_id' => 'Paket Silver (Anggun)',
                'desc_id' => 'Dekorasi panggung standar dengan backdrop bunga segar dan set meja akad cantik.',
                'desc_en' => 'Standard stage decor featuring fresh floral backdrop and bridal tables.',
                'price' => 6500000,
                'min_cap' => 80,
                'max_cap' => 250,
                'color' => '#94a3b8',
                'sort_order' => 2,
            ],
            [
                'name' => 'Gold Tier Package',
                'name_id' => 'Paket Gold (Elegan)',
                'desc_id' => 'Paket favorit terpopuler! Lengkap dengan pelaminan 6 meter, flower arch lorong, dan lighting panggung.',
                'desc_en' => 'Our most popular gold package! Includes 6-meter stage, floral aisle arch, and ambience lights.',
                'price' => 11500000,
                'min_cap' => 150,
                'max_cap' => 500,
                'color' => '#eab308',
                'sort_order' => 3,
            ],
            [
                'name' => 'Platinum Tier Package',
                'name_id' => 'Paket Platinum (Mewah)',
                'desc_id' => 'Dekorasi megah dengan bunga impor melimpah, gate masuk mewah, photo booth dan chandelier gantung.',
                'desc_en' => 'Luxurious package with abundant imported florals, grand entrance gate, and photo booth.',
                'price' => 18500000,
                'min_cap' => 300,
                'max_cap' => 1000,
                'color' => '#06b6d4',
                'sort_order' => 4,
            ],
            [
                'name' => 'Diamond Tier Package',
                'name_id' => 'Paket Diamond (Royal VIP)',
                'desc_id' => 'Paket mahakarya eksklusif: kustomisasi tema tanpa batas, instalasi gantung kristal, dan tim desainer pribadi.',
                'desc_en' => 'Exclusive royal VIP tier with bespoke custom design, crystal chandeliers, and personal floral stylist.',
                'price' => 32000000,
                'min_cap' => 500,
                'max_cap' => 2500,
                'color' => '#a855f7',
                'sort_order' => 5,
            ],
        ];

        $tier = fake()->randomElement($tiers);
        $hasDiscount = fake()->boolean(25);
        $discountPrice = $hasDiscount ? round($tier['price'] * fake()->randomFloat(2, 0.75, 0.90), 2) : null;

        return [
            'category_id' => Category::factory(),
            'catalog_id' => null,
            'name' => $tier['name_id'],
            'slug' => Str::slug($tier['name']) . '-' . Str::random(6),
            'description' => $tier['desc_id'],
            'name_translations' => [
                'id' => $tier['name_id'],
                'en' => $tier['name'],
            ],
            'description_translations' => [
                'id' => $tier['desc_id'],
                'en' => $tier['desc_en'],
            ],
            'price' => $tier['price'],
            'discount_price' => $discountPrice,
            'stock' => fake()->numberBetween(5, 20),
            'is_active' => true,
            'is_featured' => fake()->boolean(30),
            'sort_order' => $tier['sort_order'],
            'features' => [
                'Bunga segar berkualitas premium',
                'Backdrop pelaminan eksklusif',
                'Set meja akad / pemberkatan',
                'Karpet jalan merah / permadani',
                'Sound & lighting panggung dasar',
                'Tim instalasi dan bongkar profesional',
                'Konsultasi palet warna & layout venue gratis',
            ],
            'theme' => fake()->randomElement(['modern', 'classic', 'rustic', 'tropical', 'minimalis', 'mewah']),
            'color' => $tier['color'],
            'min_capacity' => $tier['min_cap'],
            'max_capacity' => $tier['max_cap'],
        ];
    }

    /**
     * Hubungkan ke Katalog tertentu.
     */
    public function forCatalog(Catalog|int $catalog): static
    {
        $id = $catalog instanceof Catalog ? $catalog->id : $catalog;
        return $this->state(fn () => ['catalog_id' => $id]);
    }

    /**
     * Hubungkan ke Kategori tertentu.
     */
    public function forCategory(Category|int $category): static
    {
        $id = $category instanceof Category ? $category->id : $category;
        return $this->state(fn () => ['category_id' => $id]);
    }

    /**
     * States 5 Tier Resmi (Bronze, Silver, Gold, Platinum, Diamond).
     */
    public function bronze(): static
    {
        return $this->state(fn () => [
            'name' => 'Bronze',
            'price' => 3500000,
            'discount_price' => null,
            'color' => '#cd7f32',
            'min_capacity' => 20,
            'max_capacity' => 100,
            'sort_order' => 1,
        ]);
    }

    public function silver(): static
    {
        return $this->state(fn () => [
            'name' => 'Silver',
            'price' => 6500000,
            'color' => '#94a3b8',
            'min_capacity' => 50,
            'max_capacity' => 250,
            'sort_order' => 2,
        ]);
    }

    public function gold(): static
    {
        return $this->state(fn () => [
            'name' => 'Gold',
            'price' => 11500000,
            'color' => '#eab308',
            'min_capacity' => 100,
            'max_capacity' => 500,
            'is_featured' => true,
            'sort_order' => 3,
        ]);
    }

    public function platinum(): static
    {
        return $this->state(fn () => [
            'name' => 'Platinum',
            'price' => 18500000,
            'color' => '#06b6d4',
            'min_capacity' => 250,
            'max_capacity' => 1000,
            'is_featured' => true,
            'sort_order' => 4,
        ]);
    }

    public function diamond(): static
    {
        return $this->state(fn () => [
            'name' => 'Diamond',
            'price' => 32000000,
            'color' => '#a855f7',
            'min_capacity' => 500,
            'max_capacity' => 2500,
            'is_featured' => true,
            'sort_order' => 5,
        ]);
    }

    /**
     * Status Keaktifan.
     */
    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Item Unggulan.
     */
    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function notFeatured(): static
    {
        return $this->state(fn () => ['is_featured' => false]);
    }

    /**
     * Diskon.
     */
    public function withDiscount(float $percentage = 15): static
    {
        return $this->state(function (array $attributes) use ($percentage) {
            $price = $attributes['price'] ?? 5000000;
            return [
                'discount_price' => round($price * (1 - ($percentage / 100)), 2),
            ];
        });
    }

    /**
     * Stok.
     */
    public function inStock(int $stock = 15): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    /**
     * Venue Type.
     */
    public function outdoor(): static
    {
        return $this->state(fn () => [
            'theme' => 'rustic',
            'color' => '#10b981',
            'features' => [
                'Tenda kanopi transparan / semi-outdoor',
                'Bunga segar tahan cuaca tropis',
                'Instalasi lighting fairy lights outdoor',
                'Floor platform anti becek',
            ],
        ]);
    }

    public function indoor(): static
    {
        return $this->state(fn () => [
            'theme' => 'modern',
            'color' => '#6366f1',
            'features' => [
                'Backdrop pelaminan gedung megah',
                'Chandelier kristal gantung',
                'Karpet mewah lorong utama',
                'Lighting spotlight fokus pelaminan',
            ],
        ]);
    }
}
