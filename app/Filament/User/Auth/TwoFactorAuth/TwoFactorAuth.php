<?php

namespace App\Filament\User\Auth\TwoFactorAuth;

use App\Filament\Shared\Pages\TwoFactorAuthenticatorPage\TwoFactorAuthenticatorPage;
use App\Filament\User\Pages\Home\Home;

/**
 * "Strengthen your account?" -- halaman setup 2FA di tengah alur auth.
 *
 *   OTP terverifikasi / CompleteProfile -> Auth\SecurityQuestion
 *       -> jawab ya -> halaman ini -> Home
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
 *   - Setelah selesai ke Home, bukan ke daftar Keamanan. Melompat ke
 *     Pengaturan setelah setup berarti keluar dari alur login.
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

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Aktifkan Aplikasi Autentikasi');
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return __('Pindai kode QR dengan aplikasi seperti Google Authenticator atau Duo Mobile, lalu masukkan kode 6 digit untuk memastikan semuanya benar.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString, string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        // Satu langkah di tengah alur: tidak ada induk untuk dituju. Crumb
        // "Pengaturan" akan berbohong -- pengguna tidak datang dari sana.
        return [
            $this->getTitle(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Akhir alur auth, tujuan berikutnya Home.
        return Home::getUrl();
    }
}
