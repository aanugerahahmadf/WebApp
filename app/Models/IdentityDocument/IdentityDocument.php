<?php

namespace App\Models\IdentityDocument;

use App\Models\User\User;
use App\Services\StorageService\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityDocument extends Model
{
    protected $fillable = [
        'user_id', 'document_type', 'document_number', 'photo_path',
        'ai_document_type', 'ai_document_number', 'ai_reason',
        'ai_reason_code', 'ai_blocking_issue', 'ai_score', 'ai_verified',
        'ai_number_matches_profile', 'ai_verified_at',
    ];

    protected $casts = [
        'ai_blocking_issue' => 'array',
        'ai_verified' => 'boolean',
        'ai_number_matches_profile' => 'boolean',
        'ai_verified_at' => 'datetime',
        'ai_score' => 'float',
    ];

    protected $appends = ['photo_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return filter_var($this->photo_path, FILTER_VALIDATE_URL)
            ? $this->photo_path
            : StorageService::url($this->photo_path);
    }
}
