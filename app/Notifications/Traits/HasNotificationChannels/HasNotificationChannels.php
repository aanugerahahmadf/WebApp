<?php

namespace App\Notifications\Traits\HasNotificationChannels;

use Illuminate\Bus\Queueable;

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

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function icon(): string;

    abstract protected function color(): string;
}
