<?php

namespace App\Filament\User\Auth\Concerns;

use Filament\Facades\Filament;
use App\Support\StorefrontUrls\StorefrontUrls;

/**
 * Breadcrumb dinamis untuk halaman Auth (layout simple tidak render
 * breadcrumbs bawaan Filament, jadi view me-render manual via partial).
 *
 * Parent crumb mengikuti halaman asal klik (`url()->previous()` +
 * `url.intended`), bukan hardcode Home. Tanpa asal valid, hanya
 * judul halaman saat ini yang tampil.
 */
trait HasAuthBreadcrumbs
{
    /**
     * Path halaman Sign-In panel storefront, diturunkan dari panel (path +
     * slug route login) alih-alih ditulis manual, sehingga tetap benar
     * kalau `loginRouteSlug()` di WelcomePanelProvider diubah lagi.
     *
     * PENTING -- cara merakit path.
     *
     * `getLoginRouteSlug()` SUDAH membawa garis miring di depannya ('/signin'),
     * dan path panel sekarang ROOT ('/'). Jadi:
     *
     *   '/' . path . slug  ->  '//signin'   (dua garis miring: TIDAK PERNAH
     *                                          muncul di URL mana pun)
     *   ''  . path . slug  ->  '/signin'     (yang benar)
     *
     * Path panel dirtrim dulu supaya '/' tidak jadi '/', lalu slug-nya
     * ditambahkan apa adanya (garis miringnya sudah ada).
     *
     *Efeknya kalau salah: cabang "asal klik di halaman Sign In / Sign Up"
     * mati diam-diam -- crumb-nya jatuh ke "Kembali" biasa, bukan ke
     * "Sign In"/"Sign Up".
     */
    protected function authPagePath(): string
    {
        $panel = Filament::getPanel('welcome');

        return rtrim('/'.$panel->getPath(), '/').$panel->getLoginRouteSlug();
    }

    /**
     * Path URL pendaftaran, diturunkan dari panel (path + slug route
     * registration) alih-alih ditulis manual, sehingga tetap benar kalau
     * `registrationRouteSlug()` di WelcomePanelProvider diubah lagi.
     */
    protected function registerPagePath(): string
    {
        $panel = Filament::getPanel('welcome');

        return rtrim('/'.$panel->getPath(), '/').$panel->getRegistrationRouteSlug();
    }

    public function getBreadcrumbs(): array
    {
        $parent = $this->resolveAuthParentCrumb();

        $current = $this->getAuthBreadcrumbsCurrentLabel();

        if ($parent !== null) {
            return [
                $parent['url'] => $parent['label'],
                $current,
            ];
        }

        return [$current];
    }

    protected function getAuthBreadcrumbsCurrentLabel(): string
    {
        if (method_exists($this, 'getHeading')) {
            return (string) $this->getHeading();
        }

        if (method_exists($this, 'getTitle')) {
            return (string) $this->getTitle();
        }

        return class_basename(static::class);
    }

    /**
     * @return array{url: string, label: string}|null
     */
    protected function resolveAuthParentCrumb(): ?array
    {
        // Baru saja logout -> parent ke storefront welcome/home (sekali-pakai).
        if (session()->pull('after_logout', false)) {
            return ['url' => route('filament.welcome.pages.home'), 'label' => __('Beranda')];
        }

        $current = url()->current();

        $candidates = [];

        $previous = url()->previous();
        if (is_string($previous) && $previous !== '' && $previous !== $current) {
            $candidates[] = $previous;
        }

        $intended = session()->get('url.intended');
        if (is_string($intended) && $intended !== '' && $intended !== $current) {
            $candidates[] = $intended;
        }

        foreach ($candidates as $url) {
            if (str_contains($url, 'livewire') || str_contains($url, '/admin')) {
                continue;
            }

            if (str_contains($url, $this->authPagePath())) {
                return ['url' => route('filament.welcome.auth.login'), 'label' => __('Sign In')];
            }

            // Sign Up sudah HIDUP di panel User: `->registration(SignUp::class)` dipanggil
            // di UserPanelProvider dengan slug `signup`, jadi route
            // `filament.welcome.auth.register` terdaftar lagi.
            //
            // Cabang ini memanggil route()-nya dengan nama, bukan
            // `Route::has()` -- aman justru karena halamannya hidup. Kalau
            // `->registration()` dimatikan lagi tanpa cabang ini ikut dilepas,
            // route() yang hilang akan membuat halaman auth 500 untuk tamu yang
            // datang dengan referer /signup. Kalau registration-nya
            // dimatikan, cabangnya harus dihapus BERSAMA pemanggilan itu.
            if (str_contains($url, $this->registerPagePath())) {
                return ['url' => route('filament.welcome.auth.register'), 'label' => __('Sign Up')];
            }

            if (str_contains($url, 'complete-profile')) {
                return ['url' => route('filament.welcome.pages.complete-profile'), 'label' => __('Lengkapi Profil Anda')];
            }

            if (str_contains($url, 'email-verification')) {
                return ['url' => route('filament.welcome.auth.email-verification.prompt'), 'label' => __('Verifikasi Email Anda')];
            }

            if (str_contains($url, 'password-reset/verify') || str_contains($url, 'verify-otp')) {
                return ['url' => $url, 'label' => __('Verifikasi Kode OTP')];
            }

            if (str_contains($url, 'password-reset/request')) {
                return ['url' => route('filament.welcome.auth.password-reset.request'), 'label' => __('Lupa Kata Sandi')];
            }

            if (str_contains($url, 'password-reset/reset')) {
                return ['url' => $url, 'label' => __('Atur Ulang Kata Sandi')];
            }

            // Halaman user / storefront lain yang valid: pakai URL asalnya
            // langsung. Home hanya muncul bila asal kliknya memang home,
            // bukan default.
            //
            // Halaman AKUN (butuh login) bukan asal klik yang bermakna buat
            // tamu: kalau dia Comes dari situ, halamannya memang akan
            // bounced balik ke auth. Untuk yang sudah login, URL itu dipakai
            // apa adanya.
            if (StorefrontUrls::isStorefrontUrl($url)
                && ! StorefrontUrls::requiresAuthentication($url)) {
                // Storefront root (panel welcome di path '/') maupun URL lama
                // /welcome/* (redirect legacy di web.php ke URL root).
                return ['url' => $url, 'label' => __('Kembali')];
            }

            if (auth()->check() && (
                str_contains($url, '/user/')
                || StorefrontUrls::requiresAuthentication($url)
            )) {
                // URL lama /user/* — redirect sudah di web.php ke URL baru.
                return ['url' => $url, 'label' => __('Kembali')];
            }
        }

        return null;
    }
}
