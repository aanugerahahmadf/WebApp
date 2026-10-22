<x-filament-panels::page>
    @php
        /** @var \App\Models\TrustedDevice\TrustedDevice $reviewDevice */
        $device = $reviewDevice;
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ $device->displayLabel() }}
            </x-slot>
            <x-slot name="description">
                <span class="text-sm">
                    {{ __('Pertama dipakai:') }} {{ $device->first_seen_at?->translatedFormat('d M Y, H:i') }}
                </span>
            </x-slot>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('Terakhir dipakai:') }}</span>
                    {{ $device->last_seen_at?->translatedFormat('d F Y, H:i:s') }}
                </p>
                <p>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('Alamat:') }}</span>
                    {{ $device->last_ip ?? '-' }} ({{ $device->locationSentence() }})
                </p>
                <p>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('Sidik perangkat:') }}</span>
                    <span class="break-all font-mono text-xs">{{ substr($device->device_fingerprint, 0, 16) }}&hellip;</span>
                </p>
            </div>
        </x-filament::section>

        @if ($answered)
            <x-filament::section>
                <x-slot name="heading">
                    @if ($device->trusted_at)
                        {{ __('Perangkat ini sudah dipercaya') }}
                    @else
                        {{ __('Sesi sudah dikeluarkan') }}
                    @endif
                </x-slot>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    @if ($device->trusted_at)
                        {{ __('Perangkat ini ditandai milik Anda. Sign in berikutnya dari perangkat ini tidak diperiksa lagi.') }}
                    @else
                        {{ __('Perangkat ini ditandai bukan milik Anda. Jangan lupa mengganti kata sandi di menu Sandi & Keamanan.') }}
                    @endif
                </div>
            </x-filament::section>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <button
                    wire:click="confirmItIsMe"
                    type="button"
                    class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2"
                >
                    {{ __('Ya, Ini Saya') }}
                </button>

                <button
                    wire:click="denyItIsNotMe"
                    wire:confirm="{{ __('Semua sesi lain dan token aplikasi HP akan dikeluarkan. Lanjutkan?') }}"
                    type="button"
                    class="w-full rounded-lg bg-rose-600 px-4 py-3 text-base font-semibold text-white hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2"
                >
                    {{ __('Bukan Saya') }}
                </button>
            </div>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Bukan Anda?') }}
                </x-slot>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Menjawab "Bukan Saya" akan mengeluarkan semua sesi web lain DAN semua token aplikasi HP akun ini, lalu membawa Anda ke menu ganti kata sandi.') }}
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
