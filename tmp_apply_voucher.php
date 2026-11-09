<?php

// Sekali-pakai untuk Voucher.php: sisipkan trait tanpa memecah byte UTF-8.
// Pemisah baris dibatasi \r\n | \n | \r saja (BUKAN \R) karena \R ikut
// mencocokkan byte 0x85 yang muncul di dalam sekuens UTF-8 multi-byte.

$path = __DIR__.'/app/Models/Voucher/Voucher.php';
$import = 'use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;';
$traitUse = '    use ResolvesCamelCaseAttributes;';

$src = file_get_contents($path);

if (str_contains($src, 'ResolvesCamelCaseAttributes')) {
    echo "sudah ada, dilewati\n";
    exit(0);
}

$eol = str_contains($src, "\r\n") ? "\r\n" : "\n";
$lines = preg_split('/\r\n|\n|\r/', rtrim($src, "\r\n"));

$classIdx = null;
foreach ($lines as $i => $line) {
    if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/', $line)) {
        $classIdx = $i;
        break;
    }
}

if ($classIdx === null) {
    fwrite(STDERR, "class tidak ditemukan\n");
    exit(1);
}

$lastUse = null;
for ($i = 0; $i < $classIdx; $i++) {
    if (preg_match('/^use\s+[A-Za-z_\\\\]/', $lines[$i])) {
        $lastUse = $i;
    }
}

if ($lastUse === null) {
    fwrite(STDERR, "use tidak ditemukan\n");
    exit(1);
}

array_splice($lines, $lastUse + 1, 0, [$import, '']);

$braceIdx = null;
for ($i = $classIdx; $i < count($lines); $i++) {
    if (str_contains($lines[$i], '{')) {
        $braceIdx = $i;
        break;
    }
}

array_splice($lines, $braceIdx + 1, 0, [$traitUse]);

file_put_contents($path, implode($eol, $lines).$eol);
echo "ok\n";
