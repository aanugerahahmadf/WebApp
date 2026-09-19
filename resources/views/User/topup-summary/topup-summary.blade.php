<x-filament::section
    compact
    class="mt-6 bg-gray-50/50 dark:bg-white/10 shadow-none border-gray-100 dark:border-gray-800"
>
    <div class="space-y-4 py-2">
        {{-- Subtotal Row --}}
        <div class="flex justify-between items-center text-sm px-1">
            <span class="text-gray-900 dark:text-white font-black tracking-tight uppercase text-[11px]">{{ __('Subtotal') }}</span>
            <span class="font-black text-gray-900 dark:text-white text-base">
                Rp {{ number_format($amount, 2, ',', '.') }}
            </span>
        </div>
        
        {{-- Service Fee Row with more spacing and a better separator --}}
        <div class="space-y-4">
            <div class="flex justify-between items-center text-sm px-1">
                <span class="text-gray-900 dark:text-white font-black tracking-tight uppercase text-[11px]">{{ __('Biaya Layanan') }}</span>
                <x-filament::badge color="danger" size="sm" class="font-black px-2.5">
                    + Rp {{ number_format($fee, 2, ',', '.') }}
                </x-filament::badge>
            </div>
            
            {{-- Separator line with enough margin --}}
            <div class="border-b border-gray-100 dark:border-gray-800 border-dashed mx-1"></div>
        </div>
        
        {{-- Grand Total Row --}}
        <div class="flex justify-between items-end pt-2 px-1">
            <div class="flex flex-col gap-1">
                <span class="text-[12px] font-black uppercase tracking-[0.2em] text-gray-900 dark:text-yellow-400">{{ __('TOTAL TAGIHAN') }}</span>
                <span class="text-[10px] font-black text-gray-900 dark:text-white/80 uppercase tracking-widest leading-none">
                    {{ __('Termasuk pajak & admin') }}
                </span>
            </div>
            <div class="text-right">
                <span class="text-xl font-black text-primary-600 dark:text-yellow-500 leading-none tracking-tight">
                    Rp {{ number_format($total, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
</x-filament::section>
