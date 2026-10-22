<?php

namespace App\Filament\Admin\Auth\Concerns;

use Filament\Facades\Filament;

/**
 * Breadcrumb untuk halaman Auth panel admin.
 *
 * Layout `simple` milik Filament tidak merender breadcrumbs, jadi view
 * `Admin.auth.breadcrumbs.breadcrumbs` me-render-nya manual.
 *
 * Berbeda dari versi storefront: panel admin TIDAK punya pendaftaran
 * (akun admin dibuat dari luar), dan crumb inductionya selalu panel admin
 * sendiri -- bukan storefront.
 */
trait HasAuthBreadcrumbs
{
    /**
     * Path halaman Sign-In panel admin, diturunkan dari panel (path + slug
     * route login) supaya tetap benar kalau `loginRouteSlug()` di
     * AdminPanelProvider diubah lagi.
     */
    protected function authPagePath(): string
    {
        $panel = Filament::getPanel('admin');

        return rtrim('/'.$panel->getPath(), '/').$panel->getLoginRouteSlug();
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
            if (str_contains($url, 'livewire')) {
                continue;
            }

            if (str_contains($url, $this->authPagePath())) {
                return ['url' => route('filament.admin.auth.login'), 'label' => __('Sign In')];
            }

            if (str_contains($url, 'password-reset/verify') || str_contains($url, 'verify-otp')) {
                return ['url' => $url, 'label' => __('Verifikasi Kode OTP')];
            }

            if (str_contains($url, 'password-reset/request')) {
                return ['url' => route('filament.admin.auth.password-reset.request'), 'label' => __('Lupa Kata Sandi')];
            }

            if (str_contains($url, 'password-reset/reset')) {
                return ['url' => $url, 'label' => __('Atur Ulang Kata Sandi')];
            }

            if (str_contains($url, 'email-verification')) {
                return ['url' => route('filament.admin.auth.email-verification.prompt'), 'label' => __('Verifikasi Email Anda')];
            }
        }

        return null;
    }
}
