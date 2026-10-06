{{-- Ringkasan level keamanan akun. --}}
@php
    $checks = [
        [__('Kata sandi kuat dan diperbarui'), true, \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\ChangePassword\ChangePassword::class],
        [__('Autentikasi dua faktor aktif'), (bool) ($user?->two_factor_enabled ?? false), \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor::class],
        [__('Email pemulihan terverifikasi'), ! empty($user?->email_verified_at), \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\RecentEmails\RecentEmails::class],
        [__('Nomor telepon terverifikasi'), ! empty($user?->whatsapp_verified_at), null],
        [__('Tidak ada perangkat mencurigakan'), true, \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\SignInActivity::class],
    ];

    $secureCount = collect($checks)->where(1, true)->count();
    $percent = $secureCount * 20;
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-4xl">
        <x-filament::section :heading="$secureCount >= 4 ? __('Keamanan Akun: Baik') : __('Keamanan Akun Perlu Perhatian')">
            <div class="mb-5 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div
                    class="h-full rounded-full {{ $percent >= 80 ? 'bg-success-500' : ($percent >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}"
                    style="width: {{ $percent }}%"
                ></div>
            </div>

            <div class="space-y-3">
                @foreach ($checks as [$label, $secured, $target])
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <x-filament::icon
                            :icon="$secured ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'"
                            class="h-5 w-5 shrink-0 {{ $secured ? 'text-success-600' : 'text-warning-500' }}"
                        />

                        <p class="min-w-0 flex-1 text-sm font-medium">{{ $label }}</p>

                        @if (! $secured && $target)
                            <x-filament::button
                                tag="a"
                                wire:navigate
                                :href="$target::getUrl(panel: 'user')"
                                size="sm"
                                color="primary"
                                class="shrink-0"
                            >
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
    </div>
</x-filament-panels::page>