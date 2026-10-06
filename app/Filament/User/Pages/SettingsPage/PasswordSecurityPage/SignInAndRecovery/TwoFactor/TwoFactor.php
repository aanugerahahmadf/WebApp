<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Ringkasan 2FA. Aktivasi Authenticator App ada di TwoFactorySetup,
 * halaman turunannya -- bukan form inline di sini.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/two-factor
 */
class TwoFactor extends Page
{
    use HasDynamicBreadcrumbs;

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.sign-in-and-recovery.two-factor.two-factor';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/two-factor';
    }

    public function getTitle(): string
    {
        return __('Autentikasi Dua Faktor');
    }

    public function getHeading(): string
    {
        return __('Autentikasi Dua Faktor');
    }

    public function getSubheading(): ?string
    {
        return __('Kelola metode verifikasi tambahan, aplikasi autentikasi, dan perangkat tepercaya dalam satu tempat.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
