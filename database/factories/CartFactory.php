<?php

namespace Database\Factories;

use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'catalog_id' => Catalog::factory(),
            'package_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            'meta' => [
                'notes' => fake()->optional(0.5)->randomElement([
                    'Tolong pakai warna pastel pink',
                    'Bunga impor kalau memungkinkan',
                    'Tanpa bunga lily (alergi)',
                    'Setup sebelum jam 8 pagi',
                    'Konfirmasi via WhatsApp',
                ]),
                'color_preference' => fake()->optional(0.4)->hexColor(),
                'booking_date' => fake()->optional(0.6)->dateTimeBetween('+7 days', '+90 days')?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Cart berisi Paket Dekorasi.
     */
    public function withPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => null,
            'package_id' => $package instanceof Package ? $package->id : ($package ?? Package::factory()),
        ]);
    }

    /**
     * Cart berisi Katalog Produk.
     */
    public function withCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => $catalog instanceof Catalog ? $catalog->id : ($catalog ?? Catalog::factory()),
            'package_id' => null,
        ]);
    }

    /**
     * Cart untuk user tertentu.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }

    /**
     * Cart dengan jumlah banyak.
     */
    public function bulk(int $quantity = 10): static
    {
        return $this->state(fn () => ['quantity' => $quantity]);
    }

    /**
     * Cart tanpa catatan / meta kosong.
     */
    public function withoutMeta(): static
    {
        return $this->state(fn () => ['meta' => null]);
    }
}
