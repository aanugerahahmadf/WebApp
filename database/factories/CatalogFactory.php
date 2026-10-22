<?php

namespace Database\Factories;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Catalog>
 */
class CatalogFactory extends Factory
{
    protected $model = Catalog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $products = [
            ['name' => 'Buket Mawar Merah Mewah', 'name_en' => 'Luxury Red Rose Bouquet', 'theme' => 'classic', 'color' => '#e11d48'],
            ['name' => 'Buket Lily Putih Elegan', 'name_en' => 'Elegant White Lily Bouquet', 'theme' => 'modern', 'color' => '#ffffff'],
            ['name' => 'Buket Bunga Peony & Hydrangea', 'name_en' => 'Peony & Hydrangea Floral Bouquet', 'theme' => 'rustic', 'color' => '#f472b6'],
            ['name' => 'Dekorasi Pelaminan Rustic Garden', 'name_en' => 'Rustic Garden Stage Decoration', 'theme' => 'rustic', 'color' => '#84cc16'],
            ['name' => 'Dekorasi Panggung Modern Minimalis', 'name_en' => 'Modern Minimalist Stage Decor', 'theme' => 'minimalis', 'color' => '#3b82f6'],
            ['name' => 'Dekorasi Pelaminan Royal Diamond', 'name_en' => 'Royal Diamond Grand Backdrop', 'theme' => 'mewah', 'color' => '#fbbf24'],
            ['name' => 'Bunga Meja Anggrek Bulan Premium', 'name_en' => 'Premium Moon Orchid Table Flower', 'theme' => 'classic', 'color' => '#a855f7'],
            ['name' => 'Centerpiece Lilin & Mawar Pastel', 'name_en' => 'Candle & Pastel Rose Centerpiece', 'theme' => 'modern', 'color' => '#fda4af'],
            ['name' => 'Dekorasi Lorong Masuk & Flower Arch', 'name_en' => 'Aisle & Floral Arch Entrance Decor', 'theme' => 'garden', 'color' => '#10b981'],
            ['name' => 'Hiasan Mobil Pengantin Bunga Segar', 'name_en' => 'Fresh Flower Wedding Car Decoration', 'theme' => 'modern', 'color' => '#ec4899'],
            ['name' => 'Souvenir Kering Cantik & Wangi', 'name_en' => 'Dried Flower Scented Wedding Favors', 'theme' => 'rustic', 'color' => '#d97706'],
            ['name' => 'Dekorasi Photo Booth Bohemian Chic', 'name_en' => 'Bohemian Chic Photo Booth Decor', 'theme' => 'bohemian', 'color' => '#d946ef'],
            ['name' => 'Lighting & Fairy Lights Panggung Resepsi', 'name_en' => 'Reception Fairy Lights & Ambience Lighting', 'theme' => 'mewah', 'color' => '#eab308'],
            ['name' => 'Tenda Pernikahan Transparan Eksklusif', 'name_en' => 'Exclusive Transparent Wedding Tent', 'theme' => 'modern', 'color' => '#64748b'],
            ['name' => 'Boutonniere Jas Pengantin Pria Elegan', 'name_en' => 'Elegant Groom Boutonniere Lapel Flower', 'theme' => 'classic', 'color' => '#0284c7'],
        ];

        $item = fake()->randomElement($products);
        $nameId = $item['name'];
        $nameEn = $item['name_en'];
        $price = fake()->randomFloat(2, 500000, 7500000);
        $hasDiscount = fake()->boolean(35);
        $discountPrice = $hasDiscount ? round($price * fake()->randomFloat(2, 0.70, 0.90), 2) : null;

        $descId = fake()->paragraphs(3, true) . "\n\nSpesifikasi: Termasuk rangkaian bunga impor segar, instalasi oleh tim profesional, dan garansi kesegaran hingga acara selesai.";
        $descEn = "Premium wedding flower and decoration package. Includes fresh imported blooms, on-site professional styling, and setup/breakdown support.";

