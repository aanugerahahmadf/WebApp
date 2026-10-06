{{--
    Field CheckoutAddress: ringkasan alamat + tombol Edit + modal "Ubah Alamat".

    Modul JavaScript-nya ada di `resources/js/checkout-address/checkout-address.js`
    (Alpine `checkoutAddress`) dan baru memuat Google Maps API kalau key tersedia.
    Semua state yang diedit di modal ditulis balik ke form lewat
    `Livewire.find(componentId)->set(statePath + '...')` -- bukan `$wire` langsung,
    supaya field ini tetap bekerja di Livewire manapun (checkout, draft, dll).

    Semua kontrol punya fallback tanpa Google:
      - `x-show="placesReady"` untuk peta + autocomplete,
      - input tetap bisa diketik manual,
      - tombol "Gunakan Lokasi Saya" memakai `navigator.geolocation` yang tersedia
        di browser/Android WebView tanpa API key apa pun.
--}}
@php
    $mapsKey = $field->getMapsKey();
    $statePath = $getStatePath();
    $modalId = 'checkout-address-' . $field->getId();
    // `normalize()` (bukan `(array) $getState()`) karena state lama dari
    // `Textarea::make('notes')` berupa string polos, dan `(array)` akan
    // mengubahnya jadi indeks 0 -- bukan `street`.
    $initial = \App\Forms\Components\CheckoutAddress\CheckoutAddress::normalize($getState());
    $summary = $field->getSummary();
@endphp

@if ($mapsKey !== '')
    @vite('resources/js/checkout-address/checkout-address.js')
@endif

