<?php

namespace App\Models\PaymentGateway;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'name',
        'code',
        'description',
        'config',
        'is_active',
    ];

    protected $casts = [
        'config' => 'json',
        'is_active' => 'boolean',
    ];
}
