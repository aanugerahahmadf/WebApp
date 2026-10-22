<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTimeline extends Model
{
    use HasFactory;

    protected $table = 'order_timelines';

    protected $fillable = [
        'order_id',
        'status',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'created_by' => 'integer',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class, 'created_by');
    }
}