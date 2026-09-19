<?php

namespace App\Models\Help;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use App\Traits\HasTranslations\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Help extends Model
{
    use LegacyMorphClass;
    use HasTranslations;

    protected $table = 'helps';

    protected array $translatable = ['title', 'subtitle', 'faqs'];

    protected $fillable = [
        'title',
        'subtitle',
        'faqs',
        'contact_options',
        'title_translations',
        'subtitle_translations',
        'faqs_translations',
    ];

    protected $casts = [
        'faqs' => 'array',
        'contact_options' => 'array',
        'title_translations' => 'json',
        'subtitle_translations' => 'json',
        'faqs_translations' => 'json',
    ];

    public function getTitleAttribute($value): ?string
    {
        return $this->trans('title') ?? $value;
    }

    public function getSubtitleAttribute($value): ?string
    {
        return $this->trans('subtitle') ?? $value;
    }

    public function getFaqsAttribute($value): mixed
    {
        $translated = $this->trans('faqs');
        return $translated ?? $value;
    }
}
