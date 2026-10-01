@php
    $order = $getRecord();
    $tx = $order?->latestTransaction;
    $pm = $tx?->payment_method_id ? \App\Models\PaymentMethod\PaymentMethod::find($tx->payment_method_id) : null;
    $methodName = $pm?->name ?? $tx?->payment_method ?? '-';
    $methodType = $pm?->type ?? $tx?->payment_gateway ?? 'manual';
    $payable = in_array($order?->payment_status?->value, ['unpaid', 'pending', 'failed', 'partial']);
    $deadline = \Carbon\Carbon::parse($order?->created_at)->addHours(24);
    $expired = $deadline->isPast();
@endphp

<div class="space-y-3">
    <div class="flex flex-col gap-2 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Metode Pembayaran') }}</span>
            <span class="inline-flex items-center gap-1 text-sm font-semibold">
                @if ($pm?->image_url)
                    <img src="{{ $pm->image_url }}" alt="{{ $methodName }}" class="h-5 w-5 object-contain">
                @endif
                {{ \App\Models\PaymentMethod\PaymentMethod::typeLabel((string) $methodType) }} · {{ $methodName }}
            </span>
        </div>

        @if ($pm?->bank_name)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Bank') }}</span>
                <span class="font-semibold">{{ $pm->bank_name }}</span>
            </div>
        @endif
        @if ($pm?->account_number)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('No. Rekening') }}</span>
                <span class="font-mono font-semibold">{{ $pm->account_number }}</span>
            </div>
        @endif
        @if ($pm?->account_holder)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Atas Nama') }}</span>
                <span class="font-semibold">{{ $pm->account_holder }}</span>
            </div>
        @endif
        @if ($pm && $pm->fee > 0)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Biaya Admin') }}</span>
                <span class="font-semibold">Rp {{ number_format($pm->fee, 0, ',', '.') }}</span>
            </div>
        @endif

        @if ($pm?->type === 'qris' && $pm?->image_url)
            <div class="mt-2 flex justify-center">
                <img src="{{ $pm->image_url }}" alt="QRIS" class="max-h-56 rounded-lg bg-white object-contain p-2 shadow-sm">
            </div>
        @endif

        @if($pm?->type === 'cash')
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Pembayaran') }}</span>
                <span class="font-semibold">{{ __('Bayar di Tempat saat layanan tiba') }}</span>
            </div>
        @endif
    </div>

    @if ($pm?->instructions)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="mb-2 text-sm font-semibold">{{ __('Instruksi Pembayaran') }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $pm->instructions }}</div>
        </div>
    @endif

    @if ($paymentStatus && ! $expired)
        <div class="rounded-xl border border-warning-300 bg-warning-50 dark:bg-warning-950 p-4">
            <div class="mb-1 text-sm font-semibold text-warning-700 dark:text-warning-300">{{ __('Batas Waktu Pembayaran') }}</div>
            <div
                x-data="paymentCountdown('{{ $deadline->format('Y-m-d H:i:s') }}')"
                x-init="init()"
                class="text-2xl font-bold tabular-nums text-warning-700 dark:text-warning-300"
                x-text="display"
            ></div>
            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('Batas akhir') }}: {{ $deadline->translatedFormat('d F Y H:i') }}</div>
        </div>
        <script>
            function paymentCountdown(deadline) {
                return {
                    deadline: new Date(deadline.replace(' ', 'T')).getTime(),
                    display: '',
                    init() {
                        this.tick();
                        setInterval(() => this.tick(), 1000);
                    },
                    tick() {
                        const diff = this.deadline - Date.now();
                        if (diff <= 0) { this.display = '{{ __("Waktu pembayaran telah berakhir") }}'; return; }
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
            }
        </script>
    @elseif ($payable && $expired)
        <div class="rounded-xl border border-danger-strong bg-danger-50 dark:bg-danger-950 p-4 text-danger-700 dark:text-danger-300">
            {{ __('Waktu pembayaran telah berakhir. Pesanan dapat dibatalkan atau hubungi admin.') }}
        </div>
    @endif
</div>