@php
    $hasReviews = $reviews->isNotEmpty();
    $panelHeight = $this->expanded ? 'max-h-[92vh]' : 'max-h-[80vh]';
@endphp

{{--
    Modal "Komentar Penilaian Katalog". Tidak ada route sendiri:
    host-nya Welcome.widgets.catalog-rating-comments-modal-host yang memasang
    komponen ini, dan kelas ini yang menahan state urutan selama modal terbuka.
    Host itu sendiri tidak lagi dirender di runtime: kartunya sengaja tidak
    didaftarkan di `Home::getWidgets()`, jadi view ini hanya dipanggil oleh host
    tersebut dan tetap diuji lewat instansiasi kelas Livewire-nya langsung.

    Tidak ada kolom cari: menyaring dilakukan lewat chip pada `review-summary`,
    sama seperti halaman detail katalog.
--}}
<div>
    @if ($this->open)
        <div
            @keydown.window.escape="$wire.close()"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4 sm:p-6"
            wire:click.self="close"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Komentar Penilaian Katalog') }}"
        >
            <div
                class="flex w-full max-w-4xl flex-col gap-4 overflow-hidden rounded-2xl bg-white p-4 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-white dark:ring-white/10 sm:p-5 {{ $panelHeight }}"
            >
                <header class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold">{{ __('Komentar Penilaian Katalog') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ trans_choice(':count penilaian|:count penilaian', (int) $summary['total'], ['count' => (int) $summary['total']]) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-filament::button
                            color="gray"
                            outlined
                            icon="{{ $this->expanded ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-up' }}"
                            wire:click="toggleExpanded"
                        >
                            {{ $this->expanded ? __('Perkecil') : __('Perbesar') }}
                        </x-filament::button>
                        <x-filament::icon-button
                            icon="heroicon-o-x-mark"
                            color="gray"
                            :label="__('Tutup')"
                            wire:click="close"
                        />
                    </div>
                </header>

                <div class="flex items-center justify-end gap-2">
                    <label for="rating-comments-sort" class="text-sm text-gray-500 dark:text-gray-400">{{ __('Urutkan') }}</label>
                    <select
                        id="rating-comments-sort"
                        wire:model.live="ratingCommentsSort"
                        class="fi-input w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:w-56"
                    >
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($hasReviews)
                    @include('Welcome.components.review-summary.review-summary', $summary)
                @endif

                <div class="min-h-0 flex-1 overflow-y-auto pe-1">
                    @include('Welcome.components.review-list.review-list', [
                        'reviews' => $reviews,
                        'activeFilter' => $activeFilter,
                    ])
                </div>
            </div>
        </div>
    @endif
</div>
