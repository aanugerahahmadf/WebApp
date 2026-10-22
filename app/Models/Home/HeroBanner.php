<?php

namespace App\Models\Home;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroBanner extends Model
{
    use HasFactory;

    protected $table = 'hero_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'link_url',
        'link_type',
        'link_id',
        'position',
        'is_active',
    ];

    protected $casts = [
        'link_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('active', function ($query) {
            // Only apply when explicitly requested
        });
    }
}