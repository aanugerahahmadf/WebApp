@php
    $order = $getRecord();
    $courier = $order?->shipping_courier;
    $courierName = $order?->shipping_courier_name;
    $trackingNumber = $order?->tracking_number;
    $trackingUrl = $order?->tracking_url;
    $shippingStatus = $order?->shipping_status;
    $shippedAt = $order?->shipped_at;
    $deliveredAt = $order?->delivered_at;
    $shippingNotes = $order?->shipping_notes;
    $destination = $order?->notes;

    $courierLabel = \App\Support\ShippingTracking\ShippingTracking::courierName($courier, $courierName);
    if (! $trackingUrl && filled($trackingNumber)) {
        $trackingUrl = \App\Support\ShippingTracking\ShippingTracking::trackingUrl($courier, $trackingNumber);
    }
    $statusLabels = \App\Support\ShippingTracking\ShippingTracking::shippingStatuses();
    $statusLabel = $shippingStatus ? ($statusLabels[$shippingStatus] ?? $shippingStatus) : null;

    $hasShipping = filled($courier) || filled($trackingNumber) || filled($trackingUrl) || filled($shippingStatus) || filled($shippingNotes);
    $events = collect();
    try {
        $events = $order?->relationLoaded('timelines')
            ? $order->timelines->where('status', 'shipping')->sortByDesc('id')->take(20)->values()
            : $order?->timelines()->where('status', 'shipping')->latest('id')->limit(20)->get();
        $events = collect($events ?? []);
    } catch (\Throwable) {
        $events = collect();
    }

    $steps = [
        'packed' => __('Dikemas'),
        'picked_up' => __('Diambil Kurir'),
        'in_transit' => __('Dalam Perjalanan'),
        'out_for_delivery' => __('Sedang Diantar'),
        'delivered' => __('Terkirim'),
    ];
    $rank = [
        'pending' => 0,
        'packed' => 1,
        'picked_up' => 2,
        'in_transit' => 3,
        'out_for_delivery' => 4,
        'delivered' => 5,
        'returned' => 5,
        'failed' => 0,
    ];
    $currentRank = $rank[$shippingStatus] ?? 0;
    $isReturned = $shippingStatus === 'returned';
    $isFailed = $shippingStatus === 'failed';
@endphp

<div class="space-y-4">
    <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-4 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Info Pengiriman') }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Resi terhubung ke kurir (J&T, SiCepat, Shopee Express, dll) untuk pelacakan paket.') }}</div>
            </div>
            @if ($statusLabel)
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                    {{ $isFailed ? 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300' : ($isReturned ? 'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300' : 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300') }}">
                    {{ $statusLabel }}
                </span>
            @endif
        </div>

        @if ($hasShipping)
            {{-- Progress ala marketplace --}}
            @if (! $isFailed)
                <div class="grid grid-cols-5 gap-2 text-center">
                    @foreach ($steps as $key => $label)
                        @php $active = $currentRank >= ($rank[$key] ?? 0) && $currentRank > 0; @endphp
                        <div class="flex flex-col items-center gap-1">
                            <div class="h-2.5 w-full rounded-full {{ $active ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                            <div class="text-[11px] leading-tight {{ $active ? 'text-primary-700 dark:text-primary-300 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-2 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Kurir Pengiriman') }}</span>
                    <span class="font-semibold text-right">{{ $courierLabel ?: __('Belum ditentukan') }}</span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Nomor Resi') }}</span>
                    @if (filled($trackingNumber))
                        <span class="font-mono font-semibold select-all text-right">{{ $trackingNumber }}</span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400 text-right">{{ __('Belum tersedia') }}</span>
                    @endif
                </div>

                @if ($shippedAt)
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Waktu Dikirim') }}</span>
                        <span class="font-medium text-right">{{ \Illuminate\Support\Carbon::parse($shippedAt)->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                @endif

                @if ($deliveredAt)
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Waktu Diterima') }}</span>
                        <span class="font-medium text-right">{{ \Illuminate\Support\Carbon::parse($deliveredAt)->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                @endif

                @if (filled($shippingNotes))
                    <div class="pt-1">
                        <div class="text-gray-600 dark:text-gray-400 mb-1">{{ __('Catatan Pengiriman') }}</div>
                        <div class="font-medium whitespace-pre-line">{{ $shippingNotes }}</div>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if (filled($trackingUrl))
                    <a href="{{ $trackingUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25m-2.25 0V6.375c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h1.5" /></svg>
                        {{ __('Lacak Paket') }}
                    </a>
                @elseif (filled($trackingNumber))
                    <span class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                        {{ __('Salin nomor resi dan lacak di situs/aplikasi :courier.', ['courier' => $courierLabel ?: __('kurir')]) }}
                    </span>
                @endif
            </div>

            @if ($events->isNotEmpty())
                <div class="pt-2">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Riwayat Pelacakan (Sinkron)') }}</div>
                    <div class="space-y-3 max-h-72 overflow-auto pr-1">
                        @foreach ($events as $event)
                            @php
                                $meta = is_array($event->metadata ?? null) ? $event->metadata : [];
                                $etime = $meta['time'] ?? null;
                            @endphp
                            <div class="flex gap-3">
                                <div class="mt-1.5 h-2.5 w-2.5 rounded-full bg-primary-500 flex-shrink-0"></div>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $event->description }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        @if ($etime) {{ $etime }} @else {{ optional($event->created_at)->translatedFormat('d M Y H:i') }} @endif
                                        @if (!empty($meta['location'])) · {{ $meta['location'] }} @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif (filled($trackingNumber))
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Belum ada riwayat checkpoint tersinkron. Jalankan sinkronisasi pelacakan (membutuhkan provider tracking/API key yang dikonfigurasi) untuk mengambil status dari J&T, SiCepat, Shopee Express, dll.') }}
                </div>
            @endif
        @else
            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-4 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Pesanan belum memiliki nomor resi. Setelah admin/toko menginput kurir dan resi (J&T, SiCepat, Shopee Express, atau kurir lain), status pengiriman dan tautan lacak akan muncul di sini.') }}
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-gray-700">
            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Lokasi Pengantaran') }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Posisi kurir/pengantar secara live (real-time) memerlukan integrasi data pelacakan GPS dari kurir/driver. Saat ini pelacakan menggunakan nomor resi dan tautan kurir (J&T, SiCepat, Shopee Express, dll.) jika tersedia.') }}
            </div>
        </div>
        <div class="p-4 space-y-3 text-sm">
            <div>
                <div class="text-gray-600 dark:text-gray-400 mb-1">{{ __('Alamat/Lokasi Tujuan') }}</div>
                @if (filled($destination))
                    <div class="font-medium whitespace-pre-line">{{ $destination }}</div>
                @else
                    <div class="text-gray-500 dark:text-gray-400">{{ __('Alamat/lokasi tujuan belum diisi.') }}</div>
                @endif
            </div>
            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-3 text-xs text-gray-600 dark:text-gray-400">
                @if ($hasShipping && filled($trackingNumber))
                    {{ __('Pelacakan aktif melalui resi: :resi. Lokasi kurir live belum tersedia tanpa integrasi tracking real-time.', ['resi' => $trackingNumber]) }}
                @else
                    {{ __('Menunggu nomor resi dan data pelacakan kurir. Lokasi kurir live akan tersedia jika integrasi tracking real-time terhubung.') }}
                @endif
            </div>
        </div>
    </div>
</div>