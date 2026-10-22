<?php

namespace App\Support\NotificationSound;

use App\Models\User\User;

/**
 * Sumber tunggal "channel FCM mana yang dipakai per user", berdasarkan
 * toggle Suara di panel (users.notification_preferences["sound"]).
 *
 * Android tidak bisa membisukan channel yang sudah dibuat -- aplikasi HP
 * punya pasangan channel ber-suara dan hening (lihat
 * mobile_app/lib/core/services/notification_service). Backend yang
 * memilih kirim ke channel mana sesuai preferensi user, sehingga user
 * yang mematikan suara tetap hening walau app HP sedang ditutup.
 */
class NotificationSound
{
    /** Channel ber-suara umum -- sinkron dengan channel app (v2 + chime). */
    public const SOUNDING = 'wedding_notifications_v2';

    /** Pasangan hening dari SOUNDING. */
    public const SOUNDING_SILENT = 'wedding_notifications_silent';

    /** Channel ber-suara untuk notifikasi keamanan (verifikasi login, dst). */
    public const SECURITY = 'security_notifications_v2';

    /** Pasangan hening dari SECURITY. */
    public const SECURITY_SILENT = 'security_notifications_silent';

    /**
     * Preferensi suara user. Default ON bila belum pernah disetel
     * (baris users.lama berkolom kosong tetap berbunyi).
     */
    public static function enabledFor(User $user): bool
    {
        $prefs = $user->notification_preferences;

        if (! is_array($prefs) || ! array_key_exists('sound', $prefs)) {
            return true;
        }

        return (bool) $prefs['sound'];
    }

    /**
     * Pilih channel (ber-suara / hening) untuk user & konteks yang diberikan.
     *
     * @param  string  $sounding  id channel ber-suara (mis. SECURITY untuk
     *                            notifikasi keamanan)
     */
    public static function channelIdFor(User $user, string $sounding = self::SOUNDING): string
    {
        return self::enabledFor($user) ? $sounding : self::silentVariantOf($sounding);
    }

    /**
     * Pasangan hening dari sebuah channel ber-suara. Channel tak dikenal
     * (custom dari pemanggil) dikembalikan apa adanya.
     */
    public static function silentVariantOf(string $sounding): string
    {
        return match ($sounding) {
            self::SECURITY => self::SECURITY_SILENT,
            self::SOUNDING => self::SOUNDING_SILENT,
            default => $sounding,
        };
    }
}
