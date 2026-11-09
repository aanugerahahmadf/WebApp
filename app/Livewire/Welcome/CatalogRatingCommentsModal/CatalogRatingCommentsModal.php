<?php

namespace App\Livewire\Welcome\CatalogRatingCommentsModal;

use App\Filament\Concerns\RendersCatalogRatingComments;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

/**
 * Kelas isi modal "Komentar Penilaian Katalog" -- TIDAK DIPASANG DI MANA-MANA.
 *
 * Ini bukan halaman: tidak ada route, tidak ada item menu. Komponen ini juga
 * tidak lagi dibuka dari Beranda, karena kartu `CatalogRatingCommentsWidget`
 * sendiri tidak didaftarkan di `Home::getWidgets()` -- satu-satunya yang
 * me-mount modal lewat host `Welcome.widgets.catalog-rating-comments-modal-host`
 * adalah view kartu itu.
 *
 * Kelasnya tetap ada di disk mengikuti aturan "ganti nama, jangan hapus" dan
 * diuji dengan `Livewire::test` langsung. Rating + komentar pengguna dibaca di
 * section detail katalog dan di halaman `/my-reviews`.
 *
 * `#[Isolate]` memisahkan siklus render komponen dari halaman induk, supaya
 * mengganti urutan atau filter di dalam modal tidak me-render ulang seluruh
 * halaman.
 */
#[Isolate]
class CatalogRatingCommentsModal extends Component
{
    use RendersCatalogRatingComments;

    public bool $open = false;

    public bool $expanded = false;

    public string $ratingCommentsSort = 'newest';

    public function ratingCommentsSortValue(): string
    {
        return $this->ratingCommentsSort;
    }

    /** Diminta host ketika tombol "Lihat semua" pada kartu ditekan. */
    public function openRatingComments(): void
    {
        $this->open = true;
    }

    public function toggleExpanded(): void
    {
        $this->expanded = ! $this->expanded;
    }

    public function close(): void
    {
        $this->reset('ratingCommentsSort', 'expanded');

        $this->open = false;

        // Host melepas komponen setelah modal tertutup, jadi state urutan
        // tidak tertinggal di halaman induknya.
        $this->dispatch('close-rating-comments');
    }

    public function render(): View
    {
        return view('Welcome.pages.catalog-rating-comments.catalog-rating-comments-modal', [
            'summary' => $this->ratingCommentsSummaryData(__('Penilaian Katalog')),
            // `review-list` adalah komponen bersama milik detail katalog; nama
            // parameter-nya tidak ikut berubah hanya karena pemanggilnya berganti nama.
            'reviews' => $this->ratingCommentsList(),
            'activeFilter' => 'all',
            'sorts' => [
                'newest' => __('Terbaru'),
                'oldest' => __('Terlama'),
                'highest' => __('Rating Tertinggi'),
                'lowest' => __('Rating Terendah'),
                'most_helpful' => __('Paling Membantu'),
            ],
        ]);
    }
}
