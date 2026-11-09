{{--
    View kartu "Komentar Penilaian Katalog".

    Kartu ini SENGAJA tidak dirender lagi: `Home::getWidgets()` tidak
    mendaftarkan CatalogRatingCommentsWidget, dan tidak ada permukaan lain yang
    memakainya -- tombol "Lihat semua" di bawah ini mengirim event yang hanya
    didengar host modal di file yang sama, jadi seluruh view (termasuk
    catalog-rating-comments-modal-host) tidak punya jalur masuk runtime.
    Kelas dan view-nya dipertahankan mengikuti aturan "ganti nama, jangan
    hapus". Rating + komentar dibaca pengguna lewat section di halaman detail
    katalog dan lewat halaman `/my-reviews`.
--}}
@php
    $summary = $this->ratingCommentsSummaryData(__('Penilaian Katalog'));
    $average = (float) $summary['average'];
    $total = (int) $summary['total'];
@endphp

<x-filament-widgets::widget
    x-data="{ ratingCommentsOpen: false }"
    class="fi-wi-stats-overview catalog-rating-comments-widget"
>
    <div class="fi-wi-stats-overview-header flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-filament::icon
                :icon="$this->icon()"
                class="h-6 w-6 text-primary-600 dark:text-primary-400"
            />
            <div>
                <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ __('Komentar Penilaian Katalog') }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($total > 0)
                        {{ number_format($average, 1) }} {{ __('dari 5') }} &middot;
                        {{ trans_choice(':count penilaian|:count penilaian', $total, ['count' => $total]) }}
                    @else
                        {{ __('Belum ada ulasan') }}
                    @endif
                </p>
            </div>
        </div>

        <x-filament::button
            color="gray"
            outlined
            icon="heroicon-o-chat-bubble-left-right"
            icon-position="after"
            x-on:click="$dispatch('open-rating-comments')"
        >
            {{ __('Lihat semua') }}
        </x-filament::button>
    </div>

    @include('Welcome.widgets.catalog-rating-comments-modal-host')
</x-filament-widgets::widget>
