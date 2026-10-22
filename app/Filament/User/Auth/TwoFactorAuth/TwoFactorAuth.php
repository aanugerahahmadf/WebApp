<?php

namespace App\Filament\User\Auth\TwoFactorAuth;

use App\Filament\Shared\Pages\TwoFactorAuthenticatorPage\TwoFactorAuthenticatorPage;
use App\Filament\Welcome\Pages\Home\Home;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * "Strengthen your account?" -- halaman setup 2FA di tengah alur auth.
 *
 *   Sign Up -> Email OTP -> SecurityQuestion
 *       -> jawab Ya  -> halaman ini -> Sign In -> OtpEmailOrTwoFactory -> Home
 *       -> jawab Tidak -> Sign In -> Home
 *
 * Alurnya sama persis dengan TwoFactorySetup di Pengaturan: pilih tipe (Email /
 * Authenticator App / None), verifikasi OTP atau TOTP, simpan, plus daftar
 * perangkat tepercaya. Semuanya diwarisi dari mix-code/filament-multi-2fa lewat
 * TwoFactorAuthenticatorPage, jadi keduanya tidak mungkin terlihat berbeda.
 *
 * Yang berbeda hanya tiga hal, dan ketiganya memang harus:
 *
 *   - URL: dua-factor-auth, bukan di subtree Keamanan.
 *
 *   - Breadcrumb: halaman ini adalah satu langkah di tengah alur, bukan
 *     tempat yang punya induk. Menempelkannya di Pengaturan akan menyesatkan
 *     -- pengguna tidak datang dari sana.
 *
 *   - Setelah 2FA aktif, Sign In dulu, baru Home. Ini BUKAN langkah yang bisa
 *     dilewati: Filament memantulkan user yang sudah login balik ke Home
 *     (Login::mount()), jadi tanpa sign-out di sini seluruh rantai
 *     "Sign In -> OtpEmailOrTwoFactory" akan hilang dan 2FA yang baru
 *     diaktifkan tidak pernah benar-benar diuji. Lihat signOutAfterSetup().
 *
 * Opsi "None" tetap tersedia di sini seperti di halaman Pengaturan: pengguna
 * boleh memutuskan tidak memakai 2FA, dan halaman ini tidak memaksanya.
 */
class TwoFactorAuth extends TwoFactorAuthenticatorPage
{
    public static function getSlug(): string
    {
        return 'two-factor-auth';
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Sudah aktif: tidak perlu setup lagi, dan halaman paket akan
        // menolak tipe yang sudah dipakai ("You have already verified your
        // account with this method before").
        if ($user?->hasSetupTwoFactor()) {
            $this->redirect(Home::getUrl());

            return;
        }

        parent::mount();
    }

    public function getHeading(): string|Htmlable
    {
        return __('Aktifkan Aplikasi Autentikasi');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Pindai kode QR dengan aplikasi seperti Google Authenticator atau Duo Mobile, lalu masukkan kode 6 digit untuk memastikan semuanya benar.');
    }

    /**
     * @return array<string|HtmlString, string|HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        // Satu langkah di tengah alur: tidak ada induk untuk dituju. Crumb
        // "Pengaturan" akan berbohong -- pengguna tidak datang dari sana.
        return [
            $this->getTitle(),
        ];
    }

    /**
     * Login ulang setelah 2FA diaktifkan.
     *
     * Dipanggil SETELAH parent::verifyTOTP() / parent::verifyOTP(). Paket
     * sudah menandai `2fa_passed` dan memanggil redirect() kalau berhasil, dan
     * berhenti tanpa redirect kalau kodenya salah -- jadi `2fa_passed` itu
     * penanda sukses yang sudah tersedia, bukan tebakan baru.
     *
     * Kenapa sign-out wajib, bukan cuma redirect: `Filament\Pages\Auth\Login`
     * memantulkan user yang SUDAH login balik ke panel (`Login::mount()`).
     * Tanpa sign-out, mengarahkan ke /signin akan memantul balik ke Home,
     * dan seluruh rantai "Sign In -> OtpEmailOrTwoFactory" dilewati -- artinya
     * 2FA yang baru saja diaktifkan tidak pernah diuji sekali pun.
     *
     * Penanda yang harus dibersihkan:
     *   - `2fa_passed`         -- kalau dibiarkan, sesi berikutnya lolos 2FA
     *                             tanpa diminta kode.
     *   - `trusted_device_validated` -- bentuk lain dari bypass yang sama.
     *
     * Session TIDAK di-invalidate: notifikasi sukses dari paket disimpan sebagai
     * flash di session, dan `invalidate()` akan membuangnya sebelum sempat
     * tampil. Yang diregenerate hanya token CSRF.
     */
    protected function signOutAfterSetup(): void
    {
        // Gagal verifikasi tidak pernah menandai ini, jadi gagal = jangan logout.
        if (session('2fa_passed') !== true) {
            return;
        }

        auth()->logout();

        session()->forget(['2fa_passed', 'trusted_device_validated']);

        session()->regenerateToken();
    }

    public function verifyTOTP(): void
    {
        parent::verifyTOTP();

        $this->signOutAfterSetup();
    }

    public function verifyOTP(): void
    {
        parent::verifyOTP();

        $this->signOutAfterSetup();
    }

    protected function getRedirectUrl(): string
    {
        // Sign In dulu, bukan Home. Untuk 2FA TOTP, CheckTrustedDevice akan
        // mengarahkan ke OtpEmailOrTwoFactory begitu kredensial dimasukkan.
        // Untuk "None", Sign In langsung menuju Home.
        //
        // URL diambil dari PANEL, bukan `SignIn::getUrl()`: SignIn extends
        // `Filament\Pages\Auth\Login` yang tidak punya helper getUrl() statis,
        // jadi memanggilnya jatuh ke __callStatic dan melempar
        // BadMethodCallException.
        return Filament::getPanel('welcome')->getLoginUrl();
    }
}
