<?php

namespace App\Models\Cbir;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbirFeedback extends Model
{
    use HasFactory;

    protected $table = 'cbir_feedback';

    protected $fillable = [
        'user_id',
        'search_id',
        'item_id',
        'item_type',
        'is_relevant',
        'feedback',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'search_id' => 'integer',
        'item_id' => 'integer',
        'is_relevant' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class);
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(CbirSearch::class, 'search_id');
    }
}