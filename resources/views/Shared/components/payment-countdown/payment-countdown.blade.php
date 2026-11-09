@php
    /*
     * Kotak hitungan mundur pembayaran.
     *
     * Dipakai kartu Virtual Account, kartu QRIS, dan halaman pesanan -- jadi
     * tampilan dan hitungannya hanya ada di satu berkas ini. `paymentCountdown`
     * dideklarasikan sekali per halaman (@once) supaya beberapa kartu di satu
     * halaman tidak saling menimpa fungsi yang sama.
     */
    $countdownDeadline = isset($deadline) && $deadline ? \Illuminate\Support\Carbon::parse($deadline) : null;
    $countdownExpired = $countdownDeadline?->isPast() ?? false;
    $countdownLabel = $label ?? __('Batas Waktu Pembayaran');
@endphp

@if ($countdownDeadline)
    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 dark:bg-warning-950">
        <div class="mb-1 text-sm font-semibold text-warning-700 dark:text-warning-300">{{ $countdownLabel }}</div>

        @if ($countdownExpired)
            <div class="text-lg font-semibold text-warning-700 dark:text-warning-300">
                {{ __('Waktu pembayaran telah berakhir') }}
            </div>
        @else
            <div
                x-data="paymentCountdown('{{ $countdownDeadline->format('Y-m-d H:i:s') }}')"
                x-init="init()"
                class="text-2xl font-semibold tabular-nums text-warning-700 dark:text-warning-300"
                x-text="display"
            ></div>
        @endif

        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            {{ __('Batas akhir') }}: {{ $countdownDeadline->translatedFormat('d F Y H:i') }}
        </div>
    </div>

    @once
        <script>
            window.paymentCountdown = window.paymentCountdown || function (deadline) {
                return {
                    deadline: new Date(deadline.replace(' ', 'T')).getTime(),
                    display: '',
                    init() {
                        this.tick();
                        setInterval(() => this.tick(), 1000);
                    },
                    tick() {
                        const diff = this.deadline - Date.now();
                        if (diff <= 0) {
                            this.display = '{{ __("Waktu pembayaran telah berakhir") }}';
                            return;
                        }
                        const d = Math.floor(diff / 86400000);
                        const h = Math.floor((diff % 86400000) / 3600000);
                        const m = Math.floor((diff % 3600000) / 60000);
                        const s = Math.floor((diff % 60000) / 1000);
                        this.display = d.toString().padStart(2, '0') + ' : ' +
                            h.toString().padStart(2, '0') + ' : ' +
                            m.toString().padStart(2, '0') + ' : ' +
                            s.toString().padStart(2, '0');
                    }
                };
            };
        </script>
    @endonce
@endif
