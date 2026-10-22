<?php

namespace App\Support\ExportText;

use App\Support\PdfLayout\PdfText;
use Carbon\Carbon;
use Throwable;

/**
 * TEKS DOKUMEN EKSPOR -- satu-satunya tempat yang membersihkan teks.
 *
 * Aturan mainannya satu, dan berlaku untuk XLSX maupun PDF:
 *
 *   TIDAK ADA "_" DAN TIDAK ADA "/" DI DALAM TEKS MANA PUN.
 *
 * Kenapa dua karakter itu, dan kenapa harus diurus di satu tempat:
 *
 *   - "_" dan "/" adalah tanda nilai mentah. `full_name`, `created_at`,
 *     `/media/avatars/1_1792_Onx6Q.jpg` -- semuanya mentah. Dokumen resmi
 *     (dan laporan analisis) harusnya menampilkan "Full Name", "Created At",
 *     "Onx6Q.jpg".
 *
 *   - Kalau tiap generator membersihkan sendiri, hasilnya pasti berbeda
 *     antar berkas: PDF rapi, Excel mentah (atau sebaliknya). Satu helper
 *     untuk keduanya = keduanya tidak bisa berbeda pendapat.
 *
 * besides itu, tiga hal lain yang ikut dijaga:
 *
 *   1. TANGGAL -> "21 Oktober 2026, 09.12" (bukan "2026-10-21 09:12:00",
 *      dan tentu bukan "21/10/2026" yang mengandung "/").
 *
 *   2. JSON -> "kunci: nilai; kunci: nilai". Kolom `meta`, `config`,
 *      `faqs`, `data` menyimpan JSON mentah -- yang isinya penuh "{" dan
 *      "/" dan tidak bisa dibaca manusia.
 *
 *   3. TEKS TIDAK DIPOTONG. Nilai panjang (deskripsi, catatan, alamat)
 *      ditampilkan UTUH. Memotong isi cuma bikin data hilang; di PDF teks
 *      panjang diperbolehkan membungkus ke baris berikutnya.
 */
class ExportText
{
    /**
     * Singkatan yang tetap huruf kapital saat judul kolom dirapikan.
     *
     * @var list<string>
     */
    public const ACRONYMS = [
        'id', 'url', 'uri', 'api', 'ktp', 'npwp', 'sim', 'qr', 'pdf', 'csv',
        'xlsx', 'sla', 'faq', 'vip', 'kpi', 'uuid', 'otp', 'sso', 'gdpr',
    ];

    /**
     * Judul kolom database -> judul yang enak dibaca, bebas "_" dan "/".
     *
     * `full_name` -> "Full Name", `created_at` -> "Created At",
     * `ktp_photo_url` -> "KTP Photo URL", `id` -> "ID".
     *
     * Kalau input-nya SUDAH berupa label ("Nama Lengkap", "IP Login
     * Terakhir"), kapitalisasinya dihormati -- hanya "_" dan "/" yang
     * dibersihkan. Menyamakan ulang jadi Title Case akan merusak singkatan
     * ("IP" -> "Ip").
     */
    public static function heading(string $column): string
    {
        $column = trim($column);

        if ($column === '') {
            return '-';
        }

        // Sudah label? (ada huruf kapital dan tidak ada "_" / "/")
        if (! preg_match('/[_\/]/', $column) && preg_match('/[A-Z]/', $column)) {
            return self::clean($column);
        }

        $words = preg_split('/[_\-\/\.\s]+/', $column, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '-';
        }

        $out = [];

        foreach ($words as $word) {
            $lower = mb_strtolower($word);

            $out[] = in_array($lower, self::ACRONYMS, true)
                ? mb_strtoupper($word)
                : mb_strtoupper(mb_substr($word, 0, 1)).mb_strtolower(mb_substr($word, 1));
        }

        return implode(' ', $out);
    }

    /**
     * Nilai sel -> teks yang layak masuk dokumen.
     *
     * Tidak pernah memotong isi. Sel kosong -> "-".
     */
    public static function value(mixed $value, string $fallback = '-'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value ? __('Ya') : __('Tidak');
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return PdfText::date($value, $fallback);
        }

        if (is_array($value)) {
            return self::pairs($value, $fallback);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                /** @var array<string, mixed> $data */
                $data = $value->toArray();

                return self::pairs($data, $fallback);
            }

