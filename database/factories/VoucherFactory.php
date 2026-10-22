<?php

namespace Database\Factories;

use App\Enums\DiscountType\DiscountType;
use App\Models\Voucher\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountType = fake()->randomElement(DiscountType::cases());

        $codes = [
            'WEDDING2026', 'NIKAH10', 'NEWBRIDE', 'HAPPYDAY',
            'DEKOR20', 'BUNGA15', 'PROMO50K', 'ELEGAN25',
            'GARDEN30', 'SPESIAL', 'FIRSTORDER', 'ROMANTIC',
        ];

        $descriptions = [
            'Potongan spesial untuk dekorasi pelaminan',
            'Diskon khusus paket buket bunga pengantin',
            'Promo akhir tahun untuk semua layanan dekorasi',
            'Voucher eksklusif untuk pelanggan baru',
            'Potongan harga paket outdoor garden party',
            'Diskon lighting & tenda untuk resepsi',
            'Promo bundling dekorasi + bunga meja',
            'Cashback untuk pesanan di atas Rp 10 juta',
        ];

        return [
            'name' => fake()->randomElement($descriptions),
            'code' => fake()->randomElement($codes) . strtoupper(Str::random(4)),
            'description' => fake()->randomElement($descriptions),
            'discount_id' => \App\Models\Discount\Discount::factory(),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'is_active' => true,
            'is_global' => fake()->boolean(70),
            'max_uses' => fake()->optional(0.6)->randomElement([10, 25, 50, 100, 200, 500]),
            'uses_count' => 0,
        ];
    }

    /**
     * Voucher yang sudah kadaluarsa.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Voucher yang masih berlaku lama.
     */
    public function longValidity(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addMonths(6),
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
     * Tipe Diskon Tetap (Rp).
     */
    public function fixed(int $amount = 100000): static
    {
        return $this->state(fn () => [
            'discount_type' => DiscountType::FIXED,
            'discount_amount' => $amount,
        ]);
    }

    /**
     * Tipe Diskon Persentase (%).
     */
    public function percentage(int $percent = 15): static
    {
        return $this->state(fn () => [
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_amount' => $percent,
        ]);
    }

    /**
     * Voucher global (berlaku untuk semua produk).
     */
    public function global(): static
    {
        return $this->state(fn () => ['is_global' => true]);
    }

    /**
     * Voucher khusus produk tertentu.
     */
    public function exclusive(): static
    {
        return $this->state(fn () => ['is_global' => false]);
    }

    /**
     * Voucher sekali pakai (1 user).
     */
    public function singleUse(): static
    {
        return $this->state(fn () => ['max_uses' => 1]);
    }

    /**
     * Voucher tanpa batas pemakaian.
     */
    public function unlimited(): static
    {
        return $this->state(fn () => ['max_uses' => null]);
    }

    /**
     * Voucher yang sudah habis terpakai.
     */
    public function exhausted(): static
    {
        return $this->state(fn () => [
            'max_uses' => 10,
            'uses_count' => 10,
        ]);
    }

    /**
     * Voucher minimum pembelian tinggi.
     */
    public function highMinPurchase(float $min = 10000000): static
    {
        return $this->state(fn () => [
            'min_purchase' => $min,
            'discount_amount' => 500000,
            'discount_type' => DiscountType::FIXED,
        ]);
    }
}
