{{--
    Grid huruf di dalam modal.

    Setiap kartu menampilkan NAMA huruf dengan bentuk hurufnya sendiri
    (`font-family` inline + `!important` -- aturan global `* { font-family:
    var(--app-font-family) !important }` di app-font.css akan menimpa
    style inline yang biasa, jadi `!important` wajib di sini).

    Kotak pencarian hanya MEMBATASI daftar yang tampil (246 keluarga terlalu
    banyak untuk digulir) -- itu bukan "select searchable": yang dipilih
    tetap kartu hurunya, bukan hasil pencarian dropdown.
--}}
<div x-data="{ query: '' }" class="fi-font-picker-grid">
    <div class="mb-4">
        <x-filament::input.wrapper>
            <input
                type="search"
                x-model="query"
                placeholder="{{ __('Cari huruf...') }}"
                class="fi-input block w-full border-none bg-transparent py-2 text-sm text-gray-950 transition duration-100 placeholder:text-gray-400 focus:ring-0 dark:text-white"
            />
        </x-filament::input.wrapper>
    </div>

    <div class="fi-font-picker-grid-list grid max-h-[55vh] grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($families as $family)
            <button
                type="button"
                wire:click="applyFont('{{ addslashes($family) }}')"
                x-show="! query || '{{ \Illuminate\Support\Str::lower($family) }}'.includes(query.toLowerCase())"
                style="font-family: '{{ $family }}' !important;"
                @class([
                    'fi-font-picker-card flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
                    'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $family === $current,
                    'border-gray-200 bg-white hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10' => $family !== $current,
                ])
            >
                <span class="truncate">{{ $family }}</span>

                @if ($family === $current)
                    <x-filament::icon icon="/images/icons/check.svg" class="h-4 w-4 shrink-0 text-primary-500" />
                @endif
            </button>
        @endforeach
    </div>

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        {{ __('Semua huruf di atas terpasang lokal di aplikasi -- tidak ada yang diambil dari internet.') }}
    </p>
</div>
