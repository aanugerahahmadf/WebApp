<?php

namespace App\Providers\Filament\AdminPanelProvider;

use App\Filament\Admin\Auth\SignIn\SignIn;
use App\Filament\Admin\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\Admin\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\Admin\Auth\VerifyOtp\VerifyOtp;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Pages\EditProfilePage\EditProfilePage;
use App\Filament\Admin\Widgets\OrdersChart\OrdersChart;
use App\Filament\Admin\Widgets\RecentOrders\RecentOrders;
use App\Filament\Admin\Widgets\RevenueChart\RevenueChart;
use App\Filament\Admin\Widgets\StatsOverview\StatsOverview;
use App\Filament\Concerns\RedirectsLogoutToWelcomeHome;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;

use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\SuperAdmin\SuperAdmin;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Support\AppPlatform\AppPlatform;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\View\PanelsRenderHook;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    // SignOut / Logout panel admin diarahkan ke Welcome Home.
    use RedirectsLogoutToWelcomeHome;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->login(SignIn::class)
            // Slug route sign-in panel admin = `signin` (bukan `login`), jadi
            // URL utamanya `/admin/signin`. Nama route tetap
            // `filament.admin.auth.login`; URL lama `/admin/login`
            // dilayani redirect di routes/web/web.php.
            ->loginRouteSlug('signin')
            // Tidak ada ->registration() dan memang tidak akan ada: akun admin
            // dibuat dari luar (seed / panel user), jadi `/admin/register`
            // sengaja 404. Class-nya sendiri tetap ada di
            // app/Filament/Admin/Auth/Register/Register.php (tidak dihapus),
            // kalau suatu saat dibutuhkan tinggal daftarkan di sini.
            // ->registration(Register::class)
            ->passwordReset(
                OtpRequestPasswordReset::class,
                OtpResetPassword::class
            )
            ->emailVerification(OtpEmailVerificationPrompt::class)
            // ->sidebarFullyCollapsibleOnDesktop()
            ->brandName(fn () => __('Dekorasi Bunga Pernikahan'))
            ->brandLogo(fn () => '/images/logo.png')
            ->brandLogoHeight('5rem')
            // ->simplePageMaxContentWidth(MaxWidth::Small)
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->defaultThemeMode(ThemeMode::System)
            ->topNavigation()
            // ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                // Switcher tema + bahasa di menu geser (sidebar) -- HP,
                // mobile web Android/iOS, app shell Android/iOS, dan aplikasi
                // desktop. Sama persis dengan WelcomePanelProvider dan
                // UserPanelProvider, lewat predikat yang sama.
                //
                // Panel admin sebelumnya tidak punya switcher bahasa maupun
                // tema di sidebar sama sekali; yang ada di topbar cuma
                // theme switcher di dalam user menu. Di HP topbar itu menyingkir
                // (user menu tidak dirender untuk layar sempit), jadi tanpa
                // hook ini panel admin tidak punya cara ganti tema maupun
                // bahasa di HP.
                //
                // Partial themanya di Shared/ karena isinya tanpa panel; view
                // bahasanya tetap milik Admin (warna aktifnya indigo,
                // mengikuti brand panel ini).
                //
                // Di tablet dan website desktop switcher tetap di topbar, jadi
                // hook sidebar ini mengembalikan string kosong di sana --
                // AppPlatform::switchersBelongInSidebar() yang memutuskan, sama
                // seperti ketiga hook lain, supaya tidak pernah dobel.
                //
                // Kedua switcher teleport ke <body>, jadi overflow sidebar
                // tidak akan memotongnya.
                //
                // Tanpa wrapper sendiri, sama seperti di WelcomePanelProvider
                // dan UserPanelProvider: barisnya adalah baris logo di dalam
                // header sidebar, dan wrapper-nya (.fi-sidebar-switchers)
                // dimiliki file override sidebar per panel --
                // resources/views/{Panel}/vendor/filament-panels/components/
                // sidebar/index.blade.php.
                fn (): View|string => AppPlatform::switchersBelongInSidebar()
                    ? view('Shared.components.theme-switcher.theme-switcher')->render()
                        .view('Admin.filament-language-switcher.language-switcher.language-switcher')->render()
                    : '',
            )
            ->renderHook(
                'panels::styles.after',
                fn (): string => Blade::render('@vite(\'resources/css/Admin/Admin.css\')')
            )
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => Auth::user()?->full_name ?? __('Profil'))
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon('eos-account-circle')
                    ->visible(fn (): bool => Auth::check()),

                // SignOut: label dikunci "Sign Out" (bukan "Log out"/"Keluar"
                // hasil terjemahan per-bahasa) dan tujuannya Welcome Home.
                // `->url()` tidak di-set -- Filament tetap POST ke route logout
                // panel admin, lalu WelcomeLogoutResponse mengarahkan ke Welcome
                // Home. Lihat RedirectsLogoutToWelcomeHome.
                'logout' => static::signOutMenuItem(),
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('Beranda')),
                NavigationGroup::make()->label(fn () => __('Data Master')),
                NavigationGroup::make()->label(fn () => __('Transaksi')),
                NavigationGroup::make()->label(fn () => __('Pesan')),
                NavigationGroup::make()->label(fn () => __('Manajemen Legal')),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                StatsOverview::class,
                RevenueChart::class,
                OrdersChart::class,
                RecentOrders::class,
            ])
            ->middleware([
                ClerkFilamentAuth::class,
                // WAJIB subclass APLIKASI. Lihat catatan lengkap di
                // UserPanelProvider: yang base punya $except kosong sehingga
                // POST /admin/logout kena 419.
                VerifyCsrfToken::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SuperAdmin::class,
            ])
            ->routes(function (Panel $panel): void {
                VerifyOtp::registerRoutes($panel);
            });
    }
}
