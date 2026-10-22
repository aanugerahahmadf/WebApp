<?php

namespace App\Models\ApiKey;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class ApiKey extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tokenable_type',
        'tokenable_id',
    ];

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
