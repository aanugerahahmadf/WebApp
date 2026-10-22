<?php

namespace Database\Factories;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reviews = [
            [
                'title' => 'Dekorasi Impian yang Sempurna!',
                'comment' => 'Sangat puas dengan hasilnya! Bunga mawarnya segar sekali, wangi, dan tata letak pelaminannya persis seperti moodboard yang kami konsultasikan. Tamu-tamu banyak yang memuji.',
                'rating' => 5,
            ],
            [
                'title' => 'Pelayanan Sangat Ramah & On-Time',
                'comment' => 'Tim dekorator datang tepat waktu di venue, proses bongkar pasang sangat rapi dan profesional. Sangat recommended untuk calon pengantin!',
                'rating' => 5,
            ],
            [
                'title' => 'Buket Bunga Cantik & Segar',
                'comment' => 'Buket bunganya sangat elegan, tahan seharian tanpa layu. Pemilihan warna pastelnya pas sekali dengan gaun pengantin saya.',
                'rating' => 5,
            ],
            [
                'title' => 'Bagus dan Rapi',
                'comment' => 'Secara umum dekorasi sangat bagus dan sesuai harga. Ada sedikit penyesuaian lighting saat acara tapi tim langsung tanggap memperbaiki.',
                'rating' => 4,
            ],
            [
                'title' => 'Cukup Puas dengan Hasilnya',
                'comment' => 'Bunga meja resepsi cantik, hanya saja backdrop pelaminan sedikit lebih kecil dari yang dibayangkan. Namun tetap berkesan dan estetik.',
                'rating' => 4,
            ],
            [
                'title' => 'Standar Sesuai Deskripsi',
                'comment' => 'Dekorasi standar oke, waktu pasang agak mepet dengan waktu akad tapi selesai tepat pada waktunya.',
                'rating' => 3,
            ],
        ];

        $sample = fake()->randomElement($reviews);

        return [
            'user_id' => User::factory(),
            'package_id' => null,
            'catalog_id' => Catalog::factory(),
            'rating' => $sample['rating'],
            'title' => $sample['title'],
            'comment' => $sample['comment'],
            'photo' => null,
            'photos' => fake()->boolean(40) ? [
                'https://picsum.photos/seed/' . fake()->word() . '/800/600',
                'https://picsum.photos/seed/' . fake()->word() . '/800/600',
            ] : null,
            'helpful_count' => fake()->numberBetween(0, 25),
        ];
    }

    /**
     * Ulasan untuk Paket Dekorasi.
     */
    public function forPackage(Package|int|null $package = null): static
    {
        return $this->state(fn () => [
            'package_id' => $package instanceof Package ? $package->id : ($package ?? Package::factory()),
            'catalog_id' => null,
        ]);
    }

    /**
     * Ulasan untuk Katalog Produk Tunggal.
     */
    public function forCatalog(Catalog|int|null $catalog = null): static
    {
        return $this->state(fn () => [
            'catalog_id' => $catalog instanceof Catalog ? $catalog->id : ($catalog ?? Catalog::factory()),
            'package_id' => null,
        ]);
    }

    /**
     * Rating Bintang 5 (Sempurna).
     */
    public function fiveStar(): static
    {
        return $this->state(fn () => [
            'rating' => 5,
            'title' => 'Luar Biasa Memuaskan & Mewah!',
            'comment' => 'Kualitas bunga luar biasa segar, dekorasi megah dan tim sangat responsif sepanjang acara.',
        ]);
    }

    /**
     * Rating Bintang 4 (Sangat Bagus).
     */
    public function fourStar(): static
    {
        return $this->state(fn () => [
            'rating' => 4,
            'title' => 'Dekorasi Sangat Bagus & Estetik',
            'comment' => 'Hasil memuaskan dan tim sangat membantu. Cocok untuk pasangan yang mencari dekorasi berkualitas.',
        ]);
    }

    /**
     * Rating Bintang 1 atau 2 (Masukan Kritis).
     */
    public function criticalFeedback(): static
    {
        return $this->state(fn () => [
            'rating' => fake()->randomElement([1, 2]),
            'title' => 'Perlu Peningkatan Ketepatan Waktu',
            'comment' => 'Bunga cantik namun proses setup terlambat 30 menit dari jadwal awal. Harap ditingkatkan koordinasinya.',
        ]);
    }

    /**
     * Ulasan yang menyertakan foto dokumentasi asli.
     */
    public function withPhotos(): static
    {
        return $this->state(fn () => [
            'photos' => [
                'https://picsum.photos/seed/wedding1/800/600',
                'https://picsum.photos/seed/wedding2/800/600',
                'https://picsum.photos/seed/wedding3/800/600',
            ],
        ]);
    }

    /**
     * Ulasan populer dengan banyak voting bermanfaat.
     */
    public function helpful(int $count = 15): static
    {
        return $this->state(fn () => ['helpful_count' => $count]);
    }
}
