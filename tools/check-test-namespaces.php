<?php

/**
 * Memeriksa bahwa setiap file test berbasis class punya namespace yang cocok
 * dengan FOLDER-nya.
 *
 * Konvensi repo (lihat tests/README.md): satu file test = satu folder bernama
 * sama, dan PSR-4 memetakan `Tests\` -> `tests/`. Jadi
 *
 *   tests/Feature/User/AuthTest/AuthTest.php  ->  namespace Tests\Feature\User\AuthTest;
 *
 * Yang dipetakan adalah lokasi FOLDER, bukan lokasi file. Kalau yang
 * dipetakan file, nama folder akan terduplikasi dua kali
 * (`...\AuthTest\AuthTest`) -- dan itu yang membuat `composer dump-autoload -o`
 * memperingatkan.
 *
 * File ini dieksekusi manual (`php tools/check-test-namespaces.php`), bukan
 * bagian dari suite, karena tuannya laporan diagnostik -- bukan assertion.
 */

$root = str_replace('\\', '/', realpath(__DIR__ . '/..'));

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/tests', FilesystemIterator::SKIP_DOTS)
);

$mismatched = [];
$checked = 0;

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $contents = file_get_contents($path);

    // File berbasis closure Pest tidak punya namespace; yang punya namespace
    // hanya file berbasis class.
    if (! preg_match('/^\s*namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $contents, $m)) {
        continue;
    }

    $checked++;

    $relative = str_replace('\\', '/', substr($path, strlen($root . '/tests/')));

    // Buang nama file -- yang dipetakan PSR-4 adalah lokasi folder.
    $folder = substr($relative, 0, (int) strrpos($relative, '/'));

    // `tests/TestCase.php` berada langsung di root `tests/`, jadi namespace-nya
    // adalah `Tests` tanpa subfolder. Penyeragaman di sini supaya tidak salah
    // dilaporkan sebagai pelanggaran.
    $expected = $folder === '' ? 'Tests' : 'Tests\\' . str_replace('/', '\\', $folder);

    $actual = trim($m[1]);

    if ($actual !== $expected) {
        $mismatched[] = sprintf(
            "  %s\n      expected: %s\n      actual:   %s",
            $relative,
            $expected,
            $actual
        );
    }
}

printf("File test berbasis class yang diperiksa: %d\n\n", $checked);

if ($mismatched === []) {
    echo "OK -- semua namespace cocok dengan lokasi foldernya (PSR-4).\n";
    exit(0);
}

printf("Namespace TIDAK cocok (%d):\n\n%s\n", count($mismatched), implode("\n\n", $mismatched));
exit(1);