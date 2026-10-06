{{--
    View paket mix-code/filament-multi-2fa, disalin ke resources/views/User/
    supaya bisa diubah tanpa menyentuh vendor.

    Paket tidak mempublish view-nya (ServiceProvider-nya hanya publishes
    stubs), jadi disalin manual. Satu-satunya perubahannya:

        <x-filament-panels::page.simple>  ->  <x-filament-panels::page>

    Alasan: page.simple memakai layout.simple yang tidak punya topbar,
    breadcrumb, maupun header halaman -- sehingga halaman ini tampil polos
    dengan tombol bahasa sendirian di kanan atas. Page biasa memakai
    layout.index, yang memuat Top Navigation, breadcrumb, dan judul.

    Selebihnya (form pemilih tipe 2FA, QR, verifikasi TOTP, daftar
    perangkat) tetap milik paket dan tidak diubah.
--}}
<x-filament-panels::page>

    @if ($showSetupForm)
        <x-filament-panels::form id="setup-form" wire:submit="setup">
            {{ $this->setupForm }}

            <x-filament-panels::form.actions :actions="$this->getSetupFormActions()" :full-width="$this->hasFullWidthSetupFormActions()" />
        </x-filament-panels::form>
    @endif

    @if ($showVerifyOTPForm)
        <div wire:poll.1s>
            <x-filament-panels::form id="verify-otp-form" wire:submit="verifyOTP">
                {{ $this->verifyOTPForm }}

                <x-filament-panels::form.actions :actions="$this->getVerifyOTPFormActions()" :full-width="$this->hasFullWidthVerifyOTPFormActions()" />
            </x-filament-panels::form>
        </div>
    @endif

    @if ($showVerifyTOTPForm)
        {{-- QR code + Kode Manual, keduanya infolist native Filament.
             Dipisah dari form karena nilainya baru ada setelah tombol Setup
             ditekan, sedangkan state form sudah terisi lebih dulu. --}}
        {{ $this->setupDetails }}

        <x-filament-panels::form id="verify-totp-form" wire:submit="verifyTOTP">
            {{ $this->verifyTOTPForm }}

            <x-filament-panels::form.actions :actions="$this->getVerifyTOTPFormActions()" :full-width="$this->hasFullWidthVerifyTOTPFormActions()" />
        </x-filament-panels::form>
    @endif

</x-filament-panels::page>