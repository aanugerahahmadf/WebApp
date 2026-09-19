<?php

namespace App\Livewire\Traits\CanMarkAsRead;

use App\Models\Message\Message;
use Illuminate\Support\Facades\Auth;

trait CanMarkAsRead
{
    public function markAsRead(): void
    {
        $authId = Auth::id();

        $this->selectedConversation?->messages()
            ->whereJsonDoesntContain('read_by', $authId)
            ->get()
            ->each(function (Message $message) use ($authId): void {
                $readBy = is_array($message->read_by) ? $message->read_by : [];
                $readAt = is_array($message->read_at) ? $message->read_at : [];

                $message->update([
                    'read_by' => [...$readBy, $authId],
                    'read_at' => [...$readAt, now()->toIso8601String()],
                ]);
            });
    }
}
