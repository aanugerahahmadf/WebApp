<x-filament::section compact class="border-2 border-dashed border-primary-500 bg-white dark:bg-gray-950">
    @php
        $methodId = $get('payment_method_id');
        $method = \App\Models\PaymentMethod\PaymentMethod::find($methodId);
    @endphp

    <div class="flex flex-col items-center justify-center p-4">
        @if($method && $method->qris_image)
            <div class="text-center mb-4">
                <x-filament::badge color="info" size="lg" class="mb-4">
                    <x-filament::icon icon="heroicon-o-qr-code" class="w-4 h-4 mr-1 inline-block" />
                    {{ __('Scan untuk Pembayaran QRIS') }}
                </x-filament::badge>
                <div class="mt-4">
                    <img src="{{ asset('storage/' . $method->qris_image) }}" class="w-64 h-64 object-contain shadow-xl rounded-2xl border-2 border-gray-100 dark:border-gray-800" alt="QRIS">
                </div>
            </div>
            <div class="text-[10px] text-gray-500 font-bold text-center uppercase tracking-widest mt-2">
                * {{ __('Simpan QRIS ini atau scan langsung dari aplikasi bank/e-wallet Anda.') }}
            </div>
        @else
            <x-filament::badge color="danger">
                {{ __('Gambar QRIS tidak tersedia.') }}
            </x-filament::badge>
        @endif
    </div>
</x-filament::section>
