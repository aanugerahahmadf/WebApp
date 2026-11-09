<?php

namespace App\Filament\Concerns;

use App\Models\Review\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data "Komentar Penilaian Katalog" lintas katalog.
 *
 * Fitur ini tidak punya halaman maupun item menu sendiri, dan juga tidak punya
 * kartu di Beranda: `Home::getWidgets()` sengaja tidak mendaftarkan
 * `CatalogRatingCommentsWidget`. Pemakai traitnya tetap dua kelas -- kartu itu
 * dan `CatalogRatingCommentsModal` yang dulu dibuka tombol "Lihat semua" dari
 * kartu -- tetapi keduanya kini tidak direferensikan runtime: tidak ada view
 * atau halaman yang me-mount-nya, dan permukaan yang benar-benar dipakai
 * pengguna adalah section di halaman detail katalog plus halaman pribadi
 * `/my-reviews`. Kedua kelas dipertahankan mengikuti aturan
 * "ganti nama, jangan hapus".
 *
 * Trait ini tidak punya properti sendiri supaya keduanya bebas memilih cara
 * menyimpan state; yang disediakan hanya cara datanya dibentuk: daftar
 * komentar yang diurutkan di server, dirender lewat komponen Blade marketplace
 * yang sudah ada (`review-list`). Angka ringkasannya (rata-rata, distribusi
 * bintang) ada di `RendersRatingCommentsSummary`.
 *
 * Tidak ada kolom cari: seperti marketplace besar, pemakai menyaring lewat chip
 * bintang / dengan-komentar / dengan-media. Chip itu dikerjakan di sisi klien
 * oleh Alpine di dalam `review-list` (sama seperti halaman detail katalog),
 * karena tombolnya mengirim event `review-filter` yang menyaring kartu tanpa
 * reload.
 */
trait RendersCatalogRatingComments
{
    use RendersRatingCommentsSummary;

    /** Nilai urutan aktif dari pemakai trait (kartu atau modal). */
    abstract public function ratingCommentsSortValue(): string;

    /** Nilai sort yang valid; selain itu jatuh ke 'newest'. */
    protected static function ratingCommentsSorts(): array
    {
        return ['newest', 'oldest', 'highest', 'lowest', 'most_helpful'];
    }

    /**
     * Daftar komentar semua pengguna, diurutkan di server dan dimuat bersama
     * relasi yang dibutuhkan kartu (penulis, balasan penjual, item).
     *
     * @return Collection<int, Review>
     */
    public function ratingCommentsList(): Collection
    {
        return $this->ratingCommentsQuery()->get();
    }

    /**
     * Query dasar komentar semua pengguna, siap dipakai lagi oleh preview
     * kartu (`CatalogRatingCommentsWidget::previewRatingComments()`) yang hanya
     * butuh beberapa komentar terbanyak.
     */
    public function ratingCommentsQuery(): Builder
    {
        $sort = in_array($this->ratingCommentsSortValue(), static::ratingCommentsSorts(), true)
            ? $this->ratingCommentsSortValue()
            : 'newest';

        return Review::query()
            ->with(['user', 'replies', 'catalog', 'package'])
            ->sorted($sort);
    }
}
