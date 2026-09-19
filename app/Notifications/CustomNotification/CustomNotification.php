<?php

namespace App\Notifications\CustomNotification;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $type = 'admin'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => [
                'type' => $this->type,
                'route' => $this->resolveRoute(),
                'id' => $notifiable->id ?? null,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
        ];
    }

    protected function resolveRoute(): string
    {
        return match ($this->type) {
            'order', 'payment' => '/orders',
            'message', 'chat' => '/chat',
            'review' => '/my-reviews',
            'promo', 'voucher' => '/vouchers',
            default => '/notifications',
        };
    }
}
