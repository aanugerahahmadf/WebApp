<?php

namespace App\Filament\Welcome\Widgets\CatalogRatingCommentsWidget;

use App\Filament\Concerns\RendersCatalogRatingComments;
use App\Support\AppIcon\AppIcon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Kelas kartu "Komentar Penilaian Katalog" -- TIDAK DIPASANG DI MANA-MANA.
 *
 * BUKAN halaman dan bukan item menu: komentar semua pengguna tidak punya
 * Screen/route sendiri. Kartu ini juga sengaja TIDAK lagi muncul di Beranda --
 * `Home::getWidgets()` tidak mendaftarkannya, dan tidak ada view atau komponen
 * Livewire lain yang me-mount kelas ini.
 *
 * Datanya sendiri tidak hilang dan tidak dihapus: rating + komentar dibaca di
 * dua permukaan yang benar-benar hidup, yaitu section "Komentar Penilaian
 * Katalog" di halaman detail katalog (komponen Blade `review-summary` /
 * `review-list`) dan halaman pribadi "Komentar Penilaian Katalog Saya"
 * (`/my-reviews`).
 *
 * Kelas ini dipertahankan mengikuti aturan "ganti nama, jangan hapus" dan
 * tetap diuji dengan instansiasi langsung.
 *
 * Preview dulu memakai kartu ringkas buatan sendiri, bukan partial
 * `review-list`: kedua partial (review-summary dan review-list) menyimak
 * event Alpine `review-filter` pada window, jadi kalau preview dan modal
 * memakai partial yang sama maka tombol filter di dalam modal ikut
 * menyaring kartu di belakangnya.
 */
class CatalogRatingCommentsWidget extends Widget
{
    use RendersCatalogRatingComments;

    protected static string $view = 'Welcome.widgets.catalog-rating-comments';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /** Jumlah kartu yang ditampilkan sebelum tombol "Lihat semua". */
    public const PREVIEW_LIMIT = 3;

    /**
     * Preview tidak punya penanda urutan sendiri; accessor ini hanya pemenuhan
     * kontrak trait dan selalu mengembalikan kondisi default.
     */
    public function ratingCommentsSortValue(): string
    {
        return 'newest';
    }

    /** @return Collection<int, \App\Models\Review\Review> */
    public function previewRatingComments(): Collection
    {
        return $this->ratingCommentsQuery()->take(self::PREVIEW_LIMIT)->get();
    }

    public function icon(): string
    {
        return AppIcon::REVIEW;
    }
}
