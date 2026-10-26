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
use App\Filament\Admin\Widgets\StoreProgressRoadmap\StoreProgressRoadmap;
use App\Filament\Concerns\RedirectsLogoutToWelcomeHome;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;

use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\SuperAdmin\SuperAdmin;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Support\AppIcon\AppIcon;
use App\Support\AppPlatform\AppPlatform;
use App\Support\AppFont\AppFont;
use App\Support\DesktopApp\DesktopApp;
use App\Support\PanelGlassCss\PanelGlassCss;
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
            // Guard TERPISAH: provider `admins` -> tabel `admins`.
            //
            // Dulu 'web' (tabel `users`), sekarang guard sendiri. Konsekuensinya:
            //   - session admin dan session shopper terpisah di session yang sama,
            //     jadi login di /admin/signin tidak otomatis berlaku di /signin.
            //   - user menu panel ini HARUS baca guard `admin`; `Auth::user()`
            //     membaca guard default `web` yang selalu null di sini.
            //   - `AdminAccess` (semua canViewAny/canEdit di panel ini) juga
            //     membaca guard `admin`, jadi satu sumber kebenaran.
            //
            // @see \App\Models\Admin\Admin::$guard_name
            ->authGuard('admin')
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
            ->sidebarFullyCollapsibleOnDesktop()
            ->brandName(fn () => AppFont::brandName())
            ->brandLogo(fn () => AppFont::brandLogo())
            ->brandLogoHeight('5rem')
            // Font & tebal: dari config/app-font.php, lewat AppFont.
            //
            // Ini BUKAN tempat mengatur -- hanya tempat MEMBACA. Nilai
            // keluarga huruf dan beratnya ada di config/app-font.php, dan
            // ketiga panel memanggil AppFont yang sama, jadi mengubah di sana
            // mengubah admin, user, dan welcome sekaligus.
            //
            // Setelah mengubahnya jalankan `php artisan optimize:clear`,
            // karena config ikut ter-cache.
            //
            // Dua bagian yang diurus AppFont:
            //   - `->font()` menulis `--font-family`, dipakai preset Tailwind
            //     Filament (`sans: ['var(--font-family)', ...]`).
            //   - `AppFont::styles()` di hook `panels::styles.after` yang
            //     menulis `--app-font-weight`, karena `Panel::font()` tidak
            //     pernah menyentuh font-weight.
            //
            // WAJIB eksplisit di sini: `Panel::getFontFamily()` default-nya
            // 'Inter' BUKAN null, jadi panel yang tidak memanggil ->font()
            // diam-diam memuat webfont Inter dari Bunny Fonts.
            ->font(AppFont::adminFamily(), provider: AppFont::provider())
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
            ->topbar()
            ->breadcrumbs(false)
            // ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('Shared.components.notification-sound.notification-sound')->render(),
            )
            ->renderHook(
                // PENCARIAN GLOBAL ADMIN SEBAGAI MODAL (2026-10-26).
                //
                // Kotak pencarian di sidebar hanyalah PEMICU; field dan
                // daftar hasilnya dirender di sini, di BODY_END. Modal tidak
                // boleh diletakkan di dalam <aside class="fi-sidebar">:
                // sidebar memakai `transform` (translate-x) sehingga anak
                // `position: fixed` akan diposisikan relatif terhadap
                // sidebar, bukan viewport -- modalnya menyempit selebar
                // kolom.
                //
                // Isinya tetap komponen Filament `GlobalSearch`, jadi
                // debounce, pencarian per resource, dan navigasi keyboard
                // tidak berubah. Yang berubah hanya permukaannya: hasil
                // tidak lagi berupa dropdown yang menimpa navigasi.
                //
                // Partial: resources/views/Admin/components/
                // global-search-modal/global-search-modal.blade.php
                PanelsRenderHook::BODY_END,
                fn (): string => view('Admin.components.global-search-modal.global-search-modal')->render(),
            )
            ->renderHook(
                // SHELL DESKTOP (Electron) -- app/Capacitor/DekstopAdminApp.
                //
                // Panel admin ini adalahisi dari "Dekorasi Admin", shell
                // desktop yang membungkusnya di jendela Electron. Partial ini
                // hanya dirender kalau request-nya benar-benar datang dari
                // shell desktop, jadi website dan panel lain tidak tersentuh.
                //
                // Yang dikerjakannya: menandai <html> sebagai shell desktop,
                // mempublikasikan window.DesktopApp, dan mendahulukan judul
                // aplikasi pada <title> (Electron memakainya sebagai judul
                // jendela).
                PanelsRenderHook::BODY_END,
                fn (): View|string => AppPlatform::isDesktopApp()
                    ? view('Shared.components.desktop-shell.desktop-shell')->render()
                    : '',
            )
            ->renderHook(
                // Partial shell desktop, KEDUA hook.
                //
                // Layout Filament punya dua bentuk: layout biasa (dashboard,
                // halaman resource) yang punya BODY_END, dan layout SIMPLE
                // (halaman auth: Sign In, OTP, Complete Profile) yang tidak.
                //
                // Jadi partial yang sama didaftarkan dua kali. Kalau hanya
                // di BODY_END, hotkey F1..F12 (yang didaftarkan partial itu)
                // hilang di seluruh halaman auth -- tanpa error apa pun.
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): View|string => DesktopApp::shellPartial(),
            )
            // TIDAK ADA hook GLOBAL_SEARCH_AFTER di panel ini: switcher
            // BAHASA + TEMA panel admin tidak lagi berada di topbar.
            //
            // Riwayat permukaan switcher panel admin (semua tanpa predikat
            // platform, berlaku untuk HP, tablet, website desktop, dan
            // aplikasi desktop):
            //
            //   1. topbar (hook GLOBAL_SEARCH_AFTER)
            //   2. sebaris dengan logo di sidebar (hook SIDEBAR_NAV_START
            //      dipanggil di dalam <header> sidebar)
            //   3. DASAR sidebar, bersama blok profil (permintaan terakhir,
            //      2026-10-25) -- hook SIDEBAR_NAV_START tetap, tapi
            //      dipanggil dari blok `fi-sidebar-bawah` di override
            //      sidebar/index.blade.php, SETELAH </nav>.
            //
            // Versi lama di header TIDAK dihapus, hanya dinonaktifkan lewat
            // flag $switchersAtSidebarBottom di file override sidebar itu.
            //
            // Panel Welcome dan User TIDAK ikut: kedua panel itu masih
            // memutuskan permukaan lewat AppPlatform::switchersBelong*().
            //
            // Switcher bawaan Filament (tiga ikon sun/moon/monitor) di user
            // menu tetap DIMATIKAN -- lihat override view
            // resources/views/Admin/vendor/filament-panels/components/
            // user-menu.blade.php. Dua switcher untuk satu pengaturan tidak
            // boleh terjadi; test penjaga:
            // tests/Feature/Shared/SwitcherPlacementTest/ dan
            // tests/Feature/Shared/ThemeSwitcherOnceTest/.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                // Switcher tema + bahasa di SIDEBAR, di DASAR sidebar
                // bersama blok profil -- SELALU, tanpa memandang platform.
                //
                // Riwayat: tadinya predikat AppPlatform::switchersBelongInSidebar()
                // menaruhnya di topbar untuk tablet/desktop/app-desktop; lalu
                // dipindah sebaris dengan logo; sekarang di dasar sidebar.
                //
                // Partialsnya tetap sama: temanya di Shared/ (isinya tanpa
                // panel), bahasanya view Admin (warna aktifnya indigo,
                // mengikuti brand panel ini).
                //
                // Kedua switcher teleport ke <body>, jadi overflow sidebar
                // tidak akan memotongnya.
                //
                // Tanpa wrapper sendiri: baris tempat keduanya berdiri
                // (.fi-sidebar-switchers) dimiliki file override sidebar --
                // resources/views/Admin/vendor/filament-panels/components/
                // sidebar/index.blade.php -- dan di file itu pula hook ini
                // dipanggil, di blok `fi-sidebar-bawah` setelah </nav>.
                fn (): View|string => view('Admin.components.theme-switcher.theme-switcher')->render()
                    .view('Admin.filament-language-switcher.language-switcher.language-switcher')->render(),
            )
            ->renderHook(
                // KUSTOMIZER TAMPILAN WORDPRESS-STYLE: panel gaya dirender
                // TEPAT DI ATAS TABEL halaman LIST resource
                // (RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE). Halaman
                // form/edit/view TIDAK disuntik apa pun (isi rekam
                // beda-beda, panel di sana malah jelek). Daftar isinya
                // menyesuaikan isi form resource: peran teks dari
                // deklarasi PageStyle milik file resource + satu baris
                // gaya per FIELD teks di schema form-nya.
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
                function (): string {
                    $route = (string) \Illuminate\Support\Facades\Route::currentRouteName();

                    if (! str_contains($route, '.resources.') || ! str_ends_with($route, '.index')) {
                        return '';
                    }

                    return view('Livewire.Admin.page-customizer.hook')->render();
                }
            )
            ->renderHook(
                // Semua halaman Admin, termasuk page/widget yang tidak
                // memiliki tabel, tetap menjadi target scope tipografi.
                PanelsRenderHook::PAGE_END,
                fn (): string => view('Livewire.Admin.page-customizer.scope-marker')->render(),
            )
            ->renderHook(
                'panels::styles.after',
                // Lapisan kaca dimuat lewat <link> statis, bukan lewat @vite, supaya
                // mengedit public/css/panel-glass.css langsung berlaku tanpa
                // `npm run build`. Diletakkan setelah @vite karena berkacanya
                // memakai token --fi-glass-* yang didefinisikan di Shared.css.
                fn (): string => Blade::render('@vite(\'resources/css/Admin/Admin.css\')')
                    ."\n"
                    // Berat teks dari config/app-font.php. `Panel::font()`
                    // hanya menulis --font-family, tidak menyentuh
                    // font-weight sama sekali -- jadi blok ini satu-satunya
                    // cara membuat "tebal" berlaku di ketiga panel.
                    .AppFont::styles('admin')
                    ."\n"
                    .PanelGlassCss::link()
            )
            ->userMenuItems([
                'profile' => MenuItem::make()
                    // Guard 'admin', bukan default 'web' -- lihat catatan di
                    // ->authGuard() di atas.
                    ->label(fn (): string => Auth::guard('admin')->user()?->full_name ?? __('Profil'))
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon(AppIcon::PROFILE)
                    ->visible(fn (): bool => Auth::guard('admin')->check()),

                // Pintu ke shell desktop yang lain: dari aplikasi admin ini
                // pengguna bisa membuka toko (panel Welcome) TANPA keluar
                // dari jendela.
                //
                // Hanya di desktop app. Di website,avigate ke /home dari
                // panel admin cuma memindahkan tab -- dan buka di tab baru
                // justru lebih masuk akal, jadi menunya tidak muncul di sana.
                //
                // Label harus closure: panel dibangun oleh middleware
                // `panel:admin`, yang berjalan SEBELUM SetLocale, jadi
                // `->label(__('...'))` akan membekukan labelnya di locale
                // default.
                'desktop-companion' => MenuItem::make()
                    ->label(fn (): string => DesktopApp::companionLabel('admin') ?? __('Buka Toko'))
                    ->url(fn (): string => DesktopApp::companionUrl('admin') ?? Home::getUrl(panel: 'admin'))
                    ->icon(AppIcon::EXTERNAL_LINK)
                    ->visible(fn (): bool => AppPlatform::isDesktopApp() && DesktopApp::companionUrl('admin') !== null),

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
                NavigationGroup::make()->label(fn () => __('Pengaturan Sistem')),
                NavigationGroup::make()->label(fn () => __('Pesan')),
            ])
            // TIDAK ada ->navigationItems() manual di sini.
            //
            // Pembatasan navigasi per role dilakukan lewat
            // `Resource::canViewAny()` / `Page::canAccess()` (lihat
            // app/Support/AdminAccess/AdminAccess.php). Filament memakai
            // kedua method itu untuk dua hal sekaligus: menyembunyikan entri
            // navigasi DAN memblokir URL yang diketik langsung -- jadi navigasi
            // dan otorisasi tidak bisa berbeda.
            //
            // Versi sebelumnya mendaftarkan NavigationItem manual dengan FQCN
            // halaman. Itu rapuh di project ini: konvensi folder-per-class
            // membuat FQCN halaman selalu berakhir dengan nama class-nya
            // (`...\Pages\ListCatalogs\ListCatalogs`, bukan
            // `...\Pages\ListCatalogs`), dan karena navigasi dirender di layout
            // utama, satu FQCN salah = 500 di SELURUH halaman panel admin --
            // termasuk halaman yang tidak terkait.
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                StoreProgressRoadmap::class,
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
