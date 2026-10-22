<?php

namespace Database\Factories;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\User\User;
use App\Models\Wishlist\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => null,
            'catalog_id' => Catalog::factory(),
        ];
    }

    /**
     * Wishlist untuk Paket Dekorasi.
     */
    public function forPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => null,
            'package_id' => $package instanceof Package ? $package->id : ($package ?? Package::factory()),
        ]);
    }

    /**
     * Wishlist untuk Katalog Produk.
     */
    public function forCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => $catalog instanceof Catalog ? $catalog->id : ($catalog ?? Catalog::factory()),
            'package_id' => null,
        ]);
    }

    /**
     * Wishlist untuk user tertentu.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }
}
