<?php

namespace App\Filament\User\Auth\Auth;

use App\Filament\Welcome\Pages\Home\Home as WelcomeHome;
use Filament\Panel;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

/**
 * Halaman auth pertama yang ditemui tamu: `/auth`.
 *
 * Isinya: logo, heading "Welcome Back", dan tombol masuk via Google Firebase
 * Gateway (partial social-buttons). Tombol Sign In (email/kata sandi) dihapus
 * dari halaman ini; alur masuk mengikuti gateway Google/Firebase sesuai
 * kebutuhan.
 *
 * Centang "Ingat Saya" + persetujuan syarat TIDAK ikut di sini: kewajiban
 * centang itu tetap di Sign In / Sign Up, tempat `authenticate()` /
 * `handleRegistration()` memvalidasinya.
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
    protected static string $view = 'User.auth.auth.auth';

    // Gaya auth: layout simple fullscreen seperti SignIn/VerifyOtp.
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    // SimplePage sudah punya trait HasMaxWidth, jadi::$maxWidth di sini
    // instance (bukan static seperti di CompleteProfilePage yang extends Page).
    protected ?string $maxWidth = 'md';

    /**
     * Heading halaman ini: "Welcome Back" (id: "Selamat Datang Kembali").
     *
     * Halaman ini bukan form email/kata sandi; pintu masuk yang ditampilkan
     * adalah Google Firebase Gateway. "Welcome Back" dipakai sebagai sapaan
     * agar tidak bentrok dengan heading Sign In pada halaman /signin.
     *
     * `__()` dengan kunci "Welcome Back" yang ada di lang/en.json +
     * lang/id.json.
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
     * Label halaman ini untuk pemakaian eksternal/kompatibilitas.
     *
     * Breadcrumb halaman Auth SENGAJA tidak dirender (trait
     * HasAuthBreadcrumbs dilepas); halaman Sign In tetap punya breadcrumb
     * sendiri dan tidak memakai label ini.
     */
    public static function crumbLabel(): string
    {
        return __('Welcome Back');
    }

    /**
     * Router gateway Google Firebase (sesuai spec alur):
     *
     * TERDAFTAR, 2FA Aktif  : Auth.php -> Otp Email Or Two Factory -> Home
     * TERDAFTAR, 2FA Tidak Aktif: Auth.php -> Home
     *
     * BELUM TERDAFTAR (Menjawab Iya) selesai setup 2FA:
     *   TwoFactorAuth (Berhasil) -> Auth.php -> Home (2fa_passed=true).
     *
     * `url.intended` tidak disentuh -- tidak ada penulisan session tujuan login.
     */
    public function mount(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Akun admin/superadmin selalu langsung ke user home.
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin'])) {
            $this->redirect(WelcomeHome::getUrl(panel: 'welcome'), navigate: false);

            return;
        }

        // 2FA sudah disiapkan tapi belum diverifikasi di sesi ini -> minta OTP.
        // (Belum Terdaftar/Iya pasca-setup menandai 2fa_passed=true, jadi lolos ke Home.)
        $needsTwoFactor = method_exists($user, 'hasSetupTwoFactor')
            && $user->hasSetupTwoFactor()
            && ! $this->twoFactorPassed($user);

        if ($needsTwoFactor) {
            $this->redirect(route('filament.welcome.pages.otp-verify'), navigate: false);

            return;
        }

        // `panel: 'welcome'` wajib: tanpa itu Page::getUrl() mengambil panel dari
        // getCurrentPanel(). Di sini kebetulan hasilnya sama, tapi begitu panel
        // default berubah, halaman ini diam-diam melompat ke panel lain.
        $this->redirect(WelcomeHome::getUrl(panel: 'welcome'), navigate: false);
    }

    /**
     * Cek apakah verifikasi 2FA sesi ini sudah lolos.
     * Kompatibel dengan mix-code/filament-multi-2fa (isOtpPassed / session flag)
     * dan fallback ke session('2fa_passed').
     */
    private function twoFactorPassed($user): bool
    {
        if (session('2fa_passed') === true) {
            return true;
        }

        if (method_exists($user, 'isOtpPassed')) {
            return (bool) $user->isOtpPassed();
        }

        return false;
    }

    /**
     * Route-nya didaftarkan manual, jadi route name bawaan Filament
     * (`filament.welcome.pages.auth`) tidak ada. Menunjuk langsung ke route yang
     * dibuat registerRoutes() di bawah.
     */
    public static function getUrl(array $parameters = []): string
    {
        return route('filament.welcome.auth.index', $parameters);
    }

    /**
     * Data untuk view auth landing. Tidak ada tombol Sign In manual; pintu
     * masuk menggunakan Google Firebase Gateway (partial social-buttons).
     */
    protected function getViewData(): array
    {
        return [];
    }

    public static function registerRoutes(Panel $panel): void
    {
        Route::get('/auth', static::class)
            ->name('auth.index')
            ->middleware(['web']);
    }
}
