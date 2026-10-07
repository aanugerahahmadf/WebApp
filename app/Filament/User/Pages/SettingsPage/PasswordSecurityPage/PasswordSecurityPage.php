<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use Filament\Pages\Page;

/**
 * Daftar seluruh pengaturan keamanan.
 *
 * Murni penavigasi -- tidak ada form di sini. Tiap butir punya halaman
 * sendiri di bawah folder ini:
 *
 *   ChangePassword      /user/settings/password-security/change-password
 *   TwoFactor           /user/settings/password-security/two-factor
 *   TwoFactorySetup     /user/settings/password-security/two-factor/setup
 *   SavedLogin          /user/settings/password-security/saved-login
 *   SignInActivity      /user/settings/password-security/sign-in-activity
 *   RecentEmails        /user/settings/password-security/recent-emails
 *   Checkup             /user/settings/password-security/checkup
 *
 * Semuanya pakai ROUTE, bukan ?section= -- lihat catatan di view.
 */
class PasswordSecurityPage extends Page
{
    use HasDynamicBreadcrumbs;
    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.password-security-page.password-security-page';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security';
    }

    public function getTitle(): string
    {
        return __('Kata Sandi dan Keamanan');
    }

    public function getHeading(): string
    {
        return __('Kata Sandi dan Keamanan');
    }

    /**
     * Rantai crumb: Pengaturan / Kata Sandi dan Keamanan.
     *
     * Sebelumnya halaman ini sama sekali tidak punya getBreadcrumbs(), jadi
     * Filament merender kosong dan satu-satunya cara naik adalah tombol
     * "Kembali ke Pengaturan". Trait HasDynamicBreadcrumbs yang dipakai
     * halaman ini hanya menyediakan pembantu -- Page::getBreadcrumbs()
     * tetap perlu ditulis sendiri di sini.
     *
     * @return array<string|\Illuminate\Support\HtmlString, string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }}
