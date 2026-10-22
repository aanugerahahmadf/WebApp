<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    use HasFactory;

    protected $table = 'refund_requests';

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'amount',
        'evidence_photos',
        'status',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'evidence_photos' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class);
    }
}