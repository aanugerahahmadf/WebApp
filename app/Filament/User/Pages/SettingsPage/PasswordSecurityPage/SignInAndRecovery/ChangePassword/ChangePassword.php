<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\ChangePassword;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Form ganti kata sandi. Dipisah dari TwoFactor dan halaman lain
 * supaya tiap file hanya satu urusan.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/change-password
 */
class ChangePassword extends Page
{
    use HasDynamicBreadcrumbs;

    /**
     * Hanya form milik halaman ini. Kalau tidak didaftarkan,
     * Filament menganggapnya infolist.
     *
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return ['form'];
    }

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.sign-in-and-recovery.change-password.change-password';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/change-password';
    }

    public function getTitle(): string
    {
        return __('Ubah Kata Sandi');
    }

    public function getHeading(): string
    {
        return __('Ubah Kata Sandi');
    }

    public function getSubheading(): ?string
    {
        return __('Gunakan kata sandi yang kuat untuk menjaga keamanan akun Anda.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