{{--
    Wrapper diambil lewat `$getFieldWrapperView()`, bukan ditulis sebagai
    `<x-filament::form-field.wrapper>`: nama view wrapper bisa dioverride per panel
    (Laravel Components), dan blok yang salah akan gagal saat render. Semua custom
    field di proyek ini memakai cara yang sama.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="checkoutAddress({
            mapsKey: @js($mapsKey),
            componentId: @js($getLivewire()->getId()),
            statePath: @js($statePath),
            initial: @js($initial),
            labels: @js([
                'gpsDenied' => __('Izinkan akses lokasi di browser untuk mengisi otomatis.'),
                'gpsUnsupported' => __('Browser ini tidak mendukung pengambilan lokasi.'),
                'gpsFailed' => __('Lokasi tidak bisa dibaca. Coba lagi atau isi manual.'),
                'mapsMissing' => __('Pencarian alamat otomatis belum aktif. Alamat bisa diisi manual.'),
                'selectStreetFirst' => __('Pilih "Nama Jalan" terlebih dahulu.'),
            ]),
        })"
        x-on:checkout-address:applied.window="$dispatch('open-modal', { id: @js($modalId) })"
    >
        {{-- Ringkasan + tombol Edit --}}
        <div class="fi-checkout-address-summary flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $field->getLabel() }}
                    </p>

                    @if (filled($summary))
                        <p class="mt-1 text-sm text-gray-950 whitespace-pre-line dark:text-white" data-checkout-address-summary>
                            {{ $summary }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $field->getLabelText() }}
                            @if (filled(data_get($initial, 'latitude')))
                                <span class="ms-1 font-mono">({{ number_format((float) data_get($initial, 'latitude'), 5) }}, {{ number_format((float) data_get($initial, 'longitude'), 5) }})</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 italic dark:text-gray-400" data-checkout-address-summary>
                            {{ __('Belum diisi. Gunakan lokasi saat ini atau isi manual.') }}
                        </p>
                    @endif
                </div>

                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-pencil-square"
                    x-on:click="$dispatch('open-modal', { id: @js($modalId) })"
                >
                    {{ __('Edit') }}
                </x-filament::button>
            </div>

            <input type="hidden" data-checkout-address-mirror value="{{ $summary }}">
        </div>

        {{-- Modal "Ubah Alamat" --}}
        <x-filament::modal
            :id="$modalId"
            width="2xl"
            :close-button="false"
            x-on:open-modal.window="if ($event.detail.id === @js($modalId)) init()"
        >
            <x-slot name="heading">
                {{ __('Ubah Alamat') }}
            </x-slot>

            <div class="flex flex-col gap-4">
                {{-- Autocomplete 1: wilayah --}}
                <div>
                    <label class="fi-form-field-label block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $modalId }}-area">
                        {{ __('Provinsi, Kota, Kecamatan, Kode Pos') }}
                    </label>
                    <input
                        id="{{ $modalId }}-area"
                        type="text"
                        autocomplete="off"
                        data-checkout-address-area
                        x-model="draft.administrative_area"
                        x-on:keydown.escape="clearArea()"
                        @disabled(! $field->hasMapsKey())
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                        placeholder="{{ __('Ketik provinsi atau kota') }}"
                    >
                    @unless ($field->hasMapsKey())
                        <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">
                            {{ __('Pencarian alamat otomatis belum aktif (GOOGLE_MAPS_API_KEY kosong). Ketik manual saja.') }}
                        </p>
                    @endunless
                </div>

                {{-- Autocomplete 2: jalan --}}
                <div>
                    <label class="fi-form-field-label block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $modalId }}-street">
                        {{ __('Nama Jalan, Gedung, No. Rumah') }}
                    </label>
                    <input
                        id="{{ $modalId }}-street"
                        type="text"
                        autocomplete="off"
                        data-checkout-address-street
                        x-model="draft.street"
                        x-on:keydown.escape="clearStreet()"
                        @disabled(! $field->hasMapsKey())
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                        placeholder="{{ __('Ketik nama jalan atau nomor bangunan') }}"
                    >
                </div>

                {{-- Detail bebas --}}
                <div>
                    <label class="fi-form-field-label block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $modalId }}-detail">
                        {{ __('Detail Lainnya (Cth: Blok / Unit No., Patokan)') }}
                    </label>
                    <input
                        id="{{ $modalId }}-detail"
                        type="text"
                        data-checkout-address-detail
                        x-model="draft.detail"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                        placeholder="{{ __('Contoh: no rumah 16, dekat masjid') }}"
                    >
                </div>

                {{-- Peta --}}
                <div
                    class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10"
                    x-show="placesReady"
                    x-cloak
                >
                    <div
                        data-checkout-address-map
                        class="h-56 w-full"
                        style="min-height: 14rem;"
                    ></div>
                </div>

                {{-- Status GPS --}}
                <p
                    class="text-xs text-gray-500 dark:text-gray-400"
                    data-checkout-address-status
                    x-show="status"
                    x-text="status"
                    x-cloak
                ></p>

                {{-- Tandai sebagai --}}
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Tandai Sebagai:') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label class="fi-checkout-address-chip cursor-pointer">
                            <input type="radio" class="sr-only" value="home" x-model="draft.label">
                            <span class="fi-checkout-address-chip-body" :class="draft.label === 'home' ? 'fi-checkout-address-chip-body-active' : ''">
                                {{ __('Rumah') }}
                            </span>
                        </label>
                        <label class="fi-checkout-address-chip cursor-pointer">
                            <input type="radio" class="sr-only" value="office" x-model="draft.label">
                            <span class="fi-checkout-address-chip-body" :class="draft.label === 'office' ? 'fi-checkout-address-chip-body-active' : ''">
                                {{ __('Kantor') }}
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Jangan simpan ke profil --}}
                <label class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input
                        type="checkbox"
                        x-model="draft.save_for_user"
                        class="fi-checkbox rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                    >
                    <span>{{ __('Jangan simpan sebagai alamat bawaan untuk Owner/Pembeli.') }}</span>
                </label>

                {{-- Aksi --}}
                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-200 dark:hover:bg-white/5"
                        x-on:click="locate()"
                        x-bind:disabled="locating"
                    >
                        <x-filament::icon
                            icon="heroicon-o-map-pin"
                            class="h-4 w-4"
                            x-bind:class="locating && 'animate-pulse'"
                        />
                        <span x-text="locating ? @js(__('Mencari lokasi…')) : @js(__('Gunakan Lokasi Saya'))"></span>
                    </button>

                    <div class="flex items-center gap-3">
                        <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: @js($modalId) })">
                            {{ __('Nanti Saja') }}
                        </x-filament::button>

                        <x-filament::button type="button" color="primary" x-on:click="apply()">
                            {{ __('OK') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-dynamic-component>

<style>
    /* `[x-cloak]` global tidak ada di proyek ini, jadi elemen yang bergantung
       pada Alpine disembunyikan lewat aturan scoped ini saja. */
    .fi-checkout-address [x-cloak] {
        display: none !important;
    }

    .fi-checkout-address-chip-body {
        display: inline-flex;
        align-items: center;
        border-radius: .5rem;
        border: 1px solid rgb(209 213 219);
        padding: .5rem 1rem;
        font-size: .875rem;
        line-height: 1.25rem;
        color: rgb(75 85 99);
        transition: background-color .15s, border-color .15s, color .15s;
    }

    .fi-checkout-address-chip-body:hover {
        background-color: rgb(249 250 251);
    }

    .fi-checkout-address-chip-body-active,
    .fi-checkout-address-chip-body-active:hover {
        border-color: var(--primary-500, rgb(234 179 8));
        color: var(--primary-600, rgb(202 138 4));
        background-color: rgb(254 252 232);
    }

    .dark .fi-checkout-address-chip-body {
        border-color: rgb(255 255 255 / .1);
        color: rgb(209 213 219);
    }

    .dark .fi-checkout-address-chip-body-active,
    .dark .fi-checkout-address-chip-body-active:hover {
        border-color: var(--primary-400, rgb(250 204 21));
        color: var(--primary-400, rgb(250 204 21));
        background-color: rgb(255 255 255 / .05);
    }
</style>
