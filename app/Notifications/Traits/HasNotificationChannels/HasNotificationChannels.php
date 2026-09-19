<?php

namespace App\Notifications\Traits\HasNotificationChannels;

use App\Channels\NativePHPChannel\NativePHPChannel;
use Illuminate\Bus\Queueable;
use Native\Laravel\Notification;
use Native\Mobile\Dialog;

trait HasNotificationChannels
{
    use Queueable;

    public array $actionUrl = [];

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        if (class_exists(Notification::class) || class_exists(Dialog::class)) {
            $channels[] = NativePHPChannel::class;
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'action_url' => $this->actionUrl,
            'type' => static::class,
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toNativePHP($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => strip_tags($this->body()),
        ];
    }

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function icon(): string;

    abstract protected function color(): string;
}
