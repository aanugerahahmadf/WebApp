<?php

namespace Tests\Unit\Shared\CompiledViewsIsolationTest;

use Tests\TestCase;
use Tests\TestHelpers\CompiledViews;

/**
 * Penjaga: hasil kompilasi Blade harus dipisah per proses test.
 *
 * Dua proses `php artisan test` yang jalan bersamaan di mesin yang sama akan
 * menulis file view yang sama kalau folder hasil kompilasi-nya dipakai
 * bersama. Di Windows `rename()` ke file yang sedang dipegang proses lain
 * gagal dengan "Access is denied (code: 5)", dan gejalanya muncul sebagai
 * ViewException dari dalam Filament -- jadi terlihat seperti bug produksi,
 * padahal yang salah jalannya test.
 *
 * Test ini mengunci pemecahannya, supaya isolate-per-proses tidak diam-diam
 * dihapus (mis. karena "keburu-buru" dan testnya sendiri masih hijau).
 *
 * Yang diperiksa:
 *
 *   1. `config('view.compiled')` menunjuk ke folder milik proses ini, bukan
 *      ke folder bersama `storage/framework/views`.
 *   2. Foldernya benar-benar ada, jadi Blade tidak perlu membuatnya di
 *      tengah jalan (celah di mana dua proses membuat folder yang sama).
 *   3. Namanya membawa PID, jadi dua proses tidak mungkin kebetulan memakai
 *      folder yang sama.
 *   4. `bootstrap/providers.php` tidak mendaftarkan provider yang sudah mati
 *      (`UserPanelProvider`) -- panel 'user' dilebur ke panel 'welcome', dan
 *      dua panel di path '/' akan saling menimpa route-nya.
 */
final class CompiledViewsIsolationTest extends TestCase
{
    public function test_compiled_views_path_is_inside_the_shared_views_folder(): void
    {
        $path = CompiledViews::path();

        $this->assertStringStartsWith(
            realpath(storage_path('framework/views')).DIRECTORY_SEPARATOR,
            $path,
            'Folder hasil kompilasi view harus tetap DI DALAM storage/framework/views, '
            .'supaya path-nya tidak melewati batas MAX_PATH Windows.',
        );
    }

    public function test_compiled_views_path_carries_the_process_id(): void
    {
        $this->assertStringContainsString(
            (string) getmypid(),
            CompiledViews::path(),
            'Folder hasil kompilasi view harus berbeda per proses -- kalau tidak, '
            .'dua proses test yang jalan bersamaan akan menulis file yang sama.',
        );
    }

    public function test_compiled_views_directory_exists(): void
    {
        $this->assertDirectoryExists(
            CompiledViews::path(),
            'Folder hasil kompilasi view harus sudah ada sebelum view pertama '
            .'dirender, supaya tidak ada celah di mana dua proses membuatnya.',
        );
    }

    public function test_application_uses_the_isolated_compiled_views_path(): void
    {
        $this->assertSame(
            CompiledViews::path(),
            config('view.compiled'),
            'Aplikasi yang sedang diuji harus memakai folder hasil kompilasi '
            .'yang terisolasi. Kalau tidak, `rename()` di Windows bisa gagal '
            .'dengan "Access is denied" saat dua proses test jalan bersamaan.',
        );
    }

    public function test_blade_compiler_was_rebuilt_with_the_isolated_path(): void
    {
        // `blade.compiler` adalah singleton. Kalau instance itu dibuat saat
        // boot (sebelum config-nya diganti), ia masih memegang path lama.
        //
        // `getCompiledPath()` mengembalikan path FILE (folder + hash + .php),
        // jadi yang dibandingkan adalah awal path-nya. Pemisahnya bisa `/`
        // atau `\` tergantung sistem.
        $compiled = $this->app->make('blade.compiler')->getCompiledPath('probe.php');

        $this->assertMatchesRegularExpression(
            '/^'.preg_quote(CompiledViews::path(), '/').'[\\\\\/]/',
            $compiled,
            'Blade compiler harus dibangun ulang dengan path yang terisolasi.',
        );
    }

    public function test_dead_user_panel_provider_is_not_registered(): void
    {
        $providers = (string) file_get_contents(base_path('bootstrap/providers.php'));

        // Yang dicek adalah `::class`-nya, bukan namanya: file ini masih
        // menyebut `UserPanelProvider` di komentarnya (menjelaskan kenapa
        // provider itu tidak lagi didaftarkan).
        $this->assertStringNotContainsString(
            'UserPanelProvider::class',
            $providers,
            '`UserPanelProvider` sudah tidak dipakai sejak panel \'user\' dilebur ke '
            .'panel \'welcome\`. Mendaftarkannya lagi akan membuat dua panel di '
            .'path \'/\', dan yang kalah biasanya bukan yang disangka.',
        );
    }
}
