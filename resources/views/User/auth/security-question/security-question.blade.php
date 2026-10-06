{{--
    SecurityQuestion — satu kali Pondasi Keamanan setelah OTP / Complete Profile.

    Dua jawaban, tanpa opsi ketiga yang menggantung: yang memilih "ya" diarahkan
    ke TwoFactorAuth, yang memilih "tidak" langsung ke Home. Keduanya mencatat
    session flag supaya halaman ini tidak muncul lagi.

    TEKSNYA AMBIL DARI KELAS, bukan ditulis di sini.

    Sebelumnya view ini menulis heading dan subheading sendiri dengan
    __("Lindungi akun dengan verifikasi dua langkah?") dan
    __("Anda bisa mengaktifkannya sekarang, ..."), sementara
    SecurityQuestion::getHeading() / getSubheading() mengembalikan teks yang
    sama sekali berbeda ("Perkuat Keamanan Akun Anda" / "Dengan verifikasi
    dua langkah, ..."). Karena blade/manual menang atas getHeading(), nilai
    yang benar-benar tampil di web adalah teks yang ditulis di view -- dan
    getHeading() jadi tidak pernah terpakai.

    Akibatnya aplikasi mobile (yang memakai teks dari getHeading()) dan web
    menampilkan dua kalimat berbeda untuk layar yang sama.

    Sekarang view ikut kelas, jadi hanya ada satu sumber teks: kelasnya.
    Kalau teksnya mau diganti lagi, ubah getHeading()/getSubheading() --
    dan dua sisi ikut berubah, karena keduanya membaca dari situ.
--}}
<x-filament-panels::page>
    <div class="flex flex-col items-center gap-6 text-center">
        <x-filament::icon
            icon="heroicon-o-shield-check"
            class="h-12 w-12 text-primary-500"
        />

        <div class="flex flex-col items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ $this->getHeading() }}
            </h2>

            <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                {{ $this->getSubheading() }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            {{ $this->strengthenAction }}
            {{ $this->skipAction }}
        </div>
    </div>
</x-filament-panels::page>