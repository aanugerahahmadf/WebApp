<?php

namespace App\Helpers\NativeNotificationHelper;

use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Native\Laravel\Notification;

class NativeNotificationHelper
{
    /**
     * Tampilkan notifikasi native jika berjalan di perangkat mobile
     */
    public static function show(string $title, string $message): void
    {
        if (app()->environment('mobile') || NativeServiceProvider::isNativeMobile()) {
            Notification::new()
                ->title($title)
                ->message($message)
                ->show();
        }
    }

    /**
     * Helper untuk notifikasi sukses standar
     */
    public static function success(string $message): void
    {
        self::show(__('Berhasil!'), $message);
    }

    /**
     * Helper untuk notifikasi info/pesanan
     */
    public static function info(string $title, string $message): void
    {
        self::show($title, $message);
    }
}
