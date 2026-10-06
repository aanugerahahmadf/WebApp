<?php

namespace App\Providers\Filament\WelcomePanelProvider;

use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Filament\Welcome\Pages\Home\Home;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Support\AppPlatform\AppPlatform;
use App\Support\PanelGlassCss\PanelGlassCss;
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
 *       has no auth pages of its own: the account icon in the topbar opens a
 *       dropdown whose single item is Sign In To Account (id: Masuk ke Akun),
 *       which sends guests to the user panel's guest auth landing page
 *       (/user/auth) -- that page offers both the email/password form and
 *       Continue With Google -- and from there on to registration, which the
 *       topbar deliberately does not link to.
 *
 *   ->userMenuItems()
 *       The custom entries point at App\Filament\User pages and Resources, which
 *       resolve to /user URLs and would navigate out of the Welcome panel. The
 *       stock user menu is not used either: Filament only renders it for a
 *       signed-in user, and its avatar trigger needs an avatar that a guest does
 *       not have. Both states come from the global-search.after hook below
 *       instead -- a user icon whose dropdown holds Sign In To Account for
 *       guests, and the same icon linking straight to the user panel home
 *       (/user/home) once signed in -- and Filament's own markup is dropped by the panel-scoped
 *       override in
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
 * exist under the Welcome namespace.
 *
 * Where the controls live depends on the surface, and the rule is a single
 * predicate: AppPlatform::switchersBelongInSidebar(). Both hooks below ask
 * it, so the language and theme switchers can never end up rendered in the
 * topbar AND the sidebar at the same time.
 *
 *   topbar (Top Navigation bawaan Filament) -- tablet dan website desktop.
 *     Language from PanelsRenderHook::GLOBAL_SEARCH_AFTER, which Filament
 *     places inside the topbar's own `ms-auto flex items-center gap-x-4` row
 *     -- the same row as the search box and the user menu. Theme from
 *     Welcome.components.topbar-auth-actions, in that same row.
 *
 *   sidebar (menu geser) -- HP, mobile web Android/iOS, app shell
 *     Android/iOS, dan APLIKASI DESKTOP. Both switchers, from
 *     PanelsRenderHook::SIDEBAR_NAV_START.
 *
 * Desktop app is in the sidebar group because its switchers already live
 * there; a second copy in the topbar would be two buttons for one setting.
 * Note this is not a width test -- the desktop app window has no minimum
 * width, so a breakpoint would flip it back as soon as the window narrowed.
 *
 * RuntimePlatform has no tablet case, so an iPad reads as WebsiteIos;
 * that is why AppPlatform::isTablet() exists separately and why tablet is
 * counted as topbar rather than sidebar.
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
                // Switcher bahasa di Top Navigation bawaan Filament, untuk
                // tablet, macOS, desktop, dan desktop app.
                //
                // Hook GLOBAL_SEARCH_AFTER ini bukan sisipan di luar layout:
                // di vendor/filament/.../components/topbar/index.blade.php,
                // hook itu dirender DI DALAM <nav> topbar, di dalam baris
                // `ms-auto flex items-center gap-x-4` yang juga memuat kotak
                // pencarian, notifikasi, dan user menu. Jadi posisinya benar
                // "di topbar bawaan".
                //
                // Kenapa bukan TOPBAR_END: hook itu dirender SESUDAH baris
                // flex tersebut, jadi tidak ikut gap-x-4-nya dan akan
                // terlihat menempel di tepi paling kanan, terpisah dari
                // alignment topbar yang lain.
                //
                // CUMA bahasa, bukan tema. Switcher tema sudah ada di topbar
                // ini: Welcome.components.topbar-auth-actions merendernya
                // dengan `hidden sm:block`, jadi tampil di tablet ke atas dan
                // disembunyikan di HP (HP ambil yang dari sidebar di bawah).
                // Merender temanya di sini akan memunculkan dua tombol.
                //
                // Syaratnya AppPlatform::switchersBelongInTopbar(), bukan
                // kondisi yang ditulis di sini: hook topbar dan hook sidebar
                // memakai predikat yang sama, jadi mustahil switcher bahasa
                // muncul di KEDUA tempat pada satu permukaan. Aplikasi
                // desktop, yang switcher temanya sudah dihapus dari topbar,
                // karena itu juga tidak boleh dapat switcher bahasa di sana.
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                function (): View|string {
                    if (! AppPlatform::switchersBelongInTopbar()) {
                        return '';
                    }

                    return view('Welcome.filament-language-switcher.language-switcher.language-switcher')->render();
                },
            )
            ->renderHook(
                // Switcher tema + bahasa di menu geser (sidebar) -- HP,
                // mobile web Android/iOS, app shell Android/iOS, DAN aplikasi
                // desktop.
                //
                // Syaratnya AppPlatform::switchersBelongInSidebar(), yaitu
                // kebalikan persis dari hook topbar di atas:
                //   - HP / mobile web / app shell: topbar menyingkir di bawah
                //     640px, jadi sidebar yang memegang kendali.
                //   - APLIKASI DESKTOP: switcher temanya sengaja dihapus dari
                //     topbar (lihat Welcome.components.topbar-auth-actions),
                //     dan switcher bahasanya mengikuti supaya tidak muncul dua
                //     kali. Sidebar yang menanggung keduanya.
                //   - Tablet dan website desktop: tetap di topbar. Menyalinnya
                //     ke sidebar di sana hanya membuat dua tempat untuk satu
                //     pengaturan.
                //
                // Kedua switcher teleport ke <body> juga, jadi overflow
                // sidebar tidak akan memotongnya.
                //
                // Hook ini hanya mengembalikan ISI switcher-nya, tanpa wrapper
                // sendiri: barisnya adalah baris logo di dalam header sidebar,
                // dan wrapper-nya dimiliki file override sidebar (lihat
                // resources/views/{Panel}/vendor/filament-panels/components/
                // sidebar/index.blade.php, blok .fi-sidebar-switchers).
                //
                // Kenapa tidak di sini: hook ini deciding KAPAN, file override
                // yang memutuskan DI MANA. Kalau wrapper-nya ikut di sini,
                //_switchers_ dan "tepat di sebelah logo" jadi dua keputusan
                // yang berbeda di dua file, dan menggeser switcher berarti
                // mengedit view Filament yang bukan milik kita.
                PanelsRenderHook::SIDEBAR_NAV_START,
                function (): View|string {
                    if (! AppPlatform::switchersBelongInSidebar()) {
                        return '';
                    }

                    return view('Shared.components.theme-switcher.theme-switcher')->render()
                        .view('Welcome.filament-language-switcher.language-switcher.language-switcher')->render();
                },
            )
            ->renderHook(
                'panels::global-search.after',
                // A user icon + dropdown for guests, the same icon linking to
                // /user/home for signed-in visitors -- rendered where Filament puts
                // the user menu, except guests never get a user menu at all, so this
                // cannot hang off USER_MENU_BEFORE. The avatar itself is dropped by
                // the panel-scoped override in
                // resources/views/Welcome/panel-overrides/filament-panels/components/user-menu.blade.php
                // (registered in AppServiceProvider with the other panel view
                // overrides), because a render hook cannot remove markup.
                fn (): View|string => view('Welcome.components.topbar-auth-actions.topbar-auth-actions', [
                    // Panel-explicit: the user panel's account home, not this
                    // panel's dashboard -- a signed-in visitor is already
                    // standing on the storefront, so the icon has to take them
                    // somewhere. Direct link, no dropdown: one destination, one
                    // control. (On /welcome and /welcome/home they never see it
                    // anyway -- both redirect signed-in visitors here.)
                    //
                    // The guest dropdown item -> /user/auth, the guest auth landing
                    // page (heading "Welcome Back" + Sign In To Account button +
                    // Continue With Google), NOT the email/password form directly.
                    // Di-pointing ke Auth::class (bukan route()) supaya perubahan
                    // slug/path cuma perlu diubah di satu tempat.
                    //
                    // Alias `UserAuth` wajib: file ini juga memakai facade
                    // Illuminate\Support\Facades\Auth untuk Auth::check() di
                    // hook bottom-nav, jadi nama `Auth` sudah terpakai.
                    //
                    // No register URL: the dropdown offers sign-in only. Registration
                    // is still one click away, via the "Sudah/Belum memiliki akun"
                    // link on the auth page.
                    'homeUrl' => route('filament.user.pages.home'),
                    'loginUrl' => UserAuth::getUrl(),
                ]),
            )
            ->renderHook(
                // Panel-scoped stylesheet. It is a mirror of resources/css/User/
                // User.css with the selectors re-scoped to .fi-panel-welcome, so
                // the two storefronts look alike without forking the theme.
                'panels::styles.after',
                // Lapisan kaca dimuat lewat <link> statis, bukan lewat @vite, supaya
                // mengedit public/css/panel-glass.css langsung berlaku tanpa
                // `npm run build`. Diletakkan setelah @vite karena berkacanya
                // memakai token --fi-glass-* yang didefinisikan di Shared.css.
                fn (): string => Blade::render('@vite(\'resources/css/Welcome/Welcome.css\')')
                    ."\n"
                    .PanelGlassCss::link()
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
                // WAJIB subclass APLIKASI. Lihat catatan lengkap di
                // UserPanelProvider: yang base punya $except kosong sehingga
                // POST /welcome/logout kena 419.
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
