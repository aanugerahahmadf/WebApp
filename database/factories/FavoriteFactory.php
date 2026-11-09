<?php

namespace Database\Factories;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\User\User;
use App\Models\Favorite\Favorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

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
     * Favorite untuk Paket Dekorasi.
     */
    public function forPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => null,
            'package_id' => $package instanceof Package ? $package->getKey() : ($package ?? Package::factory()),
        ]);
    }

    /**
     * Favorite untuk Katalog Produk.
     */
    public function forCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => $catalog instanceof Catalog ? $catalog->getKey() : ($catalog ?? Catalog::factory()),
            'package_id' => null,
        ]);
    }

    /**
     * Favorite untuk user tertentu.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;
        return $this->state(fn () => ['user_id' => $id]);
    }
}
