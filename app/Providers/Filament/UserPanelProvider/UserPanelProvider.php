<?php

namespace App\Providers\Filament\UserPanelProvider;

use App\Filament\User\Auth\Login\Login;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\User\Auth\Register\Register;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp;
use App\Filament\User\Pages\CompleteProfilePage\CompleteProfilePage;
use App\Filament\User\Pages\Dashboard\Dashboard;
use App\Filament\User\Pages\EditProfilePage\EditProfilePage;
use App\Filament\User\Pages\HelpCenterPage\HelpCenterPage;
use App\Filament\User\Pages\PrivacyTermsPage\PrivacyTermsPage;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use App\Filament\User\Resources\HistoryResource\HistoryResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
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
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('user')
            ->path('user')
            ->login(Login::class)
            ->registration(Register::class)
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
                    if (NativeServiceProvider::isAnyMobile()) {
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
                'panels::footer',
                fn (): ?View => (
                    ! str_contains(request()->route()?->getName() ?? '', 'auth')
                    && ! NativeServiceProvider::isAnyMobile()
                ) ? view('User.footer.footer') : null
            )
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->pages([
                Dashboard::class,
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
            ])
            ->routes(function (Panel $panel): void {
                VerifyOtp::registerRoutes($panel);
            });

        $panel->databaseNotifications();

        // snap-script — Handled globally in AppServiceProvider for both Admin and User panels

        return $panel;
    }
}
