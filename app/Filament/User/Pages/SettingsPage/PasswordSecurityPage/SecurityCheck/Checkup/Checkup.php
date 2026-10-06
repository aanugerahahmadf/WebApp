<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\Checkup;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Ringkasan level keamanan akun.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/checkup
 */
class Checkup extends Page
{
    use HasDynamicBreadcrumbs;

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.security-check.checkup.checkup';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/checkup';
    }

    public function getTitle(): string
    {
        return __('Pemeriksaan Keamanan');
    }

    public function getHeading(): string
    {
        return __('Pemeriksaan Keamanan');
    }

    public function getSubheading(): ?string
    {
        return __('Tinjau perlindungan penting untuk akun Anda.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
