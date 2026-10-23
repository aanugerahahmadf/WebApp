<?php

namespace App\Providers\Filament\WelcomePanelProvider;

use App\Filament\Concerns\RedirectsLogoutToWelcomeHome;
use App\Filament\Shared\Plugins\UserMulti2faPlugin\UserMulti2faPlugin;
use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Filament\User\Auth\CompleteProfile\CompleteProfilePage;
use App\Filament\User\Auth\LoginResponse\LoginResponse;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword;
use App\Filament\User\Auth\SignIn\SignIn;
use App\Filament\User\Auth\SignUp\SignUp;
use App\Filament\User\Auth\SecurityQuestion\SecurityQuestion;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp;
use App\Filament\User\Pages\DeviceTrustPrompt\DeviceTrustPrompt;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Filament\Welcome\Pages\EditProfilePage\EditProfilePage;
use App\Filament\Welcome\Pages\HelpCenterPage\HelpCenterPage;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Pages\PrivacyTermsPage\PrivacyTermsPage;
use App\Filament\Welcome\Pages\SettingsPage\SettingsPage;
use App\Filament\Welcome\Resources\HistoryResource\HistoryResource;
use App\Filament\Welcome\Resources\ReviewResource\ReviewResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Http\Middleware\ClerkFilamentAuth\ClerkFilamentAuth;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Support\AppIcon\AppIcon;
use App\Support\AppFont\AppFont;
use App\Support\AppPlatform\AppPlatform;
use App\Support\DesktopApp\DesktopApp;
use App\Support\MobileNav\MobileNav;
use App\Support\PanelGlassCss\PanelGlassCss;
use App\Support\TopNavigation\TopNavigation;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
use Livewire\Livewire;
use MarcoGermani87\FilamentCaptcha\FilamentCaptcha;

/**
 * SATU PANEL — SHOPEE-STYLE.
 *
 * Panel ini dulunya hanya storefront publik, sedangkan halaman auth
 * (/signin, /signup, /auth, OTP) dan halaman akun dipisah ke panel 'user'
 * di /user/*. Dua panel itu saling mencerminkan sebagian halaman dan terus
 * berpeluang drift (lihat riwayat komentar: "a layout change in one has to
 * land in the other or the panels drift apart").
 *
 * Sekarang keduanya DIGABUNG di sini, satu ruang URL di root domain seperti
 * shopee.co.id:
 *
 *   - Tamu  : /home, /messages, /flowerdecorationscatalog dibuka bebas
 *             (lanjutkan membaca AuthenticateWelcome, pola ini tidak berubah).
 *   - Auth  : ->login(SignIn), ->registration(SignUp), ->passwordReset(),
 *             ->emailVerification(), plus /auth (UserAuth) dan OTP flow
 *             (VerifyOtp) lewat ->routes() -- halaman-halaman auth KANONIK
 *             dari App\Filament\User\Auth\* didaftarkan langsung ke panel ini,
 *             tanpa memindahkan satu file. Semua nama route auth menjadi
 *             filament.welcome.auth.* otomatis karena mereka hidup di panel
 *             ini. URL-nya juga naik ke root: /signin, /signup, /auth,
 *             /password-reset/*. Bookmark /user/signin dkk. diarahkan lewat
 *             redirect legacy di routes/web/web.php.
 *   - Akun  : CompleteProfilePage, SecurityQuestion, TwoFactorAuth, dan
 *             DeviceTrustPrompt (halaman pengaturan mendalam yang TIDAK
 *             termirror di namespace Welcome) di ->pages(), sehingga hidup di
 *             root: /complete-profile, /security-question, /two-factor-auth,
 *             /device-trust/{id}.
 *
 * Yang sengaja TIDAK ikut didaftarkan dari namespace User:
 *
 *   - PasswordSecurityPage + 7 halaman anaknya
 *     (ChangePassword, TwoFactor settings, TwoFactorySetup, SavedLogin,
 *     SignInActivity, RecentEmails, Checkup). Versi WELCOME halaman yang sama
 *     sudah terdaftar di /settings/password-security (dengan pola ?section=)
 *     dan slug User-nya juga settings/password-security -- mendafkarkan
 *     keduanya double-resolve route & URL yang sama. Versi Welcome-lah
 *     yang menang.
 *   - Home, EditProfilePage, SettingsPage, MessagesPage, HelpCenter,
 *     PrivacyTerms/PrivacyPolicy/TermsOfService/WeddingPolicy,
 *     CbirSearchPage, NotificationDetailPage, semua Resources & Widgets:
 *     SEMUANYA sudah punya padanan di namespace Welcome yang hidup pada panel
 *     ini lewat discoverResources/discoverPages/discoverWidgets.
 *
 * Kaunten topbar:
 *
 *   - Tamu mendapat ikon account + dropdown (Sign In To Account + Sign Up),
 *     dari hook 'panels::global-search.after' WHERE view
 *     Welcome.components.topbar-auth-actions HANYA men-render branch tamu
 *     sekarang.
 *   - Yang sudah sign in mendapat USER MENU bawaan Filament (avatar +
 *     Pengaturan/Riwayat/Ulasan/... + Sign Out) -- lihat userMenuItems() di
 *     bawah. Override lama yang MEMBUANG menu ini
 *     (Welcome/panel-overrides/filament-panels/components/user-menu.blade.php)
 *     sudah dihapus, karena menyebabkan user yang sign in kehilangan pintu
 *     menu akun di storefront.
 *
 * Platform switchers (bahasa/tema) memakai predikat yang sama seperti sebelum
 * merge: AppPlatform::switchersBelongInTopbar() / switchersBelongInSidebar(),
 * jadi tidak ada switcher yang muncul dua kali satu permukaan.
 */
