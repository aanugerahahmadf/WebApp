<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\SavedLogin;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Pengaturan info Sign In yang tersimpan di perangkat.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/saved-login
 */
class SavedLogin extends Page
{
    use HasDynamicBreadcrumbs;

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.sign-in-and-recovery.saved-login.saved-login';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/saved-login';
    }

    public function getTitle(): string
    {
        return __('Sign In Tersimpan');
    }

    public function getHeading(): string
    {
        return __('Sign In Tersimpan');
    }

    public function getSubheading(): ?string
    {
        return __('Kelola perangkat yang menyimpan info Sign In akun Anda.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
