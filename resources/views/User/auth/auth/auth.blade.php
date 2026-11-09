{{--
    Halaman auth pertama untuk tamu: /user/auth.

    Susunan satu pintu:
      logo (dari layout simple) -> heading "Welcome Back" -> tombol Google Gateway.

    Tombol Sign In dan Sign Up dinonaktifkan sesuai kebutuhan Google Gateway.
--}}
<x-filament-panels::page.simple>
    <div
        x-data="{
            agreed: true,
            remembered: true,
            loading: false,
            googleUrl: '/auth/google/redirect'
        }"
        class="w-full flex flex-col items-center justify-center gap-3 py-0"
    >
        @include('User.social-buttons.social-buttons.social-buttons', [
            'hasParentData' => true,
            'hideCheckboxes' => true,
            'showAgreementModal' => false,
            'authMode' => 'login',
            'showGoogleButton' => true,
            'showAuthSwitchLink' => false,
        ])
    </div>
</x-filament-panels::page.simple>
