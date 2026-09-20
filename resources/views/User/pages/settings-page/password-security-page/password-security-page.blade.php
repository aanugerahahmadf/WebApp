<x-filament-panels::page>
    @php
        $backUrl = \App\Filament\User\Pages\SettingsPage\SettingsPage::getUrl(panel: 'user');
        $passwordStrength = strlen($passwordData['password'] ?? '') >= 12 ? __('Kuat') : (strlen($passwordData['password'] ?? '') >= 8 ? __('Sedang') : __('Lemah'));
        $currentSessionId = request()->session()->getId();
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        @if ($section === 'index')
            <section><h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Sign In dan Pemulihan') }}</h2><div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                @foreach ([
                    ['change-password', 'heroicon-o-lock-closed', __('Ubah Kata Sandi'), __('Perbarui kata sandi dan lindungi akun Anda.')],
                    ['two-factor', 'heroicon-o-shield-check', __('Autentikasi Dua Faktor'), __('Atur WhatsApp, kode cadangan, dan perangkat tepercaya.')],
                    ['saved-login', 'heroicon-o-bookmark', __('Sign In Tersimpan'), __('Kelola info Sign In yang tersimpan di perangkat.')],
                ] as [$key, $icon, $label, $description])
                    <a wire:navigate href="{{ \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(['section' => $key], panel: 'user') }}" class="flex items-center gap-4 border-b border-gray-100 p-4 last:border-0 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"><x-filament::icon :icon="$icon" class="h-6 w-6 text-primary-500" /><div class="min-w-0 flex-1"><p class="font-medium text-gray-950 dark:text-white">{{ $label }}</p><p class="text-sm text-gray-500">{{ $description }}</p></div><x-filament::icon icon="heroicon-o-chevron-right" class="h-5 w-5 text-gray-400" /></a>
                @endforeach
            </div></section>
            <section><h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Pemeriksaan Keamanan') }}</h2><div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                @foreach ([
                    ['sign-in-activity', 'heroicon-o-map-pin', __('Tempat Anda Sign In'), __('Lihat dan keluarkan sesi perangkat yang aktif.')],
                    ['recent-emails', 'heroicon-o-envelope', __('Ubah Email'), __('Ganti email akun dengan verifikasi kode OTP.')],
                    ['checkup', 'heroicon-o-shield-check', __('Pemeriksaan Keamanan'), __('Tinjau perlindungan penting untuk akun Anda.')],
                ] as [$key, $icon, $label, $description])
                    <a wire:navigate href="{{ \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(['section' => $key], panel: 'user') }}" class="flex items-center gap-4 border-b border-gray-100 p-4 last:border-0 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"><x-filament::icon :icon="$icon" class="h-6 w-6 text-primary-500" /><div class="min-w-0 flex-1"><p class="font-medium text-gray-950 dark:text-white">{{ $label }}</p><p class="text-sm text-gray-500">{{ $description }}</p></div><x-filament::icon icon="heroicon-o-chevron-right" class="h-5 w-5 text-gray-400" /></a>
                @endforeach
            </div></section>
        @elseif ($section === 'change-password')
            <x-filament-panels::form wire:submit="updatePassword">
                {{ $this->form }}

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <x-filament::button tag="a" wire:navigate :href="\App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(panel: 'user')" color="gray">
                        {{ __('Batal') }}
                    </x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-lock-closed">
                        {{ __('Ubah Kata Sandi') }}
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        @elseif ($section === 'two-factor')
            <x-filament::section :heading="__('Autentikasi Dua Faktor')" :description="__('Kelola metode verifikasi tambahan, aplikasi autentikasi, dan perangkat tepercaya dalam satu tempat.')">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Cara Mendapatkan Kode Sign In') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Pilih dan kelola cara verifikasi tambahan untuk akun ini.') }}</p>
                    </div>
                    <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10"><div><p class="font-medium">{{ __('WhatsApp') }} <span class="ml-1 rounded-full bg-success-100 px-2 py-0.5 text-xs text-success-700">{{ $user?->two_factor_enabled ? __('Aktif') : __('Nonaktif') }}</span></p><p class="text-sm text-gray-500">{{ $user?->whatsapp ? preg_replace('/(?<=.{4}).(?=.{3})/', '*', $user->whatsapp) : __('Belum ada nomor WhatsApp') }}</p></div><x-filament::button wire:click="toggleTwoFactor" :color="$user?->two_factor_enabled ? 'danger' : 'primary'" size="sm">{{ $user?->two_factor_enabled ? __('Nonaktifkan') : __('Aktifkan') }}</x-filament::button></div>
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10"><div><p class="font-medium">{{ __('Permintaan Sign In') }}</p><p class="text-sm text-gray-500">{{ __('Minta persetujuan saat ada Sign In baru.') }}</p></div><x-filament::button wire:click="toggleRequestSignIn" :color="$requestSignInEnabled ? 'primary' : 'gray'" size="sm">{{ $requestSignInEnabled ? __('Aktif') : __('Nonaktif') }}</x-filament::button></div>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10"><div><p class="font-medium">{{ __('Kode Cadangan') }}</p><p class="text-sm text-gray-500">{{ __('Tersisa :count dari 10 kode', ['count' => $backupCodes->count()]) }}</p></div><div class="flex gap-2"><x-filament::button wire:click="generateBackupCodes" color="gray" size="sm">{{ __('Buat Ulang Kode') }}</x-filament::button></div></div>
                    @if ($backupCodes->isNotEmpty())<div class="rounded-xl bg-gray-50 p-3 text-xs font-semibold dark:bg-white/5">{{ $backupCodes->pluck('code')->implode(' · ') }}</div>@endif
                    </div>

                    <div class="border-t border-gray-200 pt-6 dark:border-white/10">
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Aplikasi Autentikasi') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Dapatkan kode dari aplikasi seperti Duo Mobile atau Google Authenticator.') }}</p>
                        <x-filament::button wire:click="prepareAuthenticator" class="mt-4">{{ __('Tambahkan Aplikasi Autentikasi') }}</x-filament::button>
                        @if ($authenticatorSecret)<div class="mt-4 rounded-xl border border-dashed border-primary-300 p-4"><p class="text-sm font-medium">{{ __('Secret key') }}</p><x-filament::input value="{{ $authenticatorSecret }}" readonly class="mt-2 w-full" aria-label="{{ __('Secret key') }}" /><div class="mt-3 flex gap-2"><x-filament::input wire:model="authenticatorCode" inputmode="numeric" maxlength="6" placeholder="{{ __('Kode 6 digit') }}" class="flex-1" /><x-filament::button wire:click="verifyAuthenticator">{{ __('Verifikasi') }}</x-filament::button></div>@error('authenticatorCode')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</div>@endif
                    </div>

                    <div class="border-t border-gray-200 pt-6 dark:border-white/10">
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Sign In yang Diotorisasi – Perangkat Tepercaya') }}</h2>
                        <div class="mt-4 space-y-3">@forelse($devices as $device)<div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10"><div><p class="font-medium">{{ $device->device_name ?: __('Perangkat tidak dikenal') }}</p><p class="text-xs text-gray-500">{{ $device->platform ?: '-' }} · {{ $device->trusted_at?->translatedFormat('d M Y') }}</p></div><x-filament::button wire:click="removeTrustedDevice({{ $device->id }})" wire:confirm="{{ __('Hapus perangkat ini?') }}" color="danger" size="sm">{{ __('Hapus') }}</x-filament::button></div>@empty<p class="text-sm text-gray-500">{{ __('Belum ada perangkat tepercaya.') }}</p>@endforelse</div>
                        <x-filament::button wire:click="removeAllTrustedDevices" wire:confirm="{{ __('Hapus semua perangkat tepercaya?') }}" color="danger" size="sm" class="mt-4">{{ __('Hapus Semua Perangkat') }}</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @elseif ($section === 'saved-login')
            <x-filament::section :heading="__('Sign In Tersimpan')" :description="__('Kelola perangkat yang menyimpan info Sign In akun Anda.')"><div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10"><div><p class="font-medium">{{ __('Simpan info Sign In di perangkat ini') }}</p><p class="text-sm text-gray-500">{{ __('Mempermudah Sign In berikutnya pada perangkat tepercaya.') }}</p></div><x-filament::button wire:click="toggleSavedLogin" :color="($user?->saved_login_enabled ?? true) ? 'primary' : 'gray'" size="sm">{{ ($user?->saved_login_enabled ?? true) ? __('Aktif') : __('Nonaktif') }}</x-filament::button></div><div class="mt-4 space-y-3">@forelse($devices as $device)<div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10"><div><p class="font-medium">{{ $device->device_name ?: __('Perangkat tidak dikenal') }}</p><p class="text-xs text-gray-500">{{ $device->platform ?: '-' }} · {{ $device->trusted_at?->diffForHumans() }}</p></div><x-filament::button wire:click="removeTrustedDevice({{ $device->id }})" color="danger" size="sm">{{ __('Hapus') }}</x-filament::button></div>@empty<p class="py-6 text-center text-sm text-gray-500">{{ __('Belum ada Sign In tersimpan.') }}</p>@endforelse</div><x-filament::button wire:click="removeAllTrustedDevices" wire:confirm="{{ __('Hapus semua Sign In tersimpan?') }}" color="danger" size="sm" class="mt-4">{{ __('Hapus Semua Sign In Tersimpan') }}</x-filament::button></x-filament::section>
        @elseif ($section === 'sign-in-activity')
            <livewire:browser_sessions_form />
        @elseif ($section === 'recent-emails')
            <x-filament::section :heading="__('Ubah Email')" :description="__('Masukkan email baru, lalu verifikasi kode OTP yang dikirimkan ke email tersebut.')">
                <x-filament-panels::form wire:submit="{{ $emailOtpSent ? 'verifyEmailChangeOtp' : 'sendEmailChangeOtp' }}">
                    {{ $this->changeEmailForm }}

                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        @if ($emailOtpSent)
                            <x-filament::button type="button" wire:click="sendEmailChangeOtp" color="gray" icon="heroicon-m-arrow-path">
                                {{ __('Kirim Ulang OTP') }}
                            </x-filament::button>
                        @endif
                        <x-filament::button tag="a" wire:navigate :href="$backUrl" color="gray">
                            {{ __('Batal') }}
                        </x-filament::button>
                        <x-filament::button type="submit" :icon="$emailOtpSent ? 'heroicon-m-check-circle' : 'heroicon-m-paper-airplane'">
                            {{ $emailOtpSent ? __('Verifikasi OTP') : __('Kirim Kode OTP') }}
                        </x-filament::button>
                    </div>
                </x-filament-panels::form>
            </x-filament::section>
        @elseif ($section === 'checkup')
            @php
                $checks = [
                    [__('Kata sandi kuat dan diperbarui'), true, 'change-password'],
                    [__('Autentikasi dua faktor aktif'), (bool) ($user?->two_factor_enabled ?? false), 'two-factor'],
                    [__('Email pemulihan terverifikasi'), ! empty($user?->email_verified_at), 'index'],
                    [__('Nomor telepon terverifikasi'), ! empty($user?->whatsapp_verified_at), 'index'],
                    [__('Tidak ada perangkat mencurigakan'), true, 'sign-in-activity'],
                ];
                $secureCount = collect($checks)->where(1, true)->count();
                $percent = $secureCount * 20;
            @endphp
            <x-filament::section :heading="$secureCount >= 4 ? __('Keamanan Akun: Baik') : __('Keamanan Akun Perlu Perhatian')">
                <div class="mb-5 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                    <div class="h-full rounded-full {{ $percent >= 80 ? 'bg-success-500' : ($percent >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}" style="width: {{ $percent }}%"></div>
                </div>

                <div class="space-y-3">
                    @foreach($checks as [$label, $secured, $target])
                        <div class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <x-filament::icon :icon="$secured ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'" class="h-5 w-5 shrink-0 {{ $secured ? 'text-success-600' : 'text-warning-500' }}" />
                            <p class="min-w-0 flex-1 text-sm font-medium">{{ $label }}</p>
                            @if (! $secured)
                                <x-filament::button tag="a" wire:navigate :href="\App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(['section' => $target], panel: 'user')" size="sm" color="primary" class="shrink-0">
                                    {{ __('Perbaiki') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end">
                    <x-filament::button wire:click="startCheckup">
                        {{ $checkupCompleted ? __('Pemeriksaan selesai') : __('Mulai Pemeriksaan') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
