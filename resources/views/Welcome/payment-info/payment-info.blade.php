@php
    $type = $method?->type;
    $url = $method?->qris_image_url;
@endphp

@if ($type === 'qris')
    <div class="flex flex-col items-center py-6 gap-6">
        <x-filament::section
            class="bg-white dark:bg-gray-950 p-6 rounded-3xl border-2 border-primary-100 dark:border-primary-900 shadow-2xl shadow-primary-500/10"
        >
            <div class="flex flex-col items-center gap-6">
                <div class="relative group p-2 bg-gray-50 dark:bg-black rounded-2xl border border-gray-100 dark:border-gray-800">
                    <img src="{{ $url }}" class="w-64 h-64 object-contain rounded-xl shadow-inner group-hover:scale-[1.02] transition-transform duration-500" alt="QRIS" />
                </div>
                
                <div class="flex flex-col items-center gap-4">
                    <x-filament::button
                        href="{{ $url }}"
                        tag="a"
                        download="QRIS_Payment.png"
                        icon="heroicon-o-arrow-down-tray"
                        color="primary"
                        size="xl"
                        class="w-full sm:w-auto px-10 rounded-2xl shadow-lg transition-transform hover:scale-[1.02] active:scale-95 text-lg font-bold"
                    >
                        {{ __('SIMPAN QRIS') }}
                    </x-filament::button>
                    
                    <p class="text-[10px] text-center text-gray-400 dark:text-gray-500 font-bold uppercase tracking-[0.2em]">
                        {{ __('Pindai atau unduh gambar ini') }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>
@else
    <x-filament::section
        compact
        class="bg-primary-50/10 dark:bg-primary-900/10 border-primary-200/40 dark:border-primary-800/40 shadow-none mt-2"
    >
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full pr-4">
                <span class="text-xs font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                    {{ __('Detail Transfer') }}
                </span>
                <x-filament::badge color="primary" size="sm" class="font-bold">
                    {{ $method->name }}
                </x-filament::badge>
            </div>
        </x-slot>

        <div class="flex flex-col gap-5 py-2">
            <div class="flex flex-col gap-1 text-center sm:text-left">
                <span class="text-3xl font-bold tracking-[0.2em] text-gray-950 dark:text-white select-all">
                    {{ $method->account_number ?? '-' }}
                </span>
                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400 flex items-center justify-center sm:justify-start gap-2">
                    <x-filament::icon icon="heroicon-m-user-circle" class="w-4 h-4" />
                    {{ $method->account_holder ?? '-' }}
                </span>
            </div>
            
            <div class="pt-4 border-t border-primary-100 dark:border-primary-800/50 flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-information-circle" class="w-5 h-5 text-primary-500 shrink-0" />
                <span class="text-[11px] leading-relaxed text-primary-600/60 dark:text-primary-400/60 font-medium">
                    {{ __('Harap sertakan kode unik (jika ada) dan simpan bukti transfer untuk percepatan verifikasi.') }}
                </span>
            </div>
        </div>
    </x-filament::section>
@endif
