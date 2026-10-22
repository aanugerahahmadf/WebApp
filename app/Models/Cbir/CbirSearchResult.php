<?php

namespace App\Models\Cbir;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbirSearchResult extends Model
{
    use HasFactory;

    protected $table = 'cbir_search_results';

    protected $fillable = [
        'search_id',
        'type',
        'item_id',
        'similarity',
        'score',
    ];

    protected $casts = [
        'search_id' => 'integer',
        'item_id' => 'integer',
        'similarity' => 'float',
        'score' => 'float',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(CbirSearch::class, 'search_id');
    }

    public function item(): BelongsTo
    {
        if ($this->type === 'catalog') {
            return $this->belongsTo(\App\Models\Catalog\Catalog::class, 'item_id');
        }
        return $this->belongsTo(\App\Models\Package\Package::class, 'item_id');
    }
}