<?php

/**
 * Perbaiki mojibake UTF-8 yang salah-decode lewat Windows-1252.
 *
 * POLA KENAPA INI TERJADI: file ditulis UTF-8, lalu dibaca sebagai Windows-1252
 * dan ditulis lagi sebagai UTF-8. Setiap byte >= 0x80 jadi satu karakter
 * Windows-1252, jadi byte `E2 80 94` (tanda hubung em-dash) tampil sebagai tiga
 * karakter `â€"`.
 *
 * ARAH PEMBALIKANNYA HANYA SATU LANGKAH:
 *
 *     iconv('UTF-8', 'Windows-1252', $teks)  ->  byte UTF-8 yang asli
 *
 * Hasilnya sudah berupa UTF-8 yang benar, jadi TIDAK ada langkah "decode lagi".
 * Versi pertama tool ini menambahkan `mb_convert_encoding($bytes, 'UTF-8',
 * 'Windows-1252')` di antaranya -- dan langkah itu justru membatalkan
 * perbaikannya, karena ia mengubah byte yang sudah benar kembali menjadi
 * mojibake. Gejalanya jelas: tool melaporkan "tidak ada yang bisa diubah"
 * padahal ada 13 file rusak.
 *
 * KENAPA WINDOWS-1252, BUKAN ISO-8859-1:
 *
 * Karakter mojibake yang paling sering muncul justru TIDAK ada di latin-1:
 * `€` (U+20AC) untuk byte 0x80, dan `”` (U+201D) untuk byte 0x94. Di
 * ISO-8859-1 keduanya menjadi `?`, jadi byte aslinya hilang permanen dan tidak
 * bisa dipulihkan.
 *
 * KENAPA BERJALAN BERAPA KALI:
 *
 * File yang rusak beberapa kali akan menumpuk: `—` -> `â€"` -> `Ã¢â‚¬â„¢`.
 * Satu putaran hanya membongkar lapisan terluar, jadi tool ini mengulang sampai
 * tidak ada perbaikan lagi (dengan batas, supaya tidak berputar tanpa henti pada
 * teks yang sudah stabil).
 *
 * KEAMANAN: satu putaran hanya dipakai kalau HASILNYA benar-benar lebih baik:
 *   1. `iconv` harus berhasil,
 *   2. hasilnya harus valid UTF-8, dan
 *   3. jumlah penanda mojibake harus lebih sedikit daripada sebelumnya.
 *
 * Kalau salah satu tidak terpenuhi, baris TIDAK diubah. Itu yang mencegah file
 * yang benar ikut rusak: teks asli Indonesia/Inggris yang hanya kebetulan punya
 * huruf beraksen tetap aman: iconv gagal, atau hasilnya tidak
 * valid UTF-8, atau penandanya tidak berkurang.
 *
 * Dipakai: `php tools/fix-mojibake.php <path> [...] [--check]`
 * Tanpa `--check`, hanya menampilkan hitungan per file. Dengan `--check`,
 * menulis perbaikannya.
 */

$checkOnly = in_array('--check', $argv, true);

$paths = array_values(array_filter($argv, static fn (string $a): bool => ! str_starts_with($a, '--')));

/**
 * Penanda yang harus hilang setelah transformasi.
 *
 * Cakupannya sengaja luas: byte sisanya dari `E2 80 94` bisa muncul sebagai
 * `â` (U+00E2), `„` (U+201E) atau `“` (U+201C) tergantung tool yang merusak
 * file; `Â` (U+00C2) untuk byte di rentang 0xC2; dan `Ã` (U+00C3) untuk lapisan
 * kedua. Rentang C1 (U+0080-U+009F) adalah karakter kontrol yang tidak pernah
 * sah di teks.
 */
$markerPattern = '/[\x{00C2}\x{00C3}\x{00E2}\x{00E3}\x{0080}-\x{009F}\x{FFFD}\x{2018}\x{2019}\x{201A}\x{201B}\x{201C}\x{201D}\x{201E}\x{20AC}]|â|Ã|Â|�/u';

$countMarkers = static fn (string $text): int => preg_match_all($markerPattern, $text);

$fixedFiles = 0;
$fixedLines = 0;

foreach ($paths as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "lewati (bukan file): {$path}\n");
        continue;
    }

    $lines = explode("\n", (string) file_get_contents($path));
    $out = $lines;
    $changed = 0;

    foreach ($lines as $index => $line) {
        $current = $line;

        // Lapisan bisa menumpuk; bongkar sampai stabil atau batas tercapai.
        for ($round = 0; $round < 4; $round++) {
            if ($countMarkers($current) === 0) {
                break;
            }

            $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $current);

            if ($candidate === false || $candidate === $current) {
                break;
            }

            if (! mb_check_encoding($candidate, 'UTF-8')) {
                break;
            }

            if ($countMarkers($candidate) >= $countMarkers($current)) {
                break;
            }

            $current = $candidate;
        }

        if ($current !== $lines[$index]) {
            $out[$index] = $current;
            $changed++;
        }
    }

    if ($changed === 0) {
        continue;
    }

    $fixedLines += $changed;
    $fixedFiles++;

    printf("%-92s %d baris\n", $path, $changed);

    if (! $checkOnly) {
        file_put_contents($path, implode("\n", $out));
    }
}

printf("\n%d file, %d baris%s\n", $fixedFiles, $fixedLines, $checkOnly ? ' (mode check, tidak ada yang ditulis)' : '');

exit(0);