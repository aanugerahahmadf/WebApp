{{-- Info Sign In yang tersimpan di perangkat ini. --}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl">
        <x-filament::section
            :heading="__('Sign In Tersimpan')"
            :description="__('Kelola perangkat yang menyimpan info Sign In akun Anda.')"
        >
            <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div>
                    <p class="font-medium">{{ __('Simpan info Sign In di perangkat ini') }}</p>
                    <p class="text-sm text-gray-500">{{ __('Mempermudah Sign In berikutnya pada perangkat tepercaya.') }}</p>
                </div>

                <x-filament::button
                    wire:click="toggleSavedLogin"
                    :color="($user?->saved_login_enabled ?? true) ? 'primary' : 'gray'"
                    size="sm"
                >
                    {{ ($user?->saved_login_enabled ?? true) ? __('Aktif') : __('Nonaktif') }}
                </x-filament::button>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($devices as $device)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $device->device_name ?: __('Perangkat tidak dikenal') }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $device->platform ?: '-' }} - {{ $device->trusted_at?->diffForHumans() }}
                            </p>
                        </div>

                        <x-filament::button wire:click="removeTrustedDevice({{ $device->id }})" color="danger" size="sm">
                            {{ __('Hapus') }}
                        </x-filament::button>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">{{ __('Belum ada Sign In tersimpan.') }}</p>
                @endforelse
            </div>

            <x-filament::button
                wire:click="removeAllTrustedDevices"
                wire:confirm="{{ __('Hapus semua Sign In tersimpan?') }}"
                color="danger"
                size="sm"
                class="mt-4"
            >
                {{ __('Hapus Semua Sign In Tersimpan') }}
            </x-filament::button>
        </x-filament::section>
    </div>
</x-filament-panels::page>