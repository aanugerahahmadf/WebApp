<?php

namespace App\Models\History;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class History extends Model
{
    use LegacyMorphClass;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'transaction_id',
        'reference_number',
        'amount',
        'info',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
