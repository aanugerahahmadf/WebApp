<?php

namespace App\Models\SecurityEmail;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEmail extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'subject',
        'type',
        'body',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
