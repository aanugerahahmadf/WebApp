@php
    /*
     * Preview Virtual Account BRI di langkah "Metode Pembayaran".
     *
     * Nomor VA belum ada di sini: nomor itu dibuat setelah pesanan tersimpan
     * (nomornya memuat id pesanan). Isinya sengaja RINGKAS: nominal, nama
     * pemilik VA, dan hitungan mundur.
     *
     * Parameter:
     * - $amount   : nominal yang harus dibayar (float|int|null)
     * - $name     : nama pemilik VA (First Mid Last / full_name pengguna)
     * - $deadline : batas waktu (Carbon|null)
     */
    $vaAmount = isset($amount) ? (float) $amount : null;
    $vaName = isset($name) && filled($name) ? $name : null;
    $vaDeadline = isset($deadline) && $deadline ? \Illuminate\Support\Carbon::parse($deadline) : null;
@endphp

<div class="flex flex-col gap-2 rounded-2xl border-2 border-dashed border-primary-400 bg-white p-4 dark:border-primary-700 dark:bg-gray-950">
    <div class="flex items-center gap-2 text-sm font-semibold text-primary-600 dark:text-primary-400">
        <img src="/images/icons/account_balance.svg" alt="" class="h-4 w-4" aria-hidden="true" />
        <span>{{ __('Virtual Account BRI') }}</span>
    </div>

    @if ($vaAmount !== null && $vaAmount > 0)
        <div class="rounded-xl bg-primary-50 p-3 text-center dark:bg-primary-950">
            <div class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                {{ __('Nominal yang harus dibayar') }}
            </div>
            <div class="text-2xl font-bold tabular-nums text-primary-700 dark:text-primary-300">
                {{ \App\Support\PaymentWindow\PaymentWindow::format($vaAmount) }}
            </div>
        </div>
    @endif

    @if ($vaName)
        <div class="flex items-center justify-between text-xs">
            <span class="text-gray-600 dark:text-gray-400">{{ __('Atas Nama') }}</span>
            <span class="font-semibold">{{ $vaName }}</span>
        </div>
    @endif

    @if ($vaDeadline)
        @include('Shared.components.payment-countdown.payment-countdown', ['deadline' => $vaDeadline])
    @endif
</div>
