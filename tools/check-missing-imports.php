<?php

/**
 * Deteksi kelas milik project yang dipanggil TANPA import yang benar.
 *
 * Bug yang dijaga: saat penataan ulang `Product` -> `Catalog`, satu baris `use`
 * hilang dari `HistoryResource` (versi Welcome dan User). PHP tidak melempar
 * sampai class itu benar-benar dipanggil -- yaitu ketika user menekan tombol
 * di halaman Histori. Test pun baru melihatnya lewat `ViewException`.
 *
 * Cara kerjanya, per file di `app/`:
 *   1. Kumpulkan nama yang DI-IMPOR (`use ...` + alias).
 *   2. Kumpulkan nama class yang benar-benar ada di folder yang sama dengan
 *      file itu -- TIDAK folder induk, karena PHP tidak mengitu.
 *   3. Ambil semua nama yang muncul sebagai `Foo::` atau `new Foo`.
 *   4. Kalau sebuah nama dipanggil, tidak ter-import, tidak ada di folder
 *      yang sama, bukan class bawaan PHP, DAN file-nya ada di `app/` --
 *      itu dilaporkan.
 *
 * Tanpa poin (2) yang ketat, detector ini akan membiarkan apa yang seharusnya
 * ditangkap: `CatalogResource` ada di `app/Filament/User/Resources/CatalogResource/`,
 * sementara file yang memanggilnya ada di
 * `app/Filament/User/Resources/HistoryResource/`. Folder berbeda, jadi tidak
 * bisa di-resolve.
 */

$root = 'D:/WeddingFlowersDecorationApp/Mobile-And-Backend';

// Nama yang boleh muncul tanpa import (class bawaan PHP, alias global).
$builtin = [
    'self', 'static', 'parent', 'PHP_VERSION_ID', 'PHP_EOL', 'PHP_INT_MAX',
    'String', 'Array', 'Object', 'Int', 'Float', 'Bool', 'Null', 'Void', 'Mixed',
    'Never', 'Iterable', 'Callable', 'False', 'True',
    'Exception', 'Error', 'TypeError', 'ValueError', 'ArgumentCountError',
    'ArithmeticError', 'DivisionByZeroError', 'JsonException', 'LogicException',
    'RuntimeException', 'InvalidArgumentException', 'OutOfBoundsException',
    'Throwable', 'Traversable', 'Countable', 'Iterator', 'IteratorAggregate',
    'ArrayAccess', 'JsonSerializable', 'Stringable',
    'Closure', 'Generator', 'Fiber',
    'DateTime', 'DateTimeImmutable', 'DateInterval', 'DateTimeZone', 'DateTimeInterface',
    'Attribute', 'Deprecated', 'Override', 'AllowDynamicProperties',
    'SensitiveParameter', 'ReturnTypeWillChange', 'WeakMap', 'WeakReference',
    'UnitEnum', 'BackedEnum', 'Enum', 'NumberFormatter', 'Str', 'Arr',
];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);

$problems = [];
$checked = 0;

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $checked++;
    $path = $file->getPathname();
    $src = file_get_contents($path);
    $rel = str_replace($root . '/', '', $path);

    // (2) Class di folder yang SAMA persis dengan file ini.
    $siblingDir = dirname($path);
    $available = [];

    foreach (glob($siblingDir . '/*.php') ?: [] as $sibling) {
        $available[basename($sibling, '.php')] = true;
    }

    // (1) Import, termasuk alias.
    preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $src, $uses, PREG_SET_ORDER);

    foreach ($uses as $u) {
        $fqn = $u[1];
        $alias = isset($u[2]) && $u[2] !== '' ? $u[2] : substr(strrchr('\\' . $fqn, '\\'), 1);
        $available[$alias] = true;
    }

    // (3) Nama yang dipanggil: static call dan `new`.
    preg_match_all('/(?<![\\\\A-Za-z0-9_$>\-\\\\])([A-Z][A-Za-z0-9_]*)\s*::/', $src, $staticCalls);
    preg_match_all('/(?<![\\\\A-Za-z0-9_$])new\s+([A-Z][A-Za-z0-9_]*)\s*\(/', $src, $news);

    $called = array_unique(array_merge($staticCalls[1], $news[1]));

    foreach ($called as $name) {
        if (isset($available[$name]) || in_array($name, $builtin, true)) {
            continue;
        }

        // (4) Hanya lapor kalau file-nya benar-benar ada di app/.
        if (! file_exists($root . '/app/' . str_replace('\\', '/', $name) . '.php')) {
            continue;
        }

        // Cari nomor baris supaya hasilnya bisa langsung dibuka.
        $line = 0;
        $lines = explode("\n", $src);

        foreach ($lines as $i => $l) {
            if (preg_match('/(?<![\\\\A-Za-z0-9_$>\-\\\\])' . preg_quote($name, '/') . '\s*::/', $l)) {
                $line = $i + 1;
                break;
            }
        }

        $problems[] = sprintf('%-90s baris %-4d %s', $rel, $line, $name);
    }
}

printf("File diperiksa: %d\n\n", $checked);

if ($problems === []) {
    echo "OK -- tidak ada kelas milik project yang dipanggil tanpa import.\n";
    exit(0);
}

printf("Ditemukan %d. Tiap baris: file, nomor baris, nama class.\n\n%s\n", count($problems), implode("\n", $problems));
exit(1);