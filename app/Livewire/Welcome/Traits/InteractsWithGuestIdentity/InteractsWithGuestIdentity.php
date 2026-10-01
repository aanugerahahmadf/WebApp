<?php

namespace App\Livewire\Welcome\Traits\InteractsWithGuestIdentity;

use App\Models\User\User;
use App\Services\GuestIdentity\GuestIdentity;

/**
 * Chat helpers for components that run on the public storefront.
 *
 * The welcome panel's Messages pages serve signed-in visitors and guests alike,
 * so nothing in them may reach for Auth::id() directly -- for a guest that is
 * null, and null silently becomes "no conversations", "nobody's messages", or
 * a row saved with user_id null that the foreign key then rejects. Every
 * identity lookup goes through these instead.
 *
 * Scoped to the welcome copies on purpose: the user panel's messages stay
 * behind its own Authenticate middleware, where Auth::id() is the whole story.
 */
trait InteractsWithGuestIdentity
{
    /**
     * The account this browser acts as in a conversation, or null if neither a
     * signed-in visitor nor a guest row could be resolved.
     */
    public function chatUser(): ?User
    {
        return app(GuestIdentity::class)->user();
    }

    public function chatUserId(): ?int
    {
        return app(GuestIdentity::class)->id();
    }

    public function isChatGuest(): bool
    {
        return app(GuestIdentity::class)->isGuest();
    }

    /**
     * Whether this browser is a participant on the given conversation.
     *
     * The one check every conversation read and write has to pass. Guests make
     * it load-bearing in a way signed-in users never did: conversation ids are
     * sequential integers, so an unchecked find() lets a stranger walk the ids
     * and read everybody's support threads.
     */
    public function isChatParticipant(mixed $inbox): bool
    {
        $userId = $this->chatUserId();

        if (! $inbox || $userId === null) {
            return false;
        }

        return in_array($userId, array_map('intval', (array) $inbox->user_ids), true);
    }
}