class WelcomePanelProvider extends PanelProvider
{
    // SignOut / Logout diarahkan ke Welcome Home (root '/home').
    use RedirectsLogoutToWelcomeHome;

    public function boot(): void
    {
        // Custom LoginResponse: semua yang sign in (shopper, admin team via
        // storefront identity, Google OAuth) diarahkan langsung ke /home --
        // satu-satunya Home sekarang, di panel ini. Filament v3 tidak punya
        // ->loginResponse(), jadi dipakai binding.
        //
        // Binding terakhir menang: AppServiceProvider::register() membuatkan
        // fallback anonymous dulu, boot() provider yang menimpanya.
        app()->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            LoginResponse::class,
        );
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('welcome')
            // SATU PANEL = PANEL DEFAULT.
            //
            // Panel 'user' sudah tidak didaftarkan, jadi setiap kode yang
            // memanggil `Filament::getPanel()` tanpa id (atau dengan id panel
            // yang sudah tiada) jatuh ke panel default. Tanpa baris ini Filament
            // melempar NoDefaultPanelSetException saat merender halaman auth
            // (/signin) -- halaman 500, bukan redirect.
            //
            // Panel admin TIDAK ikut jadi default: ia punya path sendiri
            // (/admin) dan tidak boleh menangkap URL root.
            ->default()
            // SHOPEE-STYLE: SATU panel di ROOT domain. Semua URL -- storefront,
            // auth, dan akun -- hidup di sini: /home, /messages, /signin,
            // /signup, /auth, /settings, /orders, dst. Bookmark lama diarahkan
            // lewat redirect legacy di web.php: /welcome/* -> root (blok
            // LEGACY WELCOME) dan /user/* -> root (blok LEGACY USER).
            ->path('/')
            // Halaman auth panel ini (semua bergaya simple fullscreen). Slug
            // diturunkan dari loginRouteSlug/registrationRouteSlug, sama seperti
            // panel user yang lama -- jadi URL auth tidak berubah bentuk:
            // /signin dan /signup.
            ->login(SignIn::class)
            ->loginRouteSlug('signin')
            ->registration(SignUp::class)
            ->registrationRouteSlug('signup')
            ->passwordReset(OtpRequestPasswordReset::class, OtpResetPassword::class)
            // Parameter KEDUA `false` WAJIB, bukan default `true`.
            //
            // `->emailVerification($prompt)` selalu memanggil
            // `requiresEmailVerification(true)`, dan vendor
            // (filament/src/Pages/Concerns/HasRoutes.php:63) lalu menempelkan
            // middleware `EnsureEmailIsVerified` ke SETIAP route halaman dan
            // resource panel. Middleware itu menganggap TAMU sebagai
            // "belum verifikasi" (`! $request->user()`), lalu melemparnya ke
            // /email-verification/prompt -- sehingga SELURUH panel, termasuk
            // path publik (/home, /messages, /flowerdecorationscatalog),
            // berbalik 302 ke halaman prompt dan storefront tidak bisa dibuka
            // sama sekali.
            //
            // Di panel user lama redirect itu tidak pernah terasa karena
            // Filament `Authenticate` menolak tamu lebih dulu (302 ke
            // /user/signin). Panel gabungan ini justru dirancang menerima
            // tamu, jadi pintu masuknya harus tetap di AuthenticateWelcome.
            //
            // Dengan `false`, route prompt TETAP terdaftar (flow OTP masih
            // memakai /email-verification/prompt dan /email-verification/verify)
            // -- yang dilepas hanya pembungkusan route paksa-verifikasi.
            // Verifikasi akun baru tetap diurus flow OTP sendiri
            // (VerifyOtp + OtpEmailVerificationPrompt), bukan oleh semua route.
            ->emailVerification(OtpEmailVerificationPrompt::class, false)
            // LOGO (topbar + sidebar) untuk tamu harus mendarat di HALAMAN
            // AUTH LANDING (/auth), bukan langsung ke form sign in.
            //
            // Tanpa ini, Filament memakai `Panel::getUrl()`, yang untuk tamu
            // mengembalikan login URL -- jadi tamu yang klik logo masuk ke
            // form /signin dan melewatkan "/auth" yang di situ ada dua pintu
            // (Sign In + Masuk Dengan Google).
            //
            // Untuk yang sudah login, logo kembali ke Beranda.
            ->homeUrl(fn (): string => auth()->check()
                ? Home::getUrl(panel: 'welcome')
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
            // Nilai keluarga huruf dan berat TIDAK ditulis di sini. Ketiga panel
            // memanggil AppFont yang sama, jadi mengubah
            // config/app-font.php (atau APP_FONT_FAMILY / APP_FONT_WEIGHT
            // di .env) mengubah admin, user, dan welcome sekaligus -- tidak
            // ada lagi tiga salinan nilai yang harus diingat semua.
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
            // tetap memuat Inter dari Bunny. Jangan dihapus dan mengandalkan
            // default.
            // Storefront memakai pilihan huruf PRIBADI pengunjung
            // (ViewerFont::current()): user & tamu bisa memilih hurufnya
            // sendiri di halaman Pengaturan, jadi huruf yang dipakai panel
            // ikut pilihan itu -- bukan default global milik admin.
            ->font(\App\Support\ViewerFont\ViewerFont::current(), provider: AppFont::provider())
            ->defaultThemeMode(ThemeMode::System)
            // CHROME DI SEMUA HALAMAN.
            //
            // `topNavigation` = baris link navigasi di dalam topbar.
            // `topbar`        = topbar itu sendiri: logo, pencarian global,
            //                    notifikasi, user menu, switcher bahasa + tema.
            //
            // Dulu KEDUA-nya hanya dimunculkan di halaman Home, jadi halaman
            // storefront, pengaturan, messages... polos tanpa chrome apa pun.
            // Aturan itu dibalik untuk aplikasi desktop (Capacitor): topbar
            // adalah tempat pencarian global, user menu, switcher bahasa/tema,
            // dan tombol kembali -- semuanya dibutuhkan di setiap layar.
            //
            // Sidebar dan bottom nav HP ikut aturan yang sama, tapi bukan
            // dari sini: sidebar dikendalikan dari override view
            // resources/views/User/vendor/filament-panels/components/sidebar/
            // index.blade.php, dan bottom nav dari MobileNav::shouldRender().
            // Ketiganya membaca TopNavigation::hasChrome() yang sama.
            //
            // Closure, bukan `true`: Filament menyimpan argumen lalu
            // mengevaluasinya tiap kali hasTopNavigation()/hasTopbar() dipanggil
            // saat layout dirender. Kalau bool, keputusannya beku saat panel
            // dibangun -- saat itu belum ada route.
            ->topNavigation(TopNavigation::always())
            ->topbar(TopNavigation::always())
            ->spa()
            ->unsavedChangesAlerts(false)
            ->collapsibleNavigationGroups()
            ->globalSearch()
            ->renderHook(
                // Switcher bahasa di Top Navigation bawaan Filament, untuk
                // tablet, macOS, desktop, dan APLIKASI DESKTOP.
                //
                // Switcher TEMA-nya di partial
                // `Welcome.components.topbar-auth-actions` -- satu tempat
                // untuk semua panel yang memakai partial itu.
                //
                // Kenapa switcher bawaan Filament di dalam user menu
                // (tiga ikon sun/moon/monitor) dimatikan: partial
                // topbar-auth-actions dirender untuk TAMU DAN yang sudah
                // sign in, jadi switcher topbar akan muncul dua kali begitu
                // user masuk -- sekali dari topbar, sekali dari user menu.
                // Dua switcher untuk satu pengaturan. Lihat override view
                // resources/views/Welcome/vendor/filament-panels/components/
                // user-menu.blade.php.
                //
                // PERINGATAN: jangan tambahkan wrapper `hidden sm:block`
                // di sekitar switcher mana pun yang dirender hook ini.
                // Aplikasi desktop tidak punya minWidth, jadi di bawah 640px
                // switcher akan hilang total -- bukan pindah ke sidebar.
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
                // mobile web Android/iOS, dan APLIKASI MOBILE (Capacitor).
                //
                // APLIKASI DESKTOP TIDAK DI SINI: predikat
                // AppPlatform::switchersBelongInSidebar() tidak menghitung
                // desktop app, karena sidebar aplikasi desktop diterjemahkan ke
                // luar layar di layar lebar -- switcher di sana ter-render tapi
                // tidak terlihat. Desktop app pakai topbar.
                //
                // Hook ini hanya mengembalikan ISI switcher-nya, tanpa wrapper
                // sendiri: barisnya adalah baris logo di dalam header sidebar,
                // dan wrapper-nya dimiliki file override sidebar
                // resources/views/User/vendor/filament-panels/components/
                // sidebar/index.blade.php, blok .fi-sidebar-switchers.
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
                PanelsRenderHook::SIMPLE_PAGE_START,
                // Breadcrumb halaman Auth / SimplePage (Sign In, Sign Up, OTP,
                // Complete Profile...) DI ATAS logo, rata kiri.
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
                // Layaknya versi milik panel user yang lama: hook ini GLOBAL
                // untuk panel, tapi scope-nya dikirim Filament sebagai class
                // halaman -- dan layout `simple` dipakai juga oleh halaman auth
                // panel admin. Partial breadcrumb wajib memeriksa
                // `method_exists($page, 'getBreadcrumbs')` supaya halaman auth
                // panel admin (yang tidak memakai trait HasAuthBreadcrumbs)
                // tidak 500.
                fn (): View|string => view('User.auth.breadcrumbs.breadcrumbs', [
                    'authPage' => Livewire::current(),
                ]),
            )
            ->renderHook(
                'panels::global-search.after',
                // TAMU SAJA: ikon account + dropdown (Sign In To Account +
                // Sign Up Sekarang) yang menggantikan user menu untuk orang
                // yang belum sign in. Filament hanya merender user menu untuk
                // yang sudah sign in, jadi hook ini tidak bisa digantung di
                // USER_MENU_BEFORE.
                //
                // Yang sudah sign in TIDAK lewat sini lagi: mereka mendapat
                // user menu bawaan Filament (userMenuItems di bawah -- menu
                // ini justru gabungan menu akun panel user yang lama). View
                // topbar-auth-actions dirender tanpa lebih cabang @auth
                // (ikon link ke /home) karena satu kontrol paling personal
                // sudah dipegang user menu.
                fn (): View|string => view('Welcome.components.topbar-auth-actions.topbar-auth-actions', [
                    // Tujuan auth tamu tetap pintu pertama /auth (heading
                    // "Welcome Back" + Sign In To Account + Continue With
                    // Google), bukan form email/kata sandi langsung --
                    // di-pointing ke Auth::class (UserAuth::getUrl()) supaya
                    // perubahan slug/path cuma perlu diubah di satu tempat.
                    'loginUrl' => UserAuth::getUrl(),
                    // Pendaftaran sekarang sama-sama milik panel ini
                    // (->registration(SignUp::class)); ambil dari panel agar
                    // slug yang ganti ikut.
                    'signUpUrl' => Filament::getPanel('welcome')->getRegistrationUrl(),
                ]),
            )
            ->renderHook(
                // Panel-scoped stylesheet.
                'panels::styles.after',
                // Lapisan kaca dimuat lewat <link> statis, bukan lewat @vite, supaya
                // mengedit public/css/panel-glass.css langsung berlaku tanpa
                // `npm run build`. Diletakkan setelah @vite karena berkacanya
                // memakai token --fi-glass-* yang didefinisikan di Shared.css.
                fn (): string => Blade::render('@vite(\'resources/css/Welcome/Welcome.css\')')
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
                // PENANDA SCOPE HALAMAN -- WordPress-style. Panel ini
                // menampung SEMUA halaman storefront (resource User +
                // Welcome hasil peleburan panel, dan Livewire pages
                // keduanya). Setiap halaman membawa key scope-nya sendiri
                // (dari route aktif, lihat scope-marker view) -- CSS
                // kustom yang diatur dari PANEL ADMIN (hook PAGE_END di
                // AdminPanelProvider, AppFont::styles() yang sama di atas)
                // menarget penanda ini, jadi SEMUA halaman bisa diatur
                // dari Admin tanpa mengedit satu file halaman pun, dan
                // kustom halaman A tidak pernah bocor ke halaman B.
                PanelsRenderHook::PAGE_END,
                fn (): string => view('Livewire.Admin.page-customizer.scope-marker')->render(),
            )
            ->renderHook(
                // Phone-only bottom navigation. MobileNav menekan bar ini di
                // halaman auth dan di desktop; item-nya diambil dari
                // config/app-platform.php (mobile_nav.items.welcome).
                //
                // `Auth::check()` tetap: semua tujuan bar ini account-shaped
                // dan panel ini dibuka tamu -- bar hanya muncul yang sudah
                // sign in.
                PanelsRenderHook::BODY_END,
                fn (): string => (string) (Auth::check() && MobileNav::shouldRender('welcome')
                    ? view('Shared.components.mobile-bottom-nav.mobile-bottom-nav')->render()
                    : '')
                    . view('Shared.components.notification-sound.notification-sound')->render(),
            )
            ->renderHook(
                // SHELL DESKTOP (Electron) -- app/Capacitor/DekstopApp.
                //
                // Panel Welcome + panel User ini adalah isi dari aplikasi
                // desktop "Dekorasi". Partial ini hanya dirender kalau
                // request-nya benar-benar datang dari shell desktop, jadi
                // website tidak tersentuh.
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
            ->discoverResources(in: app_path('Filament/Welcome/Resources'), for: 'App\\Filament\\Welcome\\Resources')
            ->discoverPages(in: app_path('Filament/Welcome/Pages'), for: 'App\\Filament\\Welcome\\Pages')
            ->pages([
                Home::class,

                // Halaman akun Lexical dari APP\FILAMENT\USER\AUTH -- tidak
                // ada padanannya di namespace Welcome, jadi didaftarkan
                // langsung di sini. Pembungkusan authMiddleware panel
                // tetap menutup semua ini dari tamu (AuthenticateWelcome
                // mem-bounce ke /auth, bukan ke /signin).
                CompleteProfilePage::class,

                // Pondasi keamanan, dijalankan sekali setelah OTP / Complete
                // Profile. Sign In biasa tetap LANGSUNG ke Home.
                SecurityQuestion::class,
                TwoFactorAuth::class,

                // Halaman prompt "Tinjau Perangkat Ini?" ke notifikasi login
                // perangkat baru: satu kolom id perangkat di slug-nya.
                DeviceTrustPrompt::class,

                // Setup aplikasi autentikasi (TOTP) milik aplikasi sendiri.
                //
                // Halaman ini mewarisi alur mix-code/filament-multi-2fa lewat
                // TwoFactorAuthenticatorPage: pilih tipe, verifikasi kode
                // dengan Google2FA, simpan, plus daftar perangkat. Alur itu
                // TIDAK disalin ke halaman Keamanan, dan tidak ditulis ulang
                // dengan aturan longgar -- verifikasi sungguhan (kode 6 digit
                // yang benar) adalah yang membuat 2FA berarti sesuatu.
                //
                // Kenapa perlu didaftarkan di sini: panel 'user' dilebur ke
                // panel 'welcome', dan tanpa baris ini tidak ada halaman setup
                // sama sekali -- tombol "Tambahkan Aplikasi Autentikasi" jadi
                // buntu.
                TwoFactorySetup::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Welcome/Widgets'), for: 'App\\Filament\\Welcome\\Widgets')
            ->widgets([])            /*
             * 2FA: Email OTP + Authenticator App + perangkat terpercaya.
             *
             * Yang dibutuhkan dari plugin ini sebenarnya hanya middleware
             * CheckTrustedDevice -- itulah yang membuat Sign In berikutnya ikut
             * meminta kode ketika 2FA sudah aktif.
             *
             * Halaman TwoFactorySetup dan item menu milik plugin TIDAK dipakai:
             * 2FA di app ini punya tempat sendiri (Auth/TwoFactorAuth untuk
             * aktivasi, section two-factor di /settings/password-security untuk
             * dikelola), dan item menu tidak bisa dibuang dari luar karena
             * Panel::userMenuItems() hanya menambah.
             *
             * Karena itu yang dipakai di sini UserMulti2faPlugin -- turunan
             * yang mendaftarkan OTPVerify (versi OtpEmailOrTwoFactory) +
             * middleware saja.
             */
            ->plugins([
                UserMulti2faPlugin::make(),
                FilamentCaptcha::make(),
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('Beranda')),
                NavigationGroup::make()->label(fn () => __('Belanja & Jelajahi')),
                NavigationGroup::make()->label(fn () => __('Transaksi & Aktivitas')),
                NavigationGroup::make()->label(fn () => __('Pesan')),
            ])
            ->userMenuItems([
                // SELURUH label harus CLOSURE, bukan string.
                //
                // Panel dibangun oleh middleware `panel:welcome`, yang berjalan
                // SEBELUM SetLocale. Kalau `->label(__('...'))` dipakai,
                // labelnya dibekukan di locale default dan tidak lagi
                // merespons language switcher. Closure dievaluasi ulang saat
                // menu dirender -- setelah SetLocale sempat menyetel locale.
                //
                // Sumber halamannya namespace WELCOME: halaman-halaman akun
                // di panel ini adalah milik discover*(), bukan namespace User.
                'profile' => MenuItem::make()
                    ->label(fn (): string => Auth::user()?->full_name ?? __('Profil'))
                    ->url(fn (): string => EditProfilePage::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::PROFILE)
                    ->visible(fn (): bool => Auth::check()),

                'pengaturan' => MenuItem::make()
                    ->label(fn (): string => __('Pengaturan'))
                    ->url(fn (): string => SettingsPage::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::SETTINGS),
                'riwayat' => MenuItem::make()
                    ->label(fn (): string => __('Riwayat'))
                    ->url(fn (): string => HistoryResource::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::HISTORY),
                'ulasan' => MenuItem::make()
                    ->label(fn (): string => __('Ulasan Saya'))
                    ->url(fn (): string => ReviewResource::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::REVIEW),
                'privacy' => MenuItem::make()
                    ->label(fn (): string => __('Privasi & Ketentuan'))
                    ->url(fn (): string => PrivacyTermsPage::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::PRIVACY),
                'bantuan' => MenuItem::make()
                    ->label(fn (): string => __('Pusat Bantuan'))
                    ->url(fn (): string => HelpCenterPage::getUrl(panel: 'welcome'))
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
                    ->label(fn (): string => DesktopApp::companionLabel('welcome') ?? __('Buka Panel Admin'))
                    ->url(fn (): string => DesktopApp::companionUrl('welcome') ?? Home::getUrl(panel: 'welcome'))
                    ->icon(AppIcon::EXTERNAL_LINK)
                    ->visible(fn (): bool => AppPlatform::isDesktopApp()
                        && DesktopApp::canOpenAdminPanel()
                        && DesktopApp::companionUrl('welcome') !== null),

                // SignOut: label dikunci "Sign Out" (bukan "Log out"/"Keluar"
                // hasil terjemahan per-bahasa) dan tujuannya Welcome Home.
                // `->url()` tidak di-set -- Filament tetap POST ke route logout
                // panel ini (/logout), lalu WelcomeLogoutResponse mengarahkan
                // ke Welcome Home. Lihat RedirectsLogoutToWelcomeHome.
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
                // Yang base punya $except KOSONG, sedangkan yang aplikasi
                // mengecualikan user/logout, welcome/logout, dan admin/*.
                // Kalau panel memakai yang base, POST /logout akan 419.
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\CheckWebsiteMaintenance\CheckWebsiteMaintenance::class . ':welcome',
            ])
            ->authMiddleware([
                // Not Filament's Authenticate: panel ini TAMU-dapat dibuka di
                // storefront (PUBLIC_PATHS), tapi halaman akun/auth-lain
                // tetap tertutup -- yang tanpa sesi di-bounce ke /auth milik
                // panel ini (halaman "Welcome Back"), dan Laravel menyimpan
                // URL-nya ke sesi (`url.intended`) supaya Guest pun kembali ke
                // tempat sebelumnya setelah sign in. Lihat AuthenticateWelcome.
                //
                // Setelah lewat gate itu, data profil yang belum lengkap
                // ditangkap di sini untuk pansar extra: akun Google/social
                // yang baru daftar dipaksa ke /complete-profile.
                AuthenticateWelcome::class,
                EnsureProfileComplete::class,
            ])
            ->routes(function (Panel $panel): void {
                // Pintu auth pertama tamu: /auth (Sign In + Google).
                // Daftarkan lewat ->routes(), BUKAN ->pages(), karena ->pages()
                // dibungkus authMiddleware() panel -- yang akan mengarahkan
                // tamu ke login, padahal halaman inilah yang harus mereka
                // lihat.
                UserAuth::registerRoutes($panel);

                // OTP lupa kata sandi: /password-reset/verify.
                VerifyOtp::registerRoutes($panel);
            });

        $panel->databaseNotifications();

        // snap-script -- Handled globally in AppServiceProvider for all panels

        return $panel;
    }
}
