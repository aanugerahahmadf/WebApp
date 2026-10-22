<?php

namespace App\Support\StorefrontUrls;

/**
 * Penentu satu pertanyaan: "URL ini milik storefront publik?"
 *
 * Panel Welcome pindah ke path '/' (shopee-style: URL storefront tanpa
 * prefix), sehingga URL storefront tidak lagi bisa dibedakan dengan
 * `str_contains($url, '/welcome')` -- halaman katalog misalnya sekarang
 * /flowerdecorationscatalog, TANPA prefix. Kelas ini menggantikan semua
 * cek semacam itu di tiga tempat (SignIn fallback, HasAuthBreadcrumbs,
 * HasDynamicBreadcrumbs) supaya tidak ada lagi aturan yang terpisah-pisah.
 *
 * Definisi "storefront":
 *   - URL bawaan panel Welcome setelah pindah ke '/': /home, /messages,
 *     /flowerdecorationscatalog, /settings, /orders, /carts, /wishlists, dst.
 *   - URL legacy /welcome/* yang masih ada di bookmark lama (redirect di
 *     web.php ke URL root, jadi milik logika yang sama).
 *
 * Di luar definisi: admin, account user (/user/*), auth, legal publik,
 * livewire, media/pdf/API -- semua itu bukan halaman yang bisa dibuka
 * tamu di storefront.
 */
class StorefrontUrls
{
    /**
     * Segment path PERTAMA yang bukan milik panel Welcome (root).
     *
     * Cukup segment pertama: /user/settings, /admin/inbox, /auth,
     * /auth/google, /legal/terms, /livewire/update, /api/*, dst.
     *
     * Catatan: 'reports' & 'invoice' (PDF user) sengaja eksplisit, bukan
     * halaman storefront; 'welcome' tetap di sini karena /welcome/* kini
     * hanya legacy redirect -- masih valid sebagai storefront untuk
     * bookmark lama, tapi jangan dijadikan acuan URL baru.
     *
     * @var list<string>
     */
    private const NOT_STOREFRONT_FIRST_SEGMENTS = [
        'admin',
        'user',
        'auth',
        'api',
        'legal',
        'livewire',
        'language',
        'lang',
        'clerk',
        'media',
        'invoice',
        'reports',
        'captcha',
        'cookie-consent',
        'cookie-preferences',
        'debug',
        // Alias auth yang masih dilayani web.php (redirect ke panel user) --
        // utamanya untuk menahan SignIn fallback memilih halaman auth sendiri.
        'signin',
        'signup',
    ];

    /**
     * Segment path PERTAMA yang halamannya butuh login.
     *
     * Halaman-halaman ini berada di root (panel Welcome), jadi dari sisi URL
     * tidak bisa dibedakan dari halaman publik. Tapi isinya milik akun:
     * tamu yang menyebutnya bounced ke halaman auth, jadi menjadikannya
     * "asal klik" yang bermakna buat crumb atau tombol "kembali" hanya
     * membingungkan.
     *
     * @var list<string>
     */
    private const AUTHENTICATED_FIRST_SEGMENTS = [
        'carts',
        'orders',
        'wishlists',
        'reviews',
        'vouchers',
        'histories',
        'settings',
        'notifications',
        'complete-profile',
        'edit-profile-page',
        'security-question',
        'two-factor-auth',
        'device-trust',
        'password-reset',
        'email-verification',
        'o-t-p-verify',
    ];

    /**
     * Apakah $url (relatif atau absolut) menunjuk halaman storefront?
     *
     * Absolute URL dipecah ke path-nya dulu, jadi hasilnya konsisten untuk
     * domain tunnel/LAN dan localhost sekaligus.
     */
    public static function isStorefrontUrl(string $url): bool
    {
        $segments = static::firstSegments($url);

        if ($segments === []) {
            // '/' bukan halaman: dia cuma redirect ke beranda ('/home').
            // Kalau '/' dianggap halaman storefront, maka setiap pengunjung
            // yang membuka /signin secara langsung (tanpa referer) akan
            // mendapat crumb "Kembali" ke root -- dan tombol "kembali" di
            // halaman Sign In akan membawa ke root, bukan ke beranda.
            return false;
        }

        $first = strtolower((string) $segments[0]);

        return ! in_array($first, self::NOT_STOREFRONT_FIRST_SEGMENTS, true);
    }

    /**
     * Apakah $url menunjuk halaman yang hanya bisa dibuka yang sudah masuk?
     */
    public static function requiresAuthentication(string $url): bool
    {
        $segments = static::firstSegments($url);

        if ($segments === []) {
            return false;
        }

        return in_array(strtolower((string) $segments[0]), self::AUTHENTICATED_FIRST_SEGMENTS, true);
    }

    /**
     * Segment-segment path pertama, lowercase.
     *
     * @return list<string>
     */
    protected static function firstSegments(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            // Tanpa path sama sekali (mis. "https://host") = homepage.
            $path = '/';
        }

        return array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    }
}
