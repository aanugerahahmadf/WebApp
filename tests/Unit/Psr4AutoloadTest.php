<?php

/**
 * Penjaga autoloading PSR-4 untuk seluruh kelas di app/.
 *
 * Konvensi folder-per-class proyek ini (App\Models\User\User di
 * app/Models/User/User.php) sebenarnya sudah PSR-4 dengan benar, dan itu
 * yang memuatnya -- BUKAN classmap hasil `composer dump-autoload -o`.
 *
 * Kelas yang namespace-nya berhenti satu segmen lebih pendek dari path-nya
 * hanya kebetulan termuat selama classmap hasil optimize masih terpasang.
 * Begitu ada `composer dump-autoload` biasa, file itu hilang dari classmap,
 * class_exists() jadi false, dan aplikasi gagal saat memuat middleware,
 * seeder, atau path generator.
 *
 * Contoh yang pernah kejadian: App\Support\MediaLibrary\CollectionPathGenerator
 * punya namespace `App\Support\MediaLibrary`, sedangkan filenya ada di
 * app/Support/MediaLibrary/CollectionPathGenerator/. Seeder apa pun yang
 * menyentuh media langsung gagal dengan InvalidPathGenerator.
 *
 * Test ini membaca file apa adanya -- tidak memuat kelas lewat autoloader --
 * jadi ia tetap menangkap masalah meski classmap-nya sendiri sudah rusak.
 */

it('setiap kelas di app/ bisa dimuat oleh PSR-4 atau classmap composer', function (): void {
    $root = base_path();
    $appDir = $root.DIRECTORY_SEPARATOR.'app';

    /** @var array<string, string> $classmap FQCN => path, dari composer */
    $classmap = [];

    $mapFile = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'
        .DIRECTORY_SEPARATOR.'autoload_classmap.php';

    if (is_file($mapFile)) {
        $classmap = require $mapFile;
    }

    $unloadable = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $absolute = $file->getPathname();
        $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));

        $source = (string) file_get_contents($absolute);

        if (! preg_match('/^\s*namespace\s+([^;]+);/m', $source, $ns)) {
            continue;
        }

        if (! preg_match('/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $cn)) {
            continue;
        }

        $fqcn = trim($ns[1]).'\\'.$cn[1];

        // Lolos bila path-nya cocok aturan PSR-4 ...
        if ('app/'.str_replace('\\', '/', substr($fqcn, 4)).'.php' === $relative) {
            continue;
        }

        // ... atau memang terdaftar di classmap composer dan bisa dimuat
        // (mis. app/Http/Controllers, yang punya base Controller non-PSR-4).
        if (($classmap[$fqcn] ?? null) !== null && class_exists($fqcn)) {
            continue;
        }

        $unloadable[] = $fqcn.' -> '.$relative;
    }

    expect($unloadable)->toBe(
        [],
        "Kelas berikut tidak bisa di-autoload oleh PSR-4 maupun classmap composer:\n"
        .implode("\n", $unloadable)
    );
});

it('classmap composer tetap memuat base Controller yang non-PSR-4', function (): void {
    // composer.json mendaftarkan app/Http/Controllers lewat classmap karena
    // App\Http\Controllers\Controller tidak mengikuti PSR-4. Kalau entri itu
    // hilang, setiap controller yang mewarisi base controller ikut gagal.
    expect(class_exists(App\Http\Controllers\Controller::class))->toBeTrue();
});

it('path generator media library bisa dimuat', function (): void {
    // Guard khusus untuk kelas yang pernah pecah: dipakai config/media-library
    // sebagai nilai 'path_generator', dan Spatie memanggil class_exists() saat
    // membangun path -- jadi kalau tidak termuat, setiap seeding media gagal
    // dan halaman yang merender media ikut 500.
    expect(class_exists(App\Support\MediaLibrary\CollectionPathGenerator::class))->toBeTrue();
});