<?php

namespace App\Models\Review;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    use HasFactory;

    protected $table = 'review_reports';

    protected $fillable = [
        'review_id',
        'reporter_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'review_id' => 'integer',
        'reporter_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Review\Review::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class, 'reporter_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class, 'reviewed_by');
    }
}