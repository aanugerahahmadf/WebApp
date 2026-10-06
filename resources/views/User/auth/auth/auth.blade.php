{{--
    Halaman auth pertama untuk tamu: /user/auth.

    Susunannya persis tiga blok, tanpa yang lain:
      logo (dari layout simple) -> heading "Welcome Back" -> tombol "Sign In to
      Account" -> tombol Google.

    Yang SENGAJA TIDAK ada di sini:
      - pembatas "atau": halaman ini hanya punya dua tombol, jadi memisah
        dengan "atau" menambah satu baris tanpa menambah pilihan.
      - link "Belum memiliki akun? Sign Up": halaman ini sudah pintu masuk
        auth yang terbuka, dan Sign Up punya jalurnya sendiri dari SignIn.
      - Remember Me + Persetujuan syarat (Perjanjian Pengguna / Kebijakan
        Privasi / Kebijakan Aplikasi). Dua tombol di sini adalah pintu masuk,
        bukan persetujuan: hanya Sign In dan Sign Up yang mewajibkan centang
        dua checkbox itu (divalidasi di authenticate() / handleRegistration()).
        Membawa syarat ke sini memaksa tamu mencetaknya lebih dulu, padahal
        halaman ini justru dibuat supaya tombol Google bisa langsung dipakai.

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

    `showAgreementModal => false` untuk ikut menyembunyikan modal Perjanjian
    Pengguna / Kebijakan Privasi / Kebijakan Aplikasi yang dibawa partial social:
    di halaman ini tidak ada satu pun pemicu `open-agreement` (satu-satunya
    pemicunya adalah checkbox syarat yang baru saja disembunyikan), jadi modal
    itu markup mati -- plus tiga query ke tabel terms/privacy/policy untuk isi
    yang tak pernah dibuka. Flag-nya opsional dan default-nya `true`, jadi
    SignIn yang memakai partial ini tetap punya modalnya.
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
        {{--
            Label tombol: "Sign In To Account" (id: "Masuk ke Akun"), bukan
            "Sign In" polos. Alasannya, "Sign In" polos sudah dipakai heading
            halaman /user/signin yang jadi tujuan tombol ini, dan button Google
            di bawahnya sudah berlabel "Continue With Google" -- dua label yang
            mirip untuk dua tujuan berbeda, jadi tamu tidak tahu tombol mana
            yang membuka form email/kata sandi. Sengaja lewat __() dengan kunci
            yang ada di lang/id.json + lang/en.json, dua locale yang memang
            ditawarkan language switcher
            (config/filament-language-switcher.php), jadi ikut berubah kalau
            tamu ganti bahasa.
        --}}
        <x-filament::button
            tag="a"
            href="{{ $signInUrl }}"
            size="lg"
            icon="heroicon-o-arrow-right-on-rectangle"
            class="w-full"
        >
            {{ __('Sign In To Account') }}
        </x-filament::button>

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