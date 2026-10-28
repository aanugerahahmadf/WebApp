@php
    /*
     * Kartu QRIS bersama untuk halaman checkout, pembayaran pesanan, dan
     * preview metode pembayaran. Gambarnya SATU: berkas statis
     * public/images/qris/qris.jpg (lewat App\Support\QrisImage\QrisImage),
     * bukan kolom `payment_methods.image_url` yang selalu NULL.
     */
    $qrisUrl = \App\Support\QrisImage\QrisImage::url();
    $qrisTitle = filled($method?->name ?? null) ? $method->name : __('QRIS');
@endphp

@if ($qrisUrl)
    <div class="qris-card flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-primary-400 bg-white p-4 text-center dark:border-primary-700 dark:bg-gray-950">
        <div class="flex items-center justify-center gap-2 text-sm font-semibold text-primary-600 dark:text-primary-400">
            <img src="/images/icons/qr.svg" alt="" class="h-4 w-4" aria-hidden="true" />
            <span>{{ __('Scan QRIS Berikut') }}</span>
        </div>

        <img
            src="{{ $qrisUrl }}"
            alt="{{ $qrisTitle }}"
            loading="lazy"
            class="w-64 max-w-full rounded-xl border border-gray-200 bg-white object-contain p-2 shadow-sm dark:border-gray-800"
        />

        <div class="flex flex-col items-center gap-1">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $qrisTitle }}</span>
            <span class="text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Pindai atau unduh gambar ini') }}
            </span>
        </div>
    </div>
@else
    <x-filament::badge color="danger">
        {{ __('Gambar QRIS tidak tersedia.') }}
    </x-filament::badge>
@endif
