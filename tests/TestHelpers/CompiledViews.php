<?php

namespace Tests\TestHelpers;

use Illuminate\Foundation\Application;

/**
 * Isolasi hasil kompilasi Blade per proses test.
 *
 * Blade menulis hasil kompilasi view ke `storage/framework/views`. Dua proses
 * `php artisan test` yang jalan bersamaan di mesin yang sama menulis file yang
 * SAMA, dan di Windows `rename()` ke file yang sedang dipegang proses lain
 * gagal:
 *
 *     rename(...\views\81a1B04.tmp, ...\views\81a27ff3ff28759355d956eec4660c37.php):
 *     Access is denied (code: 5)
 *
 * Kode 5 itu `ERROR_ACCESS_DENIED` di Windows, yang muncul kalau destination
 * sedang dipegang proses lain -- termasuk `include` PHP yang baru saja selesai
 * membaca view itu, atau antivirus yang sedang memindai foldernya.
 *
 * Gejalanya muncul sebagai `ViewException` dari dalam Filament, beberapa lapis
 * dari penyebabnya:
 *
 *     at vendor\laravel\framework\src\Illuminate\Filesystem\Filesystem.php:233
 *     at vendor\livewire\livewire\src\Mechanisms\ExtendBlade\ExtendedCompilerEngine.php:58
 *
 * Jadi yang terlihat di layar bukan "dua proses test berebut file", tapi
 * "halaman Filament 500". Test yang gagal begitu tidak ada artinya: kode
 * produksi-nya benar, yang salah hanya jalannya test.
 *
 * SOLUSINYA: setiap proses test memakai folder sendiri. Tidak ada lagi file
 * yang sama ditulis dua proses sekaligus, jadi `rename()` tidak pernah
 *luetooth dengan file yang sedang dibuka.
 *
 * Konsekuensinya: hasil kompilasi view tidak bisa dipakai ulang antar proses.
 * Itu biaya yang sepadan -- satu proses = satu folder, dan foldernya sudah
 * masuk `.gitignore` (`/storage/framework/views/*`).
 *
 * Dipakai dari dua tempat, dan keduanya perlu:
 *
 *   1. `tests/bootstrap.php` -- proses ini sudah menyetel env var SEBELUM
 *      aplikasi boot, jadi `config/view.php` (yang membaca
 *      `env('VIEW_COMPILED_PATH')`) langsung memakai path yang benar.
 *   2. `tests/TestCase.php` -- set ulang config-nya setelah boot, karena
 *      `php artisan config:cache` membuat `env()` di `config/view.php` tidak
 *      dibaca sama sekali. Tanpa ini, folder per proses hanya berlaku di mesin
 *      yang tidak punya config cache.
 */
final class CompiledViews
{
    /**
     * Folder hasil kompilasi view milik proses ini.
     *
     * Panjang path dibatasi diam-diam oleh Windows (MAX_PATH 260), jadi
     * foldernya diletakkan DI DALAM `storage/framework/views`, bukan
     * menggabungkan nama storage ke path yang lebih dalam.
     */
    public static function path(): string
    {
        return dirname(__DIR__, 2)
            .DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'views'
            .DIRECTORY_SEPARATOR.'testing-'.getmypid();
    }

    /**
     * Beri tahu Laravel (lewat env var) folder mana yang dipakai.
     *
     * Harus dipanggil SEBELUM aplikasi boot, karena `config/view.php` membaca
     * `env('VIEW_COMPILED_PATH')` saat config dimuat.
     *
     * Ketiga tempat ditulis, bukan cuma `putenv()`: `env()` di Laravel
     * membaca lewat repository yang punya adapter untuk `$_ENV`, `$_SERVER`,
     * dan `putenv()`, dan tidak semua konfigurasi PHP mengaktifkan
     * `variables_order` yang memuat `E`.
     */
    public static function isolate(): void
    {
        $path = self::path();

        putenv('VIEW_COMPILED_PATH='.$path);
        $_ENV['VIEW_COMPILED_PATH'] = $path;
        $_SERVER['VIEW_COMPILED_PATH'] = $path;

        self::ensureDirectoryExists($path);
    }

    /**
     * Paksa aplikasi yang sudah boot memakai folder per proses.
     *
     * Dipanggil setelah `createApplication()`, karena di titik itu config
     * sudah dimuat -- dan kalau config di-cache, nilai `view.compiled` sudah
     * dibekukan sebelum `isolate()` sempat bicara.
     *
     * Tipe parameternya `Illuminate\Foundation\Application` (bukan kontrak
     * `Illuminate\Contracts\Foundation\Application`) karena `forgetInstance()`
     * hanya ada di kelas konkretnya.
     */
    public static function applyTo(Application $app): void
    {
        $path = self::path();

        self::ensureDirectoryExists($path);

        $app['config']->set('view.compiled', $path);

        // `blade.compiler` adalah singleton. Kalau ia sudah ter-resolve saat
        // boot (mis. halaman error yang dirender provider), instance itu
        // masih memegang path lama -- jadi dibuang supaya dibuat ulang dengan
        // path yang baru.
        $app->forgetInstance('blade.compiler');
    }

    /**
     * Buat foldernya kalau belum ada.
     *
     * Blade sendiri juga akan membuatnya (`ensureCompiledDirectoryExists`),
     * tapi foldernya dibuat di sini supaya tidak ada celah di mana dua proses
     * membuat folder yang sama.
     */
    private static function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    }
}
