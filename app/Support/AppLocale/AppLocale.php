<?php

namespace App\Support\AppLocale;

use App\Support\AppPlatform\AppPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Satu tempat untuk menebak bahasa yang sedang dipakai.
 *
 * KENAPA KELAS INI ADA (dan kenapa `SetLocale` tidak cukup):
 *
 *   Halaman error harus menampilkan teks dalam bahasa yang benar. Tetapi
 *   halaman error TIDAK SELALU melewati middleware `SetLocale`:
 *
 *   - 404 untuk URL yang tidak dikenal dilempar oleh ROUTER, sebelum
 *     middleware route (termasuk grup `web`) sempat jalan. Jadi
 *     `SetLocale` TIDAK PERNAH dipanggil, dan bahasa yang terpakai adalah
 *     default aplikasi -- bukan bahasa yang diminta browser.
 *     Gejalanya: orang berbahasa Inggris membuka tautan yang salah dan
 *     mendapat halaman "Tidak ditemukan" dalam Bahasa Indonesia.
 *
 *   - Halaman error yang dirender di luar request HTTP (misalnya dari
 *     sebuah artisan command atau dari `view()` langsung di test) juga
 *     tidak punya middleware sama sekali.
 *
 * ATURAN PRIORITASNYA SAMA PERSIS dengan `SetLocale`:
 *
 *   1. parameter `?locale=` lalu `?lang=`  (language switcher, test label)
 *   2. `session('locale')`                  (hasil request sebelumnya)
 *   3. header `Accept-Language`             (browser & Flutter)
 *   4. bahasa user di database              (HANYA di `SetLocale`)
 *   5. fallback                             (shell native = `id`)
 *
 * Yang SENGAJA TIDAK ikut di sini: branch database (4).
 *
 *   Halaman error boleh muncul justru saat database tidak terjangkau, jadi
 *   halaman error tidak boleh query apa pun. Preferensi bahasa user tetap
 *   terpakai karena `SetLocale` menyimpannya ke session pada setiap request
 *   -- jadi cabang 2 di atas sudah menutupinya.
 */
class AppLocale
{
    /**
     * Locale yang terdeteksi dari permintaan, atau null kalau tidak ada.
     *
     * Null berarti "tidak ada yang terdeteksi" -- pemanggil yang punya
     * sumber lain (misalnya `SetLocale` dengan bahasa user di database) baru
     * boleh memakai lokasinya sendiri.
     */
    public static function detected(?Request $request = null): ?string
    {
        $request = self::resolveRequest($request);

        if (! $request instanceof Request) {
            return null;
        }

        $supported = self::supported();

        // 1. Parameter eksplisit.
        //
        // HARUS disaring terhadap daftar locale yang didukung. `?locale=`
        // adalah input pengguna; tanpa saringan, seluruh terjemahan jatuh ke
        // string sumber dan tidak ada satu pun kalimat yang ter terjemahan.
        $requested = $request->has('locale')
            ? (string) $request->input('locale')
            : ($request->has('lang') ? (string) $request->input('lang') : null);

        if ($requested !== null && in_array($requested, $supported, true)) {
            return $requested;
        }

        // 2. Session.
        //
        // Hanya dibaca kalau session-nya benar-benar ADA. `session()` tanpa
        // session yang sudah dimulai akan memulai session -- yang berarti
        // query ke database pada driver `database`, persis hal yang tidak
        // boleh terjadi di halaman error.
        if ($request->hasSession()) {
            $sessionLocale = (string) session()->get('locale');

            if ($sessionLocale !== '' && in_array($sessionLocale, $supported, true)) {
                return $sessionLocale;
            }
        }

        // 3. Header Accept-Language.
        $acceptLanguage = $request->header('Accept-Language');

        if ($acceptLanguage) {
            $langs = explode(',', $acceptLanguage);
            $firstLang = trim($langs[0]);
            $cleanLang = str_replace('-', '_', $firstLang);

            if (in_array($cleanLang, $supported, true)) {
                return $cleanLang;
            }

            // `id-ID` harus dikenali sebagai `id`.
            $shortLang = explode('_', $cleanLang)[0];

            foreach ($supported as $sup) {
                if ($sup === $shortLang || explode('_', $sup)[0] === $shortLang) {
                    return $sup;
                }
            }
        }

        return null;
    }

    /**
     * Locale cadangan kalau tidak ada yang terdeteksi.
     *
     * Shell mobile native (Capacitor) selalu Bahasa Indonesia: bundle-nya
     * dikirim dalam Bahasa Indonesia-first, dan kalau bahasa sistem operasi
     * mengambil alih, aplikasi jadi campuran dua bahasa.
     */
    public static function fallback(?Request $request = null): string
    {
        $request = self::resolveRequest($request);

        if ($request instanceof Request && AppPlatform::isNativeMobile()) {
            return 'id';
        }

        $supported = self::supported();

        if ($request instanceof Request) {
            $preferred = $request->getPreferredLanguage($supported ?: ['id', 'en']);

            if ($preferred) {
                return $preferred;
            }
        }

        return 'id';
    }

    /**
     * Locale final untuk sebuah permintaan: yang terdeteksi, kalau tidak ada
     * maka cadangan.
     */
    public static function resolve(?Request $request = null): string
    {
        return self::detected($request) ?? self::fallback($request);
    }

    /**
     * Terapkan locale ke aplikasi.
     *
     * Dipakai halaman error: view dirender SESUDAH handler memilih view, jadi
     * locale harus dipasang lebih dulu supaya teksnya ikut bahasa yang
     * benar.
     */
    public static function apply(?Request $request = null): string
    {
        $locale = self::resolve($request);

        if ($locale !== '') {
            App::setLocale($locale);
            config(['app.locale' => $locale]);
        }

        return $locale;
    }

    /**
     * Daftar locale yang didukung aplikasi.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        $locals = config('filament-language-switcher.locals', ['id' => [], 'en' => []]);

        return array_keys($locals);
    }

    private static function resolveRequest(?Request $request = null): ?Request
    {
        if ($request instanceof Request) {
            return $request;
        }

        if (app()->bound('request')) {
            $current = request();

            return $current instanceof Request ? $current : null;
        }

        return null;
    }
}
