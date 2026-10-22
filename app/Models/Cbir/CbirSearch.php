<?php

namespace App\Models\Cbir;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbirSearch extends Model
{
    use HasFactory;

    protected $table = 'cbir_searches';

    protected $fillable = [
        'user_id',
        'query_image_url',
        'results_count',
        'top_similarity',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'results_count' => 'integer',
        'top_similarity' => 'float',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CbirSearchResult::class, 'search_id');
    }
}