            return self::clean((string) $value, $fallback);
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return $fallback;
        }

        // Tanggal ISO ("2026-10-21" / "2026-10-21 09:12:00") -> format
        // Indonesia panjang. Dilakukan SEBELUM pembersihan umum, karena
        // "//" dan ":" di tanggal akan jadi berantakan kalau dibersihkan
        // sebagai teks biasa.
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $raw)) {
            try {
                return PdfText::date(Carbon::parse($raw), $fallback);
            } catch (Throwable) {
                // Bukan tanggal yang bisa dibaca -- falls through ke pembersihan biasa.
            }
        }

        // JSON -> "kunci: nilai; kunci: nilai".
        if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return self::pairs($decoded, $fallback);
            }
        }

        // URL / path berkas -> nama berkas (tanpa garis miring).
        if (self::looksLikePath($raw)) {
            return self::pathLabel($raw, $fallback);
        }

        // Label mentah ("super_admin", "paket-dekorasi") -> Title Case.
        // Cuma untuk yang isinya HURUF SAJA -- "INV-2026-001" adalah kode,
        // bukan label, dan tidak boleh jadi "Inv 2026 001".
        if (self::looksLikeIdentifier($raw)) {
            return self::heading($raw);
        }

        return self::clean($raw, $fallback);
    }

    /**
     * Apakah string ini identifier/label mentah?
     *
     * Syaratnya: ada pemisah (`_`, `-`, `/`), tidak ada spasi (jadi bukan
     * kalimat), dan isinya huruf saja (jadi bukan kode seperti
     * "INV-2026-001").
     */
    protected static function looksLikeIdentifier(string $value): bool
    {
        if (str_contains($value, ' ')) {
            return false;
        }

        if (! preg_match('/[_\-\/]/', $value)) {
            return false;
        }

        // Hanya huruf + pemisah. Ada angkanya -> itu kode, bukan label.
        return (bool) preg_match('/^[A-Za-z_\-\/]+$/', $value);
    }

    /**
     * Pembersihan inti: "_" dan "/" jadi spasi, spasi dirapatkan.
     *
     * Ini FUNGSI yang dijanjikan "tidak ada underscore dan tidak ada
     * slash" -- semua jalur lain (heading, JSON, URL) berakhir di sini.
     */
    public static function clean(string $text, string $fallback = '-'): string
    {
        // Garis miring DAN garis bawah diganti spasi. Satu operasi, dua
        // karakter -- supaya tidak ada satu pun yang bisa lolos.
        $text = str_replace(['_', '/'], ' ', $text);

        // Backslash (dipakai di path Windows) ikut dirapikan.
        $text = str_replace('\\', ' ', $text);

        // Spasi berlebih dirapatkan.
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return $fallback;
        }

        // Sisa karakter "~" (pembatas nama kolom export lama) juga dibuang
        // supaya judul kolom tidak pernah memuat "~".
        return str_replace('~', ' ', $text);
    }

    /**
     * Array (atau JSON) -> "kunci: nilai; kunci: nilai".
     *
     * @param  array<array-key, mixed>  $data
     */
    protected static function pairs(array $data, string $fallback = '-'): string
    {
        $parts = [];

        foreach ($data as $key => $value) {
            $label = self::heading((string) $key);
            $clean = is_array($value)
                ? self::pairs($value, $fallback)
                : self::value($value, $fallback);

            $parts[] = $label.': '.$clean;
        }

        if ($parts === []) {
            return $fallback;
        }

        return self::clean(implode('; ', $parts), $fallback);
    }

    /**
     * Apakah string ini URL atau path berkas?
     */
    protected static function looksLikePath(string $value): bool
    {
        if (str_starts_with($value, '/') || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return true;
        }

        // Path relatif yang jelas berkas: "avatars/1_2_x.jpg", "media/x.png".
        return (bool) preg_match('#^[^\s:]+\/[^\s/]+\.[a-z0-9]{2,5}$#i', $value);
    }

    /**
     * URL / path -> label yang enak dibaca tanpa garis miring.
     *
     * Berkas -> nama berkasnya ("Onx6Q.jpg"). Halaman web -> host-nya
     * ("example.com"). Keduanya bebas "/" dan "_".
     */
    protected static function pathLabel(string $value, string $fallback = '-'): string
    {
        $path = parse_url($value, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $name = PdfText::fileName($path, '');

            if ($name !== '') {
                return self::clean($name, $fallback);
            }
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return self::clean($host, $fallback);
        }

        return self::clean($value, $fallback);
    }
}
