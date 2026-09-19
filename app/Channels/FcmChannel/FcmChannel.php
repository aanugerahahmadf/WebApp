<?php

namespace App\Channels\FcmChannel;

use App\Services\FirebaseService\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    public function __construct(
        protected FirebaseService $firebaseService
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $token = $notifiable->fcm_token) {
            return;
        }

        $data = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : [];

        $title = $data['title'] ?? 'Notifikasi';
        $body = $data['body'] ?? '';
        $payload = $data['data'] ?? [];

        $this->firebaseService->sendPushNotification($token, $title, $body, $payload);
    }
}
