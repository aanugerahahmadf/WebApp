<?php

namespace Database\Factories;

use App\Enums\DiscountType\DiscountType;
use App\Models\Catalog\Catalog;
use App\Models\Discount\Discount;
use App\Models\Package\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DiscountType::cases());

        return [
            'description' => fake()->randomElement([
                'Diskon promo musim pernikahan',
                'Potongan harga akhir tahun',
                'Diskon bundling paket dekorasi',
                'Promo khusus pelanggan setia',
                'Diskon early bird booking 3 bulan',
            ]),
            'discountable_type' => Catalog::class,
            'discountable_id' => Catalog::factory(),
            'type' => $type,
            'value' => $type === DiscountType::FIXED
                ? fake()->randomElement([50000, 100000, 200000, 500000])
                : fake()->randomElement([5, 10, 15, 20, 25, 30]),
            'min_purchase' => fake()->randomElement([0, 500000, 1000000, 2500000]),
            'start_date' => now()->subDays(fake()->numberBetween(0, 7)),
            'end_date' => now()->addDays(fake()->numberBetween(7, 60)),
            'is_active' => true,
        ];
    }

    /**
     * Diskon untuk Katalog.
     */
    public function forCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'discountable_type' => Catalog::class,
            'discountable_id' => $catalog instanceof Catalog ? $catalog->id : ($catalog ?? Catalog::factory()),
        ]);
    }

    /**
     * Diskon untuk Paket.
     */
    public function forPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'discountable_type' => Package::class,
            'discountable_id' => $package instanceof Package ? $package->id : ($package ?? Package::factory()),
        ]);
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
     * Tipe diskon tetap (Rp).
     */
    public function fixed(float $value = 100000): static
    {
        return $this->state(fn () => [
            'type' => DiscountType::FIXED,
            'value' => $value,
        ]);
    }

    /**
     * Tipe diskon persentase (%).
     */
    public function percentage(int $percent = 15): static
    {
        return $this->state(fn () => [
            'type' => DiscountType::PERCENTAGE,
            'value' => $percent,
        ]);
    }

    /**
     * Diskon yang sudah kadaluarsa.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subDay(),
        ]);
    }

    /**
     * Diskon yang akan datang.
     */
    public function upcoming(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->addWeek(),
            'end_date' => now()->addMonths(2),
        ]);
    }

    /**
     * Diskon tanpa batas waktu.
     */
    public function unlimited(): static
    {
        return $this->state(fn () => [
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
