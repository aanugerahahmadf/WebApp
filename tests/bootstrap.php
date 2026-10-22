<?php

/**
 * Bootstrap PHPUnit.
 *
 * Dipakai lewat `phpunit.xml` (`bootstrap="tests/bootstrap.php"`). Dulu file
 * ini tidak pernah dimuat -- `phpunit.xml` menunjuk `vendor/autoload.php` --
 * jadi isinya cuma jadi kode mati.
 *
 * Isi file ini sengaja KECIL, dan TIDAK memuat `set_error_handler`:
 *
 *   - PHPUnit memasang handler-nya sendiri di `TestRunner::run()` SEBELUM
 *     `setUp()` dipanggil, dan `PHPUnit\Runner\ErrorHandler::enable()` BERHENTI
 *     kalau sudah ada handler yang terpasang
 *     (`if ($oldErrorHandler !== null) { restore_error_handler(); return; }`).
 *     Kalau file ini memasang handler sendiri, PHPUnit TIDAK akan memasang
 *     handler-nya -- dan error PHP yang biasanya jadi test failure (E_WARNING,
 *     E_ERROR) cuma jadi tulisan di output. Itu persis kebalikan dari yang
 *     diinginkan.
 *
 *   - Suppressor yang dipasang di dalam test (`setUp()`) pun punya masalah
 *     sendiri: untuk meneruskan error yang tidak dikenali ke handler PHPUnit,
 *     ia memanggil handler itu secara manual, dan PHPUnit mencari test case
 *     yang sedang jalan lewat `debug_backtrace()`. Callable tambahan itu
 *     menambah satu frame di antara, sehingga atribusi deprecation gagal --
 *     dan deprecation yang seharusnya dikumpulkan PHPUnit tercetak mentah ke
 *     output. Detailnya ada di `Tests\TestCase`.
 */

require __DIR__.'/../vendor/autoload.php';

use Tests\TestHelpers\CompiledViews;

/*
 * Isolasi hasil kompilasi Blade per proses.
 *
 * Tanpa ini, dua proses `php artisan test` yang jalan bersamaan di mesin yang
 * sama menulis file view yang sama, dan `rename()` di Windows gagal dengan
 * "Access is denied (code: 5)" -- yang muncul sebagai ViewException dari
 * dalam Filament, jauh dari penyebabnya.
 *
 * Penjelasan lengkapnya ada di `Tests\TestHelpers\CompiledViews`.
 */
CompiledViews::isolate();
