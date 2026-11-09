<?php

namespace App\Models\Home;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;


class Promotion extends Model
{
    use ResolvesCamelCaseAttributes;
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'discount_percent',
        'code',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];
}
