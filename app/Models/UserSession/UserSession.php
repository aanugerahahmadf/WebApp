<?php

namespace App\Models\UserSession;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'ip_address',
        'user_agent',
        'platform',
        'is_current',
        'last_active_at',
        'expired_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'last_active_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
