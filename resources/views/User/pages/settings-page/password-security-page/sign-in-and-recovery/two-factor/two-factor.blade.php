{{--
    Ringkasan 2FA.

    Aktivasi Authenticator App TIDAK ada di sini, melainkan di TwoFactorySetup
    (sub-halaman). Supaya halaman ini tetap ringkas, dan QR + verifikasi TOTP
    hanya hidup di satu tempat.
--}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl">
        <x-filament::section
            :heading="__('Autentikasi Dua Faktor')"
            :description="__('Kelola metode verifikasi tambahan, aplikasi autentikasi, dan perangkat tepercaya dalam satu tempat.')"
        >
            <div class="space-y-6">
                {{-- Cara mendapatkan kode --}}
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Cara Mendapatkan Kode Sign In') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Pilih dan kelola cara verifikasi tambahan untuk akun ini.') }}</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div>
                            <p class="font-medium">
                                {{ __('WhatsApp') }}
                                <span class="ml-1 rounded-full bg-success-100 px-2 py-0.5 text-xs text-success-700">
                                    {{ $user?->two_factor_enabled ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $user?->whatsapp ? preg_replace('/(?<=.{4}).(?=.{3})/', '*', $user->whatsapp) : __('Belum ada nomor WhatsApp') }}
                            </p>
                        </div>

                        <x-filament::button
                            wire:click="toggleTwoFactor"
                            :color="$user?->two_factor_enabled ? 'danger' : 'primary'"
                            size="sm"
                        >
                            {{ $user?->two_factor_enabled ? __('Nonaktifkan') : __('Aktifkan') }}
                        </x-filament::button>
                    </div>

                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div>
                            <p class="font-medium">{{ __('Permintaan Sign In') }}</p>
                            <p class="text-sm text-gray-500">{{ __('Minta persetujuan saat ada Sign In baru.') }}</p>
                        </div>

                        <x-filament::button
                            wire:click="toggleRequestSignIn"
                            :color="$requestSignInEnabled ? 'primary' : 'gray'"
                            size="sm"
                        >
                            {{ $requestSignInEnabled ? __('Aktif') : __('Nonaktif') }}
                        </x-filament::button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div>
                            <p class="font-medium">{{ __('Kode Cadangan') }}</p>
                            <p class="text-sm text-gray-500">{{ __('Tersisa :count dari 10 kode', ['count' => $backupCodes->count()]) }}</p>
                        </div>

                        <x-filament::button wire:click="generateBackupCodes" color="gray" size="sm">
                            {{ __('Buat Ulang Kode') }}
                        </x-filament::button>
                    </div>

                    @if ($backupCodes->isNotEmpty())
                        <div class="rounded-xl bg-gray-50 p-3 text-xs font-semibold dark:bg-white/5">
                            {{ $backupCodes->pluck('code')->implode(' - ') }}
                        </div>
                    @endif
                </div>

                {{-- Aplikasi autentikasi: arahkan ke sub-halaman setup --}}
                <div class="border-t border-gray-200 pt-6 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Aplikasi Autentikasi') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Dapatkan kode dari aplikasi seperti Duo Mobile atau Google Authenticator.') }}</p>

                    @php $isSetUp = $user?->two_factor_type?->value !== 'none'; @endphp

                    <x-filament::button
                        tag="a"
                        wire:navigate
                        :href="\App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup::getUrl(panel: 'user')"
                        class="mt-4"
                    >
                        {{ $isSetUp ? __('Kelola Aplikasi Autentikasi') : __('Tambahkan Aplikasi Autentikasi') }}
                    </x-filament::button>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        @if ($isSetUp)
                            {{ __('Aplikasi autentikasi aktif. Kode 6 digit akan diminta setiap kali Sign In.') }}
                        @else
                            {{ __('Belum aktif. Setelah diaktifkan, kode 6 digit akan diminta setiap kali Sign In.') }}
                        @endif
                    </p>
                </div>

                {{-- Perangkat teaseÐ±Ñ€Ð¸ --}}
                <div class="border-t border-gray-200 pt-6 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Sign In yang Diotorisasi - Perangkat Tepercaya') }}</h2>

                    <div class="mt-4 space-y-3">
                        @forelse ($devices as $device)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                                <div>
                                    <p class="font-medium">{{ $device->device_name ?: __('Perangkat tidak dikenal') }}</p>
                                    <p class="text-xs text-gray-500">{{ $device->platform ?: '-' }} - {{ $device->trusted_at?->translatedFormat('d M Y') }}</p>
                                </div>

                                <x-filament::button
                                    wire:click="removeTrustedDevice({{ $device->id }})"
                                    wire:confirm="{{ __('Hapus perangkat ini?') }}"
                                    color="danger"
                                    size="sm"
                                >
                                    {{ __('Hapus') }}
                                </x-filament::button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('Belum ada perangkat tepercaya.') }}</p>
                        @endforelse
                    </div>

                    <x-filament::button
                        wire:click="removeAllTrustedDevices"
                        wire:confirm="{{ __('Hapus semua perangkat tepercaya?') }}"
                        color="danger"
                        size="sm"
                        class="mt-4"
                    >
                        {{ __('Hapus Semua Perangkat') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>