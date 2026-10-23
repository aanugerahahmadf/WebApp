<?php

namespace App\Providers\Filament\UserPanelProvider;

/*
 * ⚠️ FILE INI SUDAH TIDAK DIPAKAI (2026-10-21).
 *
 * Panel 'user' sudah DILEBUR ke panel 'welcome' (path '/'). Provider ini
 * tidak lagi terdaftar di `bootstrap/providers.php` -- semua halaman yang
 * dicakupnya sekarang hidup di `WelcomePanelProvider`.
 *
 * JANGAN mendaftarkan ulang provider ini tanpa membaca `AGENTS.md` ->
 * "SATU PANEL: 'user' DILEBUR KE 'welcome'". Dua panel di path '/' akan
 * saling menimpa route-nya, dan yang kalah biasanya bukan yang disangka.
 *
 * File ini sengaja DIPAKAI LAGI sebagai sumber rujukan: beberapa trait dan
 * kelas di dalamnya (mis. `RedirectsLogoutToWelcomeHome`) masih dirujuk
 * dari panel admin dan panel welcome.
 */

// Alias wajib: file ini juga memakai facade Illuminate\Support\Facades\Auth
// (untuk label user menu & cek Auth::check()), jadi nama `Auth` terpakai.
use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Filament\User\Auth\SignIn\SignIn;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp;
use App\Filament\User\Auth\SignUp\SignUp;
use App\Filament\User\Auth\LoginResponse\LoginResponse;
use App\Filament\Concerns\RedirectsLogoutToWelcomeHome;
use App\Filament\User\Auth\CompleteProfile\CompleteProfilePage;
use App\Filament\User\Auth\SecurityQuestion\SecurityQuestion;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\EditProfilePage\EditProfilePage;
use App\Filament\User\Pages\HelpCenterPage\HelpCenterPage;
use App\Filament\User\Pages\PrivacyTermsPage\PrivacyTermsPage;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\Checkup\Checkup;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\RecentEmails\RecentEmails;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\SignInActivity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\ChangePassword\ChangePassword;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\SavedLogin\SavedLogin;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Filament\User\Resources\HistoryResource\HistoryResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Support\AppIcon\AppIcon;
use App\Support\AppPlatform\AppPlatform;
use App\Support\AppFont\AppFont;
use App\Support\DesktopApp\DesktopApp;
use App\Support\PanelGlassCss\PanelGlassCss;
use App\Support\TopNavigation\TopNavigation;
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
use App\Filament\Shared\Plugins\UserMulti2faPlugin\UserMulti2faPlugin;
use MarcoGermani87\FilamentCaptcha\FilamentCaptcha;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
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

    public function boot(): void
    {
        // Custom LoginResponse: semua user (admin team, Google OAuth, shopper)
        // yang masuk ke panel USER diarahkan langsung ke /user/home.
        // Filament v3 tidak punya ->loginResponse(), jadi dipakai binding.
        app()->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            LoginResponse::class,
        );
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('user')
            ->path('user')
            ->login(SignIn::class)
            // Slug route sign-in panel user = `signin` (bukan `login`),
            // jadi URL utamanya `/signin`. Nama route tetap
            // `filament.user.auth.login` sehingga semua pemanggilan
            // route()/Filament::getLoginUrl() tetap aman. URL lama
            // `/user/signin` dilayani redirect di routes/web/web.php.
            ->loginRouteSlug('signin')
            // Registrasi email/password AKTIF. Tiga baris di bawah adalah kode
            // ASLI yang lama hanya dikomentari, dan sekarang dipulihkan apa
            // adanya: import di atas, `->registration()` dan
            // `->registrationRouteSlug()` di sini. View
            // `User/auth/sign-up/sign-up` juga masih utuh, jadi tidak ada form
            // yang ditulis ulang.
            //
            // Pendaftaran lewat Google di `/user/auth` TETAP ada dan tidak
            // tidak
            // menggantikan form ini -- keduanya pintu yang berbeda.
            //
            // Karena `->registration()` dipanggil, route
            // `filament.user.auth.register` terdaftar lagi. `routes/web/web.php`
            // masih me-redirect `/user/register` ke `/user/signup`; kedua door
            // itu boleh hidup bersamaan karena keduanya kini punya halaman.
            //
            ->registration(SignUp::class)
            // Sejalan dengan sign-in: slug route sign-up = `signup`, jadi URL
            // utamanya `/signup`. Nama route tetap
            // `filament.user.auth.register`; URL lama `/user/signup`
            // dilayani redirect di routes/web/web.php.
            ->registrationRouteSlug('signup')
            ->passwordReset(
                OtpRequestPasswordReset::class,
                OtpResetPassword::class
            )
            ->emailVerification(OtpEmailVerificationPrompt::class)
            // Sama seperti panel Welcome: logo untuk tamu mendarat di
            // halaman auth landing (/auth), bukan form sign in.
            ->homeUrl(fn (): string => auth()->check()
                ? Home::getUrl(panel: 'user')
                : UserAuth::getUrl())
            ->brandName(fn () => AppFont::brandName())
            ->brandLogo(fn () => AppFont::brandLogo())
            ->brandLogoHeight('5rem')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Yellow,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            // Font & tebal: dari config/app-font.php, lewat AppFont.
            //
            // Nilai keluarga huruf dan berat TIDAK ditulis di sini, supaya
            // ketiga panel tidak punya tiga salinan yang harus diingat semua.
            // Ubah config/app-font.php (atau APP_FONT_FAMILY /
            // APP_FONT_WEIGHT di .env), lalu `php artisan optimize:clear`.
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
            // tetap memuat Inter. Jangan dihapus dan mengandalkan default.
            ->font(AppFont::family(), provider: AppFont::provider())
            ->defaultThemeMode(ThemeMode::System)
            // CHROME DI SEMUA HALAMAN.
            //
            // `topNavigation` = baris link navigasi di dalam topbar.
            // `topbar`        = topbar itu sendiri: logo, pencarian global,
            //                    notifikasi, user menu, switcher bahasa + tema.
            //
            // Dulu KEDUA-nya hanya dimunculkan di halaman Home, jadi halaman
            // lain (Pengaturan, Riwayat, Ulasan, Help Center, semua halaman
            // resource) polos tanpa chrome. Aturan itu dibalik untuk aplikasi
            // desktop (Capacitor): topbar adalah tempat pencarian global, user
            // menu, switcher bahasa/tema, dan tombol kembali -- semuanya
            // dibutuhkan di setiap layar.
            //
            // Sidebar dan bottom nav HP ikut aturan yang sama, lewat
            // TopNavigation::hasChrome() -- sidebar dari override view, bottom
            // nav dari MobileNav::shouldRender().
            //
            // Yang tetap sama: HasDynamicBreadcrumbs selalu diawali crumb
            // 'Beranda' supaya selalu ada jalan pulang.
            //
            // Closure, bukan `true` -- Filament mengevaluasi argumen tiap kali
            // hasTopNavigation()/hasTopbar() dipanggil saat layout dirender.
            //
            // Aturan dan alasannya sama persis di WelcomePanelProvider. Jangan
            // salin logikanya ke sini; cukup panggil helper yang sama.
            ->topNavigation(TopNavigation::always())
            ->topbar(TopNavigation::always())
            // ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->unsavedChangesAlerts(false)
            ->collapsibleNavigationGroups()
            ->globalSearch()
            ->renderHook(
                'panels::global-search.after',
                // Switcher BAHASA + TEMA di Top Navigation -- tablet, website
                // desktop, dan APLIKASI DESKTOP.
                //
                // Syaratnya AppPlatform::switchersBelongInTopbar(), sama dengan
                // yang dipakai hook sidebar di bawah. Kalau hook ini memakai
                // syaratnya sendiri, switcher bahasa bisa muncul dua kali di
                // satu permukaan -- persis yang terjadi di aplikasi desktop
                // sebelum keduanya berbagi predikat yang sama.
                //
                // Switcher TEMA ikut di hook yang sama (bukan di partial
                // terpisah), supaya Topbar dan Sidebar benar-benar Surface
                // yang SALING MENGGANTIKAN: satu di topbar, satu di sidebar,
                // tidak pernah keduanya. Kalau tema dirender dari dua tempat
                // berbeda, akan muncul dobel.
                //
                // Switcher bawaan Filament di dalam user menu sudah dimatikan
                // di override view
                // resources/views/User/vendor/filament-panels/components/
                // user-menu.blade.php, supaya tidak ada dua switcher tema di
                // panel yang sama.
                function (): View|string {
                    if (! AppPlatform::switchersBelongInTopbar()) {
                        return '';
                    }

                    return view('User.filament-language-switcher.language-switcher.language-switcher')->render()
                        .view('Shared.components.theme-switcher.theme-switcher')->render();
                },
            )
            ->renderHook(
                // Switcher tema + bahasa di menu geser (sidebar) -- HP dan
                // mobile web Android/iOS. Sama persis dengan yang
                // WelcomePanelProvider lakukan untuk panelnya sendiri.
                //
                // APLIKASI DESKTOP TIDAK DI SINI: predikat
                // AppPlatform::switchersBelongInSidebar() tidak lagi menghitung
                // desktop app, karena sidebar aplikasi desktop diterjemahkan ke
                // luar layar di layar lebar -- switcher di sana ter-render tapi
                // tidak terlihat. Desktop app pakai topbar.
                //
                // Sidebar belum punya switcher apa pun tanpa hook ini, dan
                // topbar menyingkir di bawah 640px -- jadi di HP kedua
                // switcher ini satu-satunya jalan mengubah tema dan bahasa.
                //
                // Partial themanya di Shared/ karena isinya tanpa panel; view
                // bahasanya tetap milik User (warna aktifnya kuning, mengikuti
                // brand panel ini -- bukan warna panel lain).
                //
                // Hook ini di-`fn` bukan `function ()`, sama seperti yang lain
                // di chain ini, supaya Closure-nya tidak menyimpan $panel.
                //
                // Kedua switcher teleport ke <body>, jadi overflow sidebar
                // tidak akan memotongnya.
                //
                // Tanpa wrapper sendiri, sama seperti di WelcomePanelProvider:
                // barisnya adalah baris logo di dalam header sidebar, dan
                // wrapper-nya (.fi-sidebar-switchers) dimiliki file override
                // sidebar per panel --
                // resources/views/{Panel}/vendor/filament-panels/components/
                // sidebar/index.blade.php.
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): View|string => AppPlatform::switchersBelongInSidebar()
                    ? view('Shared.components.theme-switcher.theme-switcher')->render()
                        .view('User.filament-language-switcher.language-switcher.language-switcher')->render()
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('Shared.components.notification-sound.notification-sound')->render(),
            )
            ->renderHook(
                // SHELL DESKTOP (Electron) -- app/Capacitor/DekstopApp.
                //
                // Panel User ini adalah isi dari aplikasi desktop "Dekorasi"
                // (bersama panel Welcome). Partial ini hanya dirender kalau
                // request-nya benar-benar datang dari shell desktop.
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
            ->renderHook(
                'panels::styles.after',
                // Lapisan kaca dimuat lewat <link> statis, bukan lewat @vite, supaya
                // mengedit public/css/panel-glass.css langsung berlaku tanpa
                // `npm run build`. Diletakkan setelah @vite karena berkacanya
                // memakai token --fi-glass-* yang didefinisikan di Shared.css.
                fn (): string => Blade::render('@vite(\'resources/css/User/User.css\')')
                    ."\n"
                    // Berat teks dari config/app-font.php. `Panel::font()`
                    // hanya menulis --font-family, tidak menyentuh
                    // font-weight sama sekali -- jadi blok ini satu-satunya
                    // cara membuat "tebal" berlaku di ketiga panel.
                    .AppFont::styles()
                    ."\n"
                     .PanelGlassCss::link()
            )
            ->renderHook(
                // Penanda scope halaman dipasang juga di panel User.
                // PageStyles::styles() dimuat bersama stylesheet panel dan
                // memakai penanda ini agar pengaturan yang dibuat Admin
                // tersinkron ke seluruh halaman User/Livewire.
                PanelsRenderHook::PAGE_END,
                fn (): string => view('Livewire.Admin.page-customizer.scope-marker')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                // Breadcrumb halaman Auth (Sign In / OTP / Complete
                // Profile) DI ATAS logo, rata kiri.
                //
                // Dipindah ke hook, bukan @include per view, karena
                // `filament-panels::components.page.simple` menaruh slot di
                // bawah header/logo -- hook ini dirender tepat sebelumnya, jadi
                // posisinya benar tanpa tiap view harus tahu soalnya.
                //
                // Komponen dikirim eksplisit karena hook dijalankan di luar
                // lifecycle Livewire; partial tetap jatuh ke $this sebagai
                // fallback.
                //
                // Hook `panels::simple-page.start` di sini GLOBAL, bukan
                // khusus panel user: Filament mengirim scope = class halaman,
                // dan layout "simple" dipakai juga oleh halaman auth panel
                // admin/welcome. Karena itu partial breadcrumb wajib memeriksa
                // `method_exists($page, 'getBreadcrumbs')` -- tanpa itu,
                // Sign-In panel admin (yang tidak memakai trait
                // HasAuthBreadcrumbs) melempar BadMethodCallException dan
                // halaman auth admin jadi 500.
                fn (): View|string => view('User.auth.breadcrumbs.breadcrumbs', [
                    'authPage' => Livewire::current(),
                ])
            )
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->pages([
                Home::class,
                CompleteProfilePage::class,

                // Pondasi keamanan, dijalankan sekali setelah OTP / Complete
                // Profile. Sign In biasa tetap LANGSUNG ke Home.
                SecurityQuestion::class,
                TwoFactorAuth::class,

                /*
                 * Kata Sandi dan Keamanan.
                 *
                 * Semuanya halaman terpisah dengan route-nya sendiri -- bukan
                 * satu kelas dengan ?section=, yang membuat 7 bagian menyatu di
                 * satu file 150 baris dan mustahil dicari asal-usulnya.
                 *
                 *   settings/password-security                      -> daftar
                 *   settings/password-security/change-password      -> SignInAndRecovery
                 *   settings/password-security/two-factor           -> SignInAndRecovery
                 *   settings/password-security/two-factor/setup     -> SignInAndRecovery
                 *   settings/password-security/saved-login          -> SignInAndRecovery
                 *   settings/password-security/sign-in-activity     -> SecurityCheck
                 *   settings/password-security/recent-emails        -> SecurityCheck
                 *   settings/password-security/checkup              -> SecurityCheck
                 */
                PasswordSecurityPage::class,
                ChangePassword::class,
                TwoFactor::class,
                TwoFactorySetup::class,
                SavedLogin::class,
                SignInActivity::class,
                RecentEmails::class,
                Checkup::class,
            ])
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\\Filament\\User\\Widgets')
            ->widgets([])
            /*
             * 2FA: Email OTP + Authenticator App + perangkat terpercaya.
             *
             * Yang dibutuhkan dari plugin ini sebenarnya hanya middleware
             * CheckTrustedDevice -- itulah yang membuat Sign In berikutnya ikut
             * meminta kode ketika 2FA sudah aktif.
             *
             * Halaman TwoFactorySetup dan item menu milik plugin TIDAK dipakai:
             * 2FA di app ini punya tempat sendiri (Auth/TwoFactorAuth untuk
             * aktivasi, section two-factor di halaman Password & Security untuk
             * dikelola), dan item menu tidak bisa dibuang dari luar karena
             * Panel::userMenuItems() hanya menambah.
             *
             * Karena itu yang dipakai di sini UserMulti2faPlugin -- turunan
             * yang mendaftarkan OTPVerify + middleware saja.
             */
            ->plugins([
                UserMulti2faPlugin::make(),
                FilamentCaptcha::make(),
            ])
            // Buang item menu 2FA dari plugin. MenuItem tidak punya cara
            // menghapus satu item, jadi daftarkan ulang daftar userMenuItems
            // tanpa itu (lihat blok userMenuItems di bawah).
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
                    ->icon(AppIcon::PROFILE)
                    ->visible(fn (): bool => Auth::check()),

                // SEMUA label di bawah harus CLOSURE, bukan string.
                //
                // Panel dibangun oleh middleware `panel:user`, yang berjalan
                // SEBELUM SetLocale. Jadi kalau `->label(__('...'))` -- yang
                // memanggil __() saat itu juga -- dipakai, labelnya dibekukan
                // memakai locale default (APP_LOCALE=id) dan tidak lagi
                // responds ke language switcher: di UK tetap tampil "Pengaturan"
                // padahal lang/en.json sudah punya "Settings".
                //
                // Closure dievaluasi ulang saat menu dirender, yaitu setelah
                // SetLocale sempat menyetel locale. MenuItem::getLabel()
                // memanggil evaluate(), jadi bentuk ini yang benar.
                'pengaturan' => MenuItem::make()
                    ->label(fn (): string => __('Pengaturan'))
                    ->url(fn (): string => SettingsPage::getUrl())
                    ->icon(AppIcon::SETTINGS),
                'riwayat' => MenuItem::make()
                    ->label(fn (): string => __('Riwayat'))
                    ->url(fn (): string => HistoryResource::getUrl())
                    ->icon(AppIcon::HISTORY),
                'ulasan' => MenuItem::make()
                    ->label(fn (): string => __('Ulasan Saya'))
                    ->url(fn (): string => ReviewResource::getUrl())
                    ->icon(AppIcon::REVIEW),
                'privacy' => MenuItem::make()
                    ->label(fn (): string => __('Privasi & Ketentuan'))
                    ->url(fn (): string => PrivacyTermsPage::getUrl())
                    ->icon(AppIcon::PRIVACY),
                'bantuan' => MenuItem::make()
                    ->label(fn (): string => __('Pusat Bantuan'))
                    ->url(fn (): string => HelpCenterPage::getUrl())
                    ->icon(AppIcon::HELP),

                // Pintu ke shell desktop yang lain: dari aplikasi toko ini
                // staf bisa membuka panel admin TANPA keluar dari jendela.
                //
                // Hanya di desktop app, dan hanya untuk yang memang punya
                // baris di tabel `admins` -- kalau tidak, tautannya hanya
                // akan bouncing ke halaman login admin, dan di aplikasi
                // desktop itu berarti jendela yang terbuka bukan panel yang
                // dijanjikan. Lihat DesktopApp::canOpenAdminPanel().
                //
                // Label harus closure, seperti menu lain di blok ini.
                'desktop-companion' => MenuItem::make()
                    ->label(fn (): string => DesktopApp::companionLabel('user') ?? __('Buka Panel Admin'))
                    ->url(fn (): string => DesktopApp::companionUrl('user') ?? Home::getUrl(panel: 'user'))
                    ->icon(AppIcon::EXTERNAL_LINK)
                    ->visible(fn (): bool => AppPlatform::isDesktopApp()
                        && DesktopApp::canOpenAdminPanel()
                        && DesktopApp::companionUrl('user') !== null),

                // SignOut: label dikunci "Sign Out" (bukan "Log out"/"Keluar"
                // hasil terjemahan per-bahasa) dan tujuannya Welcome Home.
                // `->url()` tidak di-set -- Filament tetap POST ke route logout
                // panel user, lalu WelcomeLogoutResponse mengarahkan ke Welcome
                // Home. Lihat RedirectsLogoutToWelcomeHome.
                'logout' => static::signOutMenuItem(),
            ])
            ->middleware([
                ClerkFilamentAuth::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // WAJIB subclass APLIKASI (App\Http\Middleware\VerifyCsrfToken),
                // bukan Illuminate\Foundation\Http\Middleware\VerifyCsrfToken.
                // Keduanya sama-sama middleware CSRF, tapi yang base punya
                // $except KOSONG, sedangkan yang aplikasi mengecualikan
                // user/logout, welcome/logout, dan admin/*.
                //
                // Kalau panel memakai yang base, POST /logout akan lolos di
                // lapisan global (sudah dikecualikan) lalu DITOLAK lagi di
                // lapisan panel -> 419 PAGE EXPIRED. Route Filament sebenarnya
                // sudah berada di group `web` (FilamentServiceProvider
                // ->hasRoutes('web')) yang juga memakai kelas aplikasi, jadi
                // entri di sini adalah lapisan kedua -- dan hanya aman selama
                // kelasnya sama.
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\CheckWebsiteMaintenance\CheckWebsiteMaintenance::class . ':user',
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureProfileComplete::class,
            ])
            ->routes(function (Panel $panel): void {
                // Pintu auth pertama untuk tamu: /auth (Sign In + Google).
                // Daftarkan lewat ->routes(), BUKAN ->pages(), karena ->pages()
                // dibungkus authMiddleware() panel -- yang akan mengarahkan tamu
                // ke login, padahal halaman inilah yang harus mereka lihat.
                UserAuth::registerRoutes($panel);

                VerifyOtp::registerRoutes($panel);
            });

        $panel->databaseNotifications();

        // snap-script — Handled globally in AppServiceProvider for both Admin and User panels

        return $panel;
    }
}
