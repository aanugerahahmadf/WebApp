<?php

namespace App\Support\AppIcon;

use Illuminate\Support\Facades\File;

/**
 * Sumber tunggal untuk ikon navigasi dan ikon menu item.
 *
 * Kenapa ikon ini BUKAN Heroicon (monokrom):
 *
 * Heroicon (dan ikon SVG lain yang "line style") cuma satu warna, jadi
 * filesystem-nya satu warna. Di sidebar dan user menu, ikon itu dibiarkan
 * mengikuti warna teks Filament -- abu-abu di light mode, abu-abu terang di
 * dark mode. Tidak ada warna sama sekali.
 *
 * Filament v3 sebenarnya sudah mendukung ikon gambar: komponen
 * `<x-filament::icon>` memilih `<img>` kalau nama ikonnya mengandung `/`
 * (lihat `vendor/filament/support/resources/views/components/icon.blade.php`).
 * Jadi ikon berwarna cukup diberi path PNG/SVG -- tidak perlu subclass, tidak
 * perlu render hook.
 *
 * File-nya di `public/images/icons/`, bukan `resources/`, karena yang dipakai
 * adalah `<img src="...">` -- bukan lewat bundler Vite. Kalau taruh di
 * `resources/`, path-nya harus di-build dulu dan ikon nav hilang total kalau
 * `npm run build` belum dijalankan.
 *
 * FORMAT ICONNYA: dua warna.
 *
 *   - Bentuk utamanya pakai `currentColor`, jadi otomatis mengikuti warna
 *     teks (terang/gelap) Filament dan selalu terbaca.
 *   - Aksennya warna tetap, jadi ikonnya benar-benar berwarna.
 *
 * Kalau semuanya `currentColor`, ikonnyalah kembali monokrom -- itu yang
 * ingin dihindari. Kalau semuanya warna tetap, ikon gelap hilang di dark
 * mode. Dua warna adalah titik temu yang benar.
 */
final class AppIcon
{
    /**
     * Folder tempat semua ikon colorful berada, relatif terhadap root public.
     */
    public const FOLDER = '/images/icons/';

    // ---------------------------------------------------------------------
    // Storefront / akun (panel Welcome + panel User)
    // ---------------------------------------------------------------------

    public const CATALOG = self::FOLDER.'flower.svg';

    public const ORDER = self::FOLDER.'order.svg';

    public const HISTORY = self::FOLDER.'history.svg';

    public const REVIEW = self::FOLDER.'review.svg';

    public const VOUCHER = self::FOLDER.'voucher.svg';

    public const WISHLIST = self::FOLDER.'wishlist.svg';

    public const CART = self::FOLDER.'cart.svg';

    public const SETTINGS = self::FOLDER.'settings.svg';

    public const PROFILE = self::FOLDER.'profile.svg';

    public const PRIVACY = self::FOLDER.'privacy.svg';

    public const HELP = self::FOLDER.'help.svg';

    public const EXTERNAL_LINK = self::FOLDER.'external-link.svg';

    public const HOME = self::FOLDER.'home.svg';

    public const MESSAGES = self::FOLDER.'messages.svg';

    public const EXPORT = self::FOLDER.'export.svg';

    public const GIFT = self::FOLDER.'gift.svg';

    public const SPARKLES = self::FOLDER.'sparkles.svg';

    public const ARROW_RIGHT = self::FOLDER.'arrow-right.svg';

    public const SIGN_OUT = self::FOLDER.'sign-out.svg';

    // ---------------------------------------------------------------------
    // Panel admin
    // ---------------------------------------------------------------------

    public const ADMIN_SHIELD = self::FOLDER.'admin-shield.svg';

    public const API_KEY = self::FOLDER.'api-key.svg';

    public const DATABASE = self::FOLDER.'database.svg';

    public const CODE = self::FOLDER.'code.svg';

    public const DISCOUNT = self::FOLDER.'discount.svg';

    public const MAINTENANCE = self::FOLDER.'maintenance.svg';

    public const PAYMENT_GATEWAY = self::FOLDER.'payment-gateway.svg';

    public const PAYMENT_METHOD = self::FOLDER.'payment-method.svg';

    public const REFERENCE = self::FOLDER.'reference.svg';

    public const REPORT = self::FOLDER.'report.svg';

    public const TERMS = self::FOLDER.'terms.svg';

    public const TRANSACTION = self::FOLDER.'transaction.svg';

    public const USERS = self::FOLDER.'users.svg';

    public const CUSTOMIZER = self::FOLDER.'customizer.svg';

    public const WEDDING_POLICY = self::FOLDER.'wedding-policy.svg';

    public const DOCUMENT = self::FOLDER.'general-document.svg';
    public const TEXT = self::FOLDER.'text.svg';

    /** Kustomizer "Tampilan" (padanan Appearance wp-admin). */
    public const BRUSH = self::FOLDER.'brush.svg';

    /**
     * Semua ikon yang ada, untuk dipakai penjaga test.
     *
     * @return array<string, string> nama ikon => path publik
     */
    public static function all(): array
    {
        $icons = [];

        foreach ((new \ReflectionClass(self::class))->getConstants() as $name => $value) {
            if ($name === 'FOLDER' || ! is_string($value)) {
                continue;
            }

            $icons[$name] = $value;
        }

        return $icons;
    }

    /**
     * Path absolut ke file ikonnya di filesystem.
     *
     * Dipakai penjaga test untuk memastikan tidak ada ikon yang menunjuk file
     * yang tidak ada -- ikon yang hilang cuma jadi ikon yang kosong, tanpa
     * error sama sekali.
     */
    public static function absolutePath(string $publicPath, string $icon): string
    {
        return $publicPath.ltrim($icon, '/');
    }

    /**
     * Apakah file ikonnya benar-benar ada di folder public.
     */
    public static function exists(string $publicPath, string $icon): bool
    {
        return File::exists(static::absolutePath($publicPath, $icon));
    }

    /**
     * Terjemahkan nama ikon (Heroicon/Remix/GMDI/EOS) -> path SVG berwarna.
     *
     * Dipakai untuk ikon yang datanya TIDAK berasal dari kode, mis. nav item
     * yang ikonnya tersimpan di database (mobile bottom nav) dan ikon
     * bawaan Filament yang dirender <x-filament::icon>. Ikon yang sudah
     * berupa path (`/images/...`) atau tidak dikenal dikembalikan apa adanya,
     * jadi method ini aman dipanggil pada nilai apa pun.
     *
     * JEBAKAN: nilai di icon-map.php adalah NAMA FILE TANPA FOLDER dan
     * TANPA ekstensi (mis. 'archive_box'). Yang kembali dari method ini
     * HARUS path publik lengkap ('/images/icons/archive_box.svg') --
     * komponen `<x-filament::icon>` merender `<img>` HANYA kalau
     * nilainya mengandung '/'; nama polos jatuh ke `@svg()` dan
     * mencari ikon yang tidak ada (ikon hilang, tanpa error).
     */
    public static function named(?string $name): ?string
    {
        if ($name === null || str_contains($name, '/')) {
            return $name;
        }

        $map = require __DIR__.'/icon-map.php';

        $file = $map[$name] ?? null;

        if ($file === null) {
            return $name;
        }

        if (! str_ends_with($file, '.svg')) {
            $file .= '.svg';
        }

        return self::FOLDER.$file;
    }
}
