<?php

namespace App\Models\Search;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    use HasFactory;

    protected $table = 'search_logs';

    protected $fillable = [
        'query',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];
}