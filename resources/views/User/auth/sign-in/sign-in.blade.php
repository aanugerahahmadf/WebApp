<x-filament-panels::page.simple>
    {{-- Breadcrumb (di atas logo, rata kiri) dirender lewat render hook
         panels::simple-page.start -- lihat UserPanelProvider.

         Slot "subheading" bawaan Filament yang pernah memuat link pendaftaran
         TIDAK dirender: pendaftaran email/password dinonaktifkan (hanya
         `->registration()` di UserPanelProvider yang dikomentari -- class
         SignUp.php sendiri masih ada), jadi blok
         `@if (filament()->hasRegistration())` di sini tidak pernah benar-benar
         merender apa pun. --}}

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    {{-- Ukuran kartu ikut bawaan Filament (`.fi-simple-main`).

         View ini pernah overriding `max-width: 36rem` plus `white-space: nowrap`
         pada label identifier, karena labelnya dulu panjang:
         "KTP / Passport / SIM / NPWP / Username / Email". Sekarang field login
         hanya "Email / Username", jadi tidak ada lagi yang perlu dilebarkan --
         style itu dihapus agar lebar, padding, dan tipografi mengikuti
         `.fi-simple-main` bawaan Filament. --}}

    <div
        x-data="{
            agreed: $wire.entangle('data.agreement'),
            remembered: $wire.entangle('data.remember'),
            loading: false,
            googleUrl: '/auth/google/redirect'
        }"
        class="w-full flex flex-col items-center justify-center gap-0 py-0"
    >
        {{--
            Dinonaktifkan (bukan dihapus): form login manual (Email/Username,
            Password + show/hide, Lupa Kata Sandi, CAPTCHA, tombol Sign In)
            dan pintu pendaftaran.

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}
            </x-filament-panels::form>

            @if ($signUpUrl = \Filament\Facades\Filament::getPanel('welcome')->getRegistrationUrl())
                <p class="mt-4 w-full text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Belum memiliki akun?') }}
                    <x-filament::link :href="$signUpUrl" class="font-semibold">
                        {{ __('Sign Up') }}
                    </x-filament::link>
                </p>
            @endif
        --}}

        @include('User.social-buttons.social-buttons.social-buttons', [
            'hasParentData' => true,
            'hideCheckboxes' => true,
            'showAgreementModal' => true,
            'authMode' => 'login',
            'showGoogleButton' => true,
            'showAuthSwitchLink' => false,
        ])

        <x-filament-panels::form id="form" class="w-full mt-2">
            <div class="w-full">
                {{ $this->form }}
            </div>
        </x-filament-panels::form>

        @include('User.social-buttons.agreement-checkboxes.agreement-checkboxes')
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
