<?php

namespace App\Filament\User\Auth\Auth;

use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\Welcome\Pages\Home\Home as WelcomeHome;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

/**
 * Halaman auth pertama yang ditemui tamu: `/user/auth`.
 *
 * Isinya sengaja dikecilkan -- logo, heading "Welcome Back", dan DUA tombol:
 * Sign In (ke form email/kata sandi) dan Masuk Dengan Google (en: "Continue
 * With Google"). Sebelumnya
 * tombol Google berdiri di Sign In DAN Sign Up, jadi ada dua tempat yang harus
 * dijaga sinkron dan keduanya men-YAML-kan alur OAuth untuk tamu yang belum
 * punya akun. Di sini Google menjadi pintu kedua yang setara dengan Sign In,
 * dan halaman Sign In / Sign Up kembali ke urusannya masing-masing.
 *
 * Centang "Ingat Saya" + persetujuan syarat TIDAK ikut di sini: dua tombol di
 * bawah adalah pintu masuk, bukan persetujuan, dan memaksakan centang di
 * halaman publik membuat tombol Google sia-sia (selalu nonaktif sampai dua
 * checkbox dicentang). Kewajiban centang itu tetap di Sign In / Sign Up,
 * tempat `authenticate()` / `handleRegistration()` memvalidasinya.
 *
 * Halamannya publik, jadi route-nya didaftarkan lewat `->routes()`, bukan
 * `->pages()`: yang latter dibungkus `authMiddleware()` panel, yang akan
 * menaikkan 302 ke login untuk tamu -- persis orang yang seharusnya melihat
 * halaman ini. `->routes()` tetap dapat middleware panel (SetLocale,
 * ClerkFilamentAuth, VerifyCsrfToken, render hook), tapi di luar authMiddleware.
 * Pola yang sama dipakai VerifyOtp untuk OTP lupa kata sandi.
 */
class Auth extends SimplePage
{
    use HasAuthBreadcrumbs;

    protected static string $view = 'User.auth.auth.auth';

    // Gaya auth: layout simple fullscreen seperti SignIn/VerifyOtp.
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    // SimplePage sudah punya trait HasMaxWidth, jadi::$maxWidth di sini
    // instance (bukan static seperti di CompleteProfilePage yang extends Page).
    protected ?string $maxWidth = 'md';

    /**
     * Heading halaman ini: "Welcome Back" (id: "Selamat Datang Kembali"),
     * bukan "Sign In".
     *
     * Halaman ini bukan form -- dia hanya menawarkan dua pintu (Sign In dan
     * Google). Menamainya "Sign In" menyesatkan karena crumb terakhir, <title>
     * tab browser, dan heading besar semuanya ikut mengulang nama form yang
     * sudah ada di halaman lain (/user/signin), sehingga /user/auth dan
     * /user/signin terlihat seperti halaman yang sama. "Welcome Back" cuma
     * sapaan untuk orang yang kembali ke akunnya -- bukan nama halaman form,
     * jadi tidak bentrok dengan heading Sign In yang tetap "Sign In".
     *
     * `__()` dengan kunci "Welcome Back" yang ada di lang/en.json +
     * lang/id.json, dua locale yang ditawarkan language switcher.
     */
    public function getHeading(): string|Htmlable
    {
        return __('Welcome Back');
    }

    /**
     * Judul halaman ini juga jadi `<title>` di tab browser, jadi ikut
     * getHeading() supaya keduanya tidak bisa berbeda.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    /**
     * Label untuk crumb halaman ini, dipakai OLEH Sign In dan Sign Up sebagai
     * parent-nya. Sengaja sama dengan getHeading(): crumb harus memakai nama
     * halaman yang ditunjuk, jadi "Sign In / Welcome Back" tetap menunjuk ke
     * /user/auth yang bergelar "Welcome Back".
     *
     * Dulu labelnya "Masuk" (id) / "Log in" (en) -- key "Masuk" sudah jadi
     * "Log in" di en.json, jadi crumb di browser English terbaca "Log in / Sign
     * In": orang dikira sudah berada di halaman login padahal masih di halaman
     * kedatangan. Sekarang satu sumber: getHeading().
     *
     * Tetap harus berbeda dari heading Sign In / Sign Up supaya tidak tampil dua
     * kali ("Welcome Back / Sign In", bukan "Sign In / Sign In").
     */
    public static function crumbLabel(): string
    {
        return __('Welcome Back');
    }

    /**
     * Parent crumb selalu Welcome Home, bukan halaman asal klik.
     *
     * Alasan yang sama seperti SignIn: halaman ini adalah pintu masuk auth dari
     * storefront publik, jadi parent crumb yang "mengikuti asal klik" dari trait
     * HasAuthBreadcrumbs hanya menghasilkan label generik "Kembali" atau
     * ditolak. "Beranda" selalu benar dan selalu mengembalikan tamu ke storefront.
     *
     * Sign In dan Sign Up tidak memakai ini: mereka sudah menunjuk ke halaman
     * ini sebagai parent, jadi mengarahkan dua-duanya ke Home lewat takhta
     * sendiri hanya membuat crumb terlewat.
     */
    public function getBreadcrumbs(): array
    {
        return [
            WelcomeHome::getUrl(panel: 'welcome') => __('Beranda'),
            $this->getAuthBreadcrumbsCurrentLabel(),
        ];
    }

    /**
     * Tamu yang sudah punya akun tidak ada urusan di halaman login: topbar
     * storefront untuk mereka sudah menampilkan "Beranda", bukan "Sign In".
     * `url.intended` tidak disentuh di sini -- tidak ada penulisan session, jadi
     * tujuan setelah login yang disimpan Laravel tetap utuh.
     */
    public function mount(): void
    {
        if (auth()->check()) {
            // `panel: 'user'` wajib: tanpa itu Page::getUrl() mengambil panel dari
            // getCurrentPanel(). Di sini kebetulan hasilnya sama, tapi begitu
            // panel default berubah, halaman ini diam-diam melompat ke panel lain.
            $this->redirect(Home::getUrl(panel: 'user'), navigate: false);
        }
    }

    /**
     * Route-nya didaftarkan manual, jadi route name bawaan Filament
     * (`filament.user.pages.auth`) tidak ada. Menunjuk langsung ke route yang
     * dibuat registerRoutes() di bawah.
     */
    public static function getUrl(array $parameters = []): string
    {
        return route('filament.user.auth.index', $parameters);
    }

    /**
     * URL tujuan tombol Sign In dikirim lewat view data, bukan ditulis di Blade,
     * supaya template tidak perlu tahu nama kelas maupun route.
     *
     * Diambil dari PANEL, bukan `SignIn::getUrl()`: SignIn extends
     * Filament\Pages\Auth\Login, yang -- berbeda dari Page/SimplePage -- tidak
     * punya helper getUrl() statis, jadi memanggilnya jatuh ke __callStatic dan
     * melempar BadMethodCallException. `getLoginUrl()` panel-lah yang benar,
     * dan otomatis ikut kalau `loginRouteSlug()` panel user diubah lagi.
     */
    protected function getViewData(): array
    {
        return [
            'signInUrl' => Filament::getPanel('user')->getLoginUrl(),
        ];
    }

    public static function registerRoutes(Panel $panel): void
    {
        Route::get('/auth', static::class)
            ->name('auth.index')
            ->middleware(['web']);
    }
}