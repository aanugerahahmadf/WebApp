@php
    $methodId = $get('payment_method_id');
    $method = \App\Models\PaymentMethod\PaymentMethod::find($methodId);
@endphp

@if($method)
    <x-filament::section
        compact
        class="p-4 bg-gray-50/50 dark:bg-gray-950/20 rounded-3xl border-gray-100 dark:border-gray-800 transition-all shadow-none"
    >
        <div class="flex items-center gap-5 mb-5 p-2 bg-white dark:bg-gray-950 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
            <div class="p-4 bg-primary-100 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400">
                @if($method->type === 'bank_transfer')
                    <x-filament::icon icon="heroicon-o-building-library" class="w-8 h-8" />
                @else
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" class="w-8 h-8" />
                @endif
            </div>
            <div>
                <x-filament::badge 
                    color="primary" 
                    size="sm" 
                    class="font-bold uppercase tracking-[0.2em] mb-1"
                >
                    {{ $method->type->getLabel() }}
                </x-filament::badge>
                <h4 class="text-xl font-bold text-gray-950 dark:text-white leading-tight">{{ $method->name }}</h4>
                @if($method->fee > 0)
                    <p class="text-[10px] font-bold text-danger-500 dark:text-danger-400 mt-1 uppercase tracking-wider">
                        {{ __('Biaya Admin:') }} Rp {{ number_format($method->fee, 0, ',', '.') }}
                    </p>
                @endif
            </div>
        </div>

        @if($method->account_number)
            <x-filament::section
                compact
                class="bg-white dark:bg-gray-950 rounded-2xl border-primary-100 dark:border-primary-900 shadow-xl shadow-primary-500/5 text-center relative overflow-hidden group"
            >
                <div class="flex flex-col items-center justify-center p-3">
                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-3">
                        {{ __('NOMOR TUJUAN') }}
                    </span>
                    <span class="text-3xl font-bold text-primary-600 dark:text-primary-400 tracking-[0.1em] select-all transition-transform group-hover:scale-[1.02]">
                        {{ $method->account_number }}
                    </span>
                    
                    <div class="mt-4 flex flex-col items-center gap-1">
                        <span class="px-6 py-1.5 bg-gray-50 dark:bg-gray-900 rounded-full text-xs font-semibold text-gray-700 dark:text-gray-200 border border-gray-100 dark:border-gray-800 flex items-center gap-2">
                             <x-filament::icon icon="heroicon-m-user-circle" class="w-4 h-4 text-gray-400" />
                             {{ __('a/n') }} <strong>{{ $method->account_holder }}</strong>
                        </span>
                    </div>
                </div>
                
                <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-950 text-[9px] text-gray-400 dark:text-gray-600 leading-tight uppercase font-bold tracking-widest">
                    {{ __('Verifikasi instan setelah pembayaran') }}
                </div>
            </x-filament::section>
        @endif
    </x-filament::section>
@endif
