<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\RecentEmails;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity\HandlesPasswordSecurity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use Filament\Pages\Page;

/**
 * Ganti email dengan verifikasi OTP.
 *
 * Halaman terpisah, bukan ?section= di satu kelas giant. URL:
 *   /user/settings/password-security/recent-emails
 */
class RecentEmails extends Page
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
        return ['changeEmailForm'];
    }

    use HandlesPasswordSecurity;

    protected static string $view = 'User.pages.settings-page.password-security-page.security-check.recent-emails.recent-emails';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        return 'settings/password-security/recent-emails';
    }

    public function getTitle(): string
    {
        return __('Ubah Email');
    }

    public function getHeading(): string
    {
        return __('Ubah Email');
    }

    public function getSubheading(): ?string
    {
        return __('Masukkan email baru, lalu verifikasi kode OTP yang dikirimkan ke email tersebut.');
    }

    /**
     * @return array<string|\Illuminate\Support\HtmlString>
     */
    public function getBreadcrumbs(): array
    {
        return $this->securityBreadcrumbs();
    }
}
