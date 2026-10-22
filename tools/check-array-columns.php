<?php

/**
 * Cari kolom tabel Filament yang underlying-nya di-cast jadi array.
 *
 * Bug yang dijaga: `TextColumn` me-render nilai yang sudah di-cast jadi array
 * (JSON, `'array'`, atau `collection`), lalu `{{ $formattedState }}` memanggil
 * `e()` -> `htmlspecialchars()` yang menolak array:
 *
 *     htmlspecialchars(): Argument #1 ($string) must be of type string,
 *     array given
 *
 * Gejalanya muncul jauh dari penyebabnya: `ViewException` dari dalam
 * `filament-tables/columns/text-column.blade.php`, beberapa lapis dari resource
 * yangCause-nya.
 *
 * Yang dikunci: setiap kolom yang memanggil `TextColumn::make('x')` dengan
 * `x` yang ada di `$casts` sebagai `array`/`json`/`collection`, DAN kolom itu
 * tidak punya `->formatStateUsing()` yang memaksa jadi string.
 *
 * Kolom dengan `formatStateUsing` TIDAK dilaporkan: closure itu yangnea menerima
 * array dan mengembalikan string, jadi render-nya aman -- kecuali closure-nya
 * bertipe `string` (bukan `mixed`), yang akan melempar TypeError dengan pesan
 * berbeda.
 */

$root = 'D:/WeddingFlowersDecorationApp/Mobile-And-Backend';

// 1. Kumpulkan atribut yang di-cast jadi array, per model.
$arrayCasts = [];

$models = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/Models', FilesystemIterator::SKIP_DOTS)
);

foreach ($models as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $src = file_get_contents($file->getPathname());

    if (! preg_match('/^\s*protected\s+\$casts\s*=\s*\[(.*?)\];/ms', $src, $m)) {
        continue;
    }

    $class = $file->getBasename('.php');

    if (preg_match_all("/'([A-Za-z0-9_]+)'\s*=>\s*'(array|json|collection|object)'/", $m[1], $pairs, PREG_SET_ORDER)) {
        foreach ($pairs as $pair) {
            $arrayCasts[strtolower($class)][$pair[1]] = $pair[2];
        }
    }
}

printf("Model dengan cast array/json: %d\n", count($arrayCasts));

// 2. Cari TextColumn yang menunjuk atribut itu.
$hits = [];

$resources = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/Filament', FilesystemIterator::SKIP_DOTS)
);

