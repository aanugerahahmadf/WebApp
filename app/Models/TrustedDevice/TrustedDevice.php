<?php

namespace App\Models\TrustedDevice;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'device_name',
        'device_fingerprint',
        'platform',
        'trusted_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
