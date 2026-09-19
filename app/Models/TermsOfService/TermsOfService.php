<?php

namespace App\Models\TermsOfService;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use App\Traits\HasTranslations\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class TermsOfService extends Model
{
    use LegacyMorphClass;
    use HasTranslations;

    protected $table = 'terms_of_service';

    protected array $translatable = ['title', 'content'];

    protected $fillable = ['title', 'content', 'title_translations', 'content_translations'];

    protected $casts = [
        'content' => 'array',
        'title_translations' => 'json',
        'content_translations' => 'json',
    ];

    public function getTitleAttribute($value): ?string
    {
        return $this->trans('title') ?? $value;
    }

    public function getContentAttribute($value): mixed
    {
        $translated = $this->trans('content');
        return $translated ?? $value;
    }
}
