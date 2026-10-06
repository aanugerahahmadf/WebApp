<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup;

use App\Filament\Shared\Pages\TwoFactorAuthenticatorPage\TwoFactorAuthenticatorPage;
use App\Filament\User\Pages\SettingsPage\SettingsPage;

/**
 * Tujuan tombol "Tambahkan Aplikasi Autentikasi" di halaman Two Factor.
 *
 * Alur setup 2FA (pilih Email / Authenticator App / None, verifikasi, simpan,
 * daftar perangkat) diwarisi dari mix-code/filament-multi-2fa lewat
 * TwoFactorAuthenticatorPage -- tidak disalin. Alih-alih menyalinnya, setiap
 * perbaikan pada verifikasi TOTP harus diterapkan dua kali dan pasti ketinggal
 * satu.
 *
 * Yang membedakan halaman ini dari TwoFactorAuth di folder Auth:
 *
 *   - URL, supaya hidup di subtree Keamanan dan tombol di halaman Two Factor
 *     bisa langsung menuju ke sini.
 *
 *   - Breadcrumb, mengikuti struktur Pengaturan.
 *
 *   - Setelah selesai kembali ke halaman Two Factor, bukan Home. Di sini
 *     pengguna sedang mengatur keamanan dan belum selesai.
 */
class TwoFactorySetup extends TwoFactorAuthenticatorPage
{
    public static function getSlug(): string
    {
        return 'settings/password-security/two-factor/setup';
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString, string|\Illuminate\Support\HtmlString>
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
        return [
            SettingsPage::getUrl(panel: 'user') => __('Pengaturan'),
            \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage::getUrl(panel: 'user') => __('Kata Sandi dan Keamanan'),
            __('Sign In dan Pemulihan'),
            \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor::getUrl(panel: 'user') => __('Autentikasi Dua Faktor'),
            $this->getTitle(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return \App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor::getUrl(panel: 'user');
    }
}