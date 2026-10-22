<x-filament-panels::page>
    @if ($this->choosingCatalog)
        {{-- MODE PILIH KATALOG.
             Halaman CreateReviews tanpa record (route /reviews). Dipakai tombol
             "Tulis Ulasan" di daftar ulasan -- TIDAK lewat modal, tapi langsung
             ke halaman penuh ini. User memilih katalog, lalu halaman CreateReviews
             milik katalog itu (route /{record}/reviews) yang terbuka.

             Kartu katalognya memakai komponen grid katalog yang SAMA dengan
             halaman katalog (ukuran + isi identik: kategori, nama, harga,
             rating, stok). Yang beda cuma tautannya: ke halaman tulis ulasan. --}}
        <div class="w-full space-y-6">
            <div class="fi-section rounded-xl bg-white dark:bg-white/5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 sm:p-6">
                <label for="catalog-search" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('Cari katalog') }}
                </label>

                <input
                    id="catalog-search"
                    type="search"
                    wire:model.live.debounce.400ms="catalogSearch"
                    placeholder="{{ __('Ketik nama katalog yang ingin Anda ulas...') }}"
                    class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500"
                />

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Katalog yang sudah Anda ulas tidak akan muncul di sini.') }}
                </p>
            </div>

            @php($catalogs = $this->reviewableCatalogs())

            @if ($catalogs->isEmpty())
                <div class="fi-section rounded-xl bg-white dark:bg-white/5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6 text-center">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('Tidak ada katalog yang bisa Anda ulas saat ini.') }}
                    </p>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Semua katalog aktif sudah Anda ulas, atau belum ada katalog yang tersedia.') }}
                    </p>

                    <a
                        href="{{ $this->catalogIndexUrl() }}"
                        wire:navigate
                        class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
                    >
                        {{ __('Lihat Katalog') }}
                    </a>
                </div>
            @else
                {{ view('Welcome.components.catalog-grid.catalog-grid', [
                    'records' => $catalogs,
                    'urlResolver' => fn ($catalog) => $this->reviewUrl($catalog),
                ]) }}
            @endif
        </div>
    @else
        {{-- Form dibungkus tag <form> biasa dengan wire:submit, bukan memakai
             <x-filament-panels::form> dengan headerActions submit-nya, supaya
             tombol Kirim bisa berada di dalam kartu form dan tetap tampil di
             mobile tanpa geser ke header. --}}
        <div class="max-w-3xl mx-auto w-full">
            <form wire:submit.prevent="submit" class="space-y-6">
                <div class="fi-section rounded-xl bg-white dark:bg-white/5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 sm:p-6">
                    {{ $this->form }}
                </div>

                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="/images/icons/star.svg" color="warning">
                        {{ __('Kirim') }}
                    </x-filament::button>
                </div>
            </form>
        </div>
    @endif
</x-filament-panels::page>
