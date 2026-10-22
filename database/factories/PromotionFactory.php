<?php

namespace Database\Factories;

use App\Models\Home\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $promos = [
            ['title' => 'Promo Musim Pernikahan', 'desc' => 'Potongan harga spesial untuk pemesanan dekorasi di bulan baik pernikahan.', 'discount' => 20, 'code' => 'WEDDING'],
            ['title' => 'Early Bird Discount', 'desc' => 'Pesan 3 bulan sebelum acara dan dapatkan diskon ekstra untuk semua paket.', 'discount' => 15, 'code' => 'EARLYBIRD'],
            ['title' => 'Promo Bundling Buket + Dekorasi', 'desc' => 'Gabungkan buket bunga tangan dengan paket dekorasi pelaminan, hemat hingga jutaan rupiah.', 'discount' => 25, 'code' => 'BUNDLE'],
            ['title' => 'Flash Sale Akhir Pekan', 'desc' => 'Diskon terbatas hanya Sabtu-Minggu untuk paket Gold ke atas.', 'discount' => 30, 'code' => 'FLASHSALE'],
            ['title' => 'Referral Bonus', 'desc' => 'Ajak teman memesan dan dapatkan potongan untuk kedua pihak.', 'discount' => 10, 'code' => 'REFERRAL'],
        ];

        $promo = fake()->randomElement($promos);

        return [
            'title' => $promo['title'],
            'description' => $promo['desc'],
            'image_url' => 'https://picsum.photos/seed/' . fake()->word() . '/800/400',
            'discount_percent' => $promo['discount'],
            'code' => $promo['code'] . strtoupper(Str::random(3)),
            'valid_from' => now(),
            'valid_until' => now()->addMonths(fake()->numberBetween(1, 3)),
            'is_active' => true,
        ];
    }

    /**
     * Promosi aktif / non-aktif.
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
     * Promosi yang sudah kadaluarsa.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->subMonths(3),
            'valid_until' => now()->subDay(),
        ]);
    }

    /**
     * Promosi yang akan datang (belum mulai).
     */
    public function upcoming(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->addWeek(),
            'valid_until' => now()->addMonths(2),
        ]);
    }

    /**
     * Diskon besar (Flash Sale).
     */
    public function bigSale(int $percent = 50): static
    {
        return $this->state(fn () => [
            'title' => 'Mega Flash Sale!',
            'discount_percent' => $percent,
        ]);
    }
}
