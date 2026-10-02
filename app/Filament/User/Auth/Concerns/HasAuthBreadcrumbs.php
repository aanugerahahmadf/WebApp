<?php

namespace App\Filament\User\Auth\Concerns;

use Filament\Facades\Filament;

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
     * Path halaman Sign-In panel user, diturunkan dari panel (path +
     * slug route login) alih-alih ditulis manual, sehingga tetap benar
     * kalau `loginRouteSlug()` di UserPanelProvider diubah lagi.
     */
    protected function authPagePath(): string
    {
        $panel = Filament::getPanel('user');

        return '/'.$panel->getPath().$panel->getLoginRouteSlug();
    }

    /**
     * Path halaman Sign-Up panel user, diturunkan dari panel (path +
     * slug route registration) — alasan yang sama seperti authPagePath().
     */
    protected function registrationPagePath(): string
    {
        $panel = Filament::getPanel('user');

        return '/'.$panel->getPath().$panel->getRegistrationRouteSlug();
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
                return ['url' => route('filament.user.auth.login'), 'label' => __('Sign In')];
            }

            if (str_contains($url, $this->registrationPagePath())) {
                return ['url' => route('filament.user.auth.register'), 'label' => __('Sign Up')];
            }

            if (str_contains($url, 'complete-profile')) {
                return ['url' => route('filament.user.pages.complete-profile'), 'label' => __('Lengkapi Profil Anda')];
            }

            if (str_contains($url, 'email-verification')) {
                return ['url' => route('filament.user.auth.email-verification.prompt'), 'label' => __('Verifikasi Email Anda')];
            }

            if (str_contains($url, 'password-reset/verify') || str_contains($url, 'verify-otp')) {
                return ['url' => $url, 'label' => __('Verifikasi Kode OTP')];
            }

            if (str_contains($url, 'password-reset/request')) {
                return ['url' => route('filament.user.auth.password-reset.request'), 'label' => __('Lupa Kata Sandi')];
            }

            if (str_contains($url, 'password-reset/reset')) {
                return ['url' => $url, 'label' => __('Atur Ulang Kata Sandi')];
            }

            // Halaman user / welcome lain yang valid: pakai URL asalnya langsung.
            // Home hanya muncul bila asal kliknya memang home, bukan default.
            // Tamu (belum login) tidak diarahkan balik ke /user/* karena semua
            // halaman user butuh auth (kliknya mental balik ke login).
            if (str_contains($url, '/welcome')) {
                return ['url' => $url, 'label' => __('Kembali')];
            }

            if (str_contains($url, '/user/') && auth()->check()) {
                return ['url' => $url, 'label' => __('Kembali')];
            }
        }

        return null;
    }
}
