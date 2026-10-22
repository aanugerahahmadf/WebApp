<?php

namespace App\Support\PdfLayout;

use Carbon\Carbon;
use Throwable;

/**
 * PRIMITIF TEKS PDF: tanggal dan nama berkas.
 *
 * Dua hal yang sama-sama soal "dokumen resmi tidak boleh menampilkan nilai
 * mentah":
 *
 * 1. TANGGAL. Format `d/m/Y` menghasilkan "21/10/2026" -- garis miring
 *    di dokumen resmi terasa seperti mentah. Format panjang Indonesia
 *    ("21 Oktober 2026, 09.12") jauh lebih enak dibaca.
 *
 * 2. NAMA FILE LAMPIRAN. URL lampiran penuh dengan garis miring dan
 *    garis bawah (`/media/avatars/1_1792351644_Onx6Q.jpg`). Yang
 *    ditampilkan ke pembaca cukup nama filenya, tanpa garis miring.
 *
 * Untuk judul kolom dan nilai sel.exports, pakalah
 * {@see \App\Support\ExportText\ExportText} -- satu sumber untuk XLSX
 * dan PDF sekaligus. Klasanya di sini sengaja hanya PDF, supaya tidak
 * ada dua implementasi aturan yang sama.
 */
class PdfText
{

    /**
     * Tanggal/waktu -> "21 Oktober 2026, 09.12".
     *
     * Tanpa garis miring dan tanpa garis bawah, dan berbahasa Indonesia
     * supaya konsisten dengan isi dokumen.
     */
    public static function date(mixed $value, string $fallback = '-'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        try {
            $date = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return $fallback;
        }

        return $date->locale('id')->translatedFormat('d F Y, H.i');
    }

    /**
     * URL lampiran -> nama file yang enak dibaca.
     *
     * Buang path-nya (dan garis miringnya), lalu buang prefix penyimpanan
     * (`{id}_{timestamp}_`) yang ditambahkan Filament saat mengunggah.
     * Sisa garis bawah diganti spasi, jadi tidak ada "_" pun di dokumen.
     */
    public static function fileName(string $url, string $fallback = 'Lampiran'): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $url;
        }

        $name = basename($path);
        $name = rawurldecode($name);

        // Path yang berakhiran "/" itu DIREKTORI, bukan file -- jadi
        // tidak ada nama file untuk ditampilkan.
        if ($name === '' || $name === '.' || $name === '/' || str_ends_with($path, '/')) {
            return $fallback;
        }

        // Prefix penyimpanan Filament: {idPengguna}_{timestamp}_nama-asli.
        $name = preg_replace('/^\d+_\d+_/', '', $name) ?? $name;

        // Sisa garis bawah diganti spasi supaya dokumen bebas "_".
        $name = str_replace('_', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name !== '' ? $name : $fallback;
    }
}
