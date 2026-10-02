<?php

namespace App\Filament\Concerns;

/**
 * Breadcrumb dinamis untuk semua halaman Filament (Admin / User / Welcome).
 *
 * Parent crumb mengikuti halaman asal klik (`url()->previous()` +
 * `url.intended`), bukan hardcode Home. Tanpa asal valid, hanya trail
 * halaman saat ini yang tampil — Home tidak pernah jadi default.
 */
trait HasDynamicBreadcrumbs
{
    /**
     * Kembalikan crumb parent sebagai [url => label] atau [] bila tidak ada
     * asal valid. Pakai spread di getBreadcrumbs():
     * `[...$this->breadcrumbParentCrumb(), $this->getTitle()]`.
     */
    protected function breadcrumbParentCrumb(): array
    {
        $parent = $this->resolveBreadcrumbParent();

        if ($parent === null) {
            return [];
        }

        return [$parent['url'] => $parent['label']];
    }

    /**
     * @return array{url: string, label: string}|null
     */
    protected function resolveBreadcrumbParent(): ?array
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
            $path = (string) parse_url($url, PHP_URL_PATH);

            if ($path === '' || str_contains($url, 'livewire')) {
                continue;
            }

            // Lewati halaman sendiri (perbandingan slug).
            if (method_exists(static::class, 'getSlug')) {
                $slug = (string) static::getSlug();
                if ($slug !== '' && str_ends_with(rtrim($path, '/'), '/'.$slug)) {
                    continue;
                }
            }

            // Tamu tidak diarahkan balik ke halaman butuh-auth (/user/*, /admin);
            // halaman auth publik (login/register/password-reset) tetap valid.
            if (! auth()->check()
                && preg_match('#^/(user|admin)(/|$)#', $path)
                && ! str_contains($path, 'password-reset')
                && ! str_contains($path, '/login')
                && ! str_contains($path, '/register')
            ) {
                continue;
            }

            $label = $this->resolveBreadcrumbLabelForPath($path);

            // Home hanya muncul bila asal kliknya memang home, bukan default.
            if ($label === null && $this->isInternalBreadcrumbUrl($url)) {
                $label = __('Kembali');
            }

            if ($label !== null) {
                return ['url' => $url, 'label' => $label];
            }
        }

        return null;
    }

    protected function resolveBreadcrumbLabelForPath(string $path): ?string
    {
        if (str_contains($path, '/login')) {
            return __('Sign In');
        }

        if (str_contains($path, '/register')) {
            return __('Sign Up');
        }

        if (str_contains($path, 'complete-profile')) {
            return __('Lengkapi Profil Anda');
        }

        if (str_contains($path, 'email-verification')) {
            return __('Verifikasi Email Anda');
        }

        if (str_contains($path, 'password-reset/verify') || str_contains($path, 'verify-otp')) {
            return __('Verifikasi Kode OTP');
        }

        if (str_contains($path, 'password-reset/request')) {
            return __('Lupa Kata Sandi');
        }

        if (str_contains($path, 'password-reset/reset')) {
            return __('Atur Ulang Kata Sandi');
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $last = end($segments);

        // Abaikan segmen numerik (id) — pakai segmen sebelumnya.
        while ($last !== false && is_numeric($last)) {
            array_pop($segments);
            $last = end($segments);
        }

        if ($last === false || $last === '') {
            return null;
        }

        $known = [
            'home' => 'Beranda',
            'help-center' => 'Pusat Bantuan',
            'settings' => 'Pengaturan',
            'messages' => 'Pesan',
            'notifications' => 'Notifikasi',
            'carts' => 'Keranjang',
            'wishlists' => 'Wishlist',
            'histories' => 'Riwayat',
            'orders' => 'Pesanan',
            'packages' => 'Paket',
            'products' => 'Produk',
            'reviews' => 'Ulasan',
            'vouchers' => 'Voucher',
            'edit-profile' => 'Edit Profil',
            'password-security' => 'Keamanan Kata Sandi',
            'privacy-policy' => 'Kebijakan Privasi',
            'privacy-terms' => 'Privasi & Ketentuan',
            'terms-of-service' => 'Syarat & Ketentuan',
            'wedding-policy' => 'Kebijakan Pernikahan',
            'cbir-search' => 'Pencarian CBIR',
            'notification-detail' => 'Detail Notifikasi',
        ];

        if (isset($known[$last])) {
            return __($known[$last]);
        }

        // Aksi CRUD standar bukan halaman asal yang bermakna.
        if (in_array($last, ['create', 'edit', 'view'], true)) {
            return null;
        }

        return ucwords(str_replace(['-', '_'], ' ', (string) $last));
    }

    protected function isInternalBreadcrumbUrl(string $url): bool
    {
        if (str_contains($url, '/admin') || str_contains($url, '/user/') || str_contains($url, '/welcome')) {
            return true;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host === '' || $host === request()->getHost();
    }
}
