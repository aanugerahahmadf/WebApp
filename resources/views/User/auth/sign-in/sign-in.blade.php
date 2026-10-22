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
        x-effect="
            $nextTick(function() {
                let form = $el.querySelector('form');
                if (form && !form.dataset.validationBound) {
                    form.dataset.validationBound = 'true';
                    form.addEventListener('submit', function(e) {
                        if (!(agreed && remembered)) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            
                            new FilamentNotification()
                                .title('{{ __('Perhatian') }}')
                                .body('{{ __('Silakan centang opsi Ingat Saya dan Setujui Syarat & Ketentuan untuk melanjutkan.') }}')
                                .warning()
                                .send();
                        }
                    });
                }
            })
        "
        class="w-full flex flex-col items-center justify-center gap-0 py-0"
    >
        <x-filament-panels::form id="form" wire:submit="authenticate">
            {{ $this->form }}
        </x-filament-panels::form>

        {{--
            Pintu pendaftaran.

            Ditulis langsung di sini, bukan lewat partial social-buttons:
            partial itu hanya dirender di /user/auth (yang sudah punya dua
            tombol sendiri) dan tidak pernah dipanggil dengan
            `showAuthSwitchLink => true`. Menempel switch link di dua tempat
            berarti dua copy yang harus dijaga sinkron; di halaman form
            (sign in dan sign up) cukup satu blok masing-masing.

            URL-nya diambil dari PANEL, bukan `SignUp::getUrl()`: SignUp
            extends `Filament\Pages\Auth\Register`, yang -- tidak seperti
            Page/SimplePage -- tidak punya helper getUrl() statis.
            Memanggilnya jatuh ke __callStatic dan melempar
            BadMethodCallException. Sama seperti catatannya di Auth\Auth
            untuk `SignIn::getUrl()`.

            `getRegistrationUrl()` bisa null kalau pendaftaran dimatikan
            lagi; disembunyikan supaya tidak pernah ada tautan mati.
        --}}
        @if ($signUpUrl = \Filament\Facades\Filament::getPanel('welcome')->getRegistrationUrl())
            <p class="mt-4 w-full text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('Belum memiliki akun?') }}
                <x-filament::link :href="$signUpUrl" class="font-semibold">
                    {{ __('Sign Up') }}
                </x-filament::link>
            </p>
        @endif
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