        return [
            'category_id' => Category::factory(),
            'name' => $nameId,
            'slug' => Str::slug($nameId) . '-' . Str::random(6),
            'description' => $descId,
            'name_translations' => [
                'id' => $nameId,
                'en' => $nameEn,
            ],
            'description_translations' => [
                'id' => $descId,
                'en' => $descEn,
            ],
            'price' => $price,
            'discount_price' => $discountPrice,
            'stock' => fake()->numberBetween(5, 50),
            'is_active' => true,
            'is_featured' => fake()->boolean(25),
            'features' => [
                'include_fresh_flower' => true,
                'include_setup' => true,
                'include_teardown' => true,
                'free_consultation' => true,
                'custom_color_palette' => fake()->boolean(70),
                'lighting_included' => fake()->boolean(50),
            ],
            'theme' => $item['theme'],
            'color' => $item['color'],
            'min_capacity' => fake()->numberBetween(30, 100),
            'max_capacity' => fake()->numberBetween(150, 1000),
        ];
    }

    /**
     * Sinkronkan override `name` ke `name_translations`.
     *
     * `Catalog::getNameAttribute()` membaca `trans('name')` DULU, dan baru
     * jatuh ke kolom `name` kalau translations-nya kosong. Akibatnya
     * `Catalog::factory()->create(['name' => 'X'])` TIDAK menghasilkan nama
     * `X`: yang muncul tetap nama dari daftar produk di `definition()`.
     *
     * Dan itu tidak kelihatan. Barisnya tetap tersimpan, tidak ada error, test
     * jalan -- hanya assertion yang membandingkan nama jadi gagal jauh dari
     * penyebabnya. Test yang paling terpengaruh di repo ini adalah
     * `WelcomeChatAdminTest`, di mana `meta.name` pada context card chat
     * men takersis nama katalog lain padahal `meta.id`-nya benar.
     *
     * Yang perlu diketahui sebelum memakai state ini:
     *
     * - `afterMaking` dipakai, bukan `configure()`. `configure()` berjalan
     *   SEBELUM state dari `create()` digabung, jadi override pemanggil akan
     *   menimpanya lagi dan sinkronisasi ini jadi tidak berlaku.
     * - `getRawOriginal('name')` dibaca, bukan `$catalog->name`. Yang kedua
     *   sudah melewati accessor, jadi selalu mengembalikan nama dari
     *   translations -- persis yang tidak ingin dikejar di sini.
     * - Kalau pemanggil override `name_translations` juga, itu yang menang:
     *   `name_translations` sengaja tidak disentuh di sini.
     */
    /*
 * CATATAN UNTUK PENULIS TEST -- kenapa override `name` tidak tampak.
 *
 * `Catalog::getNameAttribute()` membaca `trans('name')` lebih dulu dan baru
 * jatuh ke kolom `name` kalau `name_translations`-nya kosong. Jadi
 *
 *     Catalog::factory()->create(['name' => 'X'])
 *
 * TIDAK menghasilkan katalog bernama `X`; yang tampil tetap nama dari daftar
 * produk di `definition()`. Tidak ada error dan baris tetap tersimpan -- gejalanya
 * hanya assertion yang membandingkan nama jadi gagal jauh dari penyebabnya. Di
 * `WelcomeChatAdminTest` gejalanya `meta.name` pada context card chat berisi
 * nama katalog lain sementara `meta.id`-nya benar.
 *
 * Yang benar untuk test adalah men-set translation-nya:
 *
 *     Catalog::factory()->create([
 *         'name_translations' => ['id' => 'Bunga Mawar Test', 'en' => 'Red Rose Test'],
 *     ]);
 *
 * Sengaja TIDAK ada sinkronisasi otomatis di factory ini. Sinkronisasi berarti
 * factory harus menebak apakah `name` yang masuk memang override pemanggil atau
 * bawaan `definition()`, dan `definition()` sudah selesai di-merge sebelum
 * callback apa pun berjalan -- jadi tidak ada sumber yang bisa dipakai untuk
 * membedakannya tanpa rapuh. Menebak di sini lebih berbahaya daripada membiarkan
 * pemanggil memakai API yang benar, karena `CatalogFactory` juga dipakai seeder.
 */

    /**
     * Set specific category.
     */
    public function forCategory(Category|int $category): static
    {
        $id = $category instanceof Category ? $category->id : $category;
        return $this->state(fn () => ['category_id' => $id]);
    }

    /**
     * Status aktif / non-aktif.
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
     * Item pilihan / unggulan.
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
     * Manajemen stok.
     */
    public function inStock(int $stock = 25): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    /**
     * Potongan harga diskon.
     */
    public function withDiscount(float $percentage = 20): static
    {
        return $this->state(function (array $attributes) use ($percentage) {
            $price = $attributes['price'] ?? 1000000;
            return [
                'discount_price' => round($price * (1 - ($percentage / 100)), 2),
            ];
        });
    }

    public function withoutDiscount(): static
    {
        return $this->state(fn () => ['discount_price' => null]);
    }

    /**
     * States berdasarkan Tema Dekorasi.
     */
    public function rustic(): static
    {
        return $this->state(fn () => [
            'theme' => 'rustic',
            'color' => '#84cc16',
        ]);
    }

    public function modern(): static
    {
        return $this->state(fn () => [
            'theme' => 'modern',
            'color' => '#3b82f6',
        ]);
    }

    public function classic(): static
    {
        return $this->state(fn () => [
            'theme' => 'classic',
            'color' => '#e11d48',
        ]);
    }

    public function luxury(): static
    {
        return $this->state(fn () => [
            'theme' => 'mewah',
            'color' => '#fbbf24',
            'price' => fake()->randomFloat(2, 5000000, 15000000),
            'is_featured' => true,
        ]);
    }

    public function garden(): static
    {
        return $this->state(fn () => [
            'theme' => 'garden',
            'color' => '#10b981',
        ]);
    }

    public function intimate(): static
    {
        return $this->state(fn () => [
            'min_capacity' => 10,
            'max_capacity' => 80,
            'price' => fake()->randomFloat(2, 750000, 2500000),
        ]);
    }

    public function grandBallroom(): static
    {
        return $this->state(fn () => [
            'min_capacity' => 250,
            'max_capacity' => 2000,
            'price' => fake()->randomFloat(2, 8000000, 25000000),
        ]);
    }
}
