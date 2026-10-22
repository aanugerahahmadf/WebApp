<?php

namespace App\Support\AvatarUrl;

use App\Support\AppPlatform\AppPlatform;

/**
 * Satu-satunya tempat yang menerjemahkan nilai mentah kolom `avatar_url`
 * menjadi URL yang bisa ditampilkan.
 *
 * Nilai di database bisa berupa tiga bentuk:
 *
 *   1. Path relatif          `avatars/1_x.jpg` atau `storage/avatars/1_x.jpg`
 *   2. URL absolut ke host ini  `https://host/media/avatars/1_x.jpg`
 *   3. URL ke layanan pihak ketiga `https://i.pravatar.cc/300?u=andi`
 *
 * Bentuk 1 -> `url('media/...')`, yaitu origin server yang sedang
 * melayani request. Server yang sama melayani website, panel lokal, IP
 * LAN, dan jendela desktop -- jadi file-nya selalu ketemu.
 *
 * Bentuk 2 -> dipindahkan ke origin request yang sedang berjalan
 * (AppPlatform::normalizeUrl). Tanpa itu, aplikasi desktop -- yang
 * menjangkau server lewat `http://127.0.0.1:8000` -- akan meminta file
 * ke host produksi, dan avatarnya tampil sebagai gambar rusak.
 *
 * Bentuk 3 -> dibiarkan apa adanya. File-nya memang bukan milik server
 * ini. Tapi paket CSP di shell Electron harus mengizinkan gambar dari
 * luar, kalau tidak bentuk ini juga jadi gambar rusak.
 *
 * Kenapa helper, bukan accessor di masing-masing model:
 *
 * User dan Admin punya aturan yang sama, dan aturan ini yang menentukan
 * apakah avatar tampil atau tidak. Dua salinan berarti satu model bisa
 * diam-diam berbeda dari yang lain -- dan gejalanya cuma "avatarnya
 * rusak di satu tempat".
 */
class AvatarUrl
{
    /**
     * URL avatar, atau null kalau tidak ada avatar yang bisa ditampilkan.
     *
     * @param  string|null  $value  Nilai mentah kolom `avatar_url`.
     * @param  string|null  $fallbackPath  Path alternatif (kolom `avatar`),
     *                                    dipakai kalau `$value` kosong.
     */
    public static function for(?string $value, ?string $fallbackPath = null): ?string
    {
        $path = $value ?: $fallbackPath;

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return AppPlatform::normalizeUrl($path);
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');

        if ($cleanPath === 'avatar.png' && ! file_exists(storage_path('app/public/avatar.png'))) {
            return null;
        }

        return url('media/'.$cleanPath);
    }
}
