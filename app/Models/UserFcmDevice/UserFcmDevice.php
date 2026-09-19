<?php

namespace App\Models\UserFcmDevice;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmDevice extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'device_name',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
