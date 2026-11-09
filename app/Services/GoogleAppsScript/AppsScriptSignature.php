<?php

namespace App\Services\GoogleAppsScript;

/**
 * Kanonisasi payload + HMAC untuk Google Apps Script.
 *
 * Aturan tanda tangan HARUS identik dengan `Auth.gs` di sisi Apps Script dan
 * dengan klien Flutter/Kotlin/AI Core:
 *
 *     canonical = timestamp . "." . nonce . "." . action . "." . canonicalJson(payload)
 *     signature = strtolower(hex2sha256_hmac(canonical, secret))
 *
 * `canonicalJson` = JSON tanpa spasi dengan kunci objek diurutkan naik secara
 * rekursif. Kelas ini adalah satu-satunya tempat aturan itu hidup di PHP supaya
 * tidak ada dua implementasi yang bisa lari bersamaan.
 *
 * Konvensi payload yang wajib diikuti pemanggil:
 *  - nominal uang dan id besar dikirim sebagai STRING ("150000"), bukan float;
 *  - float non-integral akan dinormalisasi ke repr terpendek dan bisa berbeda
 *    dengan JS, jadi hindari;
 *  - array PHP kosong menjadi `{}` (objek), bukan `[]`: payload selalu dibaca
 *    Apps Script sebagai objek, dan klien mengirim `payload` dengan cast
 *    `(object)`, sehingga keduanya harus kanonis sama. Daftar yang memang
 *    kosong lebih baik dikirim sebagai `null`.
 *  - kunci objek sebaiknya ASCII snake_case (Apps Script mengurutkan by code
 *    unit, PHP SORT_STRING by byte; keduanya sama untuk ASCII).
 */
class AppsScriptSignature
{
    public static function canonicalJson(mixed $value): string
    {
        return (string) json_encode(
            self::prepare($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * Susun ulang payload: kunci terurut, float integral -> int, tipe terjaga.
     */
    public static function prepare(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            if (is_bool($value) || is_null($value) || is_string($value) || is_int($value)) {
                return $value;
            }

            if (is_float($value)) {
                // 150000.0 -> 150000 agar String() di JS dan json_encode di PHP
                // menghasilkan teks yang sama.
                return $value === floor($value) && abs($value) < PHP_INT_MAX ? (int) $value : $value;
            }

            return (string) $value;
        }

        // Array PHP kosong bersifat ambigu: [] sekaligus array() . Apps Script
        // membaca body sebagai objek, dan `payload` kosong dikirim sebagai `{}`
        // (lihat cast `(object)` di GoogleAppsScriptClient). Jadi kanonisasi
        // HARUS menghasilkan '{}' juga, kalau tidak tanda tangan tidak cocok.
        if ($value === []) {
            return new \stdClass;
        }

        if (array_is_list($value)) {
            return array_map(self::prepare(...), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(self::prepare(...), $value);
    }

    /**
     * String yang ditandatangani, tanpa HMAC. Dipakai juga oleh test dan
     * `--dry-run` supaya payload bisa dibandingkan dengan `auth.sampleSignature`.
     */
    public static function canonicalString(int $timestamp, string $nonce, string $action, mixed $payload): string
    {
        return implode('.', [
            (string) $timestamp,
            $nonce,
            $action,
            self::canonicalJson($payload),
        ]);
    }

    public static function sign(int $timestamp, string $nonce, string $action, mixed $payload, string $secret): string
    {
        return hash_hmac('sha256', self::canonicalString($timestamp, $nonce, $action, $payload), $secret);
    }
}
