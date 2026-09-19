<?php

namespace App\Models\WhatsappOtp;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappOtp extends Model
{
    use LegacyMorphClass;
    use HasFactory;

    protected $fillable = [
        'whatsapp',
        'otp_code',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
