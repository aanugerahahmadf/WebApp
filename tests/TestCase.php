<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\TestHelpers\CompiledViews;

/**
 * Base class untuk seluruh test.
 *
 * Yang dikerjakan di sini: **hasil kompilasi Blade dipisah per proses.**
 *
 * Blade menulis hasil kompilasi view ke `storage/framework/views`. Dua proses
 * `php artisan test` yang jalan bersamaan di mesin yang sama menulis file yang
 * SAMA, dan di Windows `rename()` ke file yang sedang dipegang proses lain
 * gagal:
 *
 *     rename(...\views\81a1B04.tmp, ...\views\81a27ff3....php): Access is denied (code: 5)
 *
 * Kode 5 itu `ERROR_ACCESS_DENIED`. Gejalanya muncul sebagai `ViewException`
 * dari dalam Filament, beberapa lapis dari penyebabnya -- jadi terlihat
 * seperti bug produksi, padahal yang salah hanya jalannya test.
 *
 * Lihat `Tests\TestHelpers\CompiledViews` untuk penjelasan lengkapnya.
 *
 * CATATAN: TIDAK ADA `set_error_handler()` DI SINI, dan memang tidak boleh
 * ada. Alasannya bukan sekadar "biarkan PHPUnit yangurus":
 *
 *   - PHPUnit memasang handler-nya sendiri di `TestRunner::run()` SEBELUM
 *     `setUp()` dipanggil, dan `PHPUnit\Runner\ErrorHandler::enable()` BERHENTI
 *     kalau sudah ada handler yang terpasang
 *     (`if ($oldErrorHandler !== null) { restore_error_handler(); return; }`).
 *     Jadi suppressor di level bootstrap membuat PHPUnit TIDAK memasang
 *     handler-nya -- dan error PHP yang biasanya jadi test failure (E_WARNING,
 *     E_ERROR) berubah jadi tulisan biasa di output. Itu persis kebalikan dari
 *     yang diinginkan.
 *
 *   - Suppressor yang dipasang di `setUp()` (yaitu DI ATAS handler PHPUnit)
 *     punya masalah lain: untuk meneruskan error yang tidak dikenali, ia
 *     memanggil handler PHPUnit secara manual, dan PHPUnit mencari test case
 *     yang sedang jalan lewat `debug_backtrace()`. Callable itu menambah satu
 *     frame di antara, sehingga atribusinya gagal -- dan deprecation yang
 *     seharusnya dikumpulkan PHPUnit justru tercetak mentah ke output:
 *
 *         PHP Deprecated: Using null as an array offset is deprecated ...
 *         in vendor/filament/support/src/View/ViewManager.php on line 63
 *
 * Jadi lebih baik TIDAK menekan apa pun: dua pesan yang dulu dimaksudkan
 * hilang dari file `tests/bootstrap.php` (`tempnam()` di AliasLoader, dan
 * `PDO::MYSQL_ATTR_SSL_CA` yang deprecated) tidak pernah muncul di suite ini
 * -- `config/database.php` sudah memakai `PDO\Mysql::ATTR_SSL_CA` yang ada
 * sejak PHP 8.4, jadi konstanta lama tidak pernah dievaluasi.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Paksa folder hasil kompilasi view milik proses ini.
     *
     * `tests/bootstrap.php` sudah menyetel env var-nya sebelum aplikasi boot,
     * jadi `config/view.php` (yang membaca `env('VIEW_COMPILED_PATH')`)
     * langsung memakai path yang benar. Penge-set-ulangan di sini menutup
     * kasus `php artisan config:cache`, di mana `env()` di `config/view.php`
     * tidak dibaca sama sekali dan nilainya sudah dibekukan di cache.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        CompiledViews::applyTo($app);

        return $app;
    }
}
