@php
    $order = $getRecord();
    $tx = $order?->latestTransaction;
    $pm = $tx?->payment_method_id ? \App\Models\PaymentMethod\PaymentMethod::find($tx->payment_method_id) : null;
    $methodName = $pm?->name ?? $tx?->payment_method ?? '-';
    $methodType = $pm?->type ?? $tx?->payment_gateway ?? 'manual';
    $payable = in_array($order?->payment_status?->value, ['unpaid', 'pending', 'failed']);

    // NOMINAL dan BATAS WAKTU diambil dari satu sumber yang sama dengan
    // webhook BRI (`App\Support\PaymentWindow\PaymentWindow`). Sebelumnya
    // kartu ini menampilkan `total_price` dan memakai batas 24 jam yang
    // dihitung sendiri, padahal webhook mencocokkan `total_amount`
    // (harga + biaya admin) dan VA punya `virtual_account_expiry` sendiri.
    $amount = \App\Support\PaymentWindow\PaymentWindow::amount($order, $tx);
    $deadline = \App\Support\PaymentWindow\PaymentWindow::deadline($order, $tx);
    $expired = \App\Support\PaymentWindow\PaymentWindow::isExpired($deadline);

    // Virtual Account BRI: nomor yang dipesan unik per transaksi. Rekening
    // statis di tabel payment_methods HANYA berlaku untuk metode transfer
    // manual, jadi tidak boleh ditampilkan di sini -- kalau iya, user akan
    // transfer ke rekening admin dan pembayarannya tidak bisa dicocokkan.
    $isVirtualAccount = $methodType === 'virtual_account';
    $isQris = $methodType === 'qris';
    $qrisImage = $pm?->type === 'qris' && $pm?->qris_image_url ? $pm->qris_image_url : null;
    $vaNumber = $isVirtualAccount ? $tx?->virtual_account_no : null;
    $vaName = $isVirtualAccount ? $tx?->virtual_account_name : null;
    $vaExpiry = $isVirtualAccount ? ($tx?->virtual_account_expiry ?? $deadline) : null;
@endphp

<div class="space-y-3">
    {{-- NOMINAL: paling atas, karena inilah angka yang harus diketik pembeli
         di m-banking / aplikasi e-wallet. Tanpa angka ini nominal yang
         ditransfer bisa berbeda dan pembayaran tidak pernah cocok. --}}
    @if ($amount > 0)
        <div class="rounded-xl border-2 border-primary-300 bg-primary-50 p-4 dark:border-primary-700 dark:bg-primary-950">
            <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Nominal yang harus dibayar') }}</div>
            <div class="text-3xl font-bold tabular-nums text-primary-700 dark:text-primary-300">
                {{ \App\Support\PaymentWindow\PaymentWindow::format($amount) }}
            </div>
        </div>
    @endif

    <div class="flex flex-col gap-2 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Metode Pembayaran') }}</span>
            <span class="inline-flex items-center gap-1 text-sm font-semibold">
                @if ($pm?->image_url)
                    <img src="{{ $pm->image_url }}" alt="{{ $methodName }}" class="h-5 w-5 object-contain">
                @endif
                {{ \App\Models\PaymentMethod\PaymentMethod::typeLabel((string) $methodType) }} &middot; {{ $methodName }}
            </span>
        </div>

        @if ($pm?->bank_name)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Bank') }}</span>
                <span class="font-semibold">{{ $pm->bank_name }}</span>
            </div>
        @endif

        {{-- Virtual Account: nomor VA milik transaksi. Rekening statis dari
             payment_methods TIDAK PERNAH ditampilkan di sini, bahkan kalau
             VA gagal dibuat -- kalau iya user akan transfer ke rekening
             admin dan pembayarannya tidak akan pernah tercatat. --}}
        @if ($isVirtualAccount)
            @if ($vaNumber)
                <div class="rounded-lg border-2 border-gray-300 bg-white p-4 dark:border-gray-600 dark:bg-gray-900">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Nomor Virtual Account') }}</div>
                    <div class="font-mono text-xl font-semibold tracking-wider select-all">{{ $vaNumber }}</div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Atas Nama') }}</span>
                    <span class="font-semibold">{{ $vaName }}</span>
                </div>
                @if ($vaExpiry)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Berlaku sampai') }}</span>
                        <span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($vaExpiry)->translatedFormat('d F Y H:i') }}</span>
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-danger-300 bg-danger-50 dark:bg-danger-950 p-3 text-sm text-danger-700 dark:text-danger-300">
                    {{ __('Nomor Virtual Account belum tersedia. Silakan hubungi admin.') }}
                </div>
            @endif
        @elseif ($isQris)
            {{-- QRIS statis: gambar + nominal + hitungan mundur dalam satu
                 kartu bersama (dipakai juga oleh preview checkout). --}}
            @include('Shared.components.qris-card.qris-card', [
                'method' => $pm,
                'amount' => $amount,
                'deadline' => $deadline,
            ])
        @else
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
        @endif

        @if ($pm && $pm->fee > 0)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Biaya Admin') }}</span>
                <span class="font-semibold">{{ \App\Support\PaymentWindow\PaymentWindow::format($pm->fee) }}</span>
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

    {{-- Hitungan mundur 24 jam. QRIS sudah menampilkan hitungannya sendiri di
         dalam kartu, jadi jangan dirender dua kali di halaman yang sama. --}}
    @if ($payable && ! $isQris && $deadline && ! $expired)
        @include('Shared.components.payment-countdown.payment-countdown', ['deadline' => $deadline])
    @elseif ($payable && $expired)
        <div class="rounded-xl border border-danger-strong bg-danger-50 dark:bg-danger-950 p-4 text-danger-700 dark:text-danger-300">
            {{ __('Waktu pembayaran telah berakhir. Pesanan dapat dibatalkan atau hubungi admin.') }}
        </div>
    @endif
</div>
