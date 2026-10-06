<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Sesi perangkat yang sedang aktif.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/sign-in-activity
 */
class SignInActivity extends Page
{
    use HasDynamicBreadcrumbs;

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.security-check.sign-in-activity.sign-in-activity';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/sign-in-activity';
    }

    public function getTitle(): string
    {
        return __('Tempat Anda Sign In');
    }

    public function getHeading(): string
    {
        return __('Tempat Anda Sign In');
    }

    public function getSubheading(): ?string
    {
        return __('Lihat dan keluarkan sesi perangkat yang aktif.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
