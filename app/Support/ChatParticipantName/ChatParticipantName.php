<?php

namespace App\Support\ChatParticipantName;

use App\Models\User\User;

/** The single display-name rule used by every chat surface. */
final class ChatParticipantName
{
    public static function for(?User $user, string $fallback = 'Pengguna'): string
    {
        if (! $user) {
            return $fallback;
        }

        $fullName = trim((string) $user->full_name);
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) $user->username);
        return $username !== '' ? $username : $fallback;
    }
}
