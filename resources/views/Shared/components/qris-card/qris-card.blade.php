@php
    /*
     * Kartu QRIS bersama untuk halaman checkout, pembayaran pesanan, dan
     * preview metode pembayaran. Gambarnya SATU: berkas statis
     * public/images/qris/qris.jpg (lewat App\Support\QrisImage\QrisImage),
     * bukan kolom `payment_methods.image_url` yang selalu NULL.
     *
     * Isinya sengaja RINGKAS: gambar QR, nominal, dan hitungan mundur.
     * Tidak ada kalimat penjelasan tambahan.
     *
     * Parameter opsional:
     * - $amount   : nominal yang harus dibayar (float|int|null)
     * - $deadline : batas waktu (Carbon|null)
     */
    $qrisUrl = \App\Support\QrisImage\QrisImage::url();
    $qrisTitle = filled($method?->name ?? null) ? $method->name : __('QRIS');
    $qrisAmount = isset($amount) ? (float) $amount : null;
    $qrisDeadline = isset($deadline) && $deadline ? \Illuminate\Support\Carbon::parse($deadline) : null;
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

        @if ($qrisAmount !== null && $qrisAmount > 0)
            <div class="w-full rounded-xl bg-primary-50 p-3 dark:bg-primary-950">
                <div class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                    {{ __('Nominal yang harus dibayar') }}
                </div>
                <div class="text-2xl font-bold tabular-nums text-primary-700 dark:text-primary-300">
                    {{ \App\Support\PaymentWindow\PaymentWindow::format($qrisAmount) }}
                </div>
            </div>
        @endif

        @if ($qrisDeadline)
            @include('Shared.components.payment-countdown.payment-countdown', ['deadline' => $qrisDeadline])
        @endif
    </div>
@else
    <x-filament::badge color="danger">
        {{ __('Gambar QRIS tidak tersedia.') }}
    </x-filament::badge>
@endif
