<?php

namespace App\Channels\FcmChannel;

use App\Services\FirebaseService\FirebaseService;
use App\Support\NotificationSound\NotificationSound;
use App\Models\User\User;
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

        // Channel mengikuti toggle suara user (ber-suara / hening) --
        // notifiable non-User (mis. model lain) jatuh ke default ber-suara.
        $androidConfig = [];
        if ($notifiable instanceof User) {
            $androidConfig['channel_id'] = NotificationSound::channelIdFor($notifiable);
        }

        $this->firebaseService->sendPushNotification($token, $title, $body, $payload, $androidConfig);
    }
}
