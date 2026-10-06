<?php

namespace App\Support\PanelGlassCss;

/**
 * Pemuat CSS statis untuk lapisan kaca panel dan kartu.
 *
 * Kenapa tidak masuk `resources/css/*` seperti gaya panel lain:
 * file di sana diproses Vite, jadi setiap perubahan menuntut
 * `npm run build` -- dan `npm run build:main` untuk menulis ulang
 * `public/build/manifest.json` yang dibaca `@vite()`. Lapisan kaca ini
 * sengaja dibuat statis supaya mengeditnya langsung berlaku setelah
 * refresh, tanpa build, di pengembangan maupun produksi.
 *
 * Token warnanya (`--fi-glass-*`) TIDAK didefinisikan di sini. Sumber
 * tunggalnya ada di `resources/css/Shared/Shared.css`, file statis ini
 * hanya memakainya. Mengubah angka kacanya berarti mengubahnya di sana
 * sekali, lalu `npm run build:main`.
 *
 * Versi cache diambil dari mtime file, bukan dari hash konten seperti
 * yang dilakukan Vite. Tanpa itu, browser akan tetap menyajikan CSS
 * lama setelah file diedit -- persis masalah yang membuat orang berpikir
 * "perubahan tidak berlaku" lalu buru-buru build lagi.
 */
class PanelGlassCss
{
    /** Lokasi relatif terhadap direktori `public/`. */
    public const RELATIVE_PATH = 'css/panel-glass.css';

    public static function url(): string
    {
        return asset(self::RELATIVE_PATH);
    }

    /**
     * Tag `<link>` untuk disisipkan lewat render hook `panels::styles.after`.
     *
     * Hook itu sudah dipakai panel untuk `@vite()` berkode panel masing-masing,
     * dan posisinya memang di dalam `<head>`, jadi menambah `<link>` di sana
     * aman dan tidak perlu layout kustom.
     */
    public static function link(): string
    {
        $file = public_path(self::RELATIVE_PATH);
        $version = is_file($file) ? filemtime($file) : null;

        $href = self::url();

        if ($version !== null) {
            $href .= '?v='.$version;
        }

        return '<link rel="stylesheet" href="'.e($href).'">';
    }
}
