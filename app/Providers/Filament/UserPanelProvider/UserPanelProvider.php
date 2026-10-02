<?php

namespace App\Providers\Filament\UserPanelProvider;

use App\Filament\User\Auth\SignIn\SignIn;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\User\Auth\SignUp\SignUp;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp;
use App\Filament\Concerns\RedirectsLogoutToWelcomeHome;
use App\Filament\User\Auth\CompleteProfile\CompleteProfilePage;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\EditProfilePage\EditProfilePage;
use App\Filament\User\Pages\HelpCenterPage\HelpCenterPage;
use App\Filament\User\Pages\PrivacyTermsPage\PrivacyTermsPage;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use App\Filament\User\Resources\HistoryResource\HistoryResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Support\AppPlatform\AppPlatform;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class UserPanelProvider extends PanelProvider
{
    // SignOut / Logout panel user diarahkan ke Welcome Home.
    use RedirectsLogoutToWelcomeHome;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('user')
            ->path('user')
            ->login(SignIn::class)
            // Slug route sign-in panel user = `signin` (bukan `login`),
            // jadi URL utamanya `/user/signin`. Nama route tetap
            // `filament.user.auth.login` sehingga semua pemanggilan
            // route()/Filament::getLoginUrl() tetap aman. URL lama
            // `/user/login` dilayani redirect di routes/web/web.php.
            ->loginRouteSlug('signin')
            ->registration(SignUp::class)
            // Sejalan dengan sign-in: slug route sign-up = `signup`, jadi URL
            // utamanya `/user/signup`. Nama route tetap
            // `filament.user.auth.register`; URL lama `/user/register`
            // dilayani redirect di routes/web/web.php.
            ->registrationRouteSlug('signup')
            ->passwordReset(
                OtpRequestPasswordReset::class,
                OtpResetPassword::class
            )
            ->emailVerification(OtpEmailVerificationPrompt::class)
            ->brandName(fn () => __('Dekorasi Bunga Pernikahan'))
            ->brandLogo(fn () => '/images/logo.png')
            ->brandLogoHeight('5rem')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Yellow,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->font('Inter')
            ->defaultThemeMode(ThemeMode::System)
            ->topNavigation()
            // ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->unsavedChangesAlerts(false)
            ->collapsibleNavigationGroups()
            ->globalSearch()
            ->renderHook(
                'panels::global-search.after',
                function (): View|string {
                    // Keep the switcher available on website/desktop apps, but
                    // hide it on native mobile and mobile-browser requests.
                    if (AppPlatform::isAnyMobile()) {
                        return '';
                    }

                    return view('User.filament-language-switcher.language-switcher.language-switcher');
                },
            )
            ->renderHook(
                'panels::styles.after',
                fn (): string => Blade::render('@vite(\'resources/css/User/User.css\')')
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                // Breadcrumb halaman Auth (Sign In / Sign Up / OTP / Complete
                // Profile) DI ATAS logo, rata kiri.
                //
                // Dipindah ke hook, bukan @include per view, karena
                // `filament-panels::components.page.simple` menaruh slot di
                // bawah header/logo -- hook ini dirender tepat sebelumnya, jadi
                // posisinya benar tanpa tiap view harus tahu soalnya.
                //
                // Komponen dikirim eksplisit karena hook dijalankan di luar
                // lifecycle Livewire; partial tetap jatuh ke $this sebagai
                // fallback. Halaman tanpa trait HasAuthBreadcrumbs tidak punya
                // getBreadcrumbs() -> partial tidak merender apa pun.
                fn (): View|string => view('User.auth.breadcrumbs.breadcrumbs', [
                    'authPage' => Livewire::current(),
                ])
            )
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->pages([
                Home::class,
                CompleteProfilePage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\\Filament\\User\\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('Beranda')),
                NavigationGroup::make()->label(fn () => __('Belanja & Jelajahi')),
                NavigationGroup::make()->label(fn () => __('Transaksi & Aktivitas')),
                NavigationGroup::make()->label(fn () => __('Pesan')),
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => Auth::user()?->full_name ?? __('Profil'))
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon('eos-account-circle')
                    ->visible(fn (): bool => Auth::check()),
                'pengaturan' => MenuItem::make()
                    ->label(__('Pengaturan'))
                    ->url(fn (): string => SettingsPage::getUrl())
                    ->icon('heroicon-o-cog-6-tooth'),
                'riwayat' => MenuItem::make()
                    ->label(__('Riwayat'))
                    ->url(fn (): string => HistoryResource::getUrl())
                    ->icon('heroicon-o-clock'),
                'ulasan' => MenuItem::make()
                    ->label(__('Ulasan Saya'))
                    ->url(fn (): string => ReviewResource::getUrl())
                    ->icon('heroicon-o-star'),
                'privacy' => MenuItem::make()
                    ->label(__('Privasi & Ketentuan'))
                    ->url(fn (): string => PrivacyTermsPage::getUrl())
                    ->icon('heroicon-o-shield-check'),
                'bantuan' => MenuItem::make()
                    ->label(__('Pusat Bantuan'))
                    ->url(fn (): string => HelpCenterPage::getUrl())
                    ->icon('heroicon-o-question-mark-circle'),
            ])
            ->middleware([
                ClerkFilamentAuth::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureProfileComplete::class,
            ])
            ->routes(function (Panel $panel): void {
                VerifyOtp::registerRoutes($panel);
            });

        $panel->databaseNotifications();

        // snap-script — Handled globally in AppServiceProvider for both Admin and User panels

        return $panel;
    }
}
