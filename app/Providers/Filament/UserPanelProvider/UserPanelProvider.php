<?php

namespace App\Providers\Filament\UserPanelProvider;

// Alias wajib: file ini juga memakai facade Illuminate\Support\Facades\Auth
// (untuk label user menu & cek Auth::check()), jadi nama `Auth` terpakai.
use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Filament\User\Auth\SignIn\SignIn;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp;
// use App\Filament\User\Auth\SignUp\SignUp;
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
use App\Support\AppPlatform\AppPlatform;
use App\Support\PanelGlassCss\PanelGlassCss;
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
            // Registrasi email/password DINONAKTIFKAN. Baris di bawah adalah
            // kode ASLI yang sengaja hanya dikomentari -- tidak dihapus, tidak
            // ditulis ulang. `App\Filament\User\Auth\SignUp\SignUp` beserta view
            // `User/auth/sign-up/sign-up` masih ada di repo, jadi menghidupkan
            // pendaftaran lagi cukup menghapus tiga tanda `//` di file ini
            // (import, ->registration, ->registrationRouteSlug) dan satu blok
            // markup di resources/views/User/social-buttons.
            //
            // Pendaftaran yang aktif sekarang hanya lewat tombol Google di
            // `/user/auth`: SocialiteController membuat akun otomatis lalu
            // mengarahkan ke CompleteProfile, jadi tidak ada pengguna yang
            // terkunci tanpa jalan daftar.
            //
            // Selama `->registration()` tidak dipanggil, route
            // `filament.user.auth.register` tidak terdaftar, `/user/signup` dan
            // `/user/register` redirect ke `/user/auth`, dan tidak ada halaman
            // auth yang menautkan ke pendaftaran. Kontrak itu dipin
            // RenameSmokeTest dan UserAuthLandingPageTest.
            //
            // ->registration(SignUp::class)
            // Sejalan dengan sign-in: slug route sign-up = `signup`, jadi URL
            // utamanya `/user/signup`. Nama route tetap
            // `filament.user.auth.register`; URL lama `/user/register`
            // dilayani redirect di routes/web/web.php.
            // ->registrationRouteSlug('signup')
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
                // Switcher bahasa di Top Navigation -- tablet dan website
                // desktop saja.
                //
                // Syaratnya AppPlatform::switchersBelongInTopbar(), sama dengan
                // yang dipakai hook sidebar di bawah. Kalau hook ini memakai
                // syaratnya sendiri, switcher bahasa bisa muncul dua kali di
                // satu permukaan -- persis yang terjadi di aplikasi desktop
                // sebelum keduanya berbagi predikat yang sama.
                function (): View|string {
                    if (! AppPlatform::switchersBelongInTopbar()) {
                        return '';
                    }

                    return view('User.filament-language-switcher.language-switcher.language-switcher');
                },
            )
            ->renderHook(
                // Switcher tema + bahasa di menu geser (sidebar) -- HP,
                // mobile web Android/iOS, app shell Android/iOS, dan aplikasi
                // desktop. Sama persis dengan yang WelcomePanelProvider
                // lakukan untuk panelnya sendiri.
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
                'panels::styles.after',
                // Lapisan kaca dimuat lewat <link> statis, bukan lewat @vite, supaya
                // mengedit public/css/panel-glass.css langsung berlaku tanpa
                // `npm run build`. Diletakkan setelah @vite karena berkacanya
                // memakai token --fi-glass-* yang didefinisikan di Shared.css.
                fn (): string => Blade::render('@vite(\'resources/css/User/User.css\')')
                    ."\n"
                    .PanelGlassCss::link()
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
                    ->icon('eos-account-circle')
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
                    ->icon('heroicon-o-cog-6-tooth'),
                'riwayat' => MenuItem::make()
                    ->label(fn (): string => __('Riwayat'))
                    ->url(fn (): string => HistoryResource::getUrl())
                    ->icon('heroicon-o-clock'),
                'ulasan' => MenuItem::make()
                    ->label(fn (): string => __('Ulasan Saya'))
                    ->url(fn (): string => ReviewResource::getUrl())
                    ->icon('heroicon-o-star'),
                'privacy' => MenuItem::make()
                    ->label(fn (): string => __('Privasi & Ketentuan'))
                    ->url(fn (): string => PrivacyTermsPage::getUrl())
                    ->icon('heroicon-o-shield-check'),
                'bantuan' => MenuItem::make()
                    ->label(fn (): string => __('Pusat Bantuan'))
                    ->url(fn (): string => HelpCenterPage::getUrl())
                    ->icon('heroicon-o-question-mark-circle'),

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
                // Kalau panel memakai yang base, POST /user/logout akan lolos di
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
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureProfileComplete::class,
            ])
            ->routes(function (Panel $panel): void {
                // Pintu auth pertama untuk tamu: /user/auth (Sign In + Google).
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