foreach ($resources as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $rel = str_replace($root . '/', '', $file->getPathname());
    $src = file_get_contents($file->getPathname());

    // Model yang dipakai file ini.
    preg_match_all('/^use\s+App\\\\Models\\\\([A-Za-z0-9_\\\\]+);/m', $src, $uses);

    $modelsHere = array_map(
        static fn (string $ns): string => strtolower(basename(str_replace('\\', '/', $ns))),
        $uses[1]
    );

    // Setiap TextColumn::make('nama') -- ambil blok baris berikutnya untuk
    // melihat apakah ada formatStateUsing.
    if (! preg_match_all('/TextColumn::make\(\s*\'([A-Za-z0-9_.]+)\'/', $src, $cols, PREG_SET_ORDER)) {
        continue;
    }

    $lines = explode("\n", $src);

    foreach ($cols as $col) {
        $attr = explode('.', $col[1])[0];

        foreach ($modelsHere as $model) {
            if (! isset($arrayCasts[$model][$attr])) {
                continue;
            }

            // Cari statement lengkap kolom ini: dari `make('attr')` sampai
            // `;` di level yang sama.
            $start = 0;

            foreach ($lines as $i => $line) {
                if (str_contains($line, "TextColumn::make('{$col[1]}')")) {
                    $start = $i;
                    break;
                }
            }

            $block = '';

            for ($i = $start; $i < min($start + 25, count($lines)); $i++) {
                $block .= $lines[$i] . "\n";

                if (str_contains($lines[$i], '];') || str_ends_with(trim($lines[$i]), ',')) {
                    if (str_contains($lines[$i], '];')) {
                        break;
                    }
                }
            }

            // Pengaman yang membuat array bisa dirender: `formatStateUsing` yang
            // memaksa jadi string, atau salah satu mode list Filament.
            //
            // `limitList()` ikut di sini, bukan hanya `limit()`. Versi pertama
            // tool ini hanya mencari `->limit(`, dan itu membuat
            // `ApiKeyResource::abilities` dilaporkan sebagai pelanggaran --
            // padahal `->limitList(3)` persis yang membuat Filament memecah
            // array jadi daftar badge. Satu false positive seperti ini cukup
            // untuk membuat orang mengabaikan seluruh laporannya.
            $formatted = str_contains($block, 'formatStateUsing');

            $toList = str_contains($block, '->list(')
                || str_contains($block, '->bulleted(')
                || str_contains($block, '->limit(')
                || str_contains($block, '->limitList(')
                || str_contains($block, '->expandableLimitedList(');

            // Kolom dianggap aman kalau ia -- ATAU kolom lain dengan nama yang
            // sama di file yang sama -- punya pengaman.
            //
            // Kenapa kolom lain ikut dihitung: `ReferenceOptionResource` punya
            // DUA `TextColumn::make('label')`, masing-masing untuk locale
            // berbeda, dan keduanya memakai `->state(fn ($record) =>
            // $record->getLabelForLocale(...))`. Pemeriksaan per-blok yang hanya
            // membaca 25 baris dari kolom pertama bisa melewatkan pengaman di
            // kolom kedua dan melaporkan keduanya sebagai pelanggaran -- dua
            // false positive untuk hal yang sebenarnya benar.
            //
            // Cara kerjanya: kumpulkan dulu SEMUA kolom bernama sama di file ini,
            // lalu untuk tiap kolom cek apakah ia atau rekannya yang aman.
            $siblingsGuarded = false;

            foreach ($cols as $sibling) {
                if ($sibling[1] !== $col[1]) {
                    continue;
                }

                foreach ($lines as $i => $line) {
                    if (! str_contains($line, "TextColumn::make('{$sibling[1]}')")) {
                        continue;
                    }

                    $block = '';

                    for ($j = $i; $j < min($i + 25, count($lines)); $j++) {
                        $block .= $lines[$j] . "\n";
                    }

                    if (str_contains($block, 'formatStateUsing')
                        || str_contains($block, '->state(')
                        || str_contains($block, '->limitList(')
                        || str_contains($block, '->expandableLimitedList(')
                        || str_contains($block, '->list(')
                        || str_contains($block, '->bulleted(')
                        || str_contains($block, '->limit(')) {
                        $siblingsGuarded = true;
                    }
                }
            }

            if ($formatted || $toList || $siblingsGuarded) {
                continue;
            }

            // Dot notation pada atribut yang di-cast array JUGA aman, dan itu
            // pola yang disengaja.
            //
            // `TextColumn::make('value.bypass_secret')` membaca
            // `$record->value['bypass_secret']` -- key di dalam array, bukan
            // array-nya sendiri. Yang di-render selalu scalar (string atau
            // null), jadi `e()` tidak pernah menerima array. Kolomnya hanya
            // bermasalah kalau `bypass_secret` tidak pernah diisi, dan itu
            // menghasilkan null, bukan crash.
            //
            // Tanpa pengecualian ini, `MaintenanceResource` dilaporkan rusak
            // padahal `fm_app_settings.value` memang dirancang sebagai satu
            // kolom JSON yang diakses per-key.
            if (str_contains($col[1], '.')) {
                continue;
            }

            $hits[] = sprintf(
                "%s  %s::%s  [%s]",
                $rel,
                $model,
                $col[1],
                $arrayCasts[$model][$attr]
            );
        }
    }
}

if ($hits === []) {
    echo "\nOK -- tidak ada kolom TextColumn yang menunjuk atribut array-cast tanpa pengaman.\n";
    exit(0);
}

echo "\nDitemukan " . count($hits) . ":\n\n" . implode("\n", array_unique($hits)) . "\n";
exit(1);