<?php

namespace App\Support\QrisImage;

use App\Support\AppPlatform\AppPlatform;

/**
 * Satu sumber kebenaran untuk gambar QRIS merchant toko.
 *
 * Gambar yang dipakai adalah berkas statis
 * `public/images/qris/qris.jpg` (QRIS GPN "TIMELESS FLORALS - FLORIST SHOP",
 * NMID ID1026626838399). Sebelumnya setiap view mengira gambar QRIS tersimpan
 * di kolom `payment_methods.image_url`, padahal kolom itu NULL -> QRIS tidak
 * pernah muncul di halaman checkout, hanya badge "Gambar QRIS tidak tersedia".
 *
 * Kenapa URL-nya dilewatkan `AppPlatform::normalizeUrl()`:
 * disk `public` sengaja memakai URL relatif (`MEDIA_URL=/storage`), dan
 * `APP_URL` bisa berisi domain tunnel yang sudah mati. Kalau URL absolut
 * dibiarkan apa adanya, gambar akan ditolak di panel lokal / IP LAN /
 * aplikasi HP. `normalizeUrl()` menulis ulang host-nya ke origin request
 * yang sedang berjalan, dan mengubah path relatif menjadi absolut.
 */
class QrisImage
{
    /** Path gambar relatif terhadap folder `public/`. */
    public const RELATIVE_PATH = 'images/qris/qris.jpg';

    /**
     * Lokasi fisik berkas di disk.
     */
    public static function path(): string
    {
        return public_path(self::RELATIVE_PATH);
    }

    /**
     * Apakah gambar QRIS benar-benar ada di server?
     */
    public static function exists(): bool
    {
        return is_file(static::path());
    }

    /**
     * URL gambar yang bisa dipakai `<img src>` di panel maupun aplikasi HP.
     *
     * Null kalau berkasnya tidak ada: lebih baik badge "tidak tersedia"
     * daripada `<img>` yang menunjuk 404.
     */
    public static function url(): ?string
    {
        if (! static::exists()) {
            return null;
        }

        return AppPlatform::normalizeUrl(asset(self::RELATIVE_PATH));
    }
}
