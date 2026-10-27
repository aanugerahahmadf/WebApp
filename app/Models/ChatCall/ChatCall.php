<?php

namespace App\Models\ChatCall;

use App\Models\Inbox\Inbox;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCall extends Model
{
    protected $fillable = [
        'token', 'inbox_id', 'caller_user_id', 'callee_user_id', 'status',
        'offer', 'answer', 'ice_candidates', 'expires_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'offer' => 'array',
            'answer' => 'array',
            'ice_candidates' => 'array',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function inbox(): BelongsTo { return $this->belongsTo(Inbox::class); }
    public function caller(): BelongsTo { return $this->belongsTo(User::class, 'caller_user_id'); }
    public function callee(): BelongsTo { return $this->belongsTo(User::class, 'callee_user_id'); }
}
