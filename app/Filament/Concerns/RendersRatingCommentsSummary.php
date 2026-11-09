<?php

namespace App\Filament\Concerns;

use App\Models\Review\Review;

/**
 * Angka ringkasan "Komentar Penilaian Katalog": skor rata-rata, jumlah
 * penilaian, distribusi bintang, dan jumlah komentar yang punya teks/media.
 *
 * Dipakai oleh dua bentuk yang berbeda: kartu ringkas `CatalogRatingCommentsWidget`
 * (hanya butuh angka ini) dan modal "Lihat semua" (angka ini dirender sebagai
 * header + chip filter lewat komponen Blade `review-summary`). Keduanya hanya
 * kelas: kartu itu tidak didaftarkan di `Home::getWidgets()`, jadi tidak ada
 * Beranda yang merendernya. Yang benar-benar terpakai di UI adalah
 * `review-summary` pada halaman detail katalog dan pada daftar `/my-reviews`.
 *
 * Angkanya selalu agregat SELURUH penilaian, bukan hasil filter aktif, supaya
 * skor rata-rata dan distribusi bintang tidak berubah-ubah saat pengguna
 * menekan chip -- sama seperti marketplace besar.
 */
trait RendersRatingCommentsSummary
{
    /**
     * Filter aktif dari query string (`review_filter`), dipakai Blade untuk
     * menandai tombol yang terpilih dan menyaring kartu di sisi klien.
     */
    public function activeRatingCommentFilter(): string
    {
        $filter = (string) request()->query('review_filter', 'all');

        return in_array(
            $filter,
            ['all', 'rating_1', 'rating_2', 'rating_3', 'rating_4', 'rating_5', 'comment', 'media'],
            true
        )
            ? $filter
            : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    public function ratingCommentsSummaryData(string $heading): array
    {
        $ratingCounts = Review::query()
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        return [
            'heading' => $heading,
            'total' => Review::query()->count(),
            'average' => round((float) Review::query()->avg('rating'), 1),
            'ratingCounts' => $ratingCounts,
            'commentCount' => Review::query()->withComment()->count(),
            'mediaCount' => Review::query()->withMedia()->count(),
            'activeFilter' => $this->activeRatingCommentFilter(),
        ];
    }
}
