<?php

namespace App\Livewire\Admin\Traits\CanMarkAsRead;

use App\Models\Message\Message;
use App\Support\ChatStaff\ChatStaff;

trait CanMarkAsRead
{
    public function markAsRead(): void
    {
        // `Auth::id()` di panel admin adalah admins.id, bukan users.id yang
        // disimpan di `read_by` -- memakai itu membuat centang "sudah dibaca"
        // tidak pernah muncul. Identitas chat panel admin = staf (ChatStaff).
        $authId = ChatStaff::adminUser()?->getKey();

        if ($authId === null) {
            return;
        }

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
