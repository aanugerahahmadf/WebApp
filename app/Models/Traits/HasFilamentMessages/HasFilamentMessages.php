<?php

namespace App\Models\Traits\HasFilamentMessages;

use App\Models\Inbox\Inbox;
use Illuminate\Database\Eloquent\Builder;

trait HasFilamentMessages
{
    /**
     * @return Builder
     */
    public function allConversations()
    {
        return Inbox::query()->whereJsonContains('user_ids', $this->id, 'and', false)->orderBy('updated_at', 'desc');
    }
}
