<?php

namespace App\Models\Bank;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'name',
        'code',
        'type',
        'account_number',
        'account_holder',
        'logo',
        'qris_payload',
        'qris_image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
