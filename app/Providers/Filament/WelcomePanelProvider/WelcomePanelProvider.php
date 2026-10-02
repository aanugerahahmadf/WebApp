<?php

namespace App\Providers\Filament\WelcomePanelProvider;

use App\Filament\Welcome\Pages\Home\Home;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Support\AppPlatform\AppPlatform;
use App\Support\MobileNav\MobileNav;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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

/**
 * The public storefront panel -- the one '/' resolves to.
 *
 * Kept deliberately in step with UserPanelProvider: both panels render the same
 * storefront from two entry points, so a layout change in one has to land in the
 * other or the panels drift apart. The chain is written in the same order, with
 * the same values, as UserPanelProvider::panel().
 *
 * What is intentionally NOT copied from UserPanelProvider:
 *
 *   ->login() / ->registration() / ->passwordReset() / ->emailVerification()
 *       This panel is the public storefront and the one `/` resolves to, so it
 *       has no auth pages of its own: the Masuk button in the topbar sends
 *       guests to the user panel's login, which is where the account flows
 *       already live -- and from there on to registration, which the topbar
 *       deliberately does not link to.
 *
 *   ->userMenuItems()
 *       The custom entries point at App\Filament\User pages and Resources, which
 *       resolve to /user URLs and would navigate out of the Welcome panel. The
 *       stock user menu is not used either: its avatar trigger and its Profile
 *       item are replaced by the Masuk / Beranda buttons rendered from
 *       the global-search.after hook below, and the avatar itself is dropped by
 *       the panel-scoped override in
 *       resources/views/Welcome/panel-overrides/filament-panels/components/user-menu.blade.php
 *       (registered in AppServiceProvider with the other panel view overrides),
 *       because a render hook cannot remove markup.
 *
 *   ->routes(VerifyOtp::registerRoutes())
 *       Part of the User OTP password-reset flow, which is not registered here.
 *
 *   ->authMiddleware([Authenticate::class])
 *       Replaced by AuthenticateWelcome, which keeps the gate but lets guests
 *       through on the landing page and the catalog. Filament's middleware has
 *       nowhere to send a guest from this panel (no login page is registered) and
 *       would have made `/` unreachable.
 *
 * The language-switcher view and the stylesheet stay panel-scoped
 * (Welcome.*, resources/css/Welcome/Welcome.css) because those assets only
 * exist under the Welcome namespace. The switcher keeps the user panel's
 * "hidden on mobile" behaviour so the two topbars stay identical in shape.
 */
class WelcomePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('welcome')
            ->path('welcome')
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

                    return view('Welcome.filament-language-switcher.language-switcher.language-switcher');
                },
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                function (): View|string {
                    // Switcher tema + bahasa untuk menu geser. Sidebar
                    // bawaan Filament hanya terbuka sebagai menu geser di
                    // layar kecil (panel ini pakai top navigation), jadi aman
                    // selalu dirender: di desktop tidak pernah terlihat.
                    // Berbasis viewport (bukan UA) agar konsisten dengan
                    // topbar yang menyembunyikan switcher-nya di bawah sm.
                    // Dropdown-nya teleport ke <body> jadi aman dari
                    // overflow sidebar.
                    return '<div class="flex items-center justify-end gap-2 px-4 pb-2">'
                        .view('Welcome.components.theme-switcher.theme-switcher')->render()
                        .view('Welcome.filament-language-switcher.language-switcher.language-switcher')->render()
                        .'</div>';
                },
            )
            ->renderHook(
                'panels::global-search.after',
                // Masuk / Beranda plus the theme switcher, rendered where Filament
                // puts the user menu -- except for guests, who never get a user
                // menu at all, so this cannot hang off USER_MENU_BEFORE. The
                // avatar itself is dropped by the panel-scoped override in
                // resources/views/Welcome/panel-overrides/filament-panels/components/user-menu.blade.php
                // (registered in AppServiceProvider with the other panel view
                // overrides), because a render hook cannot remove markup.
                fn (): View|string => view('Welcome.components.topbar-auth-actions.topbar-auth-actions', [
                    // Panel-explicit: the user panel's account home, not this
                    // panel's dashboard -- a signed-in visitor is already
                    // standing on the storefront, so "Beranda" has to take them
                    // somewhere. Matches where Masuk leads.
                    //
                    // No register URL: the topbar offers Masuk only. Registration
                    // is still one click away, via the "Belum memiliki akun? Daftar"
                    // link on the login page.
                    'homeUrl' => route('filament.user.pages.home'),
                    'loginUrl' => route(AuthenticateWelcome::LOGIN_ROUTE),
                ]),
            )
            ->renderHook(
                // Panel-scoped stylesheet. It is a mirror of resources/css/User/
                // User.css with the selectors re-scoped to .fi-panel-welcome, so
                // the two storefronts look alike without forking the theme.
                'panels::styles.after',
                fn (): string => Blade::render('@vite(\'resources/css/Welcome/Welcome.css\')')
            )
            ->renderHook(
                // Phone-only bottom navigation. Declared per panel in
                // config/app-platform.php; MobileNav suppresses it on auth
                // pages and on desktop, so this hook is safe to always register.
                //
                // Still keyed on 'user': config/app-platform.php defines
                // mobile_nav.items for admin and user only, so 'welcome' has no
                // destinations of its own and would render nothing.
                //
                // The auth check is new: every destination on that bar is
                // account-shaped (cart, orders, messages, profile) and this
                // panel is browsable by guests, who would only be bounced to
                // the login page by every tap.
                PanelsRenderHook::BODY_END,
                fn (): View|string => Auth::check() && MobileNav::shouldRender('user')
                    ? view('Shared.components.mobile-bottom-nav.mobile-bottom-nav')
                    : '',
            )
            ->discoverResources(in: app_path('Filament/Welcome/Resources'), for: 'App\\Filament\\Welcome\\Resources')
            ->discoverPages(in: app_path('Filament/Welcome/Pages'), for: 'App\\Filament\\Welcome\\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Welcome/Widgets'), for: 'App\\Filament\\Welcome\\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('Beranda')),
                NavigationGroup::make()->label(fn () => __('Belanja & Jelajahi')),
                NavigationGroup::make()->label(fn () => __('Transaksi & Aktivitas')),
                NavigationGroup::make()->label(fn () => __('Pesan')),
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
                // Not Filament's Authenticate: this panel is the public
                // storefront, so guests may open the landing page and the
                // catalog and are sent to the user panel login for anything
                // account-shaped. See AuthenticateWelcome.
                AuthenticateWelcome::class,
            ]);

        $panel->databaseNotifications();

        // snap-script — Handled globally in AppServiceProvider for both Admin and User panels

        return $panel;
    }
}
