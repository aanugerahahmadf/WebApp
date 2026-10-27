<?php

namespace App\Models\SecurityEvent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User\User;

class SecurityEvent extends Model
{
    protected $fillable = [
        'event', 'method', 'path', 'status', 'ip_address',
        'user_agent', 'user_id', 'admin_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'status' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
