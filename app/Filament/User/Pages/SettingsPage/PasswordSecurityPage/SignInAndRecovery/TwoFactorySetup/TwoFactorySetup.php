<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup;

use App\Filament\Shared\Pages\TwoFactorAuthenticatorPage\TwoFactorAuthenticatorPage;
use App\Filament\Welcome\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage as WelcomePasswordSecurityPage;
use App\Filament\Welcome\Pages\SettingsPage\SettingsPage as WelcomeSettingsPage;
use App\Support\TopNavigation\TopNavigation;

/**
 * Tujuan tombol "Tambahkan Aplikasi Autentikasi" di halaman Keamanan.
 *
 * Alur setup 2FA (pilih Email / Authenticator App / None, verifikasi, simpan,
 * daftar perangkat) diwarisi dari mix-code/filament-multi-2fa lewat
 * TwoFactorAuthenticatorPage -- tidak disalin. Alih-alih menyalinnya, setiap
 * perbaikan pada verifikasi TOTP harus diterapkan dua kali dan pasti ketinggal
 * satu.
 *
 * Yang membedakan halaman ini dari TwoFactor di folder Auth:
 *
 *   - URL, supaya hidup di subtree Keamanan dan tombol di halaman Two Factor
 *     bisa langsung menuju ke sini.
 *
 *   - Breadcrumb, mengikuti struktur Pengaturan.
 *
 *   - Setelah selesai kembali ke halaman Two Factor, bukan Home. Di sini
 *     pengguna sedang mengatur keamanan dan belum selesai.
 *
 * CATATAN -- panel 'user' sudah dilebur ke panel 'welcome' (path '/').
 * Semua URL di bawah memakai kelas Welcome dan `panel: 'welcome'`. Versi User
 * (App\Filament\User\Pages\SettingsPage\*) tidak lagi terdaftar sebagai
 * halaman, jadi `getUrl(panel: 'user')` untuk kelas-kelas itu akan melempar
 * RouteNotFoundException -- bukan 404 yang ramah, tapi exception yang
 * menggagalkan halaman.
 */
class TwoFactorySetup extends TwoFactorAuthenticatorPage
{
    public static function getSlug(): string
    {
        return 'settings/password-security/two-factor/setup';
    }

    /**
     * @return array<string|\Illuminate\Support\Htmlable, string|\Illuminate\Support\Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        // Ditulis manual, bukan lewat securityBreadcrumbs(): kelas ini tidak
        // memakai trait HandlesPasswordSecurity. Rantainya satu tingkat lebih
        // dalam dari halaman detail lain karena halaman ini anak dari
        // "Autentikasi Dua Faktor".
        //
        // Urutannya: kelompok dulu, baru halaman induknya. Kalau dibalik,
        // "Autentikasi Dua Faktor" muncul sebelum "Sign In dan Pemulihan"
        // yang justru menjadi induknya.
        //
        // Crum akar 'Beranda' ditambahkan di depan dengan TopNavigation, sama
        // seperti HasDynamicBreadcrumbs: halaman ini bukan halaman auth, jadi
        // chrome-nya ikut hilang di luar Home, dan tanpa crumb akar ini
        // pengunjung yang membuka /settings/password-security/two-factor/setup
        // lewat URL langsung tidak punya jalan pulang sama sekali.
        $crumbs = [];

        if (TopNavigation::needsHomeBreadcrumb()) {
            $crumbs[TopNavigation::homeUrl()] = __('Beranda');
        }

        return [
            ...$crumbs,
            WelcomeSettingsPage::getUrl(panel: 'welcome') => __('Pengaturan'),
            WelcomePasswordSecurityPage::getUrl(panel: 'welcome') => __('Kata Sandi dan Keamanan'),
            __('Sign In dan Pemulihan'),
            WelcomePasswordSecurityPage::getUrl(['section' => 'two-factor'], panel: 'welcome') => __('Autentikasi Dua Faktor'),
            $this->getTitle(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return WelcomePasswordSecurityPage::getUrl(['section' => 'two-factor'], panel: 'welcome');
    }
}
