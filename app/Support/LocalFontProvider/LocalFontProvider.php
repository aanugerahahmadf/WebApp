<?php

namespace App\Support\LocalFontProvider;

use Filament\FontProviders\Contracts\FontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Font provider untuk font LOKAL -- sengaja tidak mengunduh apa pun.
 *
 * Latar belakang (keputusan pengguna 2026-10-21 -- Google Sans Flex):
 *
 * `Panel::font()` punya dua efek terpisah, dan hanya satu yang kita mau:
 *
 *   1. `getFontFamily()` -> dirender sebagai
 *      `--font-family: '<family>'` di components/layout/base.blade.php, lalu
 *      dipakai preset Tailwind Filament:
 *          sans: ['var(--font-family)', ...defaultTheme.fontFamily.sans]
 *      Ini yang BENAR-BENAR menentukan huruf yang tampil.
 *
 *   2. `getFontHtml()` -> provider membuat tag <link> ke webfont.
 *
 * Efek (2) justru merusak: provider bawaan (BunnyFontProvider)
 * selalu meminta CSS dari CDN (fonts.bunny.net) -- dan sekarang font
 * aplikasi WAJIB dari file LOKAL `public/fonts/Google_Sans_Flex/`
 * (TTF variable resmi yang diunduh dari Google), BUKAN dari internet.
 * Dananya: tunnel publik, jaringan lambat, dan Electron desktop app
 * semuanya lebih sehat tanpa CDN font.
 *
 * Jadi provider ini mengembalikan HtmlString kosong: efek (1) tetap jalan,
 * efek (2) hilang. `@font-face` Google Sans Flex dan penimpaan
 * `* { font-family }` didefinisikan di `public/css/app-font.css`,
 * dimuat oleh `AppFont::styles()` (panel) dan halaman error
 * `glass.blade.php`.
 *
 * CATATAN: `Panel::getFontFamily()` default-nya 'Inter' BUKAN null -- jadi
 * panel yang TIDAK memanggil `->font()` sama sekali tetap memuat Inter dari
 * Bunny. Semua panel di project ini karena itu memanggil `->font()`
 * secara eksplisit.
 */
class LocalFontProvider implements FontProvider
{
    /**
     * Tidak ada <link>: font lokal, filenya ada di public/fonts/Google_Sans_Flex/.
     */
    public function getHtml(string $family, ?string $url = null): Htmlable
    {
        return new HtmlString('');
    }
}
