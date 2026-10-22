<?php

namespace Database\Factories;

use App\Models\Home\HeroBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroBanner>
 */
class HeroBannerFactory extends Factory
{
    protected $model = HeroBanner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banners = [
            [
                'title' => 'Dekorasi Pernikahan Impian Anda',
                'subtitle' => 'Wujudkan momen tak terlupakan dengan rangkaian bunga segar dan desain pelaminan eksklusif.',
                'link_type' => 'catalog',
            ],
            [
                'title' => 'Promo Spesial Akhir Tahun',
                'subtitle' => 'Diskon hingga 30% untuk semua paket dekorasi pemesanan bulan ini!',
                'link_type' => 'promotion',
            ],
            [
                'title' => 'Paket Gold — Favorit Pengantin 2026',
                'subtitle' => 'Lengkap dengan pelaminan 6 meter, flower arch, dan lighting panggung profesional.',
                'link_type' => 'package',
            ],
            [
                'title' => 'Konsultasi Desain Gratis',
                'subtitle' => 'Hubungi tim kami untuk konsultasi moodboard dan palet warna tanpa biaya tambahan.',
                'link_type' => 'page',
            ],
        ];

        $banner = fake()->randomElement($banners);

        return [
            'title' => $banner['title'],
            'subtitle' => $banner['subtitle'],
            'image_url' => 'https://picsum.photos/seed/' . fake()->word() . '/1920/600',
            'link_url' => fake()->optional(0.7)->url(),
            'link_type' => $banner['link_type'],
            'link_id' => fake()->optional(0.5)->numberBetween(1, 20),
            'position' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    /**
     * Banner aktif / non-aktif.
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
     * Banner di posisi utama (pertama).
     */
    public function primary(): static
    {
        return $this->state(fn () => ['position' => 1, 'is_active' => true]);
    }

    /**
     * Banner promosi.
     */
    public function promotional(): static
    {
        return $this->state(fn () => [
            'title' => 'Flash Sale Dekorasi!',
            'subtitle' => 'Diskon 50% hanya hari ini untuk paket Platinum ke atas!',
            'link_type' => 'promotion',
        ]);
    }
}
