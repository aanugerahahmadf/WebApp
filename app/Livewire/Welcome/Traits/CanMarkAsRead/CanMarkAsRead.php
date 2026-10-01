<?php

namespace App\Livewire\Welcome\Traits\CanMarkAsRead;

use App\Livewire\Welcome\Traits\InteractsWithGuestIdentity\InteractsWithGuestIdentity;
use App\Models\Message\Message;

trait CanMarkAsRead
{
    use InteractsWithGuestIdentity;

    public function markAsRead(): void
    {
        $authId = $this->chatUserId();

        // A guest resolves to null only in the pathological case where the row
        // could not be read; a null id here would make whereJsonDoesntContain
        // match every message and then write null into read_by.
        if ($authId === null || ! $this->isChatParticipant($this->selectedConversation)) {
            return;
        }

        $this->selectedConversation->messages()
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
