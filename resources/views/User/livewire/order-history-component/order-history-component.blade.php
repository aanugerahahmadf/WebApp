<div>
    <x-filament::section aside icon="heroicon-o-shopping-bag" :heading="__('Riwayat Pesanan')">
        @php
            $packageOrders = $orders->filter(fn($o) => $o->package_id);
            $productOrders = $orders->filter(fn($o) => $o->product_id);
        @endphp

        <div class="flex gap-2 mb-4 border-b border-gray-200 dark:border-gray-700">
            <button wire:click="$set('activeTab', 'packages')" @class([
                'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
                'border-primary-600 text-primary-600' => $activeTab === 'packages',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $activeTab !== 'packages',
            ])>
                {{ __('Paket') }} ({{ $packageOrders->count() }})
            </button>
            <button wire:click="$set('activeTab', 'products')" @class([
                'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
                'border-primary-600 text-primary-600' => $activeTab === 'products',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $activeTab !== 'products',
            ])>
                {{ __('Produk') }} ({{ $productOrders->count() }})
            </button>
        </div>

        @php $displayOrders = $activeTab === 'packages' ? $packageOrders : $productOrders; @endphp

        @forelse($displayOrders as $order)
            <div class="flex gap-3 p-3 mb-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700">
                {{-- Image --}}
                <div class="shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800">
                    @if($activeTab === 'packages' && $order->package)
                        @php $img = $order->package->image_url; @endphp
                        @if($img)
                            <img src="{{ $img }}" alt="{{ $order->package->name }}" class="w-full h-full object-cover">
                        @endif
                    @elseif($activeTab === 'products' && $order->product)
                        @php $img = $order->product->image_url; @endphp
                        @if($img)
                            <img src="{{ $img }}" alt="{{ $order->product->name }}" class="w-full h-full object-cover">
                        @endif
                    @endif
                </div>

                {{-- Details --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $activeTab === 'packages' ? $order->package?->name : $order->product?->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                #{{ $order->order_number }}
                            </p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full"
                            style="background: {{ match($order->status->value) { 'pending' => '#fef3c7', 'confirmed' => '#dbeafe', 'preparing' => '#e0e7ff', 'event_day' => '#d1fae5', 'completed' => '#d1fae5', 'cancelled' => '#fee2e2', default => '#f3f4f6' } }}; color: {{ match($order->status->value) { 'pending' => '#92400e', 'confirmed' => '#1e40af', 'preparing' => '#3730a3', 'event_day' => '#065f46', 'completed' => '#065f46', 'cancelled' => '#991b1b', default => '#374151' } }};">
                            <x-filament::icon :icon="$order->status->getIcon()" class="w-3.5 h-3.5" />
                            {{ $order->status->getLabel() }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-m-calendar" class="w-3.5 h-3.5" />
                                {{ $order->event_date }}
                            </span>
                        </div>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">
                            Rp {{ number_format($order->total_price, 0, ',', '.') }}
                        </span>
                    </div>

                    @if($order->notes)
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 truncate">
                            {{ $order->notes }}
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                <x-filament::icon icon="heroicon-o-shopping-bag" class="w-12 h-12 mb-3" />
                <p class="text-sm">
                    {{ $activeTab === 'packages' ? __('Belum ada pesanan paket wedding.') : __('Belum ada pesanan produk.') }}
                </p>
            </div>
        @endforelse
    </x-filament::section>
</div>
