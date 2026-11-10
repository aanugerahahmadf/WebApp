{{--
    Halaman auth pertama untuk tamu: /auth.

    Susunannya: logo (dari layout simple) -> heading "Welcome Back" -> tombol
    Google (Firebase Gateway).

    Tombol "Sign In" (email/kata sandi) sengaja dihapus; pintu masuk utama
    memakai Google Firebase Gateway (lihat partial social-buttons di bawah).

    Yang SENGAJA TIDAK ada di sini:
      - pembatas "atau"
      - link "Belum memiliki akun? Sign Up"
      - Remember Me + Persetujuan syarat. Centang syarat tetap divalidasi di
        alur Sign In / Sign Up (authenticate() / handleRegistration()).

    `agreed` / `remembered` TETAP dideklarasikan di sini, bukan di partial
    social, karena halaman ini tidak punya form sebagai "induk" -- partial social
    membacanya lewat x-model polos (kalau `hasParentData => false`, dia akan
    membuat x-data sendiri dengan $wire.entangle('data.agreement'), dan
    property `data` itu tidak ada di halaman tanpa form). Nilainya `true` dari
    awal karena centangnya sudah dihapus dari halaman ini; kalau dibiarkan
    `false`, tombol Google tetap nonaktif selamanya karena partial social
    men-disable dirinya sampai keduanya tercentang.

    `hasParentData => true` supaya x-data di atas yang jadi owner state-nya.
    `hideCheckboxes => true` menyembunyikan blok Ingat Saya + syarat.
    `showAgreementModal => false` menyembunyikan modal persetujuan yang tidak
    punya pemicu di halaman ini.

    CATATAN untuk test: nama tombol persetujuan sengaja TIDAK ditulis di
    komentar ini. Komentar Blade ikut masuk ke HTML yang dikirim ke browser, jadi
    `assertDontSee('...')` untuk salah satu label itu akan salah positives --
    test mengira halaman masih menampilkan checkbox, padahal yang ditemukan
    hanya teks di dalam komentar.
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