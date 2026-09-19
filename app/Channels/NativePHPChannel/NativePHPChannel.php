<?php

namespace App\Channels\NativePHPChannel;

use Illuminate\Notifications\Notification;
use Native\Mobile\Dialog;

class NativePHPChannel
{
    public function send($notifiable, Notification $notification): void
    {
        $data = method_exists($notification, 'toNativePHP')
            ? $notification->toNativePHP($notifiable)
            : [];

        $title = $data['title'] ?? 'Notifikasi';
        $body = $data['body'] ?? '';

        try {
            if (class_exists(\Native\Laravel\Notification::class)) {
                \Native\Laravel\Notification::new()
                    ->title($title)
                    ->message($body)
                    ->show();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if (class_exists(Dialog::class)) {
                Dialog::toast($body, 'long');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